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
 * Show/hide klassenbuch chapter
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

global $DB, $PAGE;
$id = required_param('id', PARAM_INT); // Course Module ID.
$chapterid = required_param('chapterid', PARAM_INT); // Chapter ID.

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:edit', $context);

$PAGE->set_url('/mod/klassenbuch/show.php', array('id' => $id, 'chapterid' => $chapterid));

$chapter = $DB->get_record('klassenbuch_chapters', array('id' => $chapterid, 'klassenbuchid' => $klassenbuch->id), '*', MUST_EXIST);

// Switch hidden state.
$chapter->hidden = $chapter->hidden ? 0 : 1;

// Update record.
$DB->update_record('klassenbuch_chapters', $chapter);

// Change visibility of subchapters too.
if (!$chapter->subchapter) {
    $chapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id),
                                 'pagenum', 'id, subchapter, hidden');
    $found = 0;
    foreach ($chapters as $ch) {
        if ($ch->id == $chapter->id) {
            $found = 1;
        } else if ($found and $ch->subchapter) {
            $ch->hidden = $chapter->hidden;
            $DB->update_record('klassenbuch_chapters', $ch);
        } else if ($found) {
            break;
        }
    }
}

add_to_log($course->id, 'course', 'update mod', '../mod/klassenbuch/view.php?id='.$cm->id, 'klassenbuch '.$klassenbuch->id);
add_to_log($course->id, 'klassenbuch', 'update', 'view.php?id='.$cm->id, $klassenbuch->id, $cm->id);

klassenbuch_preload_chapters($klassenbuch); // Fix structure.
$DB->set_field('klassenbuch', 'revision', $klassenbuch->revision + 1, array('id' => $klassenbuch->id));

redirect('view.php?id='.$cm->id.'&chapterid='.$chapter->id);

