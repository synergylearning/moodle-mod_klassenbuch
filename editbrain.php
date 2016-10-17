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

define('AJAX_SCRIPT', 1);

require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/edit_form.php');

global $CFG, $DB, $PAGE, $SESSION;

unset($_POST['cancel']); // Workaround for the fact that Y.io sends the whole form, including the 'cancel' button.

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

$PAGE->set_url('/mod/klassenbuch/editbrain.php', array('cmid' => $cmid, 'id' => $chapterid,
                                                      'pagenum' => $pagenum, 'subchapter' => $subchapter));

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
    $chapter = file_prepare_standard_editor($chapter, $customfield->fieldname, $options, $context,
                                            'mod_klassenbuch', $customfield->fieldname, $chapter->id);
}

$maxbytes = get_max_upload_file_size($CFG->maxbytes, $course->maxbytes);
$attachoptions = array('noclean' => true, 'subdirs' => false, 'maxfiles' => -1, 'maxbytes' => $maxbytes, 'context' => $context);
$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $context->id, 'mod_klassenbuch', 'attachment', $chapter->id, $attachoptions);
$chapter->attachments = $draftitemid;

$mform = new klassenbuch_chapter_edit_form(null, array('options' => $options, 'attachoptions' => $attachoptions,
                                                      'customfields' => $customfields));
$mform->set_data($chapter);

$result = array();
if ($data = $mform->get_data()) {
    if (!$data->id) {
        // Do not yet save chapter with empty title.
        $hascontent = (trim($data->title) != '');
        if ($hascontent) {
            // Title is filled in.
            $hascontent = isset($data->content_editor['text']);
            $hascontent = $hascontent && (trim($data->content_editor['text']) != '');
            if (!$hascontent) {
                // Content field is empty - check the custom fields.
                foreach ($customfields as $customfield) {
                    $fieldname = $customfield->fieldname;
                    $fieldnameeditor = $customfield->fieldname.'_editor';
                    if (!isset($data->$fieldnameeditor)) {
                        continue;
                    }
                    $editor = $data->$fieldnameeditor;
                    if (trim($editor['text']) != '') {
                        // Found a custom field with text in it.
                        $hascontent = true;
                        break;
                    }
                }
            }
        }
        if (!$hascontent) {
            echo json_encode(array('ready' => 0));
            exit();
        }

        // Make sure we're not accidentally creating lots and lots of new chapters.
        if (!isset($SESSION->klassenbuch_uniqueeditingids)) {
            $SESSION->klassenbuch_uniqueeditingids = array();
        }
        if (in_array($data->uniqueeditingid, $SESSION->klassenbuch_uniqueeditingids)) {
            // Something is wrong - do not autosave.
            echo json_encode(array('success' => 0));
            exit();
        }
        $SESSION->klassenbuch_uniqueeditingids[] = $data->uniqueeditingid;

        // Adding new chapter.
        $data->klassenbuchid = $klassenbuch->id;
        $data->hidden = 1; // Hide the chapter until it is really saved.
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

        // Send new id back.
        $result['newid'] = $data->id;
        $result['newchapter'] = 1;
    }

    // Store the files.
    if (isset($data->content_editor)) {
        $data = file_postupdate_standard_editor($data, 'content', $options, $context, 'mod_klassenbuch', 'chapter', $data->id);
    } else {
        $data->content = '';
        $data->contentformat = FORMAT_HTML;
    }
    $DB->update_record('klassenbuch_chapters', $data);

    $result['success'] = 1;

    if (!isset($data->customcontenthidden)) {
        $data->customcontenthidden = array();
    }
    foreach ($customfields as $customfield) {
        $fieldid = $customfield->id;
        $fieldname = $customfield->fieldname;
        $fieldnameformat = $customfield->fieldname.'format';
        $data = file_postupdate_standard_editor($data, $customfield->fieldname, $options, $context,
                                                'mod_klassenbuch', $customfield->fieldname, $chapter->id);

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
}
echo json_encode($result);
