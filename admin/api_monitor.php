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
 * API Monitor page for Evento Course Creation
 *
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_eventocoursecreation_apimonitor');

// Handle purge action
if (optional_param('purge', 0, PARAM_INT) && confirm_sesskey()) {
    $cache = new \local_eventocoursecreation\api\EventoApiCache();
    $cache->purge();
    \core\notification::success(get_string('cachepurged', 'local_eventocoursecreation'));
    redirect($PAGE->url);
}

// Get event fetcher stats from the cache
$cache = new \local_eventocoursecreation\api\EventoApiCache();
$allStats = $cache->get('api_stats') ?: [];

// Get current mode
$currentMode = get_config('local_eventocoursecreation', 'fetching_mode');

// Get totals
$totals = $allStats['totals'] ?? [
    'api_calls' => 0,
    'errors' => 0,
    'cache_hits' => 0,
    'last_run' => 0
];

// Remove totals from the stats for display
unset($allStats['totals']);

// Prepare page
$title = get_string('apimonitor', 'local_eventocoursecreation');
$PAGE->set_title($title);
$PAGE->set_heading($title);

// Add CSS
$PAGE->requires->css('/local/eventocoursecreation/styles.css');

// Add JS for charts
$PAGE->requires->js_call_amd('local_eventocoursecreation/api_monitor', 'init', [
    [
        'stats' => $allStats,
        'totals' => $totals
    ]
]);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Display current mode and configuration
echo html_writer::tag('p', get_string('currentmode', 'local_eventocoursecreation') . ': ' . 
    get_string('fetching_mode_' . $currentMode, 'local_eventocoursecreation'));

// Configuration summary
echo html_writer::start_div('api-config-summary');
echo html_writer::tag('h3', get_string('configsummary', 'local_eventocoursecreation'));

$configItems = [
    'batch_size' => get_string('batch_size', 'local_eventocoursecreation'),
    'min_batch_size' => get_string('min_batch_size', 'local_eventocoursecreation'),
    'max_batch_size' => get_string('max_batch_size', 'local_eventocoursecreation'),
    'adaptive_batch_sizing' => get_string('adaptive_batch_sizing', 'local_eventocoursecreation'),
    'date_chunk_fallback' => get_string('date_chunk_fallback', 'local_eventocoursecreation'),
    'date_chunk_days' => get_string('date_chunk_days', 'local_eventocoursecreation'),
    'max_api_retries' => get_string('max_api_retries', 'local_eventocoursecreation'),
    'cache_ttl' => get_string('cache_ttl', 'local_eventocoursecreation')
];

$configTable = new html_table();
$configTable->head = [get_string('setting', 'local_eventocoursecreation'), get_string('value', 'local_eventocoursecreation')];
$configTable->data = [];

foreach ($configItems as $setting => $label) {
    $value = get_config('local_eventocoursecreation', $setting);
    if ($setting === 'adaptive_batch_sizing' || $setting === 'date_chunk_fallback') {
        $value = $value ? get_string('yes') : get_string('no');
    }
    if ($setting === 'cache_ttl') {
        $value = format_time($value);
    }
    $configTable->data[] = [$label, $value];
}

echo html_writer::table($configTable);
echo html_writer::end_div();

// Display statistics
echo html_writer::start_div('api-stats-summary');
echo html_writer::tag('h3', get_string('apistats', 'local_eventocoursecreation'));

// Totals
echo html_writer::tag('p', get_string('statstotals', 'local_eventocoursecreation'));
echo html_writer::start_div('stats-boxes');

// API Calls Box
echo html_writer::start_div('stats-box');
echo html_writer::tag('div', $totals['api_calls'], ['class' => 'stats-number']);
echo html_writer::tag('div', get_string('api_calls', 'local_eventocoursecreation'), ['class' => 'stats-label']);
echo html_writer::end_div();

// Errors Box
echo html_writer::start_div('stats-box' . ($totals['errors'] > 0 ? ' stats-error' : ''));
echo html_writer::tag('div', $totals['errors'], ['class' => 'stats-number']);
echo html_writer::tag('div', get_string('api_errors', 'local_eventocoursecreation'), ['class' => 'stats-label']);
echo html_writer::end_div();

// Cache Hits Box
echo html_writer::start_div('stats-box');
echo html_writer::tag('div', $totals['cache_hits'], ['class' => 'stats-number']);
echo html_writer::tag('div', get_string('api_cache_hits', 'local_eventocoursecreation'), ['class' => 'stats-label']);
echo html_writer::end_div();

// Last Run Box
echo html_writer::start_div('stats-box');
$lastRunText = $totals['last_run'] ? userdate($totals['last_run'], get_string('strftimedatetime', 'core_langconfig')) : '-';
echo html_writer::tag('div', $lastRunText, ['class' => 'stats-date']);
echo html_writer::tag('div', get_string('api_last_run', 'local_eventocoursecreation'), ['class' => 'stats-label']);
echo html_writer::end_div();

echo html_writer::end_div(); // stats-boxes
echo html_writer::end_div(); // api-stats-summary

// Chart containers
echo html_writer::div('', '', ['id' => 'api-calls-chart', 'class' => 'api-chart']);
echo html_writer::div('', '', ['id' => 'errors-chart', 'class' => 'api-chart']);

// Veranstalter details
if (!empty($allStats)) {
    echo html_writer::tag('h3', get_string('veranstalterdetails', 'local_eventocoursecreation'));
    
    $veranstalterTable = new html_table();
    $veranstalterTable->head = [
        get_string('veranstalter', 'local_eventocoursecreation'),
        get_string('api_calls', 'local_eventocoursecreation'),
        get_string('api_errors', 'local_eventocoursecreation'),
        get_string('cache_hits', 'local_eventocoursecreation'),
        get_string('api_last_run', 'local_eventocoursecreation'),
        get_string('error_rate', 'local_eventocoursecreation')
    ];
    $veranstalterTable->data = [];
    
    foreach ($allStats as $veranstalterId => $stats) {
        $errorRate = ($stats['api_calls'] > 0) ? 
            round(($stats['errors'] / $stats['api_calls']) * 100, 1) . '%' : '0%';
        
        $lastRunText = isset($stats['last_run']) ? 
            userdate($stats['last_run'], get_string('strftimedatetime', 'core_langconfig')) : '-';
        
        $veranstalterTable->data[] = [
            $veranstalterId,
            $stats['api_calls'],
            $stats['errors'],
            $stats['cache_hits'],
            $lastRunText,
            $errorRate
        ];
    }
    
    echo html_writer::table($veranstalterTable);
}

// Add purge cache button
echo html_writer::start_div('mt-4');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'purge', 'value' => '1']);
echo html_writer::tag('button', get_string('purge_cache', 'local_eventocoursecreation'), 
    ['type' => 'submit', 'class' => 'btn btn-warning']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Add link to settings
echo html_writer::start_div('mt-4');
$settingsUrl = new moodle_url('/admin/settings.php', ['section' => 'local_eventocoursecreation_settings']);
echo html_writer::tag('a', get_string('change_settings', 'local_eventocoursecreation'), 
    ['href' => $settingsUrl, 'class' => 'btn btn-primary']);
echo html_writer::end_div();

echo $OUTPUT->footer();