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
 * Availability OnceMet - Language pack.
 *
 * @package    availability_oncemet
 * @copyright  2026 Mahmoud Chehada, ssystems GmbH <mchehada@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addrestriction'] = 'Fügen Sie Voraussetzungen hinzu, die nach der einmaligen Erfüllung dauerhaft gemerkt werden sollen.';
$string['confirmremove_continue'] = 'Voraussetzung entfernen';
$string['confirmremove_message'] = 'Andere Nutzer/innen haben diese "Einmal erfüllt"-Voraussetzung bereits dauerhaft freigeschaltet. Wenn Sie die Voraussetzung entfernen, werden diese dauerhaften Freischaltungen gelöscht, sobald Sie dieses Formular speichern. Wenn Sie danach genau dieselbe Voraussetzung erneut anlegen, kommen die Freischaltungen nicht zurück, denn eine neue Voraussetzung beginnt von vorne: Die betroffenen Nutzer/innen müssten die enthaltenen Voraussetzungen erneut erfüllen.';
$string['confirmremove_title'] = 'Voraussetzung "Einmal erfüllt" entfernen?';
$string['description'] = 'Sobald diese Voraussetzung einmal erfüllt wurde, bleibt sie dauerhaft erfüllt.';
$string['error_nochildren'] = 'Fügen Sie mindestens eine Voraussetzung hinzu.';
$string['error_notconfigured'] = 'Diese Voraussetzung ist nicht korrekt konfiguriert.';
$string['error_unknowninstance'] = 'Diese "Einmal erfüllt"-Voraussetzung existiert in dieser Aktivität oder in diesem Kursabschnitt nicht.';
$string['error_unknownitem'] = 'Die Aktivität oder der Kursabschnitt dieser "Einmal erfüllt"-Voraussetzung wurde nicht angegeben.';
$string['helptext_persistent'] = 'Nutzer/innen, welche die Voraussetzungen einmal erfüllt haben, behalten den Zugriff auch dann, wenn sich diese Voraussetzungen später ändern oder nicht mehr zutreffen.';
$string['helptext_remove'] = 'Wenn Sie diese Voraussetzung "Einmal erfüllt" aus der Aktivität oder dem Kursabschnitt entfernen, entfällt die dauerhafte Freischaltung. Alle verbleibenden Voraussetzungen gelten weiter wie gewohnt.';
$string['oncemet:addinstance'] = 'Voraussetzungen "Einmal erfüllt" hinzufügen';
$string['oncemet:resetunlock'] = 'Die dauerhaften Freischaltungen einer Voraussetzung "Einmal erfüllt" zurücksetzen';
$string['oncemet:viewunlocks'] = 'Die dauerhaften Freischaltungen einer Voraussetzung "Einmal erfüllt" ansehen';
$string['pluginname'] = 'Voraussetzung: Andere Voraussetzungen mindestens einmal erfüllt';
$string['privacy:metadata:availability_oncemet'] = 'Informationen über die dauerhaften Freischaltungen, welche Nutzer/innen durch die Erfüllung der Voraussetzungen einer "Einmal erfüllt"-Voraussetzung erhalten haben.';
$string['privacy:metadata:availability_oncemet:availabilityuuid'] = 'Die Kennung der "Einmal erfüllt"-Voraussetzung, welche erfüllt wurde.';
$string['privacy:metadata:availability_oncemet:cmid'] = 'Die ID der Aktivität, zu welcher die "Einmal erfüllt"-Voraussetzung hinzugefügt wurde.';
$string['privacy:metadata:availability_oncemet:courseid'] = 'Die ID des Kurses, welcher die "Einmal erfüllt"-Voraussetzung enthält.';
$string['privacy:metadata:availability_oncemet:sectionid'] = 'Die ID des Kursabschnitts, zu welchem die "Einmal erfüllt"-Voraussetzung hinzugefügt wurde.';
$string['privacy:metadata:availability_oncemet:timecreated'] = 'Der Zeitpunkt, zu welchem die Nutzerin oder der Nutzer die "Einmal erfüllt"-Voraussetzung erfüllt hat.';
$string['privacy:metadata:availability_oncemet:userid'] = 'Die ID der Nutzerin oder des Nutzers, welche/r die "Einmal erfüllt"-Voraussetzung erfüllt hat.';
$string['requires_description'] = 'Mindestens einmal erfüllt: {$a}';
$string['requires_description_prefix'] = 'Mindestens einmal erfüllt:';
$string['requires_not_description'] = 'Noch nicht mindestens einmal erfüllt: {$a}';
$string['requires_not_description_prefix'] = 'Noch nicht mindestens einmal erfüllt:';
$string['title'] = 'Einmal erfüllt';
$string['uninstaller_remainingrestrictions'] = 'Es gibt weiterhin {$a} Aktivitäten oder Kursabschnitte, welche eine "Einmal erfüllt"-Voraussetzung nutzen. Diese Voraussetzungen wurden nicht entfernt, werden ab sofort aber ignoriert. Alle darin enthaltenen Voraussetzungen werden ebenfalls ignoriert, wodurch die betroffenen Aktivitäten und Kursabschnitte für alle verfügbar geworden sein können. Trainer/innen müssen diese Voraussetzungen manuell entfernen, bevor sie die Voraussetzungen einer betroffenen Aktivität oder eines betroffenen Kursabschnitts wieder speichern können.';
$string['unlocks_button'] = 'Bestehende Freischaltungen ansehen';
$string['unlocks_column_select'] = 'Auswählen';
$string['unlocks_column_time'] = 'Freigeschaltet am';
$string['unlocks_heading'] = 'Bestehende Freischaltungen';
$string['unlocks_intro'] = 'Dieser Bericht listet die Nutzer/innen auf, welche die folgende "Einmal erfüllt"-Voraussetzung von "{$a}" dauerhaft freigeschaltet haben, indem sie die enthaltenen Voraussetzungen mindestens einmal erfüllt haben:';
$string['unlocks_reset'] = 'Freischaltung zurücksetzen';
$string['unlocks_resetdone'] = 'Die dauerhafte Freischaltung wurde für {$a} Nutzer/innen zurückgesetzt. Sie müssen die enthaltenen Voraussetzungen erneut erfüllen, um wieder Zugriff zu erhalten.';
