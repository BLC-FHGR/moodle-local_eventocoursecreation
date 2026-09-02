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

// Modulbeschreibung.
$string['moduldescriptionsettings'] = 'Modulbeschreibung';
$string['moduldescriptionsettings_help'] = 'Übernimmt die Kurzfassung der Modulbeschreibung aus Evento als Textseite in den Kurs und hält sie aktuell.';
$string['enablemoduldescription'] = 'Modulbeschreibungen übernehmen';
$string['enablemoduldescription_help'] = 'Wenn aktiviert, wird die Kurzfassung der Modulbeschreibung aus Evento geholt und als Textseite im Kurs abgelegt. Die Seite wird erst nach dem Wiederherstellen eines Kurstemplates angelegt, damit ein Template kein Duplikat erzeugt.';
$string['moduldescriptionscope'] = 'Zu synchronisierende Kurse';
$string['moduldescriptionscope_help'] = 'Welche Kurse die Synchronisation berücksichtigt. Bereits beendete Kurse werden nie angefasst.';
$string['moduldescriptionscopecurrent'] = 'Laufende und künftige Kurse (Enddatum in der Zukunft oder nicht gesetzt)';
$string['moduldescriptionscopefuture'] = 'Nur künftige Kurse (Startdatum in der Zukunft)';
$string['moduldescriptionpagename'] = 'Seitenname';
$string['moduldescriptionpagename_help'] = 'Name der Textseite, welche die Modulbeschreibung enthält.';
$string['moduldescriptioncmidnumber'] = 'ID-Nummer der Seite';
$string['moduldescriptioncmidnumber_help'] = 'ID-Nummer, die auf der Aktivität gesetzt wird. Sie kennzeichnet die Seite als von diesem Plugin verwaltet und übersteht Sicherung und Wiederherstellung eines Kurses. So wird eine aus einem Kurstemplate mitkopierte Seite erkannt. Nach dem Anlegen der ersten Kurse nicht mehr ändern.';
$string['moduldescriptionallowedstatus'] = 'Akzeptierte Evento-Statuswerte';
$string['moduldescriptionallowedstatus_help'] = 'Kommagetrennte Liste von Evento-Statuswerten (idStatus), die eine Modulbeschreibung haben muss, damit sie übernommen wird. 61003 ist "mb.Genehmigt". Leer lassen, um jeden Status zu akzeptieren.';
$string['moduldescriptionfuturevalid'] = 'Künftig gültige Beschreibungen übernehmen';
$string['moduldescriptionfuturevalid_help'] = 'Wenn aktiviert, wird eine Beschreibung auch dann übernommen, wenn das Evento-Feld mbGueltigAb in der Zukunft liegt. Dieses Feld folgt dem Semesterstart, vorab erstellte Kurse blieben sonst ohne Beschreibung.';
$string['moduldescriptiondisplay'] = 'Anzeige';
$string['moduldescriptiondisplay_help'] = 'Wie die Seite den Teilnehmenden angezeigt wird.';
$string['moduldescriptiondisplayauto'] = 'Automatisch';
$string['moduldescriptiondisplayembed'] = 'Eingebettet';
$string['moduldescriptiondisplayopen'] = 'Öffnen';
$string['moduldescriptionprintlastmodified'] = 'Datum der letzten Änderung anzeigen';
$string['moduldescriptionprintlastmodified_help'] = 'Wenn aktiviert, zeigt die Seite an, wann die Modulbeschreibung zuletzt geändert wurde.';
$string['moduldescriptionondelete'] = 'Seite manuell gelöscht';
$string['moduldescriptionondelete_help'] = 'Was die Synchronisation tut, wenn die Seite aus dem Kurs gelöscht wurde.';
$string['moduldescriptionondeleterecreate'] = 'Seite neu anlegen';
$string['moduldescriptionondeleteignore'] = 'Nichts tun, aber weiter prüfen';
$string['moduldescriptionondeletedisable'] = 'Diesen Kurs nicht mehr synchronisieren';
$string['moduldescriptiononrename'] = 'Seite manuell umbenannt';
$string['moduldescriptiononrename_help'] = 'Was die Synchronisation tut, wenn die Seite umbenannt wurde.';
$string['moduldescriptiononrenamereset'] = 'Konfigurierten Namen wiederherstellen';
$string['moduldescriptiononrenamekeep'] = 'Namen belassen';
$string['moduldescriptionbatchsize'] = 'Stapelgrösse';
$string['moduldescriptionbatchsize_help'] = 'Höchstzahl der Kurse, die ein Lauf der Synchronisationsaufgabe verarbeitet. Zuerst kommen die am längsten nicht geprüften Kurse an die Reihe, damit alle Kurse rotierend erfasst werden.';
$string['moduldescriptionretryhours'] = 'Wartezeit vor erneutem Versuch in Stunden';
$string['moduldescriptionretryhours_help'] = 'Wie lange ein Kurs nach einer fehlgeschlagenen Synchronisation übersprungen wird, bevor er erneut versucht wird.';
