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
 * @copyright  2018 HTW Chur Roger Barras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');

/**
 * Class definition for the evento course creation setting
 *
 * @package    local_eventocoursecreation
 * @copyright  2018 HTW Chur Roger Barras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_eventocoursecreation_setting {

    /** @var array list of all fields and their short name and default value for caching */
    protected static $coursecreationfields = array(
        'id' => array('id', 0),
        'category' => array('ca', 0),
        'enablecatcoursecreation' => array('en', 1),
        'templatecourse' => array('tc', null),
        'enablecoursetemplate' => array('et', 0),
        'starttimecourse' => array('st', EVENTOCOURSECREATION_DEFAULT_CUSTOM_START),
        'setcustomcoursestarttime' => array('sc', 0),
        'starttimespringtermday' => array('sd', EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTDAY),
        'starttimespringtermmonth' => array('sm', EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTMONTH),
        'execonlyonstarttimespringterm' => array('es', 1),
        'starttimeautumntermday' => array('ad', EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTDAY),
        'starttimeautumntermmonth' => array('am', EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTMONTH),
        'execonlyonstarttimeautumnterm' => array('ea', 1),
        'coursevisibility' => array('cv', 0),
        'newsitemsnumber' => array('cn', 0),
        'numberofsections' => array('cs', 0),
        'timemodified' => null, // Not cached.
    );

    /** @var int */
    protected $id;

    /** @var int */
    protected $category;

    /** @var int */
    protected $enablecatcoursecreation = 1;

    /** @var int */
    protected $templatecourse = null;

    /** @var int */
    protected $enablecoursetemplate = 0;

    /** @var int */
    protected $starttimecourse = EVENTOCOURSECREATION_DEFAULT_CUSTOM_START;

    /** @var int */
    protected $setcustomcoursestarttime = 0;

    /** @var int */
    protected $starttimespringtermday = EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTDAY;

    /** @var int */
    protected $starttimespringtermmonth = EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTMONTH;

    /** @var int */
    protected $execonlyonstarttimespringterm = 1;

    /** @var int */
    protected $starttimeautumntermday = EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTDAY;

    /** @var int */
    protected $starttimeautumntermmonth = EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTMONTH;

    /** @var int */
    protected $execonlyonstarttimeautumnterm = 1;

    /** @var int */
    protected $coursevisibility = 0;

    /** @var int */
    protected $newsitemsnumber = 0;

    /** @var int */
    protected $numberofsections = 0;

    /** @var int */
    protected $timemodified = false;

    /**
     * Magic setter method, we do not want anybody to modify properties from the outside
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value) {
        debugging('Can not change coursecat instance properties!', DEBUG_DEVELOPER);
    }

    /**
     * Magic method getter, redirects to read only values. Queries from DB the fields that were not cached
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        global $DB;
        if (array_key_exists($name, self::$coursecreationfields)) {
            if ($this->$name === false) {
                // Property was not retrieved from DB, retrieve all not retrieved fields.
                $notretrievedfields = array_diff_key(self::$coursecreationfields, array_filter(self::$coursecreationfields));
                $record = $DB->get_record('eventocoursecreation', array('id' => $this->id),
                        join(',', array_keys($notretrievedfields)), MUST_EXIST);
                foreach ($record as $key => $value) {
                    $this->$key = $value;
                }
            }
            return $this->$name;
        }
        debugging('Invalid setting property accessed! '.$name, DEBUG_DEVELOPER);
        return null;
    }

    /**
     * Full support for isset on our magic read only properties.
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name) {
        if (array_key_exists($name, self::$coursecreationfields)) {
            return isset($this->$name);
        }
        return false;
    }

    /**
     * All properties are read only, sorry.
     *
     * @param string $name
     */
    public function __unset($name) {
        debugging('Can not unset eventocoursecreation setting instance properties!', DEBUG_DEVELOPER);
    }

    /**
     * Initialize the service keeping reference to the soap-client
     *
     * Constructor is protected, use local_eventocoursecreation_setting::get($id) to retrieve the setting
     *
     * @param stdClass $record record from DB (may not contain all fields)
     */
    protected function __construct(stdClass $record) {

        $config = get_config('local_eventocoursecreation');

        $this->starttimecourse = $config->starttimecourse;
        $this->setcustomcoursestarttime = $config->setcustomcoursestarttime;
        $this->starttimespringtermday = $config->starttimespringtermday;
        $this->starttimespringtermmonth = $config->starttimespringtermmonth;
        $this->execonlyonstarttimespringterm = $config->execonlyonstarttimespringterm;
        $this->starttimeautumntermday = $config->starttimeautumntermday;
        $this->starttimeautumntermmonth = $config->starttimeautumntermmonth;
        $this->execonlyonstarttimeautumnterm = $config->execonlyonstarttimeautumnterm;
        $this->coursevisibility = $config->coursevisibility;
        $this->newsitemsnumber = $config->newsitemsnumber;
        $this->numberofsections = $config->numberofsections;

        context_helper::preload_from_record($record);
        foreach ($record as $key => $val) {
            if (array_key_exists($key, self::$coursecreationfields)) {
                $this->$key = $val;
            }
        }
    }

    /**
     * Returns eventocoursecreation_setting object for requested category
     *
     * If id is 0, the pseudo object is returned
     *
     * @param int $catid category id
     * @return null|local_eventocoursecreation_setting
     * @throws moodle_exception
     */
    public static function get($catid) {

        $coursecreationsetting = false;

        if (!$catid) {
            return new local_eventocoursecreation_setting();
        }
        if ($records = self::get_records('cc.category = :catid', array('catid' => $catid))) {
            $record = reset($records);
            $coursecreationsetting = new local_eventocoursecreation_setting($record);
        }
        if ($coursecreationsetting) {
            return $coursecreationsetting;
        } else {
            // new record
            $record = new stdClass();
            $record->category = $catid;
            return new local_eventocoursecreation_setting($record);
        }
        return null;
    }

    /**
     * Retrieves number of records from course_categories table
     *
     * Only cached fields are retrieved. Records are ready for preloading context
     *
     * @param string $whereclause
     * @param array $params
     * @return array array of stdClass objects
     */
    protected static function get_records($whereclause, $params) {
        global $DB;
        // Retrieve from DB only the fields that need to be stored in cache.
        $fields = array_keys(array_filter(self::$coursecreationfields));
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $sql = "SELECT cc.". join(',cc.', $fields). ", $ctxselect
                FROM {eventocoursecreation} cc
                JOIN {course_categories} cat ON cat.id = cc.category
                JOIN {context} ctx ON cat.id = ctx.instanceid AND ctx.contextlevel = :contextcoursecat
                WHERE ". $whereclause ." ORDER BY cat.sortorder";
        return $DB->get_records_sql($sql,
                array('contextcoursecat' => CONTEXT_COURSECAT) + $params);
    }

    /**
     * Updates the record with either form data or raw data
     *
     * Please note that this function does not verify access control.
     *
     *
     * @param array|stdClass $data

     * @throws moodle_exception
     */
    public function update($data) {
        global $DB, $CFG;

        $newrecord = false;

        $data = (object)$data;
        $newsetting = new stdClass();

        if ($this->id && ($this->id != 0)) {
            $newsetting->id = $this->id;
            $newrecord = false;
        } else {
            $newrecord = true;
        }

        if (!isset($data->category) || empty($data->category)) {
            throw new moodle_exception('categoryrequired');
        }
        if (!$DB->record_exists('course_categories', array('id' => $data->category))) {
                 throw new moodle_exception('categorynotexists');
        }

        $newsetting->category = $data->category;

        if (isset($data->starttimespringtermday)) {
            if ($data->starttimespringtermday > 31 && $data->starttimespringtermday < 1) {
                throw new moodle_exception('daynotvalid');
            }
            $newsetting->starttimespringtermday = $data->starttimespringtermday;
        }

        if (isset($data->starttimeautumntermday)) {
            if ($data->starttimeautumntermday > 31 && $data->starttimeautumntermday < 1) {
                throw new moodle_exception('daynotvalid');
            }
            $newsetting->starttimeautumntermday = $data->starttimeautumntermday;
        }

        if (isset($data->starttimespringtermmonth)) {
            if ($data->starttimespringtermmonth > 12 && $data->starttimespringtermmonth < 1) {
                throw new moodle_exception('monthnotvalid');
            }
            $newsetting->starttimespringtermmonth = $data->starttimespringtermmonth;
        }

        if (isset($data->starttimeautumntermmonth)) {
            if ($data->starttimeautumntermmonth > 12 && $data->starttimeautumntermmonth < 1) {
                throw new moodle_exception('monthnotvalid');
            }
            $newsetting->starttimeautumntermmonth = $data->starttimeautumntermmonth;
        }
        if ($data->templatecourse == 0 || !is_numeric($data->templatecourse)) {
            $newsetting->templatecourse = null;
        } else {
            $newsetting->templatecourse = $data->templatecourse;
        }
        if (isset($data->starttimecourse)) {
            if (!is_int($data->starttimecourse)) {
                throw new moodle_exception('starttimecourseinvalid');
            }
            $newsetting->starttimecourse = $data->starttimecourse;
        }
        $newsetting->enablecoursetemplate = $data->enablecoursetemplate;
        $newsetting->execonlyonstarttimespringterm = $data->execonlyonstarttimespringterm;
        $newsetting->execonlyonstarttimeautumnterm = $data->execonlyonstarttimeautumnterm;
        $newsetting->coursevisibility = $data->coursevisibility;
        $newsetting->newsitemsnumber = $data->newsitemsnumber;
        $newsetting->numberofsections = $data->numberofsections;
        $newsetting->enablecatcoursecreation = $data->enablecatcoursecreation;
        $newsetting->setcustomcoursestarttime = $data->setcustomcoursestarttime;

        $newsetting->timemodified = time();

        if ($newrecord) {
            $newsetting->id = $DB->insert_record('eventocoursecreation', $newsetting);
            $this->id = $newsetting->id;
        } else {
            $DB->update_record('eventocoursecreation', $newsetting);
        }

        // Update all fields in the current object.
        $this->restore();
    }

    /**
     * Restores the object after it has been externally modified in DB for example
     */
    protected function restore() {
        // Update all fields in the current object.
        $newrecord = self::get($this->category);
        foreach (self::$coursecreationfields as $key => $unused) {
            $this->$key = $newrecord->$key;
        }
    }

    /**
     * Returns the complete corresponding record from DB table eventocoursecreation
     *
     * @return stdClass
     */
    public function get_db_record() {
        global $DB;
        if ($record = $DB->get_record('eventocoursecreation', array('id' => $this->id))) {
            return $record;
        } else {
            $record = array();
            foreach (self::$coursecreationfields as $key => $unused) {
                $record[$key] = $this->$key;
            }
            return $record;
        }
    }

}
