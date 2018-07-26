<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Evento course creation plugin
 *
 * @package    local_eventocoursecreation
 * @copyright  2017 HTW Chur Roger Barras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');
require_once($CFG->dirroot . '/cache/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Class definition for the evento course creation
 *
 * @package    local_eventocoursecreation
 * @copyright  2017 HTW Chur Roger Barras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_eventocoursecreation_course_creation {
    // Plugin configuration.
    private $config;
    // Trace reference to null_progress_trace.
    protected $trace;
    // Evento WS reference to local_evento_evento_service.
    protected $eventoservice;
    // Soapfaultcodes for stop execution.
    protected $stopsoapfaultcodes = array('HTTP', 'soapenv:Server', 'Server');
    // Evento enrolplugin.
    protected $enrolplugin;
    // Temporary Array of moodle courses which are created or gotten from the database during sync.
    protected $moodlecourses = array();
    // Temporary member variable for filter_valid_create_events
    // This is for the course of studies modulenumber prefix.
    protected $modnrprefix;

    /**
     * Initialize the service keeping reference to the soap-client
     *
     */
    public function __construct() {
        $this->config = get_config('local_eventocoursecreation');
        $this->eventoservice = new local_evento_evento_service();
        $this->enrolplugin = enrol_get_plugin('evento');
    }

    /**
     * Sync all categories with evento links.
     *
     * @param progress_trace $trace
     * @param int $categoryid one category, empty means all
     * @param bool $force if true, forces the execution, regardless if we are in a timeslot
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function course_sync(progress_trace $trace, $categoryid = null, $force = false) {
        global $CFG;
        try {
            require_once($CFG->libdir. '/coursecatlib.php');

            // Init.
            $this->trace = $trace;
            $this->moodlecourses = array();
            $syncstart = microtime(true);

            if ($this->config->enableplugin == 0) {
                $pluginname = new lang_string('pluginname', 'local_eventocoursecreation');
                $this->trace->output($pluginname . " plugin not enabled");
                $this->trace->finished();
                return 2;
            }
            // Unfortunately this may take a long time, execution can be interrupted safely here.
            core_php_time_limit::raise();
            raise_memory_limit(MEMORY_HUGE);

            if (!$this->eventoservice->init_call()) {
                // Webservice not available.
                $this->trace->output("Evento webservice not available");
                return 2;
            }

            $this->trace->output('Starting evento course synchronisation...');

            if (isset($categoryid)) {
                $categories = self::get_category_records("cc.id = :catid", array('catid' => $categoryid));
            } else {
                $categories = self::get_categories(EVENTOCOURSECREATION_IDNUMBER_PREFIX);
            }

            foreach ($categories as $cat) {
                $catoptions = self::get_coursecat_options($cat->idnumber);
                $modnumbers = self::get_module_ids($cat->idnumber);

                $setting = local_eventocoursecreation_setting::get($cat->id);
                if ($setting->enablecatcoursecreation == 0) {
                    // Course creation not enabled for this category.
                    continue;
                }
                // Check if we are in a timeslot for course creation.
                if (!$force) {
                    if (!$this->is_creation_allowed($setting)) {
                        continue;
                    }
                }

                foreach ($modnumbers as $modn) {
                    try {
                        // Get all Evento moduls with the same eventonumber (p.I.: "mod.bsp%"), active and with a future start date.
                        $events = $this->get_future_events($modn);
                        $subcat = null;
                        $period = "";

                        foreach ($events as $event) {
                            try {
                                $starttime = strtotime($event->anlassDatumVon);
                                $starttime = $starttime ? $starttime : null;

                                $newperiod = self::get_module_period($event->anlassNummer, $starttime);
                                // Reset subcat if not in the same period.
                                $subcat = ($period != $newperiod) ? null : $subcat;
                                $period = $newperiod;
                                // Get or create the period subcategory.
                                if (empty($subcat)) {
                                    $subcatidnumber = implode(EVENTOCOURSECREATION_IDNUMBER_DELIMITER, $modnumbers) . '.' . $period;
                                    $subcat = self::get_subcategory_by_idnumber($subcatidnumber);
                                    // Create category.
                                    if (empty($subcat)) {
                                        $subcat = $this->create_subcategory($this->create_period_category_name($period), $cat->id, $subcatidnumber);
                                    }
                                }

                                // Get existing course.
                                $moodlecourse = $this->get_course_by_idnumber($event->anlassNummer, $subcat->id, $catoptions);
                                if ($moodlecourse) {
                                    // Option gm for "gemeinsame Modulanlässe".
                                    if (in_array("gm", $catoptions)) {
                                        if ($this->enrolplugin->instance_exists_by_eventnumber($moodlecourse, $event->anlassNummer)) {
                                            // Main Moodle course have got already an enrolment.
                                            continue;
                                        }
                                    } else {
                                        // Do nothing, course was already created previously.
                                        continue;
                                    }
                                } else {
                                    // Create an empty course.
                                    $moodlecourse = $this->create_new_course($event, $subcat->id, $setting);
                                }

                                // Add Evento enrolment instance ONLY if the instance is not in the course.
                                if (isset($moodlecourse) && isset($this->enrolplugin) && enrol_is_enabled('evento')) {
                                    $fields = $this->enrolplugin->get_instance_defaults();
                                    if (in_array("gm", $catoptions)) {
                                        // for "gemeinsame Module" add enrolment with alternative event number.
                                        $fields = $this->enrolplugin->set_custom_coursenumber($fields, $event->anlassNummer);
                                        $fields['name'] = 'Evento ' . $event->anlassNummer;
                                    }
                                    $this->enrolplugin->add_instance($moodlecourse, $fields);
                                }
                            } catch (SoapFault $fault) {
                                debugging("Soapfault : ". $fault->__toString());
                                $this->trace->output("...evento course synchronisation aborted unexpected with a soapfault during "
                                                    . "sync of catid: {$cat->id}; eventnr.:{$event->anlassNummer};");
                                if (in_array($fault->faultcode, $this->stopsoapfaultcodes)) {
                                    // Stop execution.
                                    $this->trace->finished();
                                    return 1;
                                }
                            } catch (Exception $ex) {
                                debugging("Category sync with id {$cat->id}; eventnr.:{$event->anlassNummer} aborted with error: ". $ex->getMessage());
                                $this->trace->output("...evento course synchronisation aborted unexpected during "
                                                    . "sync of catid: {$cat->id}; eventnr.:{$event->anlassNummer};");
                            } catch (Throwable $ex) {
                                debugging("Category sync with id {$cat->id}; eventnr.:{$event->anlassNummer} aborted with error: ". $ex->getMessage());
                                $this->trace->output("...evento course synchronisation aborted unexpected during "
                                                    . "sync of catid: {$cat->id}; eventnr.:{$event->anlassNummer};");
                            }
                        }
                        unset($subcat);

                    } catch (SoapFault $fault) {
                        debugging("Soapfault : ". $fault->__toString());
                        $this->trace->output("...evento course synchronisation aborted unexpected with a soapfault during "
                                            . "sync of catid: {$cat->id}; eventnr.:{$modn};");
                        if (in_array($fault->faultcode, $this->stopsoapfaultcodes)) {
                            // Stop execution.
                            $this->trace->finished();
                            return 1;
                        }
                    } catch (Exception $ex) {
                        debugging("Category sync with id {$cat->id}; eventnr.:{$modn} aborted with error: ". $ex->getMessage());
                        $this->trace->output("...evento course synchronisation aborted unexpected during "
                                            . "sync of catid: {$cat->id}; eventnr.:{$modn};");
                    } catch (Throwable $ex) {
                        debugging("Category sync with id {$cat->id}; eventnr.:{$modn} aborted with error: ". $ex->getMessage());
                        $this->trace->output("...evento course synchronisation aborted unexpected during "
                                            . "sync of catid: {$cat->id}; eventnr.:{$modn};");
                    }
                }
            }
        } catch (SoapFault $fault) {
            debugging("Error Soapfault: ". $fault->__toString());
            $this->trace->output("...evento course synchronisation aborted unexpected with a soapfault");
            $this->trace->finished();
            return 1;
        } catch (Exeption $ex) {
            debugging("Error: ". $ex->getMessage());
            $this->trace->output('... evento course synchronisation aborted unexpected');
            $this->trace->finished();
            return 1;
        } catch (Throwable $ex) {
            debugging("Error: ". $ex->getMessage());
            $this->trace->output('... evento course synchronisation aborted unexpected');
            $this->trace->finished();
            return 1;
        }
        $syncend = microtime(true);
        $synctime = $syncend - $syncstart;
        $debugmessage = "Evento course syncronisation process time: {$synctime}";
        debugging($debugmessage, DEBUG_DEVELOPER);
        $trace->output($debugmessage);

        $this->trace->output('Evento course synchronisation finished...');
        $this->trace->finished();
        return 0;
    }

    /**
     * Get categories with a defined idnumber prefix
     *
     * @param string $catidnprefix prefix of the cat idnumber
     * @return array of stdClass objects of categories
     */
    public static function get_categories($catidnprefix) {
        $whereclause = "UPPER(cc.idnumber) like UPPER(:catidnprefix)";
        $params = array('catidnprefix' => $catidnprefix . "%");
        $result = self::get_category_records($whereclause, $params);

        // Exclude Categories with period like .HS17 or .FS17.
        $result = array_filter($result,
                            function ($var) {
                                return (!stripos($var->idnumber, '.' . EVENTOCOURSECREATION_AUTUMNTERM_PREFIX)
                                        && !stripos($var->idnumber, '.' . EVENTOCOURSECREATION_SPRINGTERM_PREFIX));
                            }
        );

        return $result;
    }

    /**
     * Check if the creation is in a timeslot for the creation.
     *
     * @param local_eventocoursecreation_setting $setting record of eventocoursecreation
     * @return bool true if creation should proceed
     */
    public function is_creation_allowed(local_eventocoursecreation_setting $setting) {
        $allowed = false;

        // Init time.
        $now = time();
        $nowday = (int)date("d", $now);
        $nowmonth = (int)date("m", $now);
        $nowyear = (int)date("y", $now);
        $now = mktime(0, 0, 0, $nowmonth, $nowday, $nowyear);

        // Spring Term.
        // Set starttime for the spring.
        $springday = (int)$setting->starttimespringtermday;
        $springmonth = (int)$setting->starttimespringtermmonth;
        $springyear = $nowyear;
        $springtime = mktime(0, 0, 0, $springmonth, $springday, $springyear);

        // Set endtime for the spring.
        $springendday = (int)$this->config->endtimespringtermday;
        $springendmonth = (int)$this->config->endtimespringtermmonth;
        $springendyear = $nowyear;
        $springendtime = mktime(0, 0, 0, $springendmonth, $springendday, $springendyear);

        // Set the timeslot correclty.
        if ($springtime > $springendtime) {
            if ($now < $springtime) {
                // Set springtime one year back, to check if we are in a valid timeslot.
                $springyear = $springyear - 1;
                $springtime = mktime(0, 0, 0, $springmonth, $springday, $springyear);
                debugging('Set springtime one year back, to check if we are in a valid timeslot.; allowed:' . var_export($allowed, true) .
                            '; now:' . $now . '; springtime' . $springtime, DEBUG_DEVELOPER);
            } else {
                // Set end time + 1 year;
                // Set springendtime one year forward to create a valid timeslot.
                $springendyear = $springyear + 1;
                $springendtime = mktime(0, 0, 0, $springendmonth, $springendday, $springendyear);
                debugging('Set springendtime one year forward to create a valid timeslot.; allowed:' . var_export($allowed, true) .
                            '; now:' . $now . '; springendtime' . $springendtime, DEBUG_DEVELOPER);
            }
        }

        // Check srping term.
        if ($now >= $springtime && ($now <= $springendtime)) {
            if ((int)$setting->execonlyonstarttimespringterm == 1) {
                if ($now === $springtime) {
                    $allowed = true;
                    debugging('execonlyonstarttimespringterm and now === springtime; allowed:' . var_export($allowed, true) .
                                '; now:' . $now . '; springtime' . $springtime, DEBUG_DEVELOPER);
                } else {
                    $allowed = false;
                    debugging('execonlyonstarttimespringterm and now != springtime; allowed:' . var_export($allowed, true) .
                                '; now:' . $now . '; springtime' . $springtime, DEBUG_DEVELOPER);
                }
            } else {
                $allowed = true;
                debugging('execonlyonstarttimespringterm = 0; allowed:' . var_export($allowed, true) .
                            '; now:' . $now . '; springtime' . $springtime, DEBUG_DEVELOPER);
            }
        } else {
            $allowed = false;
            debugging('not in spring timeslot; allowed:' . var_export($allowed, true) .
                        '; now:' . $now . '; springtime' . $springtime, DEBUG_DEVELOPER);
        }

        // Autum Term.
        // if already allowed, skip the autumn term check.
        if (!$allowed) {
            $autumnday = (int)$setting->starttimeautumntermday;
            $autumnmonth = (int)$setting->starttimeautumntermmonth;
            $autumnyear = $nowyear;
            $autumntime = mktime(0, 0, 0, $autumnmonth, $autumnday, $autumnyear);

            // Set endtime for the autumn.
            $autumnendday = (int)$this->config->endtimeautumntermday;
            $autumnendmonth = (int)$this->config->endtimeautumntermmonth;
            $autumnendyear = $nowyear;
            $autumnendtime = mktime(0, 0, 0, $autumnendmonth, $autumnendday, $autumnendyear);

            // Set the timeslot correclty.
            if ($autumntime > $autumnendtime) {
                if ($now < $autumntime) {
                    // Set autumntime one year back, to check if we are in a valid timeslot.
                    $autumnyear = $autumnyear - 1;
                    $autumntime = mktime(0, 0, 0, $autumnmonth, $autumnday, $autumnyear);
                    debugging('Set autumntime one year back, to check if we are in a valid timeslot.; allowed:' . var_export($allowed, true) .
                                '; now:' . $now . '; autumntime' . $autumntime, DEBUG_DEVELOPER);
                } else {
                    // Set autumnendtime one year forward to create a valid timeslot.
                    $autumnendyear = $autumnendyear + 1;
                    $autumnendtime = mktime(0, 0, 0, $autumnendmonth, $autumnendday, $autumnendyear);
                    debugging('Set autumnendtime one year forward to create a valid timeslot.; allowed:' . var_export($allowed, true) .
                                '; now:' . $now . '; autumnendtime' . $autumnendtime, DEBUG_DEVELOPER);
                }
            }

            // Check the autumn term.
            if ($now >= $autumntime && ($now <= $autumnendtime)) {
                if ((int)$setting->execonlyonstarttimeautumnterm == 1) {
                    if ($now === $autumntime) {
                        $allowed = true;
                        debugging('execonlyonstarttimeautumnterm and now === autumntime; allowed:' . var_export($allowed, true) .
                                    '; now:' . $now . '; autumntime' . $autumntime, DEBUG_DEVELOPER);
                    } else {
                        $allowed = false;
                        debugging('execonlyonstarttimeautumnterm and now != autumntime; allowed:' . var_export($allowed, true) .
                                    '; now:' . $now . '; autumntime' . $autumntime, DEBUG_DEVELOPER);
                    }
                } else {
                    $allowed = true;
                    debugging('execonlyonstarttimeautumnterm = 0; allowed: ' . var_export($allowed, true) .
                                '; now:' . $now . '; autumntime:' . $autumntime, DEBUG_DEVELOPER);
                }
            } else {
                $allowed = false;
                debugging('not in autumn timeslot; allowed:' . var_export($allowed, true) .
                            '; now:' . $now . '; autumntime:' . $autumntime, DEBUG_DEVELOPER);
            }
        }
        return $allowed;
    }

    /**
     * Get subcategories with a defined idnumber prefix
     *
     * @param string $subcatname name of the subcategory
     * @param int $parentcatid category id of the parent
     * @return array of stdClass objects of categories
     */
    public static function get_subcategory_by_name($subcatname, $parentcatid) {

        if (!isset($parentcatid) || !isset($subcatname)) {
            return null;
        }
        $whereclause = "(UPPER(cc.name) = UPPER(:subcatname)) AND (cc.parent = :parentcatid)";
        $params = array('subcatname' => $subcatname, 'parentcatid' => $parentcatid );
        $result = self::get_category_records($whereclause, $params);

        return $result;
    }

    /**
     * Get subcategories with a defined idnumber prefix
     *
     * @param string $idnumber idnumber of the categorie
     * @return stdClass object of the category
     */
    public static function get_subcategory_by_idnumber($idnumber) {

        if (empty($idnumber)) {
            return null;
        }
        $whereclause = "(UPPER(cc.idnumber) = UPPER(:idnumber))";
        $params = array('idnumber' => $idnumber);
        $result = self::get_category_records($whereclause, $params);
        $return = reset($result);

        return $return;
    }

    /**
     * Extract the evento module numbers from the idnumber
     * delimited by | ignore numbers without the module prefix
     *
     * @param string $idnumber idnumber of a category
     * @return array array of strings with module numbers
     */
    public static function get_module_ids($idnumber) {
        $result = array();
        // Skip Options.
        $result = explode(EVENTOCOURSECREATION_IDNUMBER_OPTIONS_DELIMITER, $idnumber);
        // First "Option are always idnumbers".
        $result = reset($result);
        $result = explode(EVENTOCOURSECREATION_IDNUMBER_DELIMITER, $result);
        $idnprefix = EVENTOCOURSECREATION_IDNUMBER_PREFIX;
        $result = array_filter($result,
                            function ($var) use ($idnprefix) {
                                return (strncasecmp($var, $idnprefix, strlen($idnprefix)) == 0);
                            }
        );

        return $result;
    }

    /**
     * Extract the options from the idnumber
     *
     * @param string $idnumber
     * @return array
     */
    protected static function get_coursecat_options($idnumber) {
        $result = array();
        $result = explode(EVENTOCOURSECREATION_IDNUMBER_OPTIONS_DELIMITER, $idnumber);
        // Skipt First element, Because these are the evento idnumbers.
        array_shift($result);
        return $result;
    }

    /**
     * Extract the evento period (term) from the idnumber
     * assume it is the third part of the eventumber separated by '.'
     * Sets a default Value the period not matches a valid pattern
     *
     * @param string $eventnumber idnumber of a category
     * @param int $eventstarttime starttimestamp of the event
     * @return string period
     */
    public static function get_module_period($eventnumber, $eventstarttime = null) {
        $modnumbers = array();
        $result = null;

        if (isset($eventnumber)) {
            // Filter to get the substring with HS or FS prefix.
            $modnumbers = explode('.', $eventnumber);
            $modnumbers = array_filter($modnumbers,
                                function ($var) {
                                    return (strtoupper(substr($var, 0, 2 )) == EVENTOCOURSECREATION_AUTUMNTERM_PREFIX
                                            || strtoupper(substr($var, 0, 2 )) == EVENTOCOURSECREATION_SPRINGTERM_PREFIX);
                                }
            );
            $result = reset($modnumbers);
        }

        // Is the term set or valid ?
        if (!isset($result) || (!stristr($result, EVENTOCOURSECREATION_AUTUMNTERM_PREFIX)
            && !stristr($result, EVENTOCOURSECREATION_SPRINGTERM_PREFIX))) {
            // Get the default term string like HS17 or FS17.
            if (isset($eventstarttime)) {
                $month = date('n', $eventstarttime);
                $year = date('y', $eventstarttime);
                $fsmonths = array('2', '3', '4', '5', '6', '7');
                if (in_array($month, $fsmonths)) {
                    $term = EVENTOCOURSECREATION_SPRINGTERM_PREFIX;
                } else {
                    $term = EVENTOCOURSECREATION_AUTUMNTERM_PREFIX;
                }
                $result = $term . $year;
            }
        }

        return $result;
    }

    /**
     * Gets valid future events to be created
     *
     * @param string $modn evento module prefix
     * @return array filtered array of evento events
     */
    protected function get_future_events($modn) {

        $limitationfilter2 = new local_evento_limitationfilter2();
        $eventoanlassfilter = new local_evento_eventoanlassfilter();

        $limitationfilter2->thefromdate = date(LOCAL_EVENTO_DATETIME_FORMAT, strtotime('-1 year'));
        $limitationfilter2->thetodate = date(LOCAL_EVENTO_DATETIME_FORMAT, time());
        $limitationfilter2->themaxresultvalue = 10000;
        $limitationfilter2->sortfield = 'anlassNummer';

        $eventoanlassfilter->anlassnummer = $modn . '%';
        $eventoanlassfilter->idanlasstyp = local_evento_idanlasstyp::MODULANLASS;

        $events = local_evento_evento_service::to_array($this->eventoservice->get_events_by_filter($eventoanlassfilter, $limitationfilter2));
        $this->modnrprefix = $modn;
        $result = array_filter($events, array($this, 'filter_valid_create_events'));
        $this->modnrprefix = null;

        return $result;
    }

    /**
     * Filter methode to filter events, which are in the future with
     * active enrolments
     *
     * @param array $event array of evento
     * @return bool
     */
    protected function filter_valid_create_events($var) {
        $return = false;
        $now = time();

        // Check if this event is from the same course of studies?.
        if (isset($this->modnrprefix)) {
            $shortmodnr = str_replace($this->modnrprefix, "", ($var->anlassNummer));
            // is the next character in lower case? -> so it is another course of studies.
            if (!ctype_lower(substr($shortmodnr, 0, 1))) {
                $return = true;
            }
        }

        // Has start date?.
        if ($return && empty($var->anlassDatumVon)) {
            $return = false;
        }

        // Future start date.
        $fromdate = strtotime($var->anlassDatumVon);
        if ($return && ($fromdate < $now)) {
            $return = false;
        }
        if ($return) {
            // Not "Abgesagt".
            // Todo move value to config.
            if ($var->idAnlassStatus == '10270') {
                $return = false;
            }
        }
        /* Removed, because we can set the creation start time now (v2.0). May be we do a option for this in the future.
        if ($return) {
            // Has enrolments?
            $eventoenrolments = local_evento_evento_service::to_array($this->eventoservice->get_enrolments_by_eventid($var->idAnlass));
            if (empty($eventoenrolments)) {
                $return = false;
            }
        }
        */
        return $return;
    }


    /**
     * Create a new empty hidden course in a category
     *
     * @param array $event array of evento
     * @param int $catid category to create
     * @param local_eventocoursecreation_setting $setting record of eventocoursecreation
     * @return object new course instance
     */
    protected function create_new_course($event, $categoryid, local_eventocoursecreation_setting $setting) {
        global $CFG, $USER;

        try {
            require_once("$CFG->dirroot/course/lib.php");
            $course = null;
            $restoredata = null;
            $newcourse = new stdClass();

            if (!empty($event->anlassNummer)) {
                $newcourse->idnumber = trim($event->anlassNummer);
            } else {
                throw new moodle_exception('noeventnumberset', 'local_eventocoursecreation', null, null,
                                            'no "anlassNummer" set to create an new course');
            }
            $naming = new local_eventocoursecreation_course_naming($event->anlassBezeichnung, $event->anlassNummer);
            $newcourse->fullname = $naming->create_long_course_name();
            $newcourse->shortname = $naming->create_short_course_name();
            $newcourse->category = $categoryid;
            if (!empty($event->anlassDatumVon)) {
                $newcourse->startdate = strtotime($event->anlassDatumVon);
            }
            if (!empty($event->anlassDatumBis)) {
                $newcourse->enddate = strtotime($event->anlassDatumBis);
            }
            $newcourse->visible = $setting->coursevisibility;
            $newcourse->newsitems = $setting->newsitemsnumber;
            $newcourse->numsections = $setting->numberofsections;

            // Use a template?
            if ((int)$setting->enablecoursetemplate == 1) {
                $restoredata = $this->get_restore_content_dir($setting->templatecourse);
                if (isset($restoredata)) {
                    $newcourse->numsections = 0;
                }
            }

            $course = create_course($newcourse);

            // Restore a course from a template.
            if (!empty($restoredata)) {
                // Clear new created empty courses for a correct import (delete sections WITH content).
                $rc = new restore_controller($restoredata, $course->id, backup::INTERACTIVE_NO,
                    backup::MODE_IMPORT, $USER->id, backup::TARGET_CURRENT_ADDING);

                // Check if the format conversion must happen first.
                if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                    $rc->convert();
                }
                if ($rc->execute_precheck()) {
                    $rc->execute_plan();
                    debugging("Restored content for course {$course->shortname}", DEBUG_DEVELOPER);
                } else {
                    debugging("Error during restoring to the course {$course->shortname};");
                    $this->trace->output("Error during restoring to the course {$course->shortname};");
                }
                $rc->destroy();
            }

        } catch (moodle_exception $ex) {
            if (($ex->errorcode == 'courseidnumbertaken') || ($ex->errorcode == 'shortnametaken')) {
                // Course already exists not needed to be created.
                debugging("{$ex->errorcode} for course shortname {$newcourse->shortname}", DEBUG_DEVELOPER);
                return $course;
            } else {
                throw $ex;
            }
        }
        if (isset($return)) {
            $this->moodlecourses[$return->idnumber] = $return;
        }
        return $course;
    }

    /**
     * Get the directory of the object to restore.
     *
     * @param int $templatecourse id of a course, which is used to get the content
     * @return string|false|null subdirectory in $CFG->backuptempdir/..., false when an error occured
     *                           and null when there is simply nothing.
     */
    protected function get_restore_content_dir($templatecourse) {

        if (empty($templatecourse) || !is_numeric($templatecourse)) {
            return null;
        }

        $errors = array();
        $dir = self::create_restore_content_dir($templatecourse, $errors);
        if (!empty($errors)) {
            foreach ($errors as $key => $message) {
                debugging("Error during get content of course id {$templatecourse}; ". $message);
                $this->trace->output("Error during get content of course id {$templatecourse}; ". $message);
            }
            return false;
        } else if ($dir === false) {
            // We want to return null when nothing was wrong, but nothing was found.
            $dir = null;
        }
        return $dir;
    }

    /**
     * Get the restore content tempdir.
     *
     * The tempdir is the sub directory in which the backup has been extracted.
     *
     * This caches the result for better performance, but $CFG->keeptempdirectoriesonbackup
     * needs to be enabled, otherwise the cache is ignored.
     *
     * @param int $templatecourse id of a course.
     * @param array $errors will be populated with errors found.
     * @return string|false false when the backup couldn't retrieved.
     */
    public static function create_restore_content_dir($templatecourse = null, &$errors = array()) {
        global $CFG, $DB, $USER;

        $cachekey = null;
        if (!empty($templatecourse) || is_numeric($templatecourse)) {
            $cachekey = 'backup_id:' . $templatecourse;
        }

        if (empty($cachekey)) {
            return false;
        }

        // If $CFG->keeptempdirectoriesonbackup is not set to true, any restore happening would
        // automatically delete the backup directory... causing the cache to return an unexisting directory.
        $usecache = !empty($CFG->keeptempdirectoriesonbackup);
        if ($usecache) {
            $cache = cache::make('local_eventocoursecreation', 'coursecreation');
        }

        // If we don't use the cache, or if we do and not set, or the directory doesn't exist any more.
        if (!$usecache || (($backupid = $cache->get($cachekey)) === false || !is_dir(get_backup_temp_directory($backupid)))) {

            // Use null instead of false because it would consider that the cache key has not been set.
            $backupid = null;

            if (!empty($templatecourse) || is_numeric($templatecourse)) {
                // Creating restore from an existing course.
                $courseid = $DB->get_field('course', 'id', array('id' => $templatecourse), IGNORE_MISSING);
                if (!empty($courseid)) {
                    $bc = new backup_controller(backup::TYPE_1COURSE, $courseid, backup::FORMAT_MOODLE,
                        backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);
                    $bc->execute_plan();
                    $backupid = $bc->get_backupid();
                    $bc->destroy();
                } else {
                    $errors['coursetorestorefromdoesnotexist'] = new lang_string('coursetorestorefromdoesnotexist',
                                                                                    'local_eventocoursecreation');
                }
            }

            if ($usecache) {
                $cache->set($cachekey, $backupid);
            }
        }

        if ($backupid === null) {
            $backupid = false;
        }
        return $backupid;
    }

    /**
     * Retrieves number of records from course_categories table
     *
     * Records are ready for preloading context
     * Runction similar to  coursecat->get_records() in coursecatlib.php
     *
     * @param string $whereclause
     * @param array $params
     * @return array array of stdClass objects
     */
    protected static function get_category_records($whereclause, $params) {
        global $DB;

        $fields = array('id', 'idnumber', 'parent');
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $sql = "SELECT cc.". join(',cc.', $fields). ", $ctxselect
                FROM {course_categories} cc
                JOIN {context} ctx ON cc.id = ctx.instanceid AND ctx.contextlevel = :contextcoursecat
                WHERE ". $whereclause." ORDER BY cc.sortorder";
        return $DB->get_records_sql($sql,
                array('contextcoursecat' => CONTEXT_COURSECAT) + $params);
    }

    /**
     * Get a course by idnumber
     *
     * @param string $idnumber
     * @param int $catid optional
     * @param string $catoptions optional options of the category
     * @return mixed a fieldset object containing the first matching record, false or exception if error not found depending on mode
     */
    protected function get_course_by_idnumber($idnumber, $catid = null, $catoptions = null) {
        global $DB;

        $result = false;
        $params = array();
        // Lookup course in temp. array.
        if (array_key_exists($idnumber, $this->moodlecourses)) {
            $result = $this->moodlecourses[$idnumber];
        }
        if (!$result) {

            if (in_array("gm", $catoptions)) {
                // Get Module number without event nr.
                $idnumber = self::get_modulnumber_by_idnumber($idnumber);
                $params['idnumber'] = $idnumber . '%';

                if (isset($catid) && is_numeric($catid)) {
                    $params['category'] = $catid;
                }

                $result = $DB->get_record_sql(
                    "SELECT c.*
                    FROM {course} c
                    WHERE UPPER(c.idnumber) like UPPER(:idnumber)
                    AND c.category = :category
                    ORDER BY c.idnumber ASC", $params, IGNORE_MULTIPLE);
            } else {
                $params['idnumber'] = $idnumber;
                $result = $DB->get_record('course', $params);
            }
        }
        // Set lookup table.
        if (!empty($result)) {
            $this->moodlecourses[$result->idnumber] = $result;
        }
        return $result;
    }

    /**
     * Get the module number without the event extension
     * remove the numeric suffix like ".001" otherwise remove nothing
     *
     * @param string $idnumber
     * @return string shorten $idnumber
     */
    protected static function get_modulnumber_by_idnumber($idnumber) {

        $result = explode('.', $idnumber);
        $number = array_pop($result);
        if (is_numeric($number)) {
            $result = implode('.', $result);
        } else {
            $result = $idnumber;
        }
        return $result;
    }

    /**
     * Create the category name of a period
     *
     * @param string $period
     * @param int $starttimestamp
     * @return string array of stdClass objects
     */
    protected function create_period_category_name($period, $starttimestamp = null) {
        global $DB;

        // Todo generate a different categoryname for the period.

        return $period;
    }

    /**
     * Creates a new subcategory
     *
     * @param string $name
     * @param int category id of the parent
     * @param string unique idnumber
     * @return stdClass objects of the new category
     */
    protected function create_subcategory($name, $parentcatid, $subcatidnumber) {
        $newcat = new stdClass();
        $newcat->name = $name;
        $newcat->parent = $parentcatid;
        $newcat->idnumber = $subcatidnumber;
        $subcat = coursecat::create($newcat);

        return $subcat;
    }
}

/**
 * Class used to create long and short name for moodle
 *
 * @package    local_eventocoursecreation
 * @copyright  2018 HTW Chur Roger Barras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_eventocoursecreation_course_naming {
    // Plugin configuration.
    protected $config;
    // Normal long name of the module from evento.
    protected $eventolongname = '';
    // Module number from evento like "mod.bpsEA.HS18_BSC.001".
    protected $eventomodulenumber = '';
    // Term period of the module.
    protected $period = '';
    // Course of studies abrevation.
    protected $courseofstudies = '';
    // Module name abrevation.
    protected $moduleabr = '';
    // Numbertoken the evento module number.
    protected $number = '';

    /**
     * Initialize the instance of local_eventocoursecreation_course_naming
     *
     * @param string $eventolongname Normal long name of the module from evento
     * @param string $eventomodulenumber Module number from evento like "mod.bpsEA.HS18_BSC.001"
     * @param string $modulstarttime (option) Starttime of the module to determine if we are in spring or autumn term
     */
    public function __construct($eventolongname, $eventomodulenumber, $modulstarttime = null) {
        $this->eventolongname = $eventolongname;
        $this->eventomodulenumber = $eventomodulenumber;
        $this->config = get_config('local_eventocoursecreation');
        $this->period = local_eventocoursecreation_course_creation::get_module_period($this->eventomodulenumber, $modulstarttime);
        // Remove trailing info after a '_'.
        $this->period = reset(preg_split('/(?=[_])/', $this->period, -1));

        $modtokens = array();
        $modtokens = explode('.', $this->eventomodulenumber);

        $this->number = (int)end($modtokens);
        if (!isset($this->number)) {
            $this->number = '';
        }
        reset($modtokens);

        // Get courseofstudies and moduleabr out of the module token.
        $module = next($modtokens);
        $chunks = preg_split('/(?=[A-Z])/', $module, -1);

        $this->courseofstudies = array_shift($chunks);
        if (!isset($this->courseofstudies)) {
            $this->courseofstudies = '';
        }

        $this->moduleabr = str_replace($this->courseofstudies, '', $module);
        if (!isset($this->moduleabr)) {
            $this->moduleabr = $this->eventolongname;
        }
    }

    /**
     * Creates a long name for a moodle course
     *
     * @return string long name for a moodle course
     */
    public function create_long_course_name() {
        return trim($this->create_name($this->config->longcoursenaming));
    }

    /**
     * Creates a short name for a moodle course
     *
     * @return string short name for a moodle course
     */
    public function create_short_course_name() {
        global $DB;
        $namenumber = $this->create_name($this->config->shortcoursenaming);
        $name = trim($this->create_name(str_replace(EVENTOCOURSECREATION_NAME_PH_NUM, '', $this->config->shortcoursenaming)));
        // Only the the number in shortname if there are 2 Modules with the same name.
        // Check if the shortname already exists.
        $params = array('pname' => $name . '%');
        if ($DB->record_exists_select('course', 'shortname like :pname', $params)) {
            if ($DB->record_exists('course', array('shortname' => $name))) {
                // Update existing course and extend the short name with the numbertoken.
                $course = $DB->get_record('course', array('shortname' => $name), '*', MUST_EXIST);
                // Get the trailing number token.
                $modtokens = explode('.', $course->idnumber);
                $numbertoken = (int)end($modtokens);
                if (isset($numbertoken) && is_numeric($numbertoken)) {
                    $course->shortname = $course->shortname . ' ' .$numbertoken;
                    if ($DB->record_exists('course', array('shortname' => $name))) {
                        update_course($course);
                    }
                }
            }
            $name = $namenumber;
        }

        return trim($name);
    }

    /**
     * Creates a name for a cours out of a specific naming
     *
     * @param string $naming String with naming tokens to be replaced by module values
     * @return string name for a naming
     */
    protected function create_name($naming) {
        $name = $naming;
        $name = str_replace(EVENTOCOURSECREATION_NAME_PH_EVENTO_NAME, $this->eventolongname, $name);
        $name = str_replace(EVENTOCOURSECREATION_NAME_PH_EVENTO_ABR, $this->moduleabr, $name);
        $name = str_replace(EVENTOCOURSECREATION_NAME_PH_PERIOD, $this->period, $name);
        $name = str_replace(EVENTOCOURSECREATION_NAME_PH_COS, $this->courseofstudies, $name);
        $name = str_replace(EVENTOCOURSECREATION_NAME_PH_NUM, $this->number, $name);

        return trim($name);
    }

}