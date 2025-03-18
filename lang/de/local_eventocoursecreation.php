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
 * Strings for component 'local_eventocoursecreation', language 'de'
 *
 * @package    local_eventocoursecreation
 * @copyright  2018, HTW chur {@link http://www.htwchur.ch}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['pluginname'] = 'Evento-Kurserstellung';
$string['taskname'] = 'Evento-Kurserstellungs-Synchronisation';
$string['eventocoursesynctask'] = 'Synchronisation der Evento-Kurserstellung';
$string['autumnendmonth'] = 'Endmonat';
$string['autumnendmonth_help'] = 'Monat, bis zu dem die Kurserstellung für das Herbstsemester ausgeführt wird.';
$string['autumnendday'] = 'Endtag';
$string['autumnendday_help'] = 'Tag, bis zu dem die Kurserstellung für das Herbstsemester ausgeführt wird.';
$string['autumnstartday'] = 'Starttag';
$string['autumnstartday_help'] = 'Tag, ab dem die Kurserstellung für das Herbstsemester ausgeführt wird. (nur Werte zwischen 1 und 31 sind erlaubt)';
$string['autumnstartmonth'] = 'Startmonat';
$string['autumnstartmonth_help'] = 'Monat, in dem die Kurserstellung für das Herbstsemester ausgeführt wird. (nur Werte zwischen 1 und 12 sind erlaubt)';
$string['coursetorestorefromdoesnotexist'] = 'Der Kurs, aus dem wiederhergestellt werden soll, existiert nicht';
$string['dayinvalid'] = 'Der Tag ist kein gültiger Tag eines Monats (nur Werte zwischen 1 und 31 sind erlaubt)';
$string['defaultcourssettings'] = 'Standard-Kurseinstellungen';
$string['defaultcourssettings_help'] = 'Standardwerte für die Kurseinstellungen neuer Kurse';
$string['disabled'] = 'Deaktiviert';
$string['editcreationsettings'] = 'Einstellungen zur Evento-Kurserstellung bearbeiten';
$string['enabled'] = 'Aktiviert';
$string['enablecatcoursecreation'] = 'Kurserstellung aktivieren';
$string['enablecatcoursecreation_help'] = 'Kurserstellung für diese Kategorie aktivieren';
$string['enablecoursetemplate'] = 'Kursvorlage verwenden';
$string['enablecoursetemplate_help'] = 'Wenn aktiviert, werden neue Kurse auf Grundlage des gewählten Vorlagekurses erstellt.';
$string['enableplugin'] = 'Plugin aktivieren';
$string['enableplugin_help'] = 'Das Plugin aktivieren oder deaktivieren';
$string['eventosynccoursecreation'] = 'Synchronisation der Evento-Kurserstellung';
$string['execonlyonstarttimeautumnterm'] = 'Die Kurserstellung nur am Startdatum ausführen';
$string['execonlyonstarttimeautumnterm_help'] = 'Wenn aktiviert, wird die Kurserstellung nur am Startdatum ausgeführt. Andernfalls wird die Kurserstellung bis kurz nach Beginn des Semesters ausgeführt, falls neue Kurse vorhanden sind.';
$string['execonlyonstarttimespringterm'] = 'Die Kurserstellung nur am Startdatum ausführen';
$string['execonlyonstarttimespringterm_help'] = 'Wenn aktiviert, wird die Kurserstellung nur am Startdatum ausgeführt. Andernfalls wird die Kurserstellung bis kurz nach Beginn des Semesters ausgeführt, falls neue Kurse vorhanden sind.';
$string['idnumber'] = 'Studiengang (Kategorie-ID)';
$string['idnumber_help'] = 'Studiengang, der in der Kategorie-ID gespeichert ist. Er muss das Präfix der Evento-Veranstaltungsnummer sein, z.B. mod.dbm, mod.bsp oder mod.tou. Andernfalls funktioniert es nicht. Nur Kurse mit diesem Präfix werden berücksichtigt. Optionen mit | und § funktionieren weiterhin.';
$string['information'] = 'Information';
$string['longcoursenaming'] = 'Langer Name für Moodle-Kurse';
$string['longcoursenaming_help'] = 'Definiert den langen Namen für Kurse. Verfügbare Platzhalter sind: (Evento-Modulname: @EVENTONAME@; Evento-Modulabkürzung: @EVENTOABK@; Semesterperiode: @PERIODE@; Studiengang: @STG@; Implementierungsnummer: @NUM@)';
$string['monthinvalid'] = 'Monat ist ungültig (nur Werte zwischen 1 und 12 sind erlaubt)';
$string['no'] = 'Nein';
$string['numberofsections'] = 'Anzahl der Abschnitte';
$string['numberofsections_help'] = 'Anzahl der Abschnitte in neuen, leeren Kursen';
$string['plugindisabled'] = 'Das Plugin für die Evento-Kurserstellung ist deaktiviert!';
$string['pluginname_desc'] = 'Erstellt Kurse basierend auf den Evento-Modulen.';
$string['privacy:metadata'] = 'Das Plugin Evento-Kurserstellung speichert keine personenbezogenen Daten.';
$string['shortcoursenaming'] = 'Kurzer Name für Moodle-Kurse';
$string['shortcoursenaming_help'] = 'Definiert den kurzen Namen für Kurse. Verfügbare Platzhalter sind: (Evento-Modulname: @EVENTONAME@; Evento-Modulabkürzung: @EVENTOABK@; Semesterperiode: @PERIODE@; Studiengang: @STG@; Implementierungsnummer: @NUM@)';
$string['springendmonth'] = 'Endmonat';
$string['springendmonth_help'] = 'Monat, bis zu dem die Kurserstellung für das Frühjahrssemester ausgeführt wird.';
$string['springendday'] = 'Endtag';
$string['springendday_help'] = 'Tag, bis zu dem die Kurserstellung für das Frühjahrssemester ausgeführt wird.';
$string['springstartday'] = 'Starttag';
$string['springstartday_help'] = 'Tag, ab dem die Kurserstellung für das Frühjahrssemester ausgeführt wird. (nur Werte zwischen 1 und 31 sind erlaubt)';
$string['springstartmonth'] = 'Startmonat';
$string['springstartmonth_help'] = 'Monat, in dem die Kurserstellung für das Frühjahrssemester ausgeführt wird. (nur Werte zwischen 1 und 12 sind erlaubt)';
$string['startautumnterm'] = 'Herbstsemester';
$string['startautumnterm_help'] = 'Standardwerte für den Beginn des Herbstsemesters';
$string['startspringterm'] = 'Frühlingssemester';
$string['startspringterm_help'] = 'Standardwerte für den Beginn des Frühjahrssemesters';
$string['templatecourse'] = 'Vorlagekurs';
$string['templatecourse_help'] = 'Vorlagekurs für neu zu erstellende Kurse. Wenn keine Vorlage ausgewählt ist, werden die neuen Kurse leer erstellt.';
$string['yes'] = 'Ja';
$string['january'] = 'Januar';
$string['february'] = 'Februar';
$string['march'] = 'März';
$string['april'] = 'April';
$string['may'] = 'Mai';
$string['june'] = 'Juni';
$string['july'] = 'Juli';
$string['august'] = 'August';
$string['september'] = 'September';
$string['october'] = 'Oktober';
$string['november'] = 'November';
$string['december'] = 'Dezember';
$string['customcoursesettings'] = 'Benutzerdefinierte Kurseinstellungen';
$string['setcustomcoursestart'] = 'Ein benutzerdefiniertes Kursstartdatum festlegen.';
$string['setcustomcoursestart_help'] = 'Wenn aktiviert, wird der Kurs mit diesem Datum als Startdatum erstellt. Andernfalls ist das Startdatum des Kurses identisch mit dem Semesterbeginn.';
$string['coursestart'] = 'Startzeit';
$string['coursestart_help'] = 'Startzeit, die bei der Kurserstellung gesetzt wird.';
$string['starttimecourseinvalid'] = 'Die Startzeit des Kurses ist kein gültiger UNIX-Zeitstempel.';
$string['runnowheader'] = 'Kurserstellung ausführen';
$string['runnow'] = 'Kurse jetzt erstellen';
$string['runnowdesc'] = 'Sofort alle Kurse aus Evento für diese Kategorie erstellen';
$string['runnowdesc_help'] = 'Dies startet den Kurserstellungsprozess für diese Kategorie sofort. Verwenden Sie die Option "Erzwingen", um Zeitbeschränkungen zu umgehen.';
$string['forcecreation'] = 'Erzwinge Erstellung (Zeitbeschränkungen ignorieren)';
$string['runningcoursecreation'] = 'Evento-Kurse werden erstellt';
$string['creationsuccessful'] = 'Kurserstellung erfolgreich abgeschlossen';
$string['creationfailed'] = 'Kurserstellung fehlgeschlagen';
$string['creationskipped'] = 'Kurserstellung übersprungen – Voraussetzungen nicht erfüllt';
$string['creationunknown'] = 'Unbekannter Fehler während der Kurserstellung';
$string['returntocategory'] = 'Zurück zur Kategorie';
$string['defaultsubcatorganization'] = 'Standard-Organisation der Unterkategorien';
$string['defaultsubcatorganization_help'] = 'Wählen Sie aus, wie Kurse standardmäßig in Unterkategorien organisiert werden sollen.';
$string['subcatorganization'] = 'Organisation der Unterkategorien';
$string['subcatorganization_help'] = 'Wählen Sie aus, wie Kurse in Unterkategorien für diese Kategorie organisiert werden sollen.';
$string['subcatorg_none'] = 'Keine Unterkategorien';
$string['subcatorg_semester'] = 'Nach Semester (FS/HS)';
$string['subcatorg_year'] = 'Nach Jahr';
// Preview functionality
$string['nocoursestocreate'] = 'Keine Kurse zum Erstellen verfügbar';
$string['select'] = 'Auswählen';
$string['subcourses'] = 'Zugehörige Kurse';
$string['create'] = 'Erstellen';
$string['createselected'] = 'Ausgewählte Kurse erstellen';
$string['forcecreation'] = 'Erstellung erzwingen';
$string['force'] = 'Erzwingen';
$string['create'] = 'Erstellen';
$string['createselected'] = 'Ausgewählte Kurse erstellen';
$string['creating'] = 'Erstelle Kurse...';
$string['coursepreview'] = 'Kursvorschau';
$string['creationsuccessfulcount'] = '{$a} Kurse wurden erfolgreich erstellt';
$string['creationfailedcount'] = '{$a} Kurse konnten nicht erstellt werden';
$string['categorynotfound'] = 'Kategorie nicht gefunden';
$string['settingsnotfound'] = 'Kategorieeinstellungen nicht gefunden';
$string['eventnotfound'] = 'Veranstaltung in Evento nicht gefunden';
$string['outsidecreationperiod'] = 'Außerhalb des zulässigen Erstellungszeitraums';
$string['notmainevent'] = 'Keine Hauptveranstaltung';
$string['creationnotenabled'] = 'Kurserstellung ist für diese Kategorie nicht aktiviert';
$string['creationnotallowed'] = 'Kurserstellung ist derzeit nicht erlaubt';
$string['coursealreadyexists'] = 'Kurs existiert bereits in Moodle';
$string['coursecreated'] = 'Kurs erfolgreich erstellt';
$string['prerequisitesfailed'] = 'Systemvoraussetzungen nicht erfüllt';
$string['coursecreationfailed'] = 'Kurserstellung fehlgeschlagen';
$string['individualcreationheader'] = 'Individuelle Kurserstellung';
$string['previewloading'] = 'Kursvorschau wird geladen...';
$string['previewfailed'] = 'Kursvorschau konnte nicht geladen werden';
$string['previewunavailable'] = 'Vorschau nicht verfügbar – bitte Systemeinstellungen überprüfen';
$string['bulkcreation'] = 'Massen-Erstellung';
$string['bulkcreationdesc'] = 'Alle Kurse in dieser Kategorie erstellen';
$string['individualcreation'] = 'Individuelle Erstellung';
$string['loadingcourselist'] = 'Verfügbare Kurse werden geladen...';
$string['previewerror'] = 'Fehler beim Anzeigen der Kursvorschau';
$string['previewloadfailed'] = 'Kursvorschau konnte nicht geladen werden';
// Smart Event Fetcher settings
$string['fetching_heading'] = 'Einstellungen zum Abrufen von Veranstaltungen';
$string['fetching_heading_desc'] = 'Konfigurieren Sie, wie Veranstaltungen über die Evento-API abgerufen werden';
$string['fetching_mode'] = 'Abrufmodus';
$string['fetching_mode_desc'] = 'Wählen Sie die Methode aus, um Veranstaltungen aus Evento abzurufen';
$string['fetching_mode_classic'] = 'Klassisch (ursprüngliche Methode)';
$string['fetching_mode_smart'] = 'Smart (adaptives Abrufen)';
$string['fetching_mode_fast'] = 'Schnell (inkrementelle Updates)';
$string['fetching_mode_parallel'] = 'Parallel (experimentell)';
$string['batch_size'] = 'Stapelgröße';
$string['batch_size_desc'] = 'Anzahl der Veranstaltungen, die pro API-Anfrage abgerufen werden sollen';
$string['min_batch_size'] = 'Minimale Stapelgröße';
$string['min_batch_size_desc'] = 'Minimale Anzahl von Veranstaltungen, die beim Verringern der Stapelgröße abgerufen werden sollen';
$string['max_batch_size'] = 'Maximale Stapelgröße';
$string['max_batch_size_desc'] = 'Maximale Anzahl von Veranstaltungen, die beim Erhöhen der Stapelgröße abgerufen werden sollen';
$string['adaptive_batch_sizing'] = 'Adaptive Stapelgrößenanpassung';
$string['adaptive_batch_sizing_desc'] = 'Passt die Stapelgröße dynamisch basierend auf der API-Antwort an';
$string['date_chunk_fallback'] = 'Fallback für Datumsabschnitte';
$string['date_chunk_fallback_desc'] = 'Wenn die Paginierung fehlschlägt, wechsle zum Abruf per Datumsabschnitt';
$string['date_chunk_days'] = 'Größe der Datumsabschnitte (Tage)';
$string['date_chunk_days_desc'] = 'Größe der Datumsabschnitte in Tagen bei Verwendung von Date-Chunking';
$string['max_api_retries'] = 'Max API-Wiederholungen';
$string['max_api_retries_desc'] = 'Maximale Anzahl von Wiederholungsversuchen für fehlgeschlagene API-Anfragen';
$string['cache_ttl'] = 'Cache-Lebensdauer';
$string['cache_ttl_desc'] = 'Wie lange API-Antworten in Sekunden zwischengespeichert werden sollen';

// Parallel processing settings
$string['parallel_heading'] = 'Einstellungen zur parallelen Verarbeitung (experimentell)';
$string['parallel_heading_desc'] = 'Konfigurieren Sie das parallele Abrufen von Veranstaltungen (erfordert CLI)';
$string['parallel_requests'] = 'Parallele Anfragen aktivieren';
$string['parallel_requests_desc'] = 'API-Anfragen parallel verarbeiten (nur im CLI-Modus)';
$string['max_parallel_threads'] = 'Maximale parallele Threads';
$string['max_parallel_threads_desc'] = 'Maximale Anzahl paralleler Worker-Prozesse';
$string['parallel_requires_cli'] = 'Parallele Verarbeitung erfordert CLI-Modus';

// Cache maintenance task
$string['eventocachemaintenance'] = 'Wartung des Evento-Caches';
$string['fastmodesync'] = 'Schnelle Synchronisation';

// API Monitor page
$string['apimonitor'] = 'API-Monitor';
$string['apistats'] = 'API-Statistiken';
$string['configsummary'] = 'Konfigurationsübersicht';
$string['statstotals'] = 'Gesamtstatistik';
$string['api_calls'] = 'API-Aufrufe';
$string['api_errors'] = 'Fehler';
$string['api_cache_hits'] = 'Cache-Treffer';
$string['api_last_run'] = 'Letzter Lauf';
$string['cachepurged'] = 'Cache wurde geleert';
$string['purge_cache'] = 'Cache leeren';
$string['currentmode'] = 'Aktueller Modus';
$string['setting'] = 'Einstellung';
$string['value'] = 'Wert';
$string['veranstalterdetails'] = 'Veranstalter-Details';
$string['veranstalter'] = 'Veranstalter';
$string['error_rate'] = 'Fehlerrate';
$string['change_settings'] = 'Einstellungen ändern';

// Debug page
$string['debugpage'] = 'Debug-Werkzeuge';
$string['testconnection'] = 'Verbindung testen';
$string['connectionsuccessful'] = 'Verbindung erfolgreich';
$string['connectionfailed'] = 'Verbindung fehlgeschlagen';
$string['fetchevents'] = 'Veranstaltungen abrufen';
$string['selectveranstalter'] = 'Wählen Sie einen Veranstalter';
$string['maxresults'] = 'Max. Ergebnisse';
$string['fetchmode'] = 'Abrufmodus';
$string['selectfetchmode'] = 'Wählen Sie einen Abrufmodus';
$string['fetchedevents'] = '{$a} Veranstaltungen abgerufen';
$string['noevents'] = 'Keine Veranstaltungen gefunden';
$string['eventid'] = 'Veranstaltungs-ID';
$string['eventnumber'] = 'Veranstaltungsnummer';
$string['eventname'] = 'Veranstaltungsname';
$string['startdate'] = 'Startdatum';
$string['enddate'] = 'Enddatum';
$string['errorveranstalterlist'] = 'Fehler beim Abrufen der Veranstalterliste';
$string['traceoutput'] = 'Protokollausgabe';
$string['invalidfetchmode'] = 'Ungültiger Abrufmodus';
$string['experimental'] = '(experimentell)';
$string['reset_hwm'] = 'High-Watermark zurücksetzen (für Schnellmodus)';
$string['hwm_reset'] = 'High-Watermark wurde zurückgesetzt';

// Global API settings
$string['eventofetching'] = 'Veranstaltungsabruf';
$string['eventofetching_help'] = 'Konfigurieren Sie, wie Veranstaltungen über die Evento-API abgerufen werden';
$string['fetching_mode'] = 'Abrufmodus';
$string['fetching_mode_desc'] = 'Wählen Sie die Methode aus, um Veranstaltungen aus Evento abzurufen';
$string['fetching_mode_classic'] = 'Klassisch (ursprüngliche Methode)';
$string['fetching_mode_smart'] = 'Smart (adaptives Abrufen)';
$string['fetching_mode_fast'] = 'Schnell (inkrementelle Updates)';
$string['fetching_mode_parallel'] = 'Parallel (experimentell)';
$string['batch_size'] = 'Stapelgröße';
$string['batch_size_desc'] = 'Anzahl der Veranstaltungen, die pro API-Anfrage abgerufen werden sollen';
$string['min_batch_size'] = 'Minimale Stapelgröße';
$string['min_batch_size_desc'] = 'Minimale Anzahl von Veranstaltungen, die beim Verringern der Stapelgröße abgerufen werden sollen';
$string['max_batch_size'] = 'Maximale Stapelgröße';
$string['max_batch_size_desc'] = 'Maximale Anzahl von Veranstaltungen, die beim Erhöhen der Stapelgröße abgerufen werden sollen';
$string['adaptive_batch_sizing'] = 'Adaptive Stapelgrößenanpassung';
$string['adaptive_batch_sizing_desc'] = 'Passt die Stapelgröße dynamisch basierend auf der API-Antwort an';
$string['date_chunk_fallback'] = 'Fallback für Datumsabschnitte';
$string['date_chunk_fallback_desc'] = 'Wenn die Paginierung fehlschlägt, wechsle zum Abruf per Datumsabschnitt';
$string['date_chunk_days'] = 'Größe der Datumsabschnitte (Tage)';
$string['date_chunk_days_desc'] = 'Größe der Datumsabschnitte in Tagen bei Verwendung von Date-Chunking';
$string['max_api_retries'] = 'Max API-Wiederholungen';
$string['max_api_retries_desc'] = 'Maximale Anzahl von Wiederholungsversuchen für fehlgeschlagene API-Anfragen';
$string['cache_ttl'] = 'Cache-Lebensdauer';
$string['cache_ttl_desc'] = 'Wie lange API-Antworten in Sekunden zwischengespeichert werden sollen';

// Category-specific API settings
$string['apifetchingheader'] = 'API-Abruf-Einstellungen';
$string['override_global_fetching'] = 'Globale Abrufeinstellungen überschreiben';
$string['override_global_fetching_help'] = 'Kategorie-spezifische Einstellungen anstelle globaler Einstellungen verwenden';
$string['custom_batch_size'] = 'Benutzerdefinierte Stapelgröße';
$string['custom_batch_size_help'] = 'Benutzerdefinierte Stapelgröße für diese Kategorie (0 = globale Einstellung verwenden)';
$string['current_global_settings'] = 'Aktuelle globale Einstellungen';

// Admin pages
$string['apimonitor'] = 'API-Monitor';
$string['debugpage'] = 'Debug-Werkzeuge';
$string['purge_cache'] = 'Cache leeren';
$string['cachepurged'] = 'Cache wurde geleert';
$string['api_calls'] = 'API-Aufrufe';
$string['api_errors'] = 'Fehler';
$string['api_cache_hits'] = 'Cache-Treffer';
$string['api_last_run'] = 'Letzter Lauf';
$string['veranstalter'] = 'Veranstalter';
$string['error_rate'] = 'Fehlerrate';
$string['fetchevents'] = 'Veranstaltungen abrufen';
$string['noevents'] = 'Keine Veranstaltungen gefunden';
$string['reset_hwm'] = 'High-Watermark zurücksetzen (für Schnellmodus)';
$string['hwm_reset'] = 'High-Watermark wurde zurückgesetzt';
$string['experimental'] = '(experimentell)';
