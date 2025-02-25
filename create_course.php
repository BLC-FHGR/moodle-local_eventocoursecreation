<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/lib.php');

$CFG->debugdisplay = false;
ob_start();

require_login();
require_sesskey();

$eventid = required_param('eventid', PARAM_INT);
$categoryid = required_param('categoryid', PARAM_INT);
$force = optional_param('force', 0, PARAM_INT);

// New parameter for cached events
$cachedEvents = optional_param('cachedEvents', null, PARAM_RAW);
if ($cachedEvents) {
    $cachedEvents = json_decode($cachedEvents);
}

$response = array(
    'status' => false,
    'message' => '',
    'trace' => ''
);

try {
    $context = context_coursecat::instance($categoryid);
    require_capability('moodle/category:manage', $context);

    // Create trace to capture output
    $trace = new progress_trace_buffer(new text_progress_trace(), false);
    
    // Initialize course creator
    $creator = new local_eventocoursecreation_course_creation();
    $creator->set_trace($trace);
    
    // Create single course with cached events
    $result = $creator->create_single_course($eventid, $categoryid, (bool)$force, $cachedEvents);
    
    // Get trace output
    $trace->finished();
    $traceOutput = $trace->get_buffer();
    
    if ($result === true) {
        $response['status'] = true;
        $response['message'] = get_string('coursecreated', 'local_eventocoursecreation');
    } else {
        throw new moodle_exception('coursecreationfailed', 'local_eventocoursecreation');
    }
    
    $response['trace'] = $traceOutput;
    
} catch (Exception $e) {
    $response['status'] = false;
    $response['message'] = $e->getMessage();
    if (debugging()) {
        $response['debug'] = array(
            'trace' => $trace ? $trace->get_buffer() : '',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        );
    }
}

// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Send JSON response
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
die();