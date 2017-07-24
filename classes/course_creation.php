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
/**
 * Delimiter for different module numbers in the category idnumber
 */
define('LOCAL_EVENTOCOURSECREATION_IDNUMBER_DELIMITER', '|');

/**
 * Delimiter to separate different in the category idnumber
 */
define('LOCAL_EVENTOCOURSECREATION_IDNUMBER_OPTIONS_DELIMITER', '+');

/**
 * Prefix for category idnumbers which contains module numbers
 */
define('LOCAL_EVENTOCOURSECREATION_IDNUMBER_PREFIX', 'mod.');

/**
 * Prefix for spring term inside evento eventnumbers
 */
define('LOCAL_EVENTOCOURSECREATION_SPRINGTERM_PREFIX', 'FS');

/**
 * Prefix for autumn term inside evento eventnumbers
 */
define('LOCAL_EVENTOCOURSECREATION_AUTUMNTERM_PREFIX', 'HS');

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
    // trace reference to null_progress_trace
    protected $trace;
    // evento WS reference to local_evento_evento_service
    protected $eventoservice;
    // soapfaultcodes for stop execution
    protected $stopsoapfaultcodes = array('HTTP', 'soapenv:Server', 'Server');
    // Evento enrolplugin
    protected $enrolplugin;
    // Temporary Array of moodle courses which are created or gotten from the database during sync
    protected $moodlecourses = array();


    /**
     * Initialize the service keeping reference to the soap-client
     *
     * @param SoapClient $client
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
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function course_sync(progress_trace $trace, $categoryid = null) {
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
                // webservice not available
                $this->trace->output("Evento webservice not available");
                return 2;
            }

            $this->trace->output('Starting evento course synchronisation...');

            if (isset($categoryid)) {
                $categories = self::get_category_records("cc.id = :catid", array('catid' => $categoryid));
            } else {
                $categories = self::get_categories(LOCAL_EVENTOCOURSECREATION_IDNUMBER_PREFIX);
            }

            foreach ($categories as $cat) {
                $catoptions = self::get_coursecat_options($cat->idnumber);
                $modnumbers = self::get_module_ids($cat->idnumber);

                foreach ($modnumbers as $modn) {
                    try {
                        // Get all Evento moduls with the same eventonumber p.I.: "mod.bsp%" and aktive with a future start date
                        $events = $this::get_future_events($modn);
                        $subcat = null;
                        $period = "";

                        foreach ($events as $event) {
                            try {
                                $starttime = strtotime($event->anlassDatumVon);
                                $starttime = $starttime ? $starttime : null;
                                $newperiod = self::get_module_period($event->anlassNummer, $starttime);
                                // Reset subcat if not in the same period
                                $subcat = ($period != $newperiod) ? null : $subcat;
                                $period = $newperiod;
                                // Get or create the period subcategory
                                if (empty($subcat)) {
                                    $subcatidnumber = implode(LOCAL_EVENTOCOURSECREATION_IDNUMBER_DELIMITER, $modnumbers) . '.' . $period;
                                    $subcat = self::get_subcategory_by_idnumber($subcatidnumber);
                                    // create category
                                    if (empty($subcat)) {
                                        $subcat = $this->create_subcategory($this->create_period_category_name($period), $cat->id, $subcatidnumber);
                                    }
                                }

                                // Get existing course
                                $moodlecourse = $this->get_course_by_idnumber($event->anlassNummer, $subcat->id, $catoptions);
                                if ($moodlecourse) {
                                    // Option gm for "gemeinsame Modulanlässe"
                                    if (in_array("gm", $catoptions)) {
                                        if ($this->enrolplugin->instance_exists_by_eventnumber($moodlecourse, $event->anlassNummer)) {
                                            // Main Moodle course have got already an enrolment
                                            continue;
                                        }
                                    } else {
                                        // Do nothing, course was already created previously
                                        continue;
                                    }
                                } else {
                                    // Create an empty course
                                    $moodlecourse = $this->create_new_course($event, $subcat->id);
                                }

                                // Add Evento enrolment instance ONLY if the instance is not in the course
                                if (isset($moodlecourse) && isset($this->enrolplugin) && enrol_is_enabled('evento')) {
                                    $fields = $this->enrolplugin->get_instance_defaults();
                                    if (in_array("gm", $catoptions)) {
                                        // for "gemeinsame Module" add enrolment with alternative event number
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

        // Exclude Categories with period like .HS17 or .FS17
        $result = array_filter($result,
                            function ($var) {
                                return (!stripos($var->idnumber, '.' . LOCAL_EVENTOCOURSECREATION_AUTUMNTERM_PREFIX)
                                        && !stripos($var->idnumber, '.' . LOCAL_EVENTOCOURSECREATION_SPRINGTERM_PREFIX));
                            }
        );

        return $result;
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
        // Skip Options
        $result = explode(LOCAL_EVENTOCOURSECREATION_IDNUMBER_OPTIONS_DELIMITER, $idnumber);
        // First "Option are always idnumbers"
        $result = reset($result);
        $result = explode(LOCAL_EVENTOCOURSECREATION_IDNUMBER_DELIMITER, $result);
        $idnprefix = LOCAL_EVENTOCOURSECREATION_IDNUMBER_PREFIX;
        $result = array_filter($result,
                            function ($var) use ($idnprefix) {
                                return (strncasecmp($var, $idnprefix, 4) == 0);
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
        $result = explode(LOCAL_EVENTOCOURSECREATION_IDNUMBER_OPTIONS_DELIMITER, $idnumber);
        // Skipt First element, Because these are the evento idnumbers
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
            // Filter to get the substring with HS or FS prefix
            $modnumbers = explode('.', $eventnumber);
            $modnumbers = array_filter($modnumbers,
                                function ($var) {
                                    return (strtoupper(substr($var, 0, 2 )) == LOCAL_EVENTOCOURSECREATION_AUTUMNTERM_PREFIX
                                            || strtoupper(substr($var, 0, 2 )) == LOCAL_EVENTOCOURSECREATION_SPRINGTERM_PREFIX);
                                }
            );
            $result = reset($modnumbers);
        }

        // Is the term set or valid ?
        if (!isset($result) || (!stristr($result, LOCAL_EVENTOCOURSECREATION_AUTUMNTERM_PREFIX) && !stristr($result, LOCAL_EVENTOCOURSECREATION_SPRINGTERM_PREFIX))) {
            // Get the default term string like HS17 or FS17
            if (isset($eventstarttime)) {
                $month = date('n', $eventstarttime);
                $year = date('y', $eventstarttime);
                $fsmonths = array('2', '3', '4', '5', '6', '7');
                if (in_array($month, $fsmonths)) {
                    $term = LOCAL_EVENTOCOURSECREATION_SPRINGTERM_PREFIX;
                } else {
                    $term = LOCAL_EVENTOCOURSECREATION_AUTUMNTERM_PREFIX;
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

        $eventoanlassfilter->anlassnummer = $modn . '%';
        $eventoanlassfilter->idanlasstyp = local_evento_idanlasstyp::MODULANLASS;

        $events = local_evento_evento_service::to_array($this->eventoservice->get_events_by_filter($eventoanlassfilter, $limitationfilter2));
        $result = array_filter($events, array($this, 'filter_valid_create_events'));

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
        // Has start date?
        if (empty($var->anlassDatumVon)) {
            return false;
        }
        // Future start date
        $fromdate = strtotime($var->anlassDatumVon);
        if ($fromdate >= $now) {
            $return = true;
        }
        if ($return) {
            // Not "Abgesagt"
            // todo move value this to config
            if ($var->idAnlassStatus != '10230') {
                $return = true;
            }
        }
        if ($return) {
            // Has enrolments?
            $eventoenrolments = local_evento_evento_service::to_array($this->eventoservice->get_enrolments_by_eventid($var->idAnlass));
            if (!empty($eventoenrolments)) {
                $return = true;
            }
        }
        return $return;
    }


    /**
     * Create a new empty hidden course in a category
     *
     * @param array $event array of evento
     * @param int $catid category to create
     * @return object new course instance
     */
    protected function create_new_course($event, $categoryid) {
        global $CFG;
        try {
            require_once("$CFG->dirroot/course/lib.php");
            $return = null;
            $newcourse = new stdClass();

            if (!empty($event->anlassNummer)) {
                $newcourse->idnumber = trim($event->anlassNummer);
            } else {
                break;
            }
            $newcourse->shortname = trim(str_replace(LOCAL_EVENTOCOURSECREATION_IDNUMBER_PREFIX, "", $event->anlassNummer));
            $newcourse->fullname = trim($event->anlassBezeichnung);
            $newcourse->category = $categoryid;
            if (!empty($event->anlassDatumVon)) {
                $newcourse->startdate = strtotime($event->anlassDatumVon);
            }
            if (!empty($event->anlassDatumBis)) {
                $newcourse->enddate = strtotime($event->anlassDatumBis);
            }
            $newcourse->visible = 0;
            $return = create_course($newcourse);

        } catch (moodle_exception $ex) {
            if (($ex->errorcode == 'courseidnumbertaken') || ($ex->errorcode == 'shortnametaken')) {
                // course already exists not needed to be created
                debugging("{$ex->errorcode} for course shortname {$newcourse->shortname}", DEBUG_DEVELOPER);
                return $return;
            } else {
                throw $ex;
            }
        }
        if (isset($return)) {
            $this->moodlecourses[$return->idnumber] = $return;
        }
        return $return;
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
        // Lookup course in temp. array
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
        // Set lookup table
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

        // Todo generate a different categoryname for the period

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
