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

// The class loader includes this file from inside a method, so $CFG is not in scope on its own.
global $CFG;
require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');
require_once($CFG->libdir . '/resourcelib.php');

/**
 * Decision logic of the evento module description import.
 *
 * This class holds everything that can be decided without writing to a course:
 * which event number belongs to a course, how the evento html is turned into
 * something moodle may store, whether anything has to be written at all, and
 * which courses are due for a synchronisation. The writing part lives in
 * {@see modul_description_page}.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modul_description {

    /** Nothing has to be done, the page is up to date. */
    const ACTION_NONE = 'none';
    /** The page has to be created. */
    const ACTION_CREATE = 'create';
    /** The page content or name has to be written. */
    const ACTION_UPDATE = 'update';
    /** Only the evento metadata of the link record has to be written. */
    const ACTION_METADATA = 'metadata';
    /** Evento knows no description for this event number. */
    const ACTION_SKIP_NODESCRIPTION = 'skipnodescription';
    /** The status of the description is not accepted. */
    const ACTION_SKIP_STATUS = 'skipstatus';
    /** The description is not valid yet. */
    const ACTION_SKIP_FUTURE = 'skipfuture';
    /** The description is empty once it has been cleaned. */
    const ACTION_SKIP_EMPTY = 'skipempty';
    /** Evento offers an older state than the one already imported. */
    const ACTION_SKIP_OLDER = 'skipolder';
    /** The page was deleted by hand and the configuration says not to bring it back. */
    const ACTION_SKIP_DELETED = 'skipdeleted';

    /** Prefix of every css class this plugin generates out of the evento markup. */
    const CLASS_PREFIX = 'eventomb';

    /** Id of the wrapper element used while rewriting the evento markup. */
    const DOM_ROOT_ID = 'eventomb-root';

    /**
     * Reads the plugin configuration of this feature into one object.
     *
     * @return \stdClass the settings, with the list of accepted status ids already parsed
     */
    public static function get_settings(): \stdClass {
        $config = get_config('local_eventocoursecreation');

        $settings = new \stdClass();
        $settings->enabled = !empty($config->enableplugin) && !empty($config->enablemoduldescription);
        $settings->scope = $config->moduldescriptionscope ?? EVENTOCOURSECREATION_MB_SCOPE_CURRENT;
        $settings->pagename = trim((string)($config->moduldescriptionpagename ?? EVENTOCOURSECREATION_MB_PAGENAME));
        $settings->cmidnumber = trim((string)($config->moduldescriptioncmidnumber ?? EVENTOCOURSECREATION_MB_CMIDNUMBER));
        $settings->allowedstatus = self::parse_status_list(
            $config->moduldescriptionallowedstatus ?? EVENTOCOURSECREATION_MB_ALLOWEDSTATUS);
        $settings->futurevalid = !empty($config->moduldescriptionfuturevalid);
        $settings->display = (int)($config->moduldescriptiondisplay ?? RESOURCELIB_DISPLAY_AUTO);
        $settings->printlastmodified = empty($config->moduldescriptionprintlastmodified) ? 0 : 1;
        $settings->ondelete = $config->moduldescriptionondelete ?? EVENTOCOURSECREATION_MB_ONDELETE_RECREATE;
        $settings->onrename = $config->moduldescriptiononrename ?? EVENTOCOURSECREATION_MB_ONRENAME_RESET;
        $settings->batchsize = (int)($config->moduldescriptionbatchsize ?? EVENTOCOURSECREATION_MB_BATCHSIZE);
        $settings->retryhours = (int)($config->moduldescriptionretryhours ?? EVENTOCOURSECREATION_MB_RETRYHOURS);

        // Guard against a configuration that would stall the task or hammer the webservice.
        if ($settings->batchsize < 1) {
            $settings->batchsize = EVENTOCOURSECREATION_MB_BATCHSIZE;
        }
        if ($settings->retryhours < 0) {
            $settings->retryhours = EVENTOCOURSECREATION_MB_RETRYHOURS;
        }
        if ($settings->pagename === '') {
            $settings->pagename = EVENTOCOURSECREATION_MB_PAGENAME;
        }
        if ($settings->cmidnumber === '') {
            $settings->cmidnumber = EVENTOCOURSECREATION_MB_CMIDNUMBER;
        }

        return $settings;
    }

    /**
     * Turns the configured list of accepted evento status ids into integers.
     *
     * An empty list means that every status is accepted.
     *
     * @param string $list comma separated list of status ids
     * @return int[] the accepted status ids
     */
    public static function parse_status_list($list): array {
        $result = array();
        foreach (explode(',', (string)$list) as $value) {
            $value = trim($value);
            if ($value === '' || !is_numeric($value)) {
                continue;
            }
            $result[] = (int)$value;
        }

        return array_values(array_unique($result));
    }

    /**
     * Determines the evento event number a course has to be synchronised with.
     *
     * The course idnumber is written from the evento event number when the course is
     * created, so it is the first choice. Courses which were created by hand or whose
     * idnumber was changed fall back to the oldest evento enrolment instance, which
     * carries the event number in customtext1.
     *
     * @param \stdClass $course the course record, idnumber and id are used
     * @return string|null the event number or null if the course has no evento reference
     */
    public static function resolve_anlassnummer(\stdClass $course): ?string {
        global $DB;

        $idnumber = trim((string)($course->idnumber ?? ''));
        if (self::looks_like_anlassnummer($idnumber)) {
            return $idnumber;
        }

        $instances = $DB->get_records('enrol', array('courseid' => $course->id, 'enrol' => 'evento'),
            'id ASC', 'id, customtext1');
        foreach ($instances as $instance) {
            $number = trim((string)$instance->customtext1);
            if (self::looks_like_anlassnummer($number)) {
                return $number;
            }
        }

        return null;
    }

    /**
     * Checks whether a value looks like an evento event number.
     *
     * Every event number this plugin works with starts with the module prefix of the
     * category idnumber, for example "mod.bök-LEAD2.HS26_BS.001". The prefix is not
     * always followed by the separator: evento uses further prefixes of its own on top
     * of it, modk, mods, modh and modg among them, and the course creation of this
     * plugin accepts all of them, see get_module_ids() in {@see course_creation}. The
     * check is deliberately as wide as that one. A value which is no event number after
     * all costs one webservice call, which answers that it knows no description.
     *
     * @param string $value the value to check
     * @return bool true if the value may be an event number
     */
    public static function looks_like_anlassnummer($value): bool {
        $value = trim((string)$value);
        $prefix = EVENTOCOURSECREATION_IDNUMBER_PREFIX;
        if ($value === '' || strncasecmp($value, $prefix, strlen($prefix)) !== 0) {
            return false;
        }

        // Every event number carries the dotted structure, a bare category name does not.
        return strpos($value, '.') !== false;
    }

    /**
     * Rewrites the evento markup into something moodle keeps.
     *
     * Moodle purifies html against the doctype XHTML 1.0 Transitional, see
     * purify_html() in lib/weblib.php. The html5 elements article and section, the id
     * attribute and every data attribute of the evento markup are therefore dropped.
     * This method carries that information over into class attributes, which survive,
     * so the description keeps stable styling hooks.
     *
     * @param string $html the raw html of the evento field mbText
     * @return string the rewritten html, still unpurified
     */
    public static function convert_structure($html): string {
        $html = (string)$html;
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        // The meta element tells libxml the encoding, without it the markup is read as iso-8859-1.
        $wrapped = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
            . '<div id="' . self::DOM_ROOT_ID . '">' . $html . '</div>';
        $loaded = $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            // Unparsable markup is handed over untouched, the purifier still has to run on it.
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $root = $xpath->query('//div[@id="' . self::DOM_ROOT_ID . '"]')->item(0);
        if (is_null($root)) {
            return $html;
        }

        // Runs first, so that the markup unpacked here is rewritten by the passes below
        // like the rest of the document.
        foreach ($xpath->query('.//*[@data-field]', $root) as $element) {
            self::unpack_escaped_field($doc, $element);
        }

        // The node list of a xpath query is static, so the tree may be changed while iterating.
        foreach ($xpath->query('.//*', $root) as $element) {
            self::attributes_to_classes($element);
        }
        foreach ($xpath->query('.//article | .//section', $root) as $element) {
            self::rename_element($doc, $element, 'div');
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    /**
     * Turns the escaped markup of a rich text field back into real elements.
     *
     * Evento embeds the value of a rich text field entity encoded into the surrounding
     * document, so a field holds the characters &lt;p&gt; and not a paragraph. Left alone
     * the reader sees the tags as text. The unpacked markup is purified afterwards like
     * everything else, so nothing unsafe can enter the course through this.
     *
     * Only a field which holds nothing but text is looked at, and only when that text
     * starts with a tag and closes one again. A field carrying a single character like a
     * comparison sign is therefore left untouched.
     *
     * @param \DOMDocument $doc the document the element belongs to
     * @param \DOMElement $element the field element, changed in place
     * @return bool true if the field had to be unpacked
     */
    protected static function unpack_escaped_field(\DOMDocument $doc, \DOMElement $element): bool {
        foreach ($element->childNodes as $child) {
            if ($child->nodeType !== XML_TEXT_NODE && $child->nodeType !== XML_CDATA_SECTION_NODE) {
                // The field already carries real elements, there is nothing encoded here.
                return false;
            }
        }

        $text = $element->textContent;
        if (!preg_match('/^\s*<[a-z][a-z0-9]*(\s[^>]*)?>/i', $text) || !preg_match('/<\/[a-z][a-z0-9]*>/i', $text)) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $inner = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
            . '<div id="' . self::DOM_ROOT_ID . '">' . $text . '</div>';
        $loaded = $inner->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return false;
        }

        $innerroot = (new \DOMXPath($inner))->query('//div[@id="' . self::DOM_ROOT_ID . '"]')->item(0);
        if (is_null($innerroot)) {
            return false;
        }

        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }
        // Copied out of the node list first, appendChild() moves the nodes and would
        // otherwise change the list while it is being walked.
        foreach (iterator_to_array($innerroot->childNodes) as $child) {
            $element->appendChild($doc->importNode($child, true));
        }

        return true;
    }

    /**
     * Carries the id and the data attributes of one element over into its class attribute.
     *
     * @param \DOMElement $element the element to rewrite, changed in place
     * @return void
     */
    protected static function attributes_to_classes(\DOMElement $element) {
        $classes = array();
        if ($element->hasAttribute('class')) {
            $classes = preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY);
        }

        if ($element->hasAttribute('id')) {
            $classes[] = self::CLASS_PREFIX . '-' . self::to_class_token($element->getAttribute('id'));
        }
        // Only the two data attributes carrying meaning are kept, everything else is noise.
        foreach (array('data-field', 'data-value') as $attribute) {
            if (!$element->hasAttribute($attribute)) {
                continue;
            }
            $name = substr($attribute, strlen('data-'));
            $classes[] = self::CLASS_PREFIX . '-' . $name . '-' . self::to_class_token($element->getAttribute($attribute));
        }

        if ($element->tagName === 'article') {
            $classes[] = self::CLASS_PREFIX;
        } else if ($element->tagName === 'section') {
            $classes[] = self::CLASS_PREFIX . '-section';
        }

        $classes = array_values(array_unique(array_filter($classes, function($class) {
            return $class !== '' && $class !== self::CLASS_PREFIX . '-';
        })));
        if (!empty($classes)) {
            $element->setAttribute('class', implode(' ', $classes));
        }
    }

    /**
     * Replaces one element by an element of another name, keeping attributes and children.
     *
     * @param \DOMDocument $doc the document the element belongs to
     * @param \DOMElement $element the element to replace
     * @param string $tagname the name of the new element
     * @return void
     */
    protected static function rename_element(\DOMDocument $doc, \DOMElement $element, $tagname) {
        $replacement = $doc->createElement($tagname);
        // Only the class attribute is carried over, the id and the data attributes would be
        // dropped by the purifier anyway and their meaning already sits in the classes.
        if ($element->hasAttribute('class')) {
            $replacement->setAttribute('class', $element->getAttribute('class'));
        }
        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }
        $element->parentNode->replaceChild($replacement, $element);
    }

    /**
     * Turns an arbitrary value into a value usable as a css class token.
     *
     * @param string $value the value to convert
     * @return string the class token, may be empty
     */
    public static function to_class_token($value): string {
        $value = \core_text::strtolower(trim((string)$value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
        $value = trim((string)$value, '-');

        return \core_text::substr($value, 0, 60);
    }

    /**
     * Turns the html of an evento module description into content moodle may store.
     *
     * Purifying is not optional here. mod_page renders its content with the option
     * noclean, see mod/page/view.php, so nothing is filtered on output and unchecked
     * html from evento would be a stored cross site scripting vector.
     *
     * @param string $html the raw html of the evento field mbText
     * @return string the cleaned html, ready to be stored in page.content
     */
    public static function clean_content($html): string {
        $html = self::convert_structure($html);
        if (trim($html) === '') {
            return '';
        }

        return clean_text($html, FORMAT_HTML);
    }

    /**
     * Normalises html for comparison purposes.
     *
     * The result is never stored, it only feeds the hash. Without it every cosmetic
     * difference in the evento answer or in the output of the purifier would look
     * like a change and the synchronisation would write on every run.
     *
     * @param string|null $html the html to normalise
     * @return string the normalised html
     */
    public static function normalize_content($html): string {
        $html = (string)$html;
        $html = str_replace(array("\r\n", "\r"), "\n", $html);
        // Whitespace between tags never carries meaning in a module description.
        $html = preg_replace('/>\s+</u', '><', $html);
        $html = preg_replace('/\s+/u', ' ', (string)$html);

        return trim((string)$html);
    }

    /**
     * Builds the comparison hash of a piece of content.
     *
     * @param string|null $html the html to hash
     * @return string|null the hash, or null if there is no content worth storing
     */
    public static function content_hash($html): ?string {
        $normalized = self::normalize_content($html);
        if ($normalized === '') {
            return null;
        }

        return sha1($normalized);
    }

    /**
     * Turns the evento version into something readable.
     *
     * The webservice delivers a float, which normalize_modulbeschreibung() formats with
     * three decimal places. Trailing zeros are dropped again here, but one decimal place
     * is kept, so that version 1 reads as 1.0 and not as a plain 1.
     *
     * @param float|string|null $value the version as evento delivers it
     * @return string|null the version to show, or null if there is none
     */
    public static function format_version($value): ?string {
        if (is_null($value) || $value === '' || !is_numeric($value)) {
            return null;
        }

        $version = rtrim(rtrim(sprintf('%.3F', (float)$value), '0'), '.');
        if (strpos($version, '.') === false) {
            $version .= '.0';
        }

        return $version;
    }

    /**
     * Builds the description line shown above the content of the page.
     *
     * The line is deliberately not part of the content and therefore not part of the
     * comparison hash. It names the state the imported text is in, which is the only
     * way a reader can tell an outdated description from a current one.
     *
     * The line has to come out the same on every run, because decide() compares it and
     * would otherwise write the page again and again. Neither the language nor the time
     * zone may therefore be taken from whoever happens to run the synchronisation: the
     * language of the site and the time zone of the server are used instead.
     *
     * @param \stdClass|null $normalized the normalized evento answer
     * @return string the html of the description, empty when evento names neither value
     */
    public static function build_intro($normalized): string {
        if (!is_object($normalized)) {
            return '';
        }

        $lang = self::get_intro_language();
        $version = self::format_version($normalized->mbversion ?? null);
        $validfrom = empty($normalized->mbgueltigab)
            ? null
            : userdate($normalized->mbgueltigab,
                get_string_manager()->get_string('moduldescriptiondateformat',
                    'local_eventocoursecreation', null, $lang),
                \core_date::get_server_timezone(), false, false);

        if (is_null($version) && is_null($validfrom)) {
            return '';
        }

        $a = new \stdClass();
        $a->version = $version;
        $a->validfrom = $validfrom;

        if (is_null($validfrom)) {
            $key = 'moduldescriptionintroversion';
        } else if (is_null($version)) {
            $key = 'moduldescriptionintrovalidfrom';
        } else {
            $key = 'moduldescriptionintro';
        }
        $text = get_string_manager()->get_string($key, 'local_eventocoursecreation', $a, $lang);

        return \html_writer::tag('p', s($text), array('class' => self::CLASS_PREFIX . '-meta'));
    }

    /**
     * Returns the language the description line is written in.
     *
     * The language of the site and not the one of the current user, so that a run from
     * the command line produces the same line as the scheduled task.
     *
     * @return string the language code
     */
    public static function get_intro_language(): string {
        global $CFG;

        // A language code is a directory name, and the string manager falls back to
        // english on its own when the language pack is not installed.
        $lang = clean_param((string)($CFG->lang ?? ''), PARAM_SAFEDIR);

        return $lang === '' ? 'en' : $lang;
    }

    /**
     * Builds the object {@see self::decide()} compares the outside of a page with.
     *
     * Content and evento metadata are compared through the hash and the link record.
     * Everything else a reader sees, and which no hash covers, is carried in here. A
     * property left at null means that this part is not compared at all, which is how
     * the setting to keep a name given by hand is expressed.
     *
     * @param string|null $intro the description of the page
     * @param string|null $name the name of the page
     * @return \stdClass object with the properties intro and name
     */
    public static function page_state($intro = null, $name = null): \stdClass {
        $state = new \stdClass();
        $state->intro = $intro;
        $state->name = $name;

        return $state;
    }

    /**
     * Reads the stored description of a page.
     *
     * @param \cm_info|\stdClass $cm the course module of the page
     * @return string|null the description or null if the instance is gone
     */
    public static function get_page_intro($cm): ?string {
        global $DB;

        $intro = $DB->get_field('page', 'intro', array('id' => $cm->instance));

        return $intro === false ? null : (string)$intro;
    }

    /**
     * Decides what has to happen for one course.
     *
     * The order of the checks matters. A description which must not be imported at all
     * stops the decision before any hash is looked at, and a page which was edited by
     * hand is detected before the evento side is compared, so that the reason ends up
     * in the log even when the content is written anyway.
     *
     * @param \stdClass|null $record the link record of the course, null if there is none yet
     * @param \stdClass|null $normalized the normalized evento answer, null if evento knows no description
     * @param string|null $newhash hash of the cleaned evento content, null if that content is empty
     * @param string|null $currenthash hash of the content of the existing page, null if there is no page
     * @param \stdClass $settings the settings as returned by {@see self::get_settings()}
     * @param int|null $now the time to compare the validity against, defaults to the current time
     * @param \stdClass|null $current the state of the existing page, see {@see self::page_state()}
     * @param \stdClass|null $wanted the state the page should be in, a null property skips that check
     * @return \stdClass object with the properties action and reason
     */
    public static function decide($record, $normalized, $newhash, $currenthash, \stdClass $settings, $now = null,
            $current = null, $wanted = null): \stdClass {
        $now = is_null($now) ? time() : (int)$now;

        if (!is_object($normalized)) {
            return self::action(self::ACTION_SKIP_NODESCRIPTION, 'evento knows no description for this event number');
        }
        if (!empty($settings->allowedstatus) && !in_array((int)$normalized->idstatus, $settings->allowedstatus, true)) {
            return self::action(self::ACTION_SKIP_STATUS, "status {$normalized->idstatus} is not accepted");
        }
        if (empty($settings->futurevalid) && !is_null($normalized->mbgueltigab) && $normalized->mbgueltigab > $now) {
            return self::action(self::ACTION_SKIP_FUTURE, 'the description is not valid yet');
        }
        if (is_null($newhash)) {
            // Never overwrite a description with nothing, an empty answer is a fault, not a change.
            return self::action(self::ACTION_SKIP_EMPTY, 'the description is empty after cleaning');
        }
        if (is_null($currenthash)) {
            return self::action(self::ACTION_CREATE, 'there is no page yet');
        }
        if (!is_object($record)) {
            // A page carried over by a course template, or one this plugin lost track of.
            // Taking it over avoids a second page next to the one that is already there.
            return self::action(self::ACTION_UPDATE, 'an untracked page is taken over');
        }

        // A different description record replaces the old one, no matter which version it carries.
        $samedescription = is_null($record->idmb) || is_null($normalized->idmb)
            || (int)$record->idmb === (int)$normalized->idmb;
        if ($samedescription && self::is_older($record, $normalized)) {
            return self::action(self::ACTION_SKIP_OLDER, 'evento offers an older state than the one imported');
        }

        if ($currenthash !== $record->contenthash) {
            return self::action(self::ACTION_UPDATE, 'the page was changed outside of this plugin');
        }
        if ($newhash !== $record->contenthash) {
            return self::action(self::ACTION_UPDATE, 'the description changed in evento');
        }
        // Checked before the metadata, because version and validity are what the line shows.
        // Every metadata change that is visible to a reader is therefore written to the page
        // instead of only being noted in the link record.
        $wantedintro = is_object($wanted) ? $wanted->intro : null;
        if (!is_null($wantedintro) && self::normalize_content(is_object($current) ? $current->intro : null)
                !== self::normalize_content($wantedintro)) {
            return self::action(self::ACTION_UPDATE, 'the description line is out of date');
        }
        // A page renamed by hand is only brought back when the configuration asks for it,
        // which the caller expresses by leaving the wanted name empty.
        $wantedname = is_object($wanted) ? $wanted->name : null;
        if (!is_null($wantedname)
                && trim((string)(is_object($current) ? $current->name : null)) !== trim((string)$wantedname)) {
            return self::action(self::ACTION_UPDATE, 'the page was renamed');
        }
        if (self::metadata_differs($record, $normalized)) {
            // The content is identical, so the page is left alone and only the link record moves on.
            return self::action(self::ACTION_METADATA, 'only the evento metadata changed');
        }

        return self::action(self::ACTION_NONE, 'the page is up to date');
    }

    /**
     * Checks whether evento offers an older state than the one already imported.
     *
     * @param \stdClass $record the link record of the course
     * @param \stdClass $normalized the normalized evento answer
     * @return bool true if the evento state is older
     */
    protected static function is_older(\stdClass $record, \stdClass $normalized): bool {
        if (!is_null($record->mbversionscaled) && !is_null($normalized->mbversionscaled)
                && (int)$normalized->mbversionscaled < (int)$record->mbversionscaled) {
            return true;
        }
        if (!is_null($record->mbgueltigab) && !is_null($normalized->mbgueltigab)
                && (int)$normalized->mbgueltigab < (int)$record->mbgueltigab) {
            return true;
        }

        return false;
    }

    /**
     * Checks whether the evento metadata of the link record is out of date.
     *
     * @param \stdClass $record the link record of the course
     * @param \stdClass $normalized the normalized evento answer
     * @return bool true if at least one value differs
     */
    protected static function metadata_differs(\stdClass $record, \stdClass $normalized): bool {
        $fields = array('idmb', 'idstatus', 'mbversionscaled', 'mbgueltigab');
        foreach ($fields as $field) {
            $old = is_null($record->$field) ? null : (int)$record->$field;
            $new = is_null($normalized->$field) ? null : (int)$normalized->$field;
            if ($old !== $new) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the result of a decision.
     *
     * @param string $action one of the ACTION_ constants
     * @param string $reason a short explanation for the log
     * @return \stdClass object with the properties action and reason
     */
    protected static function action($action, $reason): \stdClass {
        $result = new \stdClass();
        $result->action = $action;
        $result->reason = $reason;

        return $result;
    }

    /**
     * Finds every page in a course which is marked as managed by this plugin.
     *
     * The marker is the course module idnumber. It survives a course backup and
     * restore as long as it stays unique inside the target course, see
     * restore_module_structure_step and grade_verify_idnumber, which is how a page
     * carried over from a course template is recognised.
     *
     * @param \stdClass|int $courseorid the course or its id
     * @param string|null $cmidnumber the marker, defaults to the configured one
     * @return \cm_info[] the matching course modules, in the order of the course
     */
    public static function find_managed_pages($courseorid, $cmidnumber = null): array {
        if (is_null($cmidnumber)) {
            $settings = self::get_settings();
            $cmidnumber = $settings->cmidnumber;
        }

        $result = array();
        $modinfo = get_fast_modinfo($courseorid);
        foreach ($modinfo->get_instances_of('page') as $cm) {
            if (trim((string)$cm->idnumber) === $cmidnumber) {
                $result[$cm->id] = $cm;
            }
        }

        return $result;
    }

    /**
     * Finds the page of a course which holds the module description.
     *
     * @param \stdClass|int $courseorid the course or its id
     * @param string|null $cmidnumber the marker, defaults to the configured one
     * @return \cm_info|null the course module or null if the course has none
     */
    public static function find_existing_page($courseorid, $cmidnumber = null): ?\cm_info {
        $pages = self::find_managed_pages($courseorid, $cmidnumber);

        return empty($pages) ? null : reset($pages);
    }

    /**
     * Reads the stored content of a page.
     *
     * @param \cm_info|\stdClass $cm the course module of the page
     * @return string|null the content or null if the instance is gone
     */
    public static function get_page_content($cm): ?string {
        global $DB;

        $content = $DB->get_field('page', 'content', array('id' => $cm->instance));

        return $content === false ? null : (string)$content;
    }

    /**
     * Tells whether this plugin ever put a page into a course.
     *
     * A link record alone does not say so. One is written for every course that has been
     * looked at, including the many courses evento has no description for, and those
     * carry no page and never did. Only a course which did have a page can have lost it
     * by hand, which is the case the configuration for a deleted page is about.
     *
     * The two marks are the stored course module and the time of the last write. The
     * first one is dropped again when a deleted page is not created again, the second one
     * stays, so together they still name the course afterwards.
     *
     * @param \stdClass|null $record the link record of the course
     * @return bool true if the course did have a page written by this plugin
     */
    public static function had_a_page($record): bool {
        if (!is_object($record)) {
            return false;
        }

        return !is_null($record->cmid ?? null) || (int)($record->timemodified ?? 0) > 0;
    }

    /**
     * Reads the link record of a course.
     *
     * @param int $courseid the course
     * @return \stdClass|null the record or null if the course has none
     */
    public static function get_link_record($courseid): ?\stdClass {
        global $DB;

        $record = $DB->get_record('eventocoursecreation_page', array('courseid' => $courseid));

        return $record === false ? null : $record;
    }

    /**
     * Collects the courses which are due for a synchronisation.
     *
     * Courses which have ended are never returned. Courses which failed before are held
     * back for the configured number of hours. The courses checked longest ago come
     * first, so a batch size below the number of courses still reaches every course.
     *
     * @param \stdClass|null $settings the settings, defaults to the stored ones
     * @param int|null $now the time to compare against, defaults to the current time
     * @param int|null $limit the batch size, defaults to the configured one
     * @return \stdClass[] course records, keyed by course id
     */
    public static function get_sync_candidates($settings = null, $now = null, $limit = null): array {
        global $DB, $SITE;

        $settings = is_null($settings) ? self::get_settings() : $settings;
        $now = is_null($now) ? time() : (int)$now;
        $limit = is_null($limit) ? $settings->batchsize : (int)$limit;

        if ($settings->scope === EVENTOCOURSECREATION_MB_SCOPE_FUTURE) {
            $scopewhere = 'c.startdate > :now';
        } else {
            $scopewhere = '(c.enddate = 0 OR c.enddate >= :now)';
        }

        $params = array(
            'siteid' => $SITE->id,
            'now' => $now,
            'disabled' => EVENTOCOURSECREATION_MB_STATUS_DISABLED,
            'retrybefore' => $now - ($settings->retryhours * HOURSECS),
            'enrol' => 'evento',
        );

        // An empty string behaves differently across databases, so let the driver phrase the check.
        $hasidnumber = $DB->sql_isnotempty('course', 'c.idnumber', false, false);

        $sql = "SELECT c.*
                  FROM {course} c
             LEFT JOIN {eventocoursecreation_page} p ON p.courseid = c.id
                 WHERE c.id <> :siteid
                       AND {$scopewhere}
                       AND (p.id IS NULL OR p.status <> :disabled)
                       AND (p.id IS NULL OR p.errorcount = 0 OR p.timechecked <= :retrybefore)
                       AND ({$hasidnumber}
                            OR EXISTS (SELECT 1
                                         FROM {enrol} e
                                        WHERE e.courseid = c.id AND e.enrol = :enrol))
                       AND c.id NOT IN (SELECT templatecourse
                                          FROM {eventocoursecreation}
                                         WHERE templatecourse IS NOT NULL)
              ORDER BY COALESCE(p.timechecked, 0) ASC, c.id ASC";

        return $DB->get_records_sql($sql, $params, 0, $limit);
    }
}
