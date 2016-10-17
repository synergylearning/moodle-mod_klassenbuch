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
 *
 * @package   klassenbuchtool_lernschritte
 * @copyright 2014 Andreas Wagner, SYNERGY LEARNING
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG, $DB, $PAGE;
require_once($CFG->dirroot.'/mod/klassenbuch/tool/lernschritte/lib.php');

$chapterid = required_param('chapterid', PARAM_INT);
$module = optional_param('module', 'klassenbuch', PARAM_COMPONENT);

klassenbuchtool_lernschritte::check_supported_module($module);

$chapter = $DB->get_record($module.'_chapters', array('id' => $chapterid), '*', MUST_EXIST);
$instance = $DB->get_record($module, array('id' => $chapter->{$module.'id'}), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance($module, $instance->id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);

$url = new moodle_url('/mod/klassenbuch/tool/lernschritte/createpdf.php', array('chapterid' => $chapterid, 'module' => $module));
$PAGE->set_url($url);

$contextmodule = context_module::instance($cm->id);
klassenbuchtool_lernschritte::can_print($module, $contextmodule);

// ...create report;
$classplan = klassenbuchtool_lernschritte::instance();
$classplan->output_pdf($course, $instance, $chapter, $module);

die;
