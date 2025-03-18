<?php
// local/eventocoursecreation/create_course.php

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/lib.php');

$CFG->debugdisplay = false;
ob_start();

require_login();
require_sesskey();

$eventid = required_param('eventid', PARAM_INT);
$categoryid = required_param('categoryid', PARAM_INT);
$force = optional_param('force', 0, PARAM_INT);

$response = array(
    'status' => false,
    'message' => '',
    'trace' => ''
);

try {
    $context = \context::instance_by_id(\context_coursecat::instance($categoryid)->id);
    require_capability('moodle/category:manage', $context);

    // Create trace to capture output
    $trace = new \progress_trace_buffer(new \text_progress_trace(), false);
    
    // Use our preview service
    $previewService = \local_eventocoursecreation\PreviewServiceFactory::create($trace);
    $result = $previewService->createSingleCourse($eventid, $categoryid, (bool)$force);
    
    // Get trace output
    $trace->finished();
    $traceOutput = $trace->get_buffer();
    
    if ($result) {
        $response['status'] = true;
        $response['message'] = get_string('coursecreated', 'local_eventocoursecreation');
    } else {
        throw new \moodle_exception('coursecreationfailed', 'local_eventocoursecreation');
    }
    
    $response['trace'] = $traceOutput;
    
} catch (Exception $e) {
    $response['status'] = false;
    $response['message'] = $e->getMessage();
    if (debugging()) {
        $response['debug'] = array(
            'trace' => isset($trace) ? $trace->get_buffer() : '',
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'message' => $e->getMessage()
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