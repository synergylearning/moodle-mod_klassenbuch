<?php
// This file is part of Klassenbuch plugin for Moodle - http://moodle.org/
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
 * Klassenbuch IMSCP export plugin
 *
 * @package    klassenbuchtool
 * @subpackage exportimscp
 * @copyright  2001-3001 Antonio Vicent          {@link http://ludens.es}
 * @copyright  2001-3001 Eloy Lafuente (stronk7) {@link http://contiento.com}
 * @copyright  2011 Petr Skoda                   {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
global $CFG, $DB, $PAGE;
require_once($CFG->dirroot.'/mod/klassenbuch/locallib.php');
require_once($CFG->dirroot.'/backup/lib.php');
require_once($CFG->libdir.'/filelib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

$PAGE->set_url('/mod/klassenbuch/tool/exportimscp/index.php', array('id' => $id));

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:read', $context);
require_capability('klassenbuchtool/exportimscp:export', $context);

$strklassenbuchs = get_string('modulenameplural', 'klassenbuch');
$strklassenbuch = get_string('modulename', 'klassenbuch');
$strtop = get_string('top', 'klassenbuch');

add_to_log($course->id, 'klassenbuch', 'generateimscp', 'tool/generateimscp/index.php?id='.$cm->id, $klassenbuch->id, $cm->id);

$file = klassenbuchtool_exportimscp_build_package($klassenbuch, $context);

send_stored_file($file, 10, 0, true, clean_filename($klassenbuch->name).'.zip');
