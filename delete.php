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
 * Delete klassenbuch chapter
 *
 * @package    mod_klassenbuch
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
global $DB, $PAGE, $OUTPUT;

$id        = required_param('id', PARAM_INT);        // Course Module ID.
$chapterid = required_param('chapterid', PARAM_INT); // Chapter ID.
$confirm   = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:edit', $context);

$PAGE->set_url('/mod/klassenbuch/delete.php', array('id' => $id, 'chapterid' => $chapterid));

$chapter = $DB->get_record('klassenbuch_chapters', array('id' => $chapterid, 'klassenbuchid' => $klassenbuch->id), '*', MUST_EXIST);

// Header and strings.
$PAGE->set_title(format_string($klassenbuch->name));
$PAGE->add_body_class('mod_klassenbuch');
$PAGE->set_heading(format_string($course->fullname));

// Form processing.
if ($confirm) { // The operation was confirmed.
    require_sesskey();
    $fs = get_file_storage();
    if (!$chapter->subchapter) { // Delete all its subchapters if any.
        $chapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id),
                                     'pagenum', 'id, subchapter');
        $found = false;
        foreach ($chapters as $ch) {
            if ($ch->id == $chapter->id) {
                $found = true;
            } else if ($found and $ch->subchapter) {
                $fs->delete_area_files($context->id, 'mod_klassenbuch', 'chapter', $ch->id);
                $DB->delete_records('klassenbuch_chapters', array('id' => $ch->id));
            } else if ($found) {
                break;
            }
        }
    }
    $fs->delete_area_files($context->id, 'mod_klassenbuch', 'chapter', $chapter->id);
    $DB->delete_records('klassenbuch_chapters', array('id' => $chapter->id));

    \mod_klassenbuch\event\chapter_deleted::create_from_chapter($klassenbuch, $context, $chapter)->trigger();

    klassenbuch_preload_chapters($klassenbuch); // Fix structure.
    $DB->set_field('klassenbuch', 'revision', $klassenbuch->revision + 1, array('id' => $klassenbuch->id));

    redirect('view.php?id='.$cm->id);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($klassenbuch->name));

// The operation has not been confirmed yet so ask the user to do so.
if ($chapter->subchapter) {
    $strconfirm = get_string('confchapterdelete', 'mod_klassenbuch');
} else {
    $strconfirm = get_string('confchapterdeleteall', 'mod_klassenbuch');
}
echo '<br />';
$continue = new moodle_url('/mod/klassenbuch/delete.php', array('id' => $cm->id, 'chapterid' => $chapter->id, 'confirm' => 1));
$cancel = new moodle_url('/mod/klassenbuch/view.php', array('id' => $cm->id, 'chapterid' => $chapter->id));
echo $OUTPUT->confirm("<strong>$chapter->title</strong><p>$strconfirm</p>", $continue, $cancel);

echo $OUTPUT->footer();
