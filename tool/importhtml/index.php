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
 * @subpackage importhtml
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/import_form.php');
global $DB, $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT); // Course Module ID.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('klassenbuchtool/importhtml:import', $context);

$PAGE->set_url('/mod/klassenbuch/tool/importhtml/index.php', array('id' => $id, 'chapterid' => $chapterid));

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

$mform = new klassenbuchtool_importhtml_form(null, array('id' => $id, 'chapterid' => $chapterid));

// If data submitted, then process and store.
if ($mform->is_cancelled()) {
    if (empty($chapter->id)) {
        redirect("view.php?id=$cm->id");
    } else {
        redirect("view.php?id=$cm->id&chapterid=$chapter->id");
    }

} else if ($data = $mform->get_data()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('importingchapters', 'klassenbuchtool_importhtml'));

    // This is a bloody hack - children do not try this at home!
    $fs = get_file_storage();
    $draftid = file_get_submitted_draft_itemid('importfile');
    if (!$files = $fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id DESC', false)) {
        redirect($PAGE->url);
    }
    $file = reset($files);
    toolklassenbuch_importhtml_import_chapters($file, $data->type, $klassenbuch, $context);

    echo $OUTPUT->continue_button(new moodle_url('/mod/klassenbuch/view.php', array('id' => $id)));
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('import', 'klassenbuchtool_importhtml'));

$mform->display();

echo $OUTPUT->footer();
