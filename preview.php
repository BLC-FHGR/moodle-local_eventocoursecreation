<?php
// local/eventocoursecreation/preview.php

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/lib.php');

// Prevent debug output from mixing with our JSON response
$CFG->debugdisplay = false;
ob_start();

require_login();
require_sesskey();

$categoryid = required_param('categoryid', PARAM_INT);
$refresh = optional_param('refresh', 0, PARAM_BOOL);

$response = array(
    'status' => false,
    'message' => '',
    'courses' => array(),
    'trace' => ''
);

try {
    $context = \context::instance_by_id(\context_coursecat::instance($categoryid)->id);
    require_capability('moodle/category:manage', $context);

    // Create a string progress_trace to capture output
    $trace = new \progress_trace_buffer(new \text_progress_trace(), false);
    
    // Use our preview service
    $previewService = \local_eventocoursecreation\PreviewServiceFactory::create($trace);
    $previewData = $previewService->getPreviewData($categoryid, $refresh);
    
    // Get trace output
    $trace->finished();
    $traceOutput = $trace->get_buffer();
    
    // Set response data
    $response['status'] = $previewData['status'];
    $response['courses'] = $previewData['courses'];
    $response['message'] = $previewData['message'];
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