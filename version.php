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
 * @copyright  2017 HTW Chur Roger Barras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2026091400; // The current module version (Date: YYYYMMDDXX)
$plugin->requires  = 2016120500; // Requires this Moodle version.
$plugin->component = 'local_eventocoursecreation';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = "2.2.0"; // User-friendly version number.
$plugin->dependencies = array(
    // The module description import calls getEventoModulBeschreibung, which local_evento
    // offers from 2026090100 onwards. It also asks local_evento_service_exception whether
    // a fault only says that evento knows no description, which needs this version.
    'local_evento' => 2026091400
);
