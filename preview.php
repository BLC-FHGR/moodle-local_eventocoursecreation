<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/lib.php');

// Prevent debug output from mixing with our JSON response
$CFG->debugdisplay = false;
ob_start();

require_login();
require_sesskey();

$categoryid = required_param('categoryid', PARAM_INT);

$response = array(
    'status' => false,
    'message' => '',
    'courses' => array()
);

try {
    $context = context_coursecat::instance($categoryid);
    require_capability('moodle/category:manage', $context);

    // Create a string progress_trace to capture output
    $trace = new progress_trace_buffer(new text_progress_trace(), false);
    $creator = new local_eventocoursecreation_course_creation();
    $creator->set_trace($trace); // Add this method to the class
    
    $courses = $creator->get_preview_courses($categoryid);
    
    // Get any trace output
    $trace->finished();
    $traceOutput = $trace->get_buffer();
    
    $response['status'] = true;
    $response['courses'] = $courses;
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