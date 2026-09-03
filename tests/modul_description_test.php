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

global $CFG;
require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');

/**
 * Tests for the decision logic of the evento module description import.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eventocoursecreation\modul_description
 */
final class modul_description_test extends \advanced_testcase {

    /** Markup as the webservice delivers it, shortened but structurally identical. */
    private const SAMPLE = '<article id="modulbeschreibung" data-schema-version="1.0">
            <section id="moduleigenschaften">
                <h2>Moduleigenschaften</h2>
                <dl>
                    <dt>Modultyp</dt>
                    <dd id="modultyp" data-field="modultyp" data-value="2">Wahlpflichtmodul</dd>
                </dl>
            </section>
        </article>';

    /**
     * Builds a settings object with the defaults of the plugin.
     *
     * @param array $overrides values to override
     * @return \stdClass the settings
     */
    private function make_settings(array $overrides = array()): \stdClass {
        $settings = new \stdClass();
        $settings->allowedstatus = array(61003);
        $settings->futurevalid = true;
        $settings->scope = EVENTOCOURSECREATION_MB_SCOPE_CURRENT;
        $settings->cmidnumber = EVENTOCOURSECREATION_MB_CMIDNUMBER;
        $settings->batchsize = 200;
        $settings->retryhours = 24;
        foreach ($overrides as $name => $value) {
            $settings->$name = $value;
        }

        return $settings;
    }

    /**
     * Builds a normalized webservice answer.
     *
     * @param array $overrides values to override
     * @return \stdClass the answer as local_evento_evento_service::normalize_modulbeschreibung() returns it
     */
    private function make_normalized(array $overrides = array()): \stdClass {
        $normalized = new \stdClass();
        $normalized->idanlass = 36966;
        $normalized->idmb = 18;
        $normalized->idstatus = 61003;
        $normalized->mbtext = self::SAMPLE;
        $normalized->mbversion = 1.0;
        $normalized->mbversionscaled = 1000;
        $normalized->mbversionstring = '1.000';
        $normalized->mbgueltigabraw = '2026-09-14T00:00:00.000+02:00';
        $normalized->mbgueltigab = 1789336800;
        foreach ($overrides as $name => $value) {
            $normalized->$name = $value;
        }

        return $normalized;
    }

    /**
     * Builds a link record.
     *
     * @param array $overrides values to override
     * @return \stdClass the record
     */
    private function make_record(array $overrides = array()): \stdClass {
        $record = new \stdClass();
        $record->idmb = 18;
        $record->idstatus = 61003;
        $record->mbversionscaled = 1000;
        $record->mbgueltigab = 1789336800;
        $record->contenthash = sha1('content');
        foreach ($overrides as $name => $value) {
            $record->$name = $value;
        }

        return $record;
    }

    /**
     * The configured status list is turned into unique integers, empty means no filter.
     */
    public function test_parse_status_list(): void {
        $this->assertSame(array(61003), modul_description::parse_status_list('61003'));
        $this->assertSame(array(61003, 61006), modul_description::parse_status_list(' 61003 , 61006 '));
        $this->assertSame(array(61003), modul_description::parse_status_list('61003,61003'));
        $this->assertSame(array(), modul_description::parse_status_list(''));
        $this->assertSame(array(), modul_description::parse_status_list('nonsense'));
    }

    /**
     * Only values carrying the evento module prefix are asked for at the webservice.
     */
    public function test_looks_like_anlassnummer(): void {
        $this->assertTrue(modul_description::looks_like_anlassnummer('mod.boek-LEAD2.HS26_BS.001'));
        // Event numbers do contain umlauts.
        $this->assertTrue(modul_description::looks_like_anlassnummer('mod.bök-LEAD2.HS26_BS.001'));
        $this->assertTrue(modul_description::looks_like_anlassnummer('MOD.BSPEA2.HS16_BS.001'));
        // Evento puts further prefixes of its own on top of the module prefix.
        $this->assertTrue(modul_description::looks_like_anlassnummer('modk.cds-1092.1.HS26_BS.001'));
        $this->assertTrue(modul_description::looks_like_anlassnummer('mods.isc-EINF.HS26_BS.001'));
        $this->assertTrue(modul_description::looks_like_anlassnummer('modh.bau-DIGITEC2.HS26_BS.001'));
        $this->assertTrue(modul_description::looks_like_anlassnummer('modg.tom-LEAD.HS26_BS.001'));
        $this->assertFalse(modul_description::looks_like_anlassnummer(''));
        $this->assertFalse(modul_description::looks_like_anlassnummer('   '));
        $this->assertFalse(modul_description::looks_like_anlassnummer('mod'));
        // A bare category name carries the prefix but not the dotted structure.
        $this->assertFalse(modul_description::looks_like_anlassnummer('modk'));
        $this->assertFalse(modul_description::looks_like_anlassnummer('some-manual-idnumber'));
    }

    /**
     * The html5 markup of evento is carried over into class attributes.
     */
    public function test_convert_structure_turns_html5_markup_into_classes(): void {
        $result = modul_description::convert_structure(self::SAMPLE);

        // article and section become div, because the purifier would drop them.
        $this->assertStringNotContainsString('<article', $result);
        $this->assertStringNotContainsString('<section', $result);
        $this->assertStringContainsString('class="eventomb-modulbeschreibung eventomb"', $result);
        $this->assertStringContainsString('class="eventomb-moduleigenschaften eventomb-section"', $result);
        // The id and the two meaningful data attributes end up as classes.
        $this->assertStringContainsString('class="eventomb-modultyp eventomb-field-modultyp eventomb-value-2"', $result);
        // The schema version carries no meaning for the display and is dropped.
        $this->assertStringNotContainsString('schema-version', $result);
        // The content itself is untouched.
        $this->assertStringContainsString('Wahlpflichtmodul', $result);
        $this->assertStringContainsString('<dt>Modultyp</dt>', $result);
    }

    /**
     * The rewriting must not destroy umlauts, the descriptions are german.
     */
    public function test_convert_structure_keeps_umlauts(): void {
        $result = modul_description::convert_structure('<section id="x"><p>Präsenzstudium, Grösse, Übung</p></section>');

        $this->assertStringContainsString('Präsenzstudium, Grösse, Übung', $result);
    }

    /**
     * Empty input stays empty instead of producing an empty wrapper.
     */
    public function test_convert_structure_handles_empty_input(): void {
        $this->assertSame('', modul_description::convert_structure(''));
        $this->assertSame('', modul_description::convert_structure('   '));
    }

    /**
     * Evento delivers rich text fields entity encoded, the tags must not stay visible.
     */
    public function test_convert_structure_unpacks_an_escaped_field(): void {
        $html = '<dl><dt>Leitidee</dt><dd data-field="leitidee">'
            . '&lt;p&gt;&lt;strong&gt;Erste Zeile&lt;/strong&gt;&lt;/p&gt;'
            . '&lt;ol&gt;&lt;li&gt;Punkt&lt;/li&gt;&lt;/ol&gt;</dd></dl>';

        $result = modul_description::convert_structure($html);

        $this->assertStringContainsString('<strong>Erste Zeile</strong>', $result);
        $this->assertStringContainsString('<li>Punkt</li>', $result);
        $this->assertStringNotContainsString('&lt;p&gt;', $result);
    }

    /**
     * The double encoding evento produces in a link has to resolve to a usable url.
     */
    public function test_convert_structure_unpacks_a_double_encoded_link(): void {
        $html = '<dd data-field="literatur">&lt;p&gt;&lt;a href="https://example.org/?a=1&amp;amp;b=2"&gt;'
            . 'Link&lt;/a&gt;&lt;/p&gt;</dd>';

        $result = modul_description::convert_structure($html);

        $this->assertStringContainsString('href="https://example.org/?a=1&amp;b=2"', $result);
        $this->assertStringNotContainsString('&amp;amp;', $result);
    }

    /**
     * A field holding a plain value stays exactly as it is.
     */
    public function test_convert_structure_leaves_a_plain_field_alone(): void {
        $plain = modul_description::convert_structure('<dd data-field="hilfsmittel">keine</dd>');
        // A lone comparison sign is text and not the start of a tag.
        $sign = modul_description::convert_structure('<dd data-field="note">Note &lt; 4</dd>');

        $this->assertStringContainsString('>keine<', $plain);
        $this->assertStringContainsString('Note &lt; 4', $sign);
    }

    /**
     * Markup unpacked out of a field is purified like the rest of the description.
     */
    public function test_clean_content_purifies_an_unpacked_field(): void {
        $html = '<dd data-field="leitidee">&lt;p&gt;Text&lt;/p&gt;'
            . '&lt;script&gt;alert(1)&lt;/script&gt;'
            . '&lt;a href="javascript:alert(2)"&gt;boese&lt;/a&gt;</dd>';

        $result = modul_description::clean_content($html);

        $this->assertStringContainsString('<p>Text</p>', $result);
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert(1)', $result);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    /**
     * Cleaning removes what is dangerous and keeps what carries the description.
     */
    public function test_clean_content_removes_scripts_and_keeps_the_structure(): void {
        $html = '<article id="modulbeschreibung"><section id="a"><h2>Titel</h2>'
            . '<script>alert(1)</script>'
            . '<p onclick="alert(2)">Text</p>'
            . '<dl><dt>Modultyp</dt><dd data-field="modultyp">Pflichtmodul</dd></dl>'
            . '<table><tr><th>A</th><td>B</td></tr></table>'
            . '<a href="javascript:alert(3)">boese</a>'
            . '<a href="https://example.org">gut</a>'
            . '</section></article>';

        $result = modul_description::clean_content($html);

        // mod_page renders with noclean, so nothing dangerous may survive this step.
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert(1)', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('javascript:', $result);
        // The structure and the styling hooks survive the purifier.
        $this->assertStringContainsString('eventomb-a', $result);
        $this->assertStringContainsString('eventomb-field-modultyp', $result);
        $this->assertStringContainsString('<h2>Titel</h2>', $result);
        $this->assertStringContainsString('<dt>Modultyp</dt>', $result);
        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('https://example.org', $result);
    }

    /**
     * The configured heading is put in front of the description text.
     */
    public function test_add_heading(): void {
        $settings = $this->make_settings(array('heading' => 'Modulbeschreibung'));

        $result = modul_description::add_heading('<p>Text</p>', $settings);

        $this->assertStringStartsWith('<h2 class="eventomb-heading">Modulbeschreibung</h2>', $result);
        $this->assertStringEndsWith('<p>Text</p>', $result);
    }

    /**
     * An empty heading is a valid choice and adds nothing.
     */
    public function test_add_heading_can_be_switched_off(): void {
        $this->assertSame('<p>Text</p>',
            modul_description::add_heading('<p>Text</p>', $this->make_settings(array('heading' => ''))));
        $this->assertSame('<p>Text</p>',
            modul_description::add_heading('<p>Text</p>', $this->make_settings(array('heading' => '   '))));
    }

    /**
     * An empty description stays empty, otherwise the guard against an empty evento
     * answer overwriting a description would no longer hold.
     */
    public function test_add_heading_leaves_an_empty_description_empty(): void {
        $settings = $this->make_settings(array('heading' => 'Modulbeschreibung'));

        $this->assertSame('', modul_description::add_heading('', $settings));
        $this->assertSame('   ', modul_description::add_heading('   ', $settings));
        $this->assertNull(modul_description::content_hash(modul_description::add_heading('', $settings)));
    }

    /**
     * A heading given by an administrator is text and not markup.
     */
    public function test_add_heading_escapes_the_configured_text(): void {
        $settings = $this->make_settings(array('heading' => 'Beschreibung <b>fett</b>'));

        $result = modul_description::add_heading('<p>Text</p>', $settings);

        $this->assertStringContainsString('&lt;b&gt;fett&lt;/b&gt;', $result);
        $this->assertStringNotContainsString('<b>', $result);
    }

    /**
     * The heading is part of the hash, so the page is written once when it changes and
     * left alone afterwards.
     */
    public function test_add_heading_is_part_of_the_hash(): void {
        $without = modul_description::content_hash(
            modul_description::add_heading('<p>Text</p>', $this->make_settings(array('heading' => ''))));
        $with = modul_description::content_hash(
            modul_description::add_heading('<p>Text</p>', $this->make_settings(array('heading' => 'Titel'))));
        $again = modul_description::content_hash(
            modul_description::add_heading('<p>Text</p>', $this->make_settings(array('heading' => 'Titel'))));

        $this->assertNotSame($without, $with);
        $this->assertSame($with, $again);
    }

    /**
     * Cosmetic differences must not look like a change, otherwise the sync writes on every run.
     */
    public function test_normalize_content_ignores_cosmetic_differences(): void {
        $first = "<div>\r\n  <p>Text</p>\r\n</div>";
        $second = "<div><p>Text</p></div>";
        $third = "<div>\n\n\t<p>Text</p>\n</div>";

        $this->assertSame(modul_description::normalize_content($first), modul_description::normalize_content($second));
        $this->assertSame(modul_description::normalize_content($first), modul_description::normalize_content($third));
        $this->assertSame(modul_description::content_hash($first), modul_description::content_hash($third));
        // A real change still changes the hash.
        $this->assertNotSame(modul_description::content_hash($first), modul_description::content_hash('<div><p>Anders</p></div>'));
    }

    /**
     * There is no hash for content that would leave the page empty.
     */
    public function test_content_hash_is_null_for_empty_content(): void {
        $this->assertNull(modul_description::content_hash(''));
        $this->assertNull(modul_description::content_hash("  \n\t "));
        $this->assertNull(modul_description::content_hash(null));
        $this->assertNotNull(modul_description::content_hash('<p>x</p>'));
    }

    /**
     * Without an answer from evento nothing happens at all.
     */
    public function test_decide_without_description(): void {
        $result = modul_description::decide(null, null, null, null, $this->make_settings());

        $this->assertSame(modul_description::ACTION_SKIP_NODESCRIPTION, $result->action);
    }

    /**
     * A description which is not approved is not imported.
     */
    public function test_decide_rejects_a_status_which_is_not_allowed(): void {
        // 61001 is mb.Entwurf.
        $normalized = $this->make_normalized(array('idstatus' => 61001));

        $result = modul_description::decide(null, $normalized, sha1('x'), null, $this->make_settings());

        $this->assertSame(modul_description::ACTION_SKIP_STATUS, $result->action);
    }

    /**
     * An empty list of accepted status values switches the filter off.
     */
    public function test_decide_accepts_every_status_when_the_filter_is_empty(): void {
        $normalized = $this->make_normalized(array('idstatus' => 61001));
        $settings = $this->make_settings(array('allowedstatus' => array()));

        $result = modul_description::decide(null, $normalized, sha1('x'), null, $settings);

        $this->assertSame(modul_description::ACTION_CREATE, $result->action);
    }

    /**
     * A description valid in the future is held back only when that is configured.
     */
    public function test_decide_handles_a_future_validity(): void {
        $now = 1789000000;
        $normalized = $this->make_normalized(array('mbgueltigab' => $now + DAYSECS));

        $held = modul_description::decide(null, $normalized, sha1('x'), null,
            $this->make_settings(array('futurevalid' => false)), $now);
        $imported = modul_description::decide(null, $normalized, sha1('x'), null,
            $this->make_settings(array('futurevalid' => true)), $now);

        $this->assertSame(modul_description::ACTION_SKIP_FUTURE, $held->action);
        $this->assertSame(modul_description::ACTION_CREATE, $imported->action);
    }

    /**
     * An empty description never overwrites an existing page.
     */
    public function test_decide_never_writes_empty_content(): void {
        $record = $this->make_record();

        $result = modul_description::decide($record, $this->make_normalized(), null, $record->contenthash,
            $this->make_settings());

        $this->assertSame(modul_description::ACTION_SKIP_EMPTY, $result->action);
    }

    /**
     * Without a page there is nothing to compare, the page is created.
     */
    public function test_decide_creates_a_missing_page(): void {
        $withoutrecord = modul_description::decide(null, $this->make_normalized(), sha1('x'), null,
            $this->make_settings());
        // The record survived the deletion of the page.
        $withoutpage = modul_description::decide($this->make_record(), $this->make_normalized(), sha1('x'), null,
            $this->make_settings());

        $this->assertSame(modul_description::ACTION_CREATE, $withoutrecord->action);
        $this->assertSame(modul_description::ACTION_CREATE, $withoutpage->action);
    }

    /**
     * A page carried over by a course template is taken over instead of duplicated.
     */
    public function test_decide_takes_over_an_untracked_page(): void {
        $result = modul_description::decide(null, $this->make_normalized(), sha1('new'), sha1('from the template'),
            $this->make_settings());

        $this->assertSame(modul_description::ACTION_UPDATE, $result->action);
        $this->assertStringContainsString('taken over', $result->reason);
    }

    /**
     * Running twice without a change in evento writes nothing.
     */
    public function test_decide_is_idempotent(): void {
        $record = $this->make_record();

        $result = modul_description::decide($record, $this->make_normalized(), $record->contenthash,
            $record->contenthash, $this->make_settings());

        $this->assertSame(modul_description::ACTION_NONE, $result->action);
    }

    /**
     * A changed description is written.
     */
    public function test_decide_detects_a_changed_description(): void {
        $record = $this->make_record();
        $normalized = $this->make_normalized(array('mbversionscaled' => 1100));

        $result = modul_description::decide($record, $normalized, sha1('new'), $record->contenthash,
            $this->make_settings());

        $this->assertSame(modul_description::ACTION_UPDATE, $result->action);
    }

    /**
     * A page edited outside of this plugin is detected and reset.
     */
    public function test_decide_detects_a_manually_changed_page(): void {
        $record = $this->make_record();

        $result = modul_description::decide($record, $this->make_normalized(), $record->contenthash,
            sha1('edited by hand'), $this->make_settings());

        $this->assertSame(modul_description::ACTION_UPDATE, $result->action);
        $this->assertStringContainsString('outside of this plugin', $result->reason);
    }

    /**
     * A new version with identical text only moves the metadata on, the page is left alone.
     */
    public function test_decide_writes_metadata_only_when_the_text_is_identical(): void {
        $record = $this->make_record();
        $normalized = $this->make_normalized(array('mbversionscaled' => 1100));

        $result = modul_description::decide($record, $normalized, $record->contenthash, $record->contenthash,
            $this->make_settings());

        $this->assertSame(modul_description::ACTION_METADATA, $result->action);
    }

    /**
     * A stale description line is written back, even when text and metadata match.
     */
    public function test_decide_refreshes_an_outdated_description_line(): void {
        $record = $this->make_record();
        $normalized = $this->make_normalized();

        $result = modul_description::decide($record, $normalized, $record->contenthash, $record->contenthash,
            $this->make_settings(), null,
            modul_description::page_state('<p>Version 0.9</p>'),
            modul_description::page_state('<p>Version 1.0</p>'));

        $this->assertSame(modul_description::ACTION_UPDATE, $result->action);
        $this->assertStringContainsString('description line', $result->reason);
    }

    /**
     * A description line which only differs in whitespace is not a change.
     */
    public function test_decide_ignores_cosmetic_differences_in_the_description_line(): void {
        $record = $this->make_record();
        $normalized = $this->make_normalized();

        $result = modul_description::decide($record, $normalized, $record->contenthash, $record->contenthash,
            $this->make_settings(), null,
            modul_description::page_state("<p>  Version 1.0 </p>\n"),
            modul_description::page_state('<p>Version 1.0</p>'));

        $this->assertSame(modul_description::ACTION_NONE, $result->action);
    }

    /**
     * A new version reaches the page, because the line names the version.
     */
    public function test_decide_writes_the_page_when_only_the_version_changed(): void {
        $record = $this->make_record();
        $normalized = $this->make_normalized(array('mbversionscaled' => 1100, 'mbversion' => 1.1));

        $result = modul_description::decide($record, $normalized, $record->contenthash, $record->contenthash,
            $this->make_settings(), null,
            modul_description::page_state(modul_description::build_intro($this->make_normalized())),
            modul_description::page_state(modul_description::build_intro($normalized)));

        $this->assertSame(modul_description::ACTION_UPDATE, $result->action);
    }

    /**
     * A page renamed by hand gets the configured name back.
     */
    public function test_decide_detects_a_renamed_page(): void {
        $record = $this->make_record();
        $normalized = $this->make_normalized();

        $result = modul_description::decide($record, $normalized, $record->contenthash, $record->contenthash,
            $this->make_settings(), null,
            modul_description::page_state(null, 'Von Hand umbenannt'),
            modul_description::page_state(null, 'Modulbeschreibung'));

        $this->assertSame(modul_description::ACTION_UPDATE, $result->action);
        $this->assertStringContainsString('renamed', $result->reason);
    }

    /**
     * With the setting to keep the name the caller passes none, and nothing is written.
     */
    public function test_decide_keeps_a_renamed_page_when_configured(): void {
        $record = $this->make_record();
        $normalized = $this->make_normalized();

        $result = modul_description::decide($record, $normalized, $record->contenthash, $record->contenthash,
            $this->make_settings(), null,
            modul_description::page_state(null, 'Von Hand umbenannt'),
            modul_description::page_state(null, null));

        $this->assertSame(modul_description::ACTION_NONE, $result->action);
    }

    /**
     * Surrounding whitespace in a name is not a rename.
     */
    public function test_decide_ignores_whitespace_around_the_page_name(): void {
        $record = $this->make_record();
        $normalized = $this->make_normalized();

        $result = modul_description::decide($record, $normalized, $record->contenthash, $record->contenthash,
            $this->make_settings(), null,
            modul_description::page_state(null, ' Modulbeschreibung '),
            modul_description::page_state(null, 'Modulbeschreibung'));

        $this->assertSame(modul_description::ACTION_NONE, $result->action);
    }

    /**
     * The version keeps one decimal place, so version 1 does not read as a bare 1.
     */
    public function test_format_version(): void {
        $this->assertSame('1.0', modul_description::format_version(1.0));
        $this->assertSame('1.5', modul_description::format_version(1.5));
        $this->assertSame('1.25', modul_description::format_version(1.25));
        $this->assertSame('12.0', modul_description::format_version('12.000'));
        $this->assertNull(modul_description::format_version(null));
        $this->assertNull(modul_description::format_version(''));
    }

    /**
     * The description line names the version and the validity and is reproducible.
     */
    public function test_build_intro(): void {
        $normalized = $this->make_normalized();
        $intro = modul_description::build_intro($normalized);

        $this->assertStringContainsString('1.0', $intro);
        $this->assertStringContainsString(userdate($normalized->mbgueltigab,
            get_string_manager()->get_string('moduldescriptiondateformat', 'local_eventocoursecreation',
                null, modul_description::get_intro_language()),
            \core_date::get_server_timezone(), false, false), $intro);
        // Written twice in a row it has to come out identical, otherwise the comparison
        // in decide() would rewrite the page on every single run.
        $this->assertSame($intro, modul_description::build_intro($this->make_normalized()));
    }

    /**
     * Neither the language nor the time zone of the caller may change the line.
     */
    public function test_build_intro_does_not_depend_on_the_caller(): void {
        $this->resetAfterTest();

        $normalized = $this->make_normalized();
        $expected = modul_description::build_intro($normalized);

        // A run from the command line and the scheduled task can meet different values
        // here, and a line that differs would make the page be written on every run.
        $this->setUser($this->getDataGenerator()->create_user(
            array('lang' => 'en', 'timezone' => 'Pacific/Auckland')));
        $this->assertSame($expected, modul_description::build_intro($normalized));

        $this->setUser($this->getDataGenerator()->create_user(
            array('lang' => 'en', 'timezone' => 'America/Anchorage')));
        $this->assertSame($expected, modul_description::build_intro($normalized));
    }

    /**
     * Without a version and without a validity there is nothing to show.
     */
    public function test_build_intro_without_any_metadata(): void {
        $normalized = $this->make_normalized(array('mbversion' => null, 'mbgueltigab' => null));

        $this->assertSame('', modul_description::build_intro($normalized));
        $this->assertSame('', modul_description::build_intro(null));
    }

    /**
     * One missing value still leaves a usable line.
     */
    public function test_build_intro_with_only_one_value(): void {
        $onlyversion = modul_description::build_intro($this->make_normalized(array('mbgueltigab' => null)));
        $onlyvalidity = modul_description::build_intro($this->make_normalized(array('mbversion' => null)));

        $this->assertStringContainsString('1.0', $onlyversion);
        $this->assertNotSame('', $onlyvalidity);
        // The date carries digits and dots of its own, so the wording is the safer check.
        $this->assertStringNotContainsStringIgnoringCase('version', $onlyvalidity);
    }

    /**
     * A page left over from an earlier import must not read as an up to date course.
     */
    public function test_status_for_action(): void {
        // Evento offers nothing usable, no matter what the course still carries.
        foreach (array(modul_description::ACTION_SKIP_NODESCRIPTION, modul_description::ACTION_SKIP_STATUS,
                modul_description::ACTION_SKIP_FUTURE, modul_description::ACTION_SKIP_EMPTY,
                modul_description::ACTION_SKIP_DELETED) as $action) {
            $this->assertSame(EVENTOCOURSECREATION_MB_STATUS_MISSING,
                modul_description::status_for_action($action, 4711), $action);
            $this->assertSame(EVENTOCOURSECREATION_MB_STATUS_MISSING,
                modul_description::status_for_action($action, null), $action);
        }

        // Evento offers a usable description, so only the page decides.
        $this->assertSame(EVENTOCOURSECREATION_MB_STATUS_OK,
            modul_description::status_for_action(modul_description::ACTION_NONE, 4711));
        $this->assertSame(EVENTOCOURSECREATION_MB_STATUS_OK,
            modul_description::status_for_action(modul_description::ACTION_UPDATE, 4711));
        // A description evento offers but which is older than the imported one is fine.
        $this->assertSame(EVENTOCOURSECREATION_MB_STATUS_OK,
            modul_description::status_for_action(modul_description::ACTION_SKIP_OLDER, 4711));
        $this->assertSame(EVENTOCOURSECREATION_MB_STATUS_MISSING,
            modul_description::status_for_action(modul_description::ACTION_NONE, null));
    }

    /**
     * A link record alone is no proof that the course ever had a page.
     */
    public function test_had_a_page(): void {
        // Written by note_check() for a course evento has no description for.
        $neverhadone = $this->make_record(array('cmid' => null, 'timemodified' => 0));
        // The page was written and is still there.
        $haspage = $this->make_record(array('cmid' => 4711, 'timemodified' => 1789336800));
        // The page was written, deleted by hand, and the cmid dropped afterwards.
        $lostit = $this->make_record(array('cmid' => null, 'timemodified' => 1789336800));

        $this->assertFalse(modul_description::had_a_page($neverhadone));
        $this->assertFalse(modul_description::had_a_page(null));
        $this->assertTrue(modul_description::had_a_page($haspage));
        $this->assertTrue(modul_description::had_a_page($lostit));
    }

    /**
     * A step backwards in evento does not overwrite a newer imported state.
     */
    public function test_decide_refuses_an_older_state(): void {
        $record = $this->make_record(array('mbversionscaled' => 2000));
        $normalized = $this->make_normalized(array('mbversionscaled' => 1000));

        $result = modul_description::decide($record, $normalized, sha1('old'), $record->contenthash,
            $this->make_settings());

        $this->assertSame(modul_description::ACTION_SKIP_OLDER, $result->action);
    }

    /**
     * A replacing description is imported even when it starts at a lower version again.
     */
    public function test_decide_accepts_a_replacing_description_with_a_lower_version(): void {
        $record = $this->make_record(array('idmb' => 18, 'mbversionscaled' => 2000));
        $normalized = $this->make_normalized(array('idmb' => 19, 'mbversionscaled' => 1000));

        $result = modul_description::decide($record, $normalized, sha1('new'), $record->contenthash,
            $this->make_settings());

        $this->assertSame(modul_description::ACTION_UPDATE, $result->action);
    }

    /**
     * A module without a description in evento is an answer and not a broken webservice.
     */
    public function test_is_nodescription_fault(): void {
        $fault = 'EventoHibernateManager -> getEventoModulBeschreibung -> '
            . 'org.apache.axis2.dataretrieval.DataRetrievalException: Keine Modulbeschreibung gefunden';

        $this->assertTrue(modul_description_sync::is_nodescription_fault($fault));
        // The wording alone is enough, so is the exception class alone.
        $this->assertTrue(modul_description_sync::is_nodescription_fault('keine modulbeschreibung gefunden'));
        $this->assertTrue(modul_description_sync::is_nodescription_fault('DataRetrievalException: nothing'));
    }

    /**
     * A real problem of the webservice must not be mistaken for a missing description.
     */
    public function test_is_nodescription_fault_keeps_real_failures_apart(): void {
        $this->assertFalse(modul_description_sync::is_nodescription_fault('Could not connect to host'));
        $this->assertFalse(modul_description_sync::is_nodescription_fault('Unable to parse URL'));
        $this->assertFalse(modul_description_sync::is_nodescription_fault(
            'The endpoint reference (EPR) for the Operation not found is http://example.org/EventoWebservice'));
        $this->assertFalse(modul_description_sync::is_nodescription_fault(''));
        $this->assertFalse(modul_description_sync::is_nodescription_fault(null));
    }

    /**
     * The course idnumber is the first source of the event number.
     */
    public function test_resolve_anlassnummer_from_the_course_idnumber(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(array('idnumber' => 'mod.boek-LEAD2.HS26_BS.001'));

        $this->assertSame('mod.boek-LEAD2.HS26_BS.001', modul_description::resolve_anlassnummer($course));
    }

    /**
     * Without a usable idnumber the oldest evento enrolment instance is used.
     */
    public function test_resolve_anlassnummer_from_the_enrolment_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(array('idnumber' => 'handmade'));

        $DB->insert_record('enrol', (object)array(
            'enrol' => 'evento',
            'status' => 0,
            'courseid' => $course->id,
            'customtext1' => 'mod.boek-LEAD2.HS26_BS.002',
            'sortorder' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ));

        $this->assertSame('mod.boek-LEAD2.HS26_BS.002', modul_description::resolve_anlassnummer($course));
    }

    /**
     * A course without any evento reference is left alone.
     */
    public function test_resolve_anlassnummer_without_a_reference(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->assertNull(modul_description::resolve_anlassnummer($course));
    }

    /**
     * The page is recognised by the course module idnumber, not by its name.
     */
    public function test_find_managed_pages(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $managed = $this->getDataGenerator()->create_module('page', array('course' => $course->id),
            array('idnumber' => EVENTOCOURSECREATION_MB_CMIDNUMBER));
        // A page a teacher created, carrying the same name but no marker.
        $this->getDataGenerator()->create_module('page',
            array('course' => $course->id, 'name' => EVENTOCOURSECREATION_MB_PAGENAME));

        $pages = modul_description::find_managed_pages($course->id, EVENTOCOURSECREATION_MB_CMIDNUMBER);
        $found = modul_description::find_existing_page($course->id, EVENTOCOURSECREATION_MB_CMIDNUMBER);

        $this->assertCount(1, $pages);
        $this->assertNotNull($found);
        $this->assertSame($managed->cmid, $found->id);
    }

    /**
     * A course template carries the page over, both copies have to be found.
     */
    public function test_find_managed_pages_finds_a_duplicate(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', array('course' => $course->id),
            array('idnumber' => EVENTOCOURSECREATION_MB_CMIDNUMBER));
        // A restore blanks the idnumber of the second copy, so mark it the way a manual copy would look.
        $second = $this->getDataGenerator()->create_module('page', array('course' => $course->id));
        // This core function purges the module cache and rebuilds the course cache itself.
        set_coursemodule_idnumber($second->cmid, EVENTOCOURSECREATION_MB_CMIDNUMBER);

        $pages = modul_description::find_managed_pages($course->id, EVENTOCOURSECREATION_MB_CMIDNUMBER);

        $this->assertCount(2, $pages);
    }

    /**
     * Courses which have ended are never touched, running and future ones are.
     */
    public function test_get_sync_candidates_scope_current(): void {
        $this->resetAfterTest();
        $now = time();
        $running = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.a.HS26_BS.001', 'startdate' => $now - WEEKSECS, 'enddate' => $now + WEEKSECS));
        $future = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.b.HS26_BS.001', 'startdate' => $now + WEEKSECS, 'enddate' => $now + 4 * WEEKSECS));
        $openended = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.c.HS26_BS.001', 'startdate' => $now - WEEKSECS, 'enddate' => 0));
        $ended = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.d.HS25_BS.001', 'startdate' => $now - 8 * WEEKSECS, 'enddate' => $now - WEEKSECS));

        $candidates = modul_description::get_sync_candidates(
            $this->make_settings(array('scope' => EVENTOCOURSECREATION_MB_SCOPE_CURRENT)), $now);

        $this->assertArrayHasKey($running->id, $candidates);
        $this->assertArrayHasKey($future->id, $candidates);
        $this->assertArrayHasKey($openended->id, $candidates);
        $this->assertArrayNotHasKey($ended->id, $candidates);
    }

    /**
     * The second scope narrows the synchronisation down to courses which have not started.
     */
    public function test_get_sync_candidates_scope_future(): void {
        $this->resetAfterTest();
        $now = time();
        $running = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.a.HS26_BS.001', 'startdate' => $now - WEEKSECS, 'enddate' => $now + WEEKSECS));
        $future = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.b.HS26_BS.001', 'startdate' => $now + WEEKSECS, 'enddate' => $now + 4 * WEEKSECS));

        $candidates = modul_description::get_sync_candidates(
            $this->make_settings(array('scope' => EVENTOCOURSECREATION_MB_SCOPE_FUTURE)), $now);

        $this->assertArrayNotHasKey($running->id, $candidates);
        $this->assertArrayHasKey($future->id, $candidates);
    }

    /**
     * Courses without any evento reference never reach the webservice.
     */
    public function test_get_sync_candidates_skips_courses_without_an_evento_reference(): void {
        $this->resetAfterTest();
        $now = time();
        $plain = $this->getDataGenerator()->create_course(array('startdate' => $now, 'enddate' => 0));

        $candidates = modul_description::get_sync_candidates($this->make_settings(), $now);

        $this->assertArrayNotHasKey($plain->id, $candidates);
    }

    /**
     * A disabled course and a course held back after a failure are skipped.
     */
    public function test_get_sync_candidates_respects_status_and_backoff(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $disabled = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.a.HS26_BS.001', 'startdate' => $now, 'enddate' => 0));
        $failed = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.b.HS26_BS.001', 'startdate' => $now, 'enddate' => 0));
        $retryable = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.c.HS26_BS.001', 'startdate' => $now, 'enddate' => 0));

        $DB->insert_record('eventocoursecreation_page', (object)array(
            'courseid' => $disabled->id, 'anlassnummer' => 'mod.a.HS26_BS.001',
            'status' => EVENTOCOURSECREATION_MB_STATUS_DISABLED, 'errorcount' => 0,
            'timecreated' => $now, 'timemodified' => $now, 'timechecked' => 0));
        $DB->insert_record('eventocoursecreation_page', (object)array(
            'courseid' => $failed->id, 'anlassnummer' => 'mod.b.HS26_BS.001',
            'status' => EVENTOCOURSECREATION_MB_STATUS_ERROR, 'errorcount' => 3,
            'timecreated' => $now, 'timemodified' => $now, 'timechecked' => $now - HOURSECS));
        $DB->insert_record('eventocoursecreation_page', (object)array(
            'courseid' => $retryable->id, 'anlassnummer' => 'mod.c.HS26_BS.001',
            'status' => EVENTOCOURSECREATION_MB_STATUS_ERROR, 'errorcount' => 3,
            'timecreated' => $now, 'timemodified' => $now, 'timechecked' => $now - 48 * HOURSECS));

        $candidates = modul_description::get_sync_candidates($this->make_settings(array('retryhours' => 24)), $now);

        $this->assertArrayNotHasKey($disabled->id, $candidates);
        $this->assertArrayNotHasKey($failed->id, $candidates);
        $this->assertArrayHasKey($retryable->id, $candidates);
    }

    /**
     * A course used as a template must not be filled by the synchronisation.
     */
    public function test_get_sync_candidates_skips_template_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $template = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.template.HS26_BS.001', 'startdate' => $now, 'enddate' => 0));
        $category = $this->getDataGenerator()->create_category();

        $DB->insert_record('eventocoursecreation', (object)array(
            'category' => $category->id,
            'templatecourse' => $template->id,
            'enablecoursetemplate' => 1,
            'starttimespringtermday' => 15,
            'starttimespringtermmonth' => 12,
            'starttimeautumntermday' => 2,
            'starttimeautumntermmonth' => 8,
            'timemodified' => $now));

        $candidates = modul_description::get_sync_candidates($this->make_settings(), $now);

        $this->assertArrayNotHasKey($template->id, $candidates);
    }

    /**
     * The courses checked longest ago come first, so every course is reached in turn.
     */
    public function test_get_sync_candidates_orders_by_the_oldest_check(): void {
        global $DB;
        $this->resetAfterTest();
        $now = time();
        $recent = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.a.HS26_BS.001', 'startdate' => $now, 'enddate' => 0));
        $stale = $this->getDataGenerator()->create_course(array(
            'idnumber' => 'mod.b.HS26_BS.001', 'startdate' => $now, 'enddate' => 0));

        $DB->insert_record('eventocoursecreation_page', (object)array(
            'courseid' => $recent->id, 'anlassnummer' => 'mod.a.HS26_BS.001',
            'status' => EVENTOCOURSECREATION_MB_STATUS_OK, 'errorcount' => 0,
            'timecreated' => $now, 'timemodified' => $now, 'timechecked' => $now));
        $DB->insert_record('eventocoursecreation_page', (object)array(
            'courseid' => $stale->id, 'anlassnummer' => 'mod.b.HS26_BS.001',
            'status' => EVENTOCOURSECREATION_MB_STATUS_OK, 'errorcount' => 0,
            'timecreated' => $now, 'timemodified' => $now, 'timechecked' => $now - WEEKSECS));

        $candidates = modul_description::get_sync_candidates($this->make_settings(), $now, 1);

        $this->assertCount(1, $candidates);
        $this->assertArrayHasKey($stale->id, $candidates);
    }
}
