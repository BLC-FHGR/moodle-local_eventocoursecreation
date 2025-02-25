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
 * Run course creation for a category
 *
 * @package    local_eventocoursecreation
 * @copyright  2024 FHGR
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');

$categoryid = required_param('category', PARAM_INT);
$force = optional_param('force', 0, PARAM_INT);
require_sesskey();

$context = context_coursecat::instance($categoryid);
require_login();
require_capability('moodle/category:manage', $context);

// Get settings
$setting = local_eventocoursecreation_setting::get($categoryid);

// Run the course creation
echo $setting->run_course_creation($force);