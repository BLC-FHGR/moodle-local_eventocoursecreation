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
 * Task schedule configuration for Evento Course Creation.
 *
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_eventocoursecreation\task\course_creation_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
        'disabled' => 0
    ],
    [
        'classname' => 'local_eventocoursecreation\task\evento_cache_maintenance_task',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
        'disabled' => 0
    ],
    [
        'classname' => 'local_eventocoursecreation\task\fast_mode_sync_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '*/4',  // Every 4 hours
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
        'disabled' => 1   // Disabled by default until configured
    ],
    [
        'classname' => 'local_eventocoursecreation\task\evento_course_creation_sync_task',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '22',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ]
];
