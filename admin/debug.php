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
 * Debug page for Evento Course Creation
 *
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');

admin_externalpage_setup('local_eventocoursecreation_debug');

// Handle form submissions
$action = optional_param('action', '', PARAM_ALPHA);
$veranstalterId = optional_param('veranstalter', '', PARAM_TEXT);
$maxResults = optional_param('maxresults', 50, PARAM_INT);
$fetchMode = optional_param('fetchmode', '', PARAM_ALPHA);
$resetHighWatermark = optional_param('reset_hwm', 0, PARAM_INT);

// Process actions
$result = null;
$error = null;
$events = [];

if ($action && confirm_sesskey()) {
    try {
        $eventoService = new local_evento_evento_service();
        $cache = new \local_eventocoursecreation\api\EventoApiCache();
        $traceOutput = new \html_progress_trace();
        
        if ($resetHighWatermark) {
            $fastModeSynchronizer = new \local_eventocoursecreation\api\FastModeSynchronizer(
                $eventoService,
                $cache,
                $traceOutput
            );
            $fastModeSynchronizer->resetHighWaterMark();
            \core\notification::success(get_string('hwm_reset', 'local_eventocoursecreation'));
        }
        
        if ($action === 'test') {
            // Test API connection
            $result = $eventoService->init_call();
            if ($result) {
                \core\notification::success(get_string('connectionsuccessful', 'local_eventocoursecreation'));
            } else {
                \core\notification::error(get_string('connectionfailed', 'local_eventocoursecreation'));
            }
        } else if ($action === 'fetch' && !empty($veranstalterId)) {
            // Fetch events
            switch ($fetchMode) {
                case 'classic':
                    $request = [
                        'theEventoAnlassFilter' => [
                            'anlassVeranstalter' => $veranstalterId
                        ],
                        'theLimitationFilter2' => [
                            'theMaxResultsValue' => $maxResults
                        ]
                    ];
                    
                    $response = $eventoService->execute_soap_request(
                        'listEventoAnlass',
                        $request,
                        "Debug page test fetch"
                    );
                    
                    if (property_exists($response, "return")) {
                        $events = $eventoService->to_array($response->return);
                    }
                    break;
                    
                case 'smart':
                    $fetcherConfig = [
                        'batch_size' => (int)get_config('local_eventocoursecreation', 'batch_size') ?: 200,
                        'adaptive_batch_sizing' => (bool)get_config('local_eventocoursecreation', 'adaptive_batch_sizing'),
                        'enable_incremental' => false,
                    ];
                    
                    $smartFetcher = new \local_eventocoursecreation\api\SmartEventFetcher(
                        $eventoService,
                        $cache,
                        $traceOutput,
                        $fetcherConfig
                    );
                    
                    $fromDate = new \DateTime('-1 year');
                    $toDate = new \DateTime('+1 year');
                    $events = $smartFetcher->fetchAllEvents($veranstalterId, $fromDate, $toDate, true);
                    break;
                    
                case 'fast':
                    $fastModeSynchronizer = new \local_eventocoursecreation\api\FastModeSynchronizer(
                        $eventoService,
                        $cache,
                        $traceOutput
                    );
                    
                    // Force initialization if no high watermark
                    $events = $fastModeSynchronizer->getNewEvents(true);
                    break;
                    
                case 'parallel':
                    if (PHP_SAPI !== 'cli') {
                        \core\notification::warning(get_string('parallel_requires_cli', 'local_eventocoursecreation'));
                    } else {
                        $parallelFetcher = new \local_eventocoursecreation\api\ParallelEventFetcher(
                            $cache,
                            ['num_threads' => 2]
                        );
                        
                        $fromDate = new \DateTime('-1 year');
                        $toDate = new \DateTime('+1 year');
                        $result = $parallelFetcher->fetchEventsForMultipleVeranstalter(
                            [$veranstalterId],
                            $fromDate,
                            $toDate
                        );
                        
                        if (!empty($result[$veranstalterId])) {
                            $events = $result[$veranstalterId];
                        }
                    }
                    break;
                    
                default:
                    $error = get_string('invalidfetchmode', 'local_eventocoursecreation');
                    break;
            }
            
            if (!empty($events)) {
                \core\notification::success(get_string('fetchedevents', 'local_eventocoursecreation', count($events)));
            } else if (empty($error)) {
                \core\notification::warning(get_string('noevents', 'local_eventocoursecreation'));
            }
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
        \core\notification::error($error);
    }
}

// Get Veranstalter list for dropdown
$veranstalterList = [];
try {
    $eventoService = new local_evento_evento_service();
    if ($eventoService->init_call()) {
        $list = $eventoService->get_active_veranstalter();
        foreach ($list as $ver) {
            if (!empty($ver->IDBenutzer)) {
                $veranstalterList[$ver->IDBenutzer] = $ver->benutzerName . ' (' . $ver->IDBenutzer . ')';
            }
        }
    }
} catch (\Exception $e) {
    \core\notification::error(get_string('errorveranstalterlist', 'local_eventocoursecreation') . ': ' . $e->getMessage());
}

// Prepare page
$title = get_string('debugpage', 'local_eventocoursecreation');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->requires->css('/local/eventocoursecreation/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Test Connection Form
echo html_writer::tag('h3', get_string('testconnection', 'local_eventocoursecreation'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'test']);
echo html_writer::tag('button', get_string('testconnection', 'local_eventocoursecreation'), 
    ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo html_writer::empty_tag('hr');

// Fetch Events Form
echo html_writer::tag('h3', get_string('fetchevents', 'local_eventocoursecreation'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'form-horizontal']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'fetch']);

// Veranstalter select
echo html_writer::start_div('form-group row');
echo html_writer::tag('label', get_string('veranstalter', 'local_eventocoursecreation'), 
    ['for' => 'id_veranstalter', 'class' => 'col-sm-3 col-form-label']);
echo html_writer::start_div('col-sm-9');
echo html_writer::select($veranstalterList, 'veranstalter', $veranstalterId, ['' => get_string('selectveranstalter', 'local_eventocoursecreation')], 
    ['class' => 'form-control', 'id' => 'id_veranstalter', 'required' => 'required']);
echo html_writer::end_div();
echo html_writer::end_div();

// Max Results
echo html_writer::start_div('form-group row');
echo html_writer::tag('label', get_string('maxresults', 'local_eventocoursecreation'), 
    ['for' => 'id_maxresults', 'class' => 'col-sm-3 col-form-label']);
echo html_writer::start_div('col-sm-9');
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'maxresults',
    'id' => 'id_maxresults',
    'value' => $maxResults,
    'min' => '1',
    'max' => '5000',
    'class' => 'form-control'
]);
echo html_writer::end_div();
echo html_writer::end_div();

// Fetch Mode
echo html_writer::start_div('form-group row');
echo html_writer::tag('label', get_string('fetchmode', 'local_eventocoursecreation'), 
    ['for' => 'id_fetchmode', 'class' => 'col-sm-3 col-form-label']);
echo html_writer::start_div('col-sm-9');
$fetchModes = [
    'classic' => get_string('fetching_mode_classic', 'local_eventocoursecreation'),
    'smart' => get_string('fetching_mode_smart', 'local_eventocoursecreation'),
    'fast' => get_string('fetching_mode_fast', 'local_eventocoursecreation'),
    'parallel' => get_string('fetching_mode_parallel', 'local_eventocoursecreation') . ' ' . 
        get_string('experimental', 'local_eventocoursecreation')
];
echo html_writer::select($fetchModes, 'fetchmode', $fetchMode, ['' => get_string('selectfetchmode', 'local_eventocoursecreation')], 
    ['class' => 'form-control', 'id' => 'id_fetchmode', 'required' => 'required']);
echo html_writer::end_div();
echo html_writer::end_div();

// Reset high watermark checkbox for fast mode
echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-sm-9 offset-sm-3');
echo html_writer::checkbox('reset_hwm', 1, false, get_string('reset_hwm', 'local_eventocoursecreation'), 
    ['class' => 'form-check-input']);
echo html_writer::end_div();
echo html_writer::end_div();

// Submit button
echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-sm-9 offset-sm-3');
echo html_writer::tag('button', get_string('fetchevents', 'local_eventocoursecreation'), 
    ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');

// Display fetched events
if (!empty($events)) {
    echo html_writer::empty_tag('hr');
    echo html_writer::tag('h3', get_string('fetchedevents', 'local_eventocoursecreation', count($events)));
    
    // Create table for events
    $table = new html_table();
    $table->head = [
        get_string('eventid', 'local_eventocoursecreation'),
        get_string('eventnumber', 'local_eventocoursecreation'),
        get_string('eventname', 'local_eventocoursecreation'),
        get_string('startdate', 'local_eventocoursecreation'),
        get_string('enddate', 'local_eventocoursecreation')
    ];
    $table->data = [];
    
    foreach ($events as $event) {
        $startDate = isset($event->anlassDatumVon) ? userdate(strtotime($event->anlassDatumVon), get_string('strftimedatetime', 'core_langconfig')) : '-';
        $endDate = isset($event->anlassDatumBis) ? userdate(strtotime($event->anlassDatumBis), get_string('strftimedatetime', 'core_langconfig')) : '-';
        
        $table->data[] = [
            $event->idAnlass,
            $event->anlassNummer,
            $event->anlassBezeichnung,
            $startDate,
            $endDate
        ];
    }
    
    echo html_writer::table($table);
}

// Trace output (when using smart or other fetchers)
if (!empty($action)) {
    echo html_writer::empty_tag('hr');
    echo html_writer::tag('h3', get_string('traceoutput', 'local_eventocoursecreation'));
    echo html_writer::tag('div', '', ['class' => 'progress-output']);
}

echo $OUTPUT->footer();