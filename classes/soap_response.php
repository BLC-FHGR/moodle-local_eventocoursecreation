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

/**
 * Prepares an untouched evento SOAP response for a human to read.
 *
 * The SoapClient drops every element the local wsdl does not know. Only the raw
 * response shows a field evento added later, which is why the cli tools print it.
 * The event of a course carries the addresses, mail addresses and phone numbers of
 * its leaders, which have no business in a terminal or in a pasted log, so those
 * elements are replaced by a comment naming what was left out.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class soap_response {

    /** Elements of the evento response which hold personal data, by their local name. */
    const PERSONAL_ELEMENTS = array('array_EventoAnlassLeitung', 'anlass_Veranstalter');

    /**
     * Indents the response and removes the elements holding personal data.
     *
     * Anything that is not well formed xml, an html error page of a proxy for example,
     * comes back unchanged, since it is exactly what someone looking for a fault needs
     * to see.
     *
     * @param string|null $xml the response as SoapClient::__getLastResponse() returns it
     * @return string the readable response, empty if there was none
     */
    public static function format($xml): string {
        $xml = (string)$xml;
        if (trim($xml) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        // The response comes from the configured endpoint, still never let it reach out.
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return $xml;
        }

        // Collected first, a live node list skips nodes when it is changed while walking it.
        $remove = array();
        foreach ($doc->getElementsByTagName('*') as $node) {
            if (in_array($node->localName, self::PERSONAL_ELEMENTS, true)) {
                $remove[] = $node;
            }
        }
        foreach ($remove as $node) {
            // A node nested in one replaced before is replaced inside the detached tree,
            // which does no harm and keeps this simple.
            $node->parentNode->replaceChild($doc->createComment(' ' . $node->localName . ' left out '), $node);
        }

        return rtrim((string)$doc->saveXML());
    }
}
