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
 * Tests for the preparation of raw evento SOAP responses.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_eventocoursecreation\soap_response
 */
final class soap_response_test extends \basic_testcase {

    /** A listEventoAnlass response, shortened, with made up leader data. */
    private const EVENT_RESPONSE = '<?xml version="1.0" encoding="utf-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body>'
        . '<ns:listEventoAnlassResponse xmlns:ns="http://service.webservice.htwchur.ch">'
        . '<ns:return xmlns:ax21="http://domain.hibernate.htwchur.ch/xsd">'
        . '<ax21:anlassNummer>mod.test-EXAMPLE.HS26_BS.001</ax21:anlassNummer>'
        . '<ax21:anlass_IDAnlassModul>1234</ax21:anlass_IDAnlassModul>'
        . '<ax21:anlass_Veranstalter><ax21:benutzerName>someone</ax21:benutzerName></ax21:anlass_Veranstalter>'
        . '<ax21:array_EventoAnlassLeitung><ax21:anlassLtgPerson>'
        . '<ax21:adr_Email>leader@example.org</ax21:adr_Email>'
        . '<ax21:adr_Telefon1>+41 00 000 00 00</ax21:adr_Telefon1>'
        . '</ax21:anlassLtgPerson></ax21:array_EventoAnlassLeitung>'
        . '<ax21:array_EventoAnlassLeitung><ax21:adr_Email>second@example.org</ax21:adr_Email>'
        . '</ax21:array_EventoAnlassLeitung>'
        . '<ax21:idAnlass>5678</ax21:idAnlass>'
        . '</ns:return></ns:listEventoAnlassResponse></soapenv:Body></soapenv:Envelope>';

    /**
     * The response is indented, one element per line.
     */
    public function test_format_indents_the_response(): void {
        $result = soap_response::format(self::EVENT_RESPONSE);

        $this->assertMatchesRegularExpression('/\n\s+<ax21:anlassNummer>mod\.test-EXAMPLE\.HS26_BS\.001<\/ax21:anlassNummer>\n/',
            $result);
        $this->assertStringContainsString('<ax21:anlass_IDAnlassModul>1234</ax21:anlass_IDAnlassModul>', $result);
        $this->assertStringContainsString('<ax21:idAnlass>5678</ax21:idAnlass>', $result);
    }

    /**
     * The leaders and the organiser are left out, every occurrence, and a comment says so.
     */
    public function test_format_leaves_out_the_personal_data(): void {
        $result = soap_response::format(self::EVENT_RESPONSE);

        $this->assertStringNotContainsString('leader@example.org', $result);
        $this->assertStringNotContainsString('second@example.org', $result);
        $this->assertStringNotContainsString('+41 00 000 00 00', $result);
        $this->assertStringNotContainsString('someone', $result);
        $this->assertSame(2, substr_count($result, '<!-- array_EventoAnlassLeitung left out -->'));
        $this->assertSame(1, substr_count($result, '<!-- anlass_Veranstalter left out -->'));
    }

    /**
     * A fault is a response like any other and is shown in full.
     */
    public function test_format_keeps_a_fault(): void {
        $fault = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body>'
            . '<soapenv:Fault><faultcode>soapenv:Server</faultcode>'
            . '<faultstring>Keine Modulbeschreibung gefunden</faultstring></soapenv:Fault>'
            . '</soapenv:Body></soapenv:Envelope>';

        $result = soap_response::format($fault);

        $this->assertStringContainsString('<faultstring>Keine Modulbeschreibung gefunden</faultstring>', $result);
    }

    /**
     * Anything that is not xml, an error page of a proxy for example, comes back as it is.
     */
    public function test_format_returns_no_xml_unchanged(): void {
        $html = '<html><body><h1>502 Bad Gateway</h1><br></body>';

        $this->assertSame($html, soap_response::format($html));
    }

    /**
     * Without a response there is nothing to show.
     */
    public function test_format_of_nothing(): void {
        $this->assertSame('', soap_response::format(null));
        $this->assertSame('', soap_response::format(''));
        $this->assertSame('', soap_response::format("  \n "));
    }
}
