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
 * Klassenbuch view page
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/tool/lernschritte/lib.php');

global $CFG, $DB, $USER, $PAGE, $OUTPUT, $SITE;
require_once($CFG->libdir.'/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$bid = optional_param('b', 0, PARAM_INT); // Klassenbuch id.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.
$edit = optional_param('edit', -1, PARAM_BOOL); // Edit mode.

// Security checks START - teachers edit; students view.

if ($id) {
    $cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $klassenbuch = $DB->get_record('klassenbuch', array('id' => $bid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('klassenbuch', $klassenbuch->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:read', $context);

$allowedit = has_capability('mod/klassenbuch:edit', $context);
$viewhidden = has_capability('mod/klassenbuch:viewhiddenchapters', $context);

if ($allowedit) {
    if ($edit != -1 and confirm_sesskey()) {
        $USER->editing = $edit;
    } else {
        if (isset($USER->editing)) {
            $edit = $USER->editing;
        } else {
            $edit = 0;
        }
    }
} else {
    $edit = 0;
}

// Read chapters.
$chapters = klassenbuch_preload_chapters($klassenbuch);

if ($allowedit and !$chapters) {
    redirect('edit.php?cmid='.$cm->id); // No chapters - add new one.
}
// Check chapterid and read chapter data.
if ($chapterid == '0') { // Go to first chapter if no given.
    foreach ($chapters as $ch) {
        if ($edit) {
            $chapterid = $ch->id;
            break;
        }
        if (!$ch->hidden) {
            $chapterid = $ch->id;
            break;
        }
    }
}

$chapter = null;
if (!$chapterid or !$chapter = $DB->get_record('klassenbuch_chapters',
                                               array( 'id' => $chapterid, 'klassenbuchid' => $klassenbuch->id)) ) {
    print_error('errorchapter', 'mod_klassenbuch', new moodle_url('/course/view.php', array('id' => $course->id)));
}

// Chapter is hidden for students.
if ($chapter->hidden and !$viewhidden) {
    print_error('errorchapter', 'mod_klassenbuch', new moodle_url('/course/view.php', array('id' => $course->id)));
}

$PAGE->set_url('/mod/klassenbuch/classplan.php', array('id' => $id, 'chapterid' => $chapterid));
$chapterpage = new moodle_url('/mod/klassenbuch/view.php', array('id' => $id, 'chapterid' => $chapterid));

// Security checks  END.

// Read standard strings.
$strklassenbuchs = get_string('modulenameplural', 'mod_klassenbuch');
$strklassenbuch = get_string('modulename', 'mod_klassenbuch');
$strtoc = get_string('toc', 'mod_klassenbuch');

// Prepare header.
$PAGE->set_title(format_string($klassenbuch->name));
$PAGE->add_body_class('mod_klassenbuch');
$PAGE->set_heading(format_string($course->fullname));

klassenbuch_add_fake_block($chapters, $chapter, $klassenbuch, $cm, $edit);

// Klassenbuch display HTML code.

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($klassenbuch->name));

echo html_writer::link($chapterpage, get_string('backtochapter', 'mod_klassenbuch'), array('class'=>'btn'));

echo klassenbuchtool_lernschritte_get_subcontent($chapterid, $context, 'klassenbuch');

echo $OUTPUT->footer();