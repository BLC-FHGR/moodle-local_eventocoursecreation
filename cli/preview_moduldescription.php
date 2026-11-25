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
 * Shows what the plugin would write into the page of a course.
 *
 * The script only reads, it never changes a course. It is meant to be run
 * whenever evento changes the markup of its module descriptions, because the
 * conversion and the cleaning happen before anything reaches the course.
 *
 * Usage help:
 * $ sudo -u www-data /usr/bin/php local/eventocoursecreation/cli/preview_moduldescription.php -h
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once("$CFG->libdir/clilib.php");

list($options, $unrecognized) = cli_get_params(
    array('anlassnummer' => false, 'courseid' => false, 'sample' => false,
        'wslocation' => false, 'wsdl' => false, 'raw' => false, 'soap' => false, 'help' => false),
    array('h' => 'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$hassource = !empty($options['anlassnummer']) || !empty($options['courseid']) || !empty($options['sample']);

if ($options['help'] || !$hassource) {
    echo "Show how the plugin turns an evento module description into page content.

This script changes nothing. It fetches one description, runs it through the same
conversion and cleaning the import uses and prints the result, which is exactly
what would end up in the content of the page.

Options:
--anlassnummer=NUMBER Ask the webservice for this evento event number
--courseid=ID         Take the event number from this course
--sample              Use a built in sample instead of the webservice
--wslocation=URL      Query another endpoint for this call only
--wsdl=FILENAME       Use another wsdl file for this call only
--raw                 Also print the untouched markup of evento
--soap                Also print the untouched SOAP responses of evento, including the
                      fields the local wsdl does not know. The personal data of the
                      event leaders is left out.
-h, --help            Print out this help

Example:
\$ sudo -u www-data /usr/bin/php local/eventocoursecreation/cli/preview_moduldescription.php \\
      --courseid=42
";
    exit(0);
}

// A small piece of the markup evento delivers, with a script tag and a javascript
// link added, so the checks at the end prove that the cleaning really removes them.
$sample = '<article id="modulbeschreibung" data-schema-version="1.0">
    <section id="moduleigenschaften">
        <h2>Moduleigenschaften</h2>
        <dl>
            <dt>Modultyp</dt>
            <dd id="modultyp" data-field="modultyp" data-value="2">Wahlpflichtmodul</dd>
        </dl>
        <p style="color:#FF0000">Praesenzstudium, Groesse, Uebung</p>
        <script>alert(1)</script>
        <a href="javascript:alert(2)">boese</a>
    </section>
</article>';

/**
 * Prints the untouched SOAP responses collected so far.
 *
 * @param array $responses the responses keyed by the name of the operation
 * @return void
 */
function local_eventocoursecreation_preview_print_soap(array $responses) {
    foreach ($responses as $operation => $response) {
        cli_writeln('');
        cli_writeln('== SOAP response of ' . $operation . ' ==');
        $formatted = \local_eventocoursecreation\soap_response::format($response);
        cli_writeln($formatted === '' ? '  none, the call did not get as far as an answer' : $formatted);
    }
}

$anlassnummer = empty($options['anlassnummer']) ? null : (string)$options['anlassnummer'];

if (!empty($options['courseid'])) {
    $course = $DB->get_record('course', array('id' => (int)$options['courseid']), '*', MUST_EXIST);
    $anlassnummer = \local_eventocoursecreation\modul_description::resolve_anlassnummer($course);
    if (is_null($anlassnummer)) {
        cli_error('Course ' . $course->id . ' carries no evento event number.');
    }
}

$soap = !empty($options['soap']);
if ($soap && is_null($anlassnummer)) {
    cli_problem('--soap needs the webservice, the built in sample has no SOAP response to show.');
    $soap = false;
}
$soapresponses = array();

cli_writeln('== Source ==');

if (is_null($anlassnummer)) {
    $raw = $sample;
    // A value of its own, so the sample also shows where the credits sentence lands.
    $ects = 4;
    cli_writeln('  built in sample, with 4 credits made up for it');
} else {
    $overrides = array();
    if (!empty($options['wslocation'])) {
        $overrides['wslocation'] = $options['wslocation'];
    }
    if (!empty($options['wsdl'])) {
        $overrides['wswsdlfilename'] = $options['wsdl'];
    }
    if ($soap) {
        // Without the trace the client forgets the response as soon as it has decoded it.
        $overrides['wstrace'] = 1;
    }

    // Named before the call, so a failing call still says what was asked for.
    cli_writeln('  anlassNummer:  ' . $anlassnummer);

    $client = null;
    $operation = null;
    // Keeps the response of the last call, also when that call failed.
    $keepsoap = function() use (&$client, &$operation, &$soapresponses, $soap) {
        if ($soap && !is_null($client) && !is_null($operation)) {
            $soapresponses[$operation] = $client->__getLastResponse();
        }
    };

    try {
        $client = empty($overrides) ? null : local_evento_evento_service::create_soap_client($overrides);
        $service = new local_evento_evento_service($client);
        // The credits live on the event and not on the module description. The event is
        // asked for first, so that --soap shows it even when evento knows no description.
        $operation = 'listEventoAnlass';
        $eventanswer = $service->get_event_by_number($anlassnummer);
        $keepsoap();
        $operation = 'getEventoModulBeschreibung';
        $answer = $service->get_modulbeschreibung_by_number($anlassnummer);
        $keepsoap();
    } catch (local_evento_service_exception $ex) {
        $keepsoap();
        local_eventocoursecreation_preview_print_soap($soapresponses);
        if ($ex->means_notfound()) {
            // Evento reports a module without a description with a fault and not with an
            // empty response, so this is the answer and not a problem, and the import
            // skips such a course instead of failing over it.
            cli_writeln('  evento knows no module description for this event number');
            cli_writeln('  the import would skip this course, there is nothing to preview');
            exit(0);
        }
        // The default handler hides the debug information unless debugging is on,
        // which turns a webservice problem into an unusable one line message. The
        // faultstring names the real cause, getMessage() is the localised wrapper.
        cli_problem(get_class($ex) . ': ' . ($ex->faultstring ?? $ex->getMessage()));
        cli_problem('Operation: ' . $ex->operation . ', faultcode: ' . ($ex->faultcode ?? '-'));
        if (!empty($ex->debuginfo)) {
            cli_problem('Debug info: ' . $ex->debuginfo);
        }
        exit(1);
    } catch (Throwable $ex) {
        $keepsoap();
        local_eventocoursecreation_preview_print_soap($soapresponses);
        cli_problem(get_class($ex) . ': ' . $ex->getMessage());
        if (!empty($ex->debuginfo)) {
            cli_problem('Debug info: ' . $ex->debuginfo);
        }
        exit(1);
    }

    if (is_null($answer)) {
        // The webservice answered without a description. It reports a module it has none
        // for with a fault, which is caught above, so this stays for an empty response.
        cli_writeln('  evento knows no module description for this event number');
        cli_writeln('  the import would skip this course, there is nothing to preview');
        local_eventocoursecreation_preview_print_soap($soapresponses);
        exit(0);
    }

    $normalized = local_evento_evento_service::normalize_modulbeschreibung($answer);
    $raw = (string)$normalized->mbtext;
    $settings = \local_eventocoursecreation\modul_description::get_settings();
    $accepted = empty($settings->allowedstatus) || in_array((int)$normalized->idstatus, $settings->allowedstatus);

    cli_writeln('  idMB:          ' . $normalized->idmb);
    cli_writeln('  idStatus:      ' . $normalized->idstatus
        . ($accepted ? '' : ', NOT in the accepted status ids, this description would be skipped'));
    cli_writeln('  mbVersion:     ' . $normalized->mbversionstring
        . ' (scaled ' . $normalized->mbversionscaled . ')');
    $event = is_array($eventanswer) ? reset($eventanswer) : $eventanswer;
    $ects = (is_object($event) && is_numeric($event->anlass_ECTS ?? null)) ? (float)$event->anlass_ECTS : null;

    cli_writeln('  anlass_ECTS:   ' . (is_null($ects) ? 'evento names none' : $ects));
    cli_writeln('  mbGueltigAb:   ' . $normalized->mbgueltigabraw
        . (($normalized->mbgueltigab && $normalized->mbgueltigab > time())
            ? ($settings->futurevalid ? ', in the future but accepted' : ', in the future, this would be skipped')
            : ''));
}

// Stays empty without --soap and for the built in sample.
local_eventocoursecreation_preview_print_soap($soapresponses);

$converted = \local_eventocoursecreation\modul_description::convert_structure($raw);
$previewsettings = \local_eventocoursecreation\modul_description::get_settings();
// The same call the import makes, so the preview shows exactly what would be written.
$cleaned = \local_eventocoursecreation\modul_description::build_content($raw, $ects, $previewsettings);
$hash = \local_eventocoursecreation\modul_description::content_hash($cleaned);
// The built in sample carries no evento metadata, so the description stays empty there.
$intro = \local_eventocoursecreation\modul_description::build_intro($normalized ?? null);

if (!empty($options['raw'])) {
    cli_writeln('');
    cli_writeln('== Raw markup of evento (' . strlen($raw) . ' bytes) ==');
    cli_writeln($raw);

    cli_writeln('');
    cli_writeln('== After the structure has been rewritten (' . strlen($converted) . ' bytes) ==');
    cli_writeln($converted);
}

cli_writeln('');
cli_writeln('== The description shown above the content ==');
cli_writeln('  ' . ($intro === '' ? 'none, evento names neither a version nor a validity' : $intro));

cli_writeln('');
cli_writeln('== This is what the page would hold (' . strlen($cleaned) . ' bytes) ==');
cli_writeln($cleaned);

cli_writeln('');
cli_writeln('== Comparison hash ==');
cli_writeln('  ' . ($hash ?? 'null, the content would be refused as empty'));

// The page is rendered without cleaning at display time, see mod/page/view.php, so a
// failing check here would mean unsafe markup ends up in the course.
cli_writeln('');
cli_writeln('== Checks ==');
$checks = array(
    'no script tag' => stripos($cleaned, '<script') === false,
    'no javascript uri' => stripos($cleaned, 'javascript:') === false,
    'no article tag left' => stripos($cleaned, '<article') === false,
    'no section tag left' => stripos($cleaned, '<section') === false,
    'class attributes survived' => stripos($cleaned, 'class="'
        . \local_eventocoursecreation\modul_description::CLASS_PREFIX) !== false,
    'headings survived' => (bool)preg_match('/<h[1-6][ >]/i', $cleaned),
    'definition list survived' => stripos($cleaned, '<dl') !== false,
    // A second build has to come out the same, or the import would rewrite the page on
    // every run. The heading and the credits are part of the content, so both builds
    // have to include them, comparing against the bare cleaned text always failed.
    'the hash is stable' => $hash === \local_eventocoursecreation\modul_description::content_hash(
        \local_eventocoursecreation\modul_description::build_content($raw, $ects, $previewsettings)),
);
$failed = 0;
foreach ($checks as $label => $ok) {
    cli_writeln('  [' . ($ok ? 'ok  ' : 'FAIL') . '] ' . $label);
    $failed += $ok ? 0 : 1;
}

cli_writeln('');
exit($failed > 0 ? 1 : 0);
