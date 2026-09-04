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

defined('MOODLE_INTERNAL') || die;

// Default start date for the spring term.
define('EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTDAY', 15);
define('EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTMONTH', 12);
// Default start date for the autumn term.
define('EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTDAY', 2);
define('EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTMONTH', 8);
// Default start date for the spring term.
define('EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_ENDDAY', 1);
define('EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_ENDMONTH', 3);
// Default start date for the autumn term.
define('EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_ENDDAY', 1);
define('EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_ENDMONTH', 10);
// Default start date for custom course start
define('EVENTOCOURSECREATION_DEFAULT_CUSTOM_START', 946681200);


/**
 * Delimiter for different module numbers in the category idnumber
 */
define('EVENTOCOURSECREATION_IDNUMBER_DELIMITER', '|');

/**
 * Delimiter to separate different in the category idnumber
 */
define('EVENTOCOURSECREATION_IDNUMBER_OPTIONS_DELIMITER', '§');

/**
 * Prefix for category idnumbers which contains module numbers
 */
define('EVENTOCOURSECREATION_IDNUMBER_PREFIX', 'mod');

/**
 * Prefix for spring term inside evento eventnumbers
 */
define('EVENTOCOURSECREATION_SPRINGTERM_PREFIX', 'FS');

/**
 * Prefix for autumn term inside evento eventnumbers
 */
define('EVENTOCOURSECREATION_AUTUMNTERM_PREFIX', 'HS');

/**
 * Prefix for EMBA courses
 */
define('EVENTOCOURSECREATION_EMBA_PREFIX', '20');


// Name Placeholders for the cours names.
/**
 * Placeholder for the evento long name
 */
define('EVENTOCOURSECREATION_NAME_PH_EVENTO_NAME', '@EVENTONAME@');
/**
 * Placeholder for the evento name abrevation
 */
define('EVENTOCOURSECREATION_NAME_PH_EVENTO_ABR', '@EVENTOABK@');
/**
 * Placeholder for the period
 */
define('EVENTOCOURSECREATION_NAME_PH_PERIOD', '@PERIODE@');
/**
 * Placeholder for the course of studies
 */
define('EVENTOCOURSECREATION_NAME_PH_COS', '@STG@');
/**
 * Placeholder for instance number ob the module number (the trailing 3 digits)
 */
define('EVENTOCOURSECREATION_NAME_PH_NUM', '@NUM@');
/**
 * Default setting value for the long name of a course
 */
define('EVENTOCOURSECREATION_NAME_LONGNAME', '@EVENTONAME@ (@STG@) @PERIODE@');
/**
 * Default setting value for the short name of a course
 */
define('EVENTOCOURSECREATION_NAME_SHORTNAME', '@EVENTOABK@ (@STG@) @PERIODE@ @NUM@');


// Module description (Modulbeschreibung) settings.
/**
 * Section number the module description page is placed in.
 *
 * Section 0 is deliberately not configurable. It always exists, even when a course
 * template sets the number of sections to zero, and it cannot be deleted, see
 * course_can_delete_section() in course/lib.php. Both properties are part of the
 * protection concept of the page.
 */
define('EVENTOCOURSECREATION_MB_SECTION', 0);

/**
 * Default course module idnumber which marks a page as an evento module description.
 *
 * The idnumber survives a course backup and restore as long as it stays unique
 * within the target course, so it is the marker used to recognise a page that was
 * carried over from a course template.
 */
define('EVENTOCOURSECREATION_MB_CMIDNUMBER', 'evento.modulbeschreibung');

/**
 * Default name of the module description page.
 */
define('EVENTOCOURSECREATION_MB_PAGENAME', 'Modulbeschreibung');

/**
 * Default heading put in front of the module description text of evento
 */
define('EVENTOCOURSECREATION_MB_HEADING', 'Modulbeschreibung');

/**
 * Default sentence naming the credits of the module.
 *
 * The value of the evento field anlass_ECTS replaces the placeholder. An empty
 * setting switches the sentence off and saves the webservice call it needs.
 */
define('EVENTOCOURSECREATION_MB_ECTSTEXT', 'Dieses Modul hat [ECTS]ECTS');

/**
 * Placeholder inside the credits sentence which carries the evento value.
 */
define('EVENTOCOURSECREATION_MB_ECTSPLACEHOLDER', '[ECTS]');

/**
 * Default html element the credits sentence is written into.
 */
define('EVENTOCOURSECREATION_MB_ECTSTAG', 'dt');

/**
 * Default html element put around the credits sentence.
 *
 * Evento writes every module property as a definition list of its own, so a list of
 * its own is what spaces the sentence like one property against the next. An empty
 * setting puts no element around it at all.
 */
define('EVENTOCOURSECREATION_MB_ECTSWRAP', 'dl');

/**
 * Default list of accepted evento status ids of a module description.
 *
 * 61003 is "mb.Genehmigt" (approved). The other values of the "mb." range are
 * 61001 draft, 61002 waiting for approval, 61004 not approved, 61005 discarded,
 * 61006 replaced and 61999 to be deleted.
 */
define('EVENTOCOURSECREATION_MB_ALLOWEDSTATUS', '61003');

/**
 * Default number of courses processed by one run of the synchronisation task.
 */
define('EVENTOCOURSECREATION_MB_BATCHSIZE', 200);

/**
 * Default number of hours a course is skipped after a failed synchronisation.
 */
define('EVENTOCOURSECREATION_MB_RETRYHOURS', 24);

/**
 * Synchronisation scope: courses which have not ended yet.
 */
define('EVENTOCOURSECREATION_MB_SCOPE_CURRENT', 'current');

/**
 * Synchronisation scope: courses which have not started yet.
 */
define('EVENTOCOURSECREATION_MB_SCOPE_FUTURE', 'future');

/**
 * Behaviour when the page was deleted manually: create it again.
 */
define('EVENTOCOURSECREATION_MB_ONDELETE_RECREATE', 'recreate');

/**
 * Behaviour when the page was deleted manually: do nothing, but keep checking.
 */
define('EVENTOCOURSECREATION_MB_ONDELETE_IGNORE', 'ignore');

/**
 * Behaviour when the page was deleted manually: stop synchronising this course.
 */
define('EVENTOCOURSECREATION_MB_ONDELETE_DISABLE', 'disable');

/**
 * Behaviour when the page was renamed manually: restore the configured name.
 */
define('EVENTOCOURSECREATION_MB_ONRENAME_RESET', 'reset');

/**
 * Behaviour when the page was renamed manually: keep the name.
 */
define('EVENTOCOURSECREATION_MB_ONRENAME_KEEP', 'keep');

/**
 * Status of a link record: the page exists and is up to date.
 */
define('EVENTOCOURSECREATION_MB_STATUS_OK', 0);

/**
 * Status of a link record: the page is missing in the course.
 */
define('EVENTOCOURSECREATION_MB_STATUS_MISSING', 1);

/**
 * Status of a link record: the last synchronisation failed.
 */
define('EVENTOCOURSECREATION_MB_STATUS_ERROR', 2);

/**
 * Status of a link record: this course is excluded from the synchronisation.
 */
define('EVENTOCOURSECREATION_MB_STATUS_DISABLED', 3);
