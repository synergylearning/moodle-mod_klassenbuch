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
 * Klassenbuch import
 *
 * @package    klassenbuchtool
 * @subpackage teacherimport
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/import_form.php');
global $DB, $PAGE, $CFG, $OUTPUT;

$id = required_param('id', PARAM_INT); // Course Module ID.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('klassenbuchtool/teacherimport:import', $context);

$PAGE->set_url('/mod/klassenbuch/tool/teacherimport/index.php', array('id' => $id, 'chapterid' => $chapterid));

if ($chapterid) {
    if (!$chapter = $DB->get_record('klassenbuch_chapters', array('id' => $chapterid, 'klassenbuchid' => $klassenbuch->id))) {
        $chapterid = 0;
    }
} else {
    $chapter = false;
}

$PAGE->set_title(format_string($klassenbuch->name));
$PAGE->add_body_class('mod_klassenbuch');
$PAGE->set_heading(format_string($course->fullname));

// Prepare the page header.
$strklassenbuch = get_string('modulename', 'mod_klassenbuch');
$strklassenbuchs = get_string('modulenameplural', 'mod_klassenbuch');

// Get other courses with Klassenbuch.
$courses = array();
$mycourses = enrol_get_my_courses();
foreach ($mycourses as $crs) {
    // Ensure capability.
    $crsctx = context_course::instance($crs->id);
    if (!has_capability('mod/klassenbuch:edit', $crsctx)) {
        continue;
    }
    // Don't list same course.
    if ($crs->id == $course->id) {
        continue;
    }
    $kbs = $DB->get_records('klassenbuch', array('course' => $crs->id), '', 'id,name');
    if (!$kbs || !count($kbs)) {
        continue;
    }

    foreach ($kbs as $kb) {
        $url = new moodle_url('/mod/klassenbuch/view.php', array('b' => $kb->id));
        $kb->link = html_writer::link($url, s($kb->name), array('target' => '_blank'));
    }

    $courses[$crs->id] = new stdClass();
    $courses[$crs->id]->klassenbuecher = $kbs;
    $courses[$crs->id]->id = $crs->id;
    $courses[$crs->id]->name = $crs->fullname;
}

$mform = new klassenbuchtool_teacherimport_form(null, array('id' => $id, 'chapterid' => $chapterid, 'courses' => $courses));

// If data submitted, then process and store.
if ($mform->is_cancelled()) {
    if (empty($chapter->id)) {
        redirect("$CFG->wwwroot/mod/klassenbuch/view.php?id=$cm->id");
    } else {
        redirect("$CFG->wwwroot/mod/klassenbuch/view.php?id=$cm->id&chapterid=$chapter->id");
    }

} else if ($data = $mform->get_data()) {
    if (isset($data->chosenkb) && is_number($data->chosenkb)) {
        redirect('selectchapters.php?id='.$id.'&kbfrom='.$data->chosenkb);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('import', 'klassenbuchtool_teacherimport'));

$mform->display();

echo $OUTPUT->footer();
