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
 * This page lists all the instances of klassenbuch in a particular course
 *
 * @package    mod_klassenbuch
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
global $DB, $PAGE, $OUTPUT, $CFG;
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

unset($id);

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

// Get all required strings.
$strklassenbuchs = get_string('modulenameplural', 'mod_klassenbuch');
$strklassenbuch = get_string('modulename', 'mod_klassenbuch');
$strsectionname = get_string('sectionname', 'format_'.$course->format);
$strname = get_string('name');
$strintro = get_string('moduleintro');
$strlastmodified = get_string('lastmodified');

$PAGE->set_url('/mod/klassenbuch/index.php', array('id' => $course->id));
$PAGE->set_title($course->shortname.': '.$strklassenbuchs);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strklassenbuchs);
echo $OUTPUT->header();

add_to_log($course->id, 'klassenbuch', 'view all', 'index.php?id='.$course->id, '');

// Get all the appropriate data.
if (!$klassenbuchs = get_all_instances_in_course('klassenbuch', $course)) {
    notice(get_string('thereareno', 'moodle', $strklassenbuchs), "$CFG->wwwroot/course/view.php?id=$course->id");
    die;
}

$sections = array();
$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $table->head = array($strsectionname, $strname, $strintro);
    $table->align = array('center', 'left', 'left');
} else {
    $table->head = array($strlastmodified, $strname, $strintro);
    $table->align = array('left', 'left', 'left');
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($klassenbuchs as $klassenbuch) {
    $cm = $modinfo->get_cm($klassenbuch->coursemodule);
    if ($usesections) {
        $printsection = '';
        if ($klassenbuch->section !== $currentsection) {
            if ($klassenbuch->section) {
                $printsection = get_section_name($course, $sections[$klassenbuch->section]);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $klassenbuch->section;
        }
    } else {
        $printsection = '<span class="smallinfo">'.userdate($klassenbuch->timemodified)."</span>";
    }

    $class = $klassenbuch->visible ? '' : 'class="dimmed"'; // Hidden modules are dimmed.

    $table->data[] = array(
        $printsection,
        "<a $class href=\"view.php?id=$cm->id\">".format_string($klassenbuch->name)."</a>",
        format_module_intro('klassenbuch', $klassenbuch, $cm->id)
    );
}

echo html_writer::table($table);

echo $OUTPUT->footer();
