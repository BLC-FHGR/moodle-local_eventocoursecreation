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
 * Strings for component 'local_eventocoursecreation', language 'en'
 *
 * @package    local_eventocoursecreation
 * @copyright  2018, HTW chur {@link http://www.htwchur.ch}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['autumnendmonth'] = 'Endmonat';
$string['autumnendmonth_help'] = 'Monat, bis zu welchen für das Herbstsemester die Kurserstellung durchgeführt wird.';
$string['autumnendday'] = 'Endtag';
$string['autumnendday_help'] = 'Tag, bis zu welchen für das Herbstsemester die Kurserstellung durchgeführt wird.';
$string['autumnstartday'] = 'Starttag';
$string['autumnstartday_help'] = 'Tag, ab welchen für das Herbstsemester die Kurserstellung durchgeführt wird. (nur Werte von 1 bis 31 erlaubt)';
$string['autumnstartmonth'] = 'Startmonat';
$string['autumnstartmonth_help'] = 'Monat, in welchem für das Herbstsemester die Kurserstellung durchgeführt wird. (nur Werte von 1 bis 12 erlaubt)';
$string['coursetorestorefromdoesnotexist'] = 'Der Kurs, vom welchem ein Restore gemacht werden soll, gibt es nicht';
$string['dayinvalid'] = 'Tag ist kein gültiger Monatstag (nur Werte von 1 bis 31 erlaubt)';
$string['defaultcourssettings'] = 'Standardmässige Kurseinstellungen';
$string['defaultcourssettings_help'] = 'Standardwerte für die Kurseinstellung';
$string['disabled'] = 'Deaktiviert';
$string['editcreationsettings'] = 'Einstellungen für die Evento-Kurserstellung bearbeiten ';
$string['enabled'] = 'Aktiviert';
$string['enablecatcoursecreation'] = 'Aktivierung Kurserstellung';
$string['enablecatcoursecreation_help'] = 'Aktiviere Kurserstellung für diese Kategorie';
$string['enablecoursetemplate'] = 'Kursvorlage verwenden';
$string['enablecoursetemplate_help'] = 'Wenn aktiviert, werden die neuen Kurse mit der ausgewählten Vorlage erstellt.';
$string['enableplugin'] = 'Plugin aktivieren';
$string['enableplugin_help'] = 'Plugin Aktiviern oder Deaktivieren';
$string['eventosynccoursecreation'] = 'Evento Kurserstellung synchronisieren';
$string['execonlyonstarttimeautumnterm'] = 'Kurserstellung nur am Startdatum ausführen';
$string['execonlyonstarttimeautumnterm_help'] = 'Wenn gesetzt, wird die Kurserstellung nur am Starttag ausgeführt. Ansonsten wird die Kurserstellung bis kurz nach dem Semsterstart täglich ausgeführt, falls es neue Kurse gibt.';
$string['execonlyonstarttimespringterm'] = 'Kurserstellung nur am Startdatum ausführen';
$string['execonlyonstarttimespringterm_help'] = 'Wenn gesetzt, wird die Kurserstellung nur am Starttag ausgeführt. Ansonsten wird die Kurserstellung bis kurz nach dem Semsterstart täglich ausgeführt, falls es neue Kurse gibt.';
$string['idnumber'] = 'Studiengang (Kategorie ID)';
$string['idnumber_help'] = 'Studiengang, welcher in der Kategorie ID gespeichert wird. Es darf nur aus dem Präfix der Evnento Anlassnummer bestehen: Bsp: mod.dbm oder mod.bsp oder mod.tou. Es werden dann alle Kurse mit diesem Präfix berücksichtigt. Optionen mit den Zeichen § und | funktionieren weiterhin.';
$string['information'] = 'Information';
$string['longcoursenaming'] = 'Langer Name für Moodle Kurse';
$string['longcoursenaming_help'] = 'Definiert den langen Namen für Kurse. Verfügbar sind folgende Tokens: (Evento Module Name: @EVENTONAME@; Evento Module Abkürzung: @EVENTOABK@; Semesterperiode: @PERIODE@; Studiengang: @STG@; Durchführungsnummer: @NUM@)';
$string['monthinvalid'] = 'Monat ist ungültig (nur Werte von 1 bis 12 erlaubt)';
$string['no'] = 'Nein';
$string['numberofsections'] = 'Anzahl Abschnitte';
$string['numberofsections_help'] = 'Anzahl Abschnitte bei neuen leeren Kursen';
$string['plugindisabled'] = 'Das Plugin zur Evento Kurserstellung ist deaktiviert!';
$string['pluginname'] = 'Evento Kurserstellung';
$string['pluginname_desc'] = 'Erstellt neue Moodle Kurse aufgrund der Module in Evento.';
$string['privacy:metadata'] = 'Das Plugin Evento-Kurserstellung speichert keine persönlichen Daten.';
$string['shortcoursenaming'] = 'Kurzer Name für Moodle Kurse';
$string['shortcoursenaming_help'] = 'Definiert den kurzen Namen für Kurse. Verfügbar sind folgende Tokens: (Evento Module Name: @EVENTONAME@; Evento Module Abkürzung: @EVENTOABK@; Semesterperiode: @PERIODE@; Studiengang: @STG@); Durchführungsnummer: @NUM@';
$string['springendmonth'] = 'Endmonat';
$string['springendmonth_help'] = 'Monat, bis zu welchen für das Frühlingssemester die Kurserstellung durchgeführt wird.';
$string['springendday'] = 'Endtag';
$string['springendday_help'] = 'Tag, bis zu welchen für das Frühlingssemester die Kurserstellung durchgeführt wird.';
$string['springstartday'] = 'Starttag';
$string['springstartday_help'] = 'Tag, ab welchen für das Frühlingssemester die Kurserstellung durchgeführt wird.';
$string['springstartmonth'] = 'Startmonat';
$string['springstartmonth_help'] = 'Monat, in welchem für das Frühlingssemester die Kurserstellung durchgeführt wird.';
$string['startautumnterm'] = 'Herbstsemester';
$string['startautumnterm_help'] = 'Standardwerte für das Startdatum des Herbstsemesters';
$string['startspringterm'] = 'Frühlingssemester';
$string['startspringterm_help'] = 'Standardwerte für das Startdatum des Frühlingssemesters';
$string['templatecourse'] = 'Kursvorlage';
$string['templatecourse_help'] = 'Kursvorlage, vom welcher die Inhalte übernommen werden. Wenn keine Vorlage gesetzt ist, werden leere Kurse erstellt.';
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
$string['customcoursesettings'] = 'Individuelle Kurseinstellungen';
$string['coursestart'] = 'Startzeit';
$string['coursestart_help'] = 'Startzeitpunkt der bei der Kurserstellung gesetzt wird.';
$string['setcustomcoursestart'] = 'Individuelles Startdatum verwenden.';
$string['setcustomcoursestart_help'] = 'Wenn gesetzt, wird dieses Datum bei der Kurserstellung als Kursstart gesetzt. Als Standart ist der Kursstart identisch zum Semesterstart.';
$string['starttimecourseinvalid'] = 'Kursstartzeit ist keine gültige Unixzeit.';
$string['runnowheader'] = 'Kurserstellung ausführen';
$string['runnow'] = 'Kurse jetzt erstellen';
$string['runnowdesc'] = 'Alle Evento-Kurse für diese Kategorie sofort erstellen';
$string['runnowdesc_help'] = 'Startet den Kurserstellungsprozess für diese Kategorie sofort. Verwenden Sie die Option "Erzwingen", um Zeitbeschränkungen zu umgehen.';
$string['forcecreation'] = 'Erstellung erzwingen (Zeitbeschränkungen ignorieren)';
$string['runningcoursecreation'] = 'Evento-Kurse werden erstellt';
$string['creationsuccessful'] = 'Kurserstellung erfolgreich abgeschlossen';
$string['creationfailed'] = 'Kurserstellung fehlgeschlagen';
$string['creationskipped'] = 'Kurserstellung übersprungen - Voraussetzungen nicht erfüllt';
$string['creationunknown'] = 'Unbekannter Fehler bei der Kurserstellung';
$string['returntocategory'] = 'Zurück zur Kategorie';
