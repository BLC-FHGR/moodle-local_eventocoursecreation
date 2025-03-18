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
 * Cache definitions for Evento Course Creation
 *
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'eventocreation_api' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false, // We store complex data including objects
        'staticacceleration' => true,
        'staticaccelerationsize' => 100, // Limit number of items in static cache
        'ttl' => 3600, // 1 hour default TTL
    ],
    'eventocreation_veranstalter' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 30, // Reasonable number of Veranstalter
        'ttl' => 86400, // 24 hours - Veranstalter data changes less frequently
    ],
    'eventocreation_events' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 50, // Event sets per Veranstalter
        'ttl' => 3600, // 1 hour
    ],
    'coursecreation' => [
        'mode' => cache_store::MODE_REQUEST,
    ],
];
