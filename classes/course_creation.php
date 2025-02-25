<?php
/**
 * Evento Course Creation Plugin - Refactored Version
 *
 * Handles automatic course creation and management based on Evento system data.
 * Implements improved architecture with better separation of concerns,
 * dependency injection, and error handling.
 *
 * @package    local_eventocoursecreation
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventocoursecreation;

use core_course_category;
use core\task\scheduled_task;
use Exception;
use moodle_database;
use progress_trace;
use restore_controller;
use stdClass;
use text_progress_trace;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/evento/classes/evento_service.php');
require_once($CFG->libdir . '/weblib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

// Constants for naming placeholders
const NAME_PH_EVENTO_NAME = '{EVENTO_NAME}';
const NAME_PH_EVENTO_ABR = '{EVENTO_ABR}';
const NAME_PH_PERIOD = '{PERIOD}';
const NAME_PH_COS = '{COS}';
const NAME_PH_NUM = '{NUM}';

/**
 * Custom exceptions for better error handling
 */
class EventoException extends Exception {}
class CourseCreationException extends EventoException {}
class CategoryCreationException extends EventoException {}
class ValidationException extends EventoException {}
