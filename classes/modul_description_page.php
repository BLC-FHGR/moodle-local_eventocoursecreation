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
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventocoursecreation;

defined('MOODLE_INTERNAL') || die();

// The class loader includes this file from inside a method, so $CFG is not in scope on its own.
global $CFG;
require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/resourcelib.php');

use core_courseformat\formatactions;

/**
 * Writes the evento module description into a course.
 *
 * Everything in here changes a course, the decisions are taken in
 * {@see modul_description}. The capability checks of create_module() and
 * update_module() are bypassed on purpose by calling add_moduleinfo() and
 * update_moduleinfo() directly. Those functions are documented as not checking
 * user capabilities, which is what a background process needs: the result must
 * not depend on who happens to be logged in.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modul_description_page {

    /** Capabilities taken away from the teaching roles on the module context. */
    const PROTECTED_CAPABILITIES = array(
        'moodle/course:manageactivities',
        'moodle/course:activityvisibility',
    );

    /**
     * Creates the page holding the module description.
     *
     * @param \stdClass $course the course record
     * @param string $name the name of the page
     * @param string $content the cleaned html content
     * @param \stdClass $settings the settings as returned by {@see modul_description::get_settings()}
     * @param string $intro the description line, as built by {@see modul_description::build_intro()}
     * @return \cm_info the created course module
     * @throws \moodle_exception if the page module is not available in this course
     */
    public static function create(\stdClass $course, $name, $content, \stdClass $settings, $intro = ''): \cm_info {
        global $DB;

        // Deliberately not course_allowed_module(). That function ends in a capability
        // check against the current user, which would make the result depend on who
        // happens to run the synchronisation. Only the site wide switch matters here.
        $module = $DB->get_record('modules', array('name' => 'page'), '*', MUST_EXIST);
        if (empty($module->visible)) {
            throw new \moodle_exception('moduldescriptionpagedisabled', 'local_eventocoursecreation');
        }

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'page';
        $moduleinfo->module = $module->id;
        $moduleinfo->course = $course->id;
        $moduleinfo->section = EVENTOCOURSECREATION_MB_SECTION;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->name = $name;
        $moduleinfo->cmidnumber = $settings->cmidnumber;
        // The page is placed in front of everything else that is already in the section.
        $moduleinfo->beforemod = self::get_first_cmid_in_section($course);

        self::apply_page_fields($moduleinfo, $content, $settings, (string)$intro);

        // add_moduleinfo() writes course_modules, the page record and the section
        // sequence, rebuilds the course cache and triggers course_module_created.
        $moduleinfo = add_moduleinfo($moduleinfo, $course, null);

        return get_fast_modinfo($course->id)->get_cm($moduleinfo->coursemodule);
    }

    /**
     * Writes name and content of an existing page.
     *
     * @param \stdClass $course the course record
     * @param \cm_info $cm the course module of the page
     * @param string|null $name the new name, null keeps the current one
     * @param string|null $content the new cleaned html content, null keeps the current one
     * @param \stdClass $settings the settings as returned by {@see modul_description::get_settings()}
     * @param string|null $intro the new description line, null keeps the current one
     * @return \cm_info the updated course module
     */
    public static function update(\stdClass $course, \cm_info $cm, $name, $content, \stdClass $settings,
            $intro = null): \cm_info {
        global $DB;

        $page = $DB->get_record('page', array('id' => $cm->instance), '*', MUST_EXIST);
        $cmrecord = get_coursemodule_from_id('page', $cm->id, $course->id, false, MUST_EXIST);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'page';
        $moduleinfo->module = $cmrecord->module;
        $moduleinfo->course = $course->id;
        $moduleinfo->coursemodule = $cm->id;
        $moduleinfo->instance = $cm->instance;
        $moduleinfo->section = $cm->sectionnum;
        $moduleinfo->visible = $cm->visible;
        $moduleinfo->visibleoncoursepage = $cm->visibleoncoursepage;
        $moduleinfo->name = is_null($name) ? $page->name : $name;
        $moduleinfo->cmidnumber = $settings->cmidnumber;
        // page_update_instance() increments this, so it has to carry the current value.
        $moduleinfo->revision = (int)$page->revision;

        self::apply_page_fields($moduleinfo, is_null($content) ? $page->content : $content, $settings,
            is_null($intro) ? (string)$page->intro : (string)$intro,
            is_null($intro) ? (int)$page->introformat : FORMAT_HTML);

        // update_moduleinfo() rebuilds the course cache and triggers course_module_updated.
        update_moduleinfo($cmrecord, $moduleinfo, $course, null);

        return get_fast_modinfo($course->id)->get_cm($cm->id);
    }

    /**
     * Fills in the fields mod_page and the module info need.
     *
     * page_add_instance() and page_update_instance() read display, printintro and
     * printlastmodified without providing a default, so they always have to be set.
     * The intro uses IGNORE_FILE_MERGE, which makes file_save_draft_area_files() hand the
     * text back untouched. update_moduleinfo() calls that function unconditionally, and
     * merging an empty draft area instead would delete the files of the activity.
     *
     * @param \stdClass $moduleinfo the module info to fill in, changed in place
     * @param string $content the html content of the page
     * @param \stdClass $settings the settings
     * @param string $intro the intro text to keep
     * @param int $introformat the format of the intro text
     * @return void
     */
    protected static function apply_page_fields(\stdClass $moduleinfo, $content, \stdClass $settings,
            $intro = '', $introformat = FORMAT_HTML) {

        $moduleinfo->introeditor = array(
            'text' => $intro,
            'format' => $introformat,
            'itemid' => IGNORE_FILE_MERGE,
        );
        // Used when the module info is handed to page_add_instance().
        $moduleinfo->content = $content;
        $moduleinfo->contentformat = FORMAT_HTML;
        // Used when the module info is handed to page_update_instance(). The item id has to
        // be 0 and not IGNORE_FILE_MERGE, because that function guards its file handling with
        // a plain truth test and would then call page_get_editor_options(). That function
        // lives in mod/page/locallib.php, which include_modulelib() does not load.
        $moduleinfo->page = array(
            'text' => $content,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        );

        $moduleinfo->display = self::sanitise_display($settings->display);
        // The description carries the evento version and the validity, so it is shown above
        // the text. An empty description would otherwise print an empty box, see
        // mod/page/view.php, which is why the switch follows the text and not the setting.
        $moduleinfo->printintro = trim(strip_tags((string)$intro)) === '' ? 0 : 1;
        // add_moduleinfo() and update_moduleinfo() fall back to 0 when the field is missing,
        // so leaving it out would silently reset it on every write.
        $moduleinfo->showdescription = 0;
        $moduleinfo->printlastmodified = empty($settings->printlastmodified) ? 0 : 1;
    }

    /**
     * Keeps the configured display value inside the range this plugin supports.
     *
     * The popup display is deliberately not offered, it would need a width and a height.
     *
     * @param int $display the configured value
     * @return int a usable display value
     */
    protected static function sanitise_display($display): int {
        $allowed = array(RESOURCELIB_DISPLAY_AUTO, RESOURCELIB_DISPLAY_EMBED, RESOURCELIB_DISPLAY_OPEN);

        return in_array((int)$display, $allowed, true) ? (int)$display : RESOURCELIB_DISPLAY_AUTO;
    }

    /**
     * Reads the first course module of the target section.
     *
     * @param \stdClass|int $courseorid the course or its id
     * @return int|null the course module id or null if the section is empty
     */
    protected static function get_first_cmid_in_section($courseorid): ?int {
        $modinfo = get_fast_modinfo($courseorid);
        $cmids = $modinfo->sections[EVENTOCOURSECREATION_MB_SECTION] ?? array();

        return empty($cmids) ? null : (int)reset($cmids);
    }

    /**
     * Makes sure the page sits in front of everything else in its section.
     *
     * @param \stdClass $course the course record
     * @param \cm_info $cm the course module of the page
     * @return bool true if the page had to be moved
     */
    public static function move_to_top(\stdClass $course, \cm_info $cm): bool {
        $first = self::get_first_cmid_in_section($course);
        if ($first === (int)$cm->id) {
            return false;
        }

        $action = formatactions::cm($course);
        if (is_null($first)) {
            // The page is the only module of the section, but in another section.
            $sectionid = get_fast_modinfo($course)->get_section_info(EVENTOCOURSECREATION_MB_SECTION)->id;
            $action->move_end_section($cm->id, $sectionid);
        } else {
            $action->move_before($cm->id, $first);
        }

        return true;
    }

    /**
     * Takes the editing capabilities away from the teaching roles on this page.
     *
     * The override sits on the module context, which is where core checks editing,
     * moving, hiding and deleting of an activity. Deleting a whole section is covered
     * as well, course_can_delete_section() asks for moodle/course:manageactivities on
     * the module context of every activity inside the section.
     *
     * The roles are looked up instead of being configured, and the caller runs this on
     * every pass over a course and not only when the page was written, so a role which
     * gained the right to edit activities later is covered by the next synchronisation.
     *
     * Nothing is written when the override is already in place, so the repeated call
     * costs two reads per role and leaves no trace in the log.
     *
     * @param \cm_info $cm the course module of the page
     * @return string[] the capability and role combinations that had to be written
     */
    public static function protect(\cm_info $cm): array {
        global $DB;

        $modcontext = \context_module::instance($cm->id);
        $coursecontext = \context_course::instance($cm->course);

        $written = array();
        foreach (self::PROTECTED_CAPABILITIES as $capability) {
            foreach (get_roles_with_capability($capability, CAP_ALLOW, $coursecontext) as $role) {
                // Deliberately not get_local_override(). That core function joins {capability},
                // while the table is named capabilities, so every call raises a read exception
                // in Moodle 5.2.2. This is the same lookup assign_capability() does internally.
                $override = $DB->get_record('role_capabilities', array(
                    'contextid' => $modcontext->id,
                    'roleid' => $role->id,
                    'capability' => $capability,
                ));
                if ($override && (int)$override->permission === CAP_PREVENT) {
                    continue;
                }
                // assign_capability() resets the role cache on its own.
                assign_capability($capability, CAP_PREVENT, $role->id, $modcontext->id, true);
                $written[] = $role->shortname . ':' . $capability;
            }
        }

        return $written;
    }

    /**
     * Deletes a page, used to clean up a duplicate carried over by a course template.
     *
     * @param \stdClass $course the course record
     * @param \cm_info $cm the course module of the page
     * @return void
     */
    public static function delete(\stdClass $course, \cm_info $cm) {
        // course_delete_module() is deprecated since Moodle 5.2.
        formatactions::cm($course)->delete($cm->id);
    }
}
