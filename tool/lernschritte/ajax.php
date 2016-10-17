<?php

// This file is part of Klassenbuch module for Moodle - http://moodle.org/
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
 * @package    klassenbuchtool
 * @subpackage lernschritte
 * @copyright  2014 Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__).'/../../../../config.php');
global $DB, $PAGE, $CFG;
require_once($CFG->dirroot.'/mod/klassenbuch/tool/lernschritte/lib.php');

$chapterid = required_param('chapterid', PARAM_INT);
$action = optional_param('action', '', PARAM_TEXT);
$module = optional_param('module', 'klassenbuch', PARAM_COMPONENT);

klassenbuchtool_lernschritte::check_supported_module($module);

$chapter = $DB->get_record($module.'_chapters', array('id' => $chapterid), '*', MUST_EXIST);
$instance = $DB->get_record($module, array('id' => $chapter->{$module.'id'}), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance($module, $instance->id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, true, $cm);

$url = new moodle_url('/mod/klassenbuch/tool/lernschritte/ajax.php', array('chapterid' => $chapterid, 'module' => $module));
$PAGE->set_url($url);

$contextmodule = context_module::instance($cm->id);
klassenbuchtool_lernschritte::require_edit($module, $contextmodule);
require_sesskey();

switch ($action) {
    case 'save':
        $lernschritte = klassenbuchtool_lernschritte::instance();
        echo json_encode($lernschritte->save_ajax($course->id, $chapter->id, $module));
        die;
        break;

    case 'delete':
        $lernschritte = klassenbuchtool_lernschritte::instance();
        echo json_encode($lernschritte->delete_ajax($chapter->id, $module));
        die;
        break;

    case 'updatesortorder':
        $lernschritte = klassenbuchtool_lernschritte::instance();
        $ids = optional_param('sortorder', "", PARAM_TEXT);

        if (!empty($ids)) {
            $sortorder = explode(",", $ids);
            echo json_encode($lernschritte->save_sortorder_ajax($sortorder, $chapter->id, $module));
            die;
        }

        echo json_encode(array());
        die;
        break;
}
