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
 * Edit klassenbuch chapter
 *
 * @package    mod_klassenbuch
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/edit_form.php');
global $CFG, $OUTPUT, $DB, $PAGE, $SESSION;

$cmid = required_param('cmid', PARAM_INT);  // Klassenbuch Course Module ID.
$chapterid = optional_param('id', 0, PARAM_INT); // Chapter ID.
$pagenum = optional_param('pagenum', 0, PARAM_INT);
$subchapter = optional_param('subchapter', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('klassenbuch', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:edit', $context);

$PAGE->set_url('/mod/klassenbuch/edit.php', array('cmid' => $cmid, 'id' => $chapterid,
                                                 'pagenum' => $pagenum, 'subchapter' => $subchapter));
$PAGE->set_pagelayout('admin'); // This is a bloody hack!

$options = array('noclean' => true, 'subdirs' => true, 'maxfiles' => -1, 'maxbytes' => 0, 'context' => $context);

if ($chapterid) {
    $chapter = $DB->get_record('klassenbuch_chapters', array('id' => $chapterid, 'klassenbuchid' => $klassenbuch->id),
                               '*', MUST_EXIST);
    $chapter->customcontenthidden = array();
    $chapterfields = $DB->get_records('klassenbuch_chapter_fields', array('chapterid' => $chapter->id));
    foreach ($chapterfields as $chapterfield) {
        $fieldname = 'customcontent_'.$chapterfield->fieldid;
        $fieldnameformat = $fieldname.'format';
        $chapter->$fieldname = $chapterfield->content;
        $chapter->$fieldnameformat = $chapterfield->contentformat;
        $chapter->customcontenthidden[$chapterfield->fieldid] = $chapterfield->hidden;
    }

} else {
    $chapter = new stdClass();
    $chapter->id = null;
    $chapter->subchapter = $subchapter;
    $chapter->pagenum = $pagenum + 1;
}
$chapter->cmid = $cm->id;

$chapter = file_prepare_standard_editor($chapter, 'content', $options, $context, 'mod_klassenbuch', 'chapter', $chapter->id);

// Prepare the editors for each field.
$customfields = $DB->get_records('klassenbuch_globalfields');
foreach ($customfields as $customfield) {
    $customfield->fieldname = 'customcontent_'.$customfield->id;
    if (isset($chapter->{$customfield->fieldname})) {
        $chapter = file_prepare_standard_editor($chapter, $customfield->fieldname, $options, $context,
                                                'mod_klassenbuch', $customfield->fieldname, $chapter->id);
    }
}

$maxbytes = get_max_upload_file_size($CFG->maxbytes, $course->maxbytes);
$attachoptions = array('noclean' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $maxbytes, 'context' => $context);
$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $context->id, 'mod_klassenbuch', 'attachment', $chapter->id, $attachoptions);
$chapter->attachments = $draftitemid;

$mform = new klassenbuch_chapter_edit_form(null, array('options' => $options, 'attachoptions' => $attachoptions,
                                                      'customfields' => $customfields));
$mform->set_data($chapter);

// If data submitted, then process and store.
if ($mform->is_cancelled()) {
    if (optional_param('newchapter', false, PARAM_BOOL) && $chapter->id) {
        $DB->delete_records('klassenbuch_chapters', array('id' => $chapter->id));
        unset($chapter->id);
    }
    if (empty($chapter->id)) {
        redirect("view.php?id=$cm->id");
    } else {
        redirect("view.php?id=$cm->id&chapterid=$chapter->id");
    }
} else if ($data = $mform->get_data()) {

    if (!$data->id) {
        // Make sure we're not accidentally creating lots and lots of new chapters.
        if (!isset($SESSION->uniqueeditingids)) {
            $SESSION->uniqueeditingids = array();
        }
        if (in_array($data->uniqueeditingid, $SESSION->uniqueeditingids)) {
            // Something is wrong - do not save.
            print_error(get_string('duplicatenewchaptererror', 'mod_klassenbuch', "view.php?id=$cm->id"));
        }

        // Adding new chapter.
        $data->klassenbuchid = $klassenbuch->id;
        $data->hidden = 0;
        $data->timecreated = time();
        $data->timemodified = time();
        $data->importsrc = '';
        $data->content = '';          // Updated later.
        $data->contentformat = FORMAT_HTML; // Updated later.
        // SYNERGY - initialise extra fields.
        $data->mailed = 0;
        $data->mailnow = 0;
        // SYNERGY - initialise extra fields.
        // Make room for new page.
        $sql = "UPDATE {klassenbuch_chapters}
                   SET pagenum = pagenum + 1
                 WHERE klassenbuchid = ? AND pagenum >= ?";
        $DB->execute($sql, array($klassenbuch->id, $data->pagenum));

        $data->id = $DB->insert_record('klassenbuch_chapters', $data);

        $DB->set_field('klassenbuch', 'revision', $klassenbuch->revision + 1, array('id' => $klassenbuch->id));
    }

    if ($data->newchapter) {
        // New chapter, which was hidden during autosave, now needs to be unhidden.
        $data->hidden = 0;
    }

    // Store the files.
    if (isset($data->content_editor)) {
        $data = file_postupdate_standard_editor($data, 'content', $options, $context, 'mod_klassenbuch', 'chapter', $data->id);
    } else {
        $data->content = '';
        $data->contentformat = FORMAT_HTML;
    }
    $DB->update_record('klassenbuch_chapters', $data);

    add_to_log($course->id, 'course', 'update mod', '../mod/klassenbuch/view.php?id=' . $cm->id, 'klassenbuch ' . $klassenbuch->id);
    add_to_log($course->id, 'klassenbuch', 'update', 'view.php?id=' . $cm->id . '&chapterid=' . $data->id,
               $klassenbuch->id, $cm->id);

    if (!isset($data->customcontenthidden)) {
        $data->customcontenthidden = array();
    }
    foreach ($customfields as $customfield) {
        $fieldid = $customfield->id;
        $fieldname = $customfield->fieldname;
        $fieldnameformat = $customfield->fieldname.'format';
        $rawfieldname = $customfield->fieldname.'_editor';
        if (!isset($data->$rawfieldname)) {
            continue;
        }
        $data = file_postupdate_standard_editor($data, $customfield->fieldname, $options, $context,
                                                'mod_klassenbuch', $customfield->fieldname, $data->id);

        if (!isset($data->$fieldname) || !isset($data->$fieldnameformat)) {
            continue;
        }

        // Insert of update.
        $dataobj = new stdClass();
        $dataobj->content = $data->$fieldname;
        $dataobj->contentformat = $data->$fieldnameformat;
        $dataobj->hidden = isset($data->customcontenthidden[$fieldid]) ? $data->customcontenthidden[$fieldid] : 0;

        if ($existing = $DB->get_record('klassenbuch_chapter_fields', array('chapterid' => $data->id, 'fieldid' => $fieldid))) {
            // Update.
            $dataobj->id = $existing->id;
            $DB->update_record('klassenbuch_chapter_fields', $dataobj);
        } else {
            // Insert.
            $dataobj->chapterid = $data->id;
            $dataobj->fieldid = $fieldid;
            $DB->insert_record('klassenbuch_chapter_fields', $dataobj);
        }
    }

    file_save_draft_area_files($data->attachments, $context->id, 'mod_klassenbuch', 'attachment', $data->id, $attachoptions);

    klassenbuch_preload_chapters($klassenbuch); // Fix structure.

    redirect("view.php?id=$cm->id&chapterid=$data->id");
}

// Otherwise fill and print the form.
$PAGE->set_title(format_string($klassenbuch->name));
$PAGE->add_body_class('mod_klassenbuch');
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editingchapter', 'mod_klassenbuch'));

$mform->display();

echo $OUTPUT->footer();
