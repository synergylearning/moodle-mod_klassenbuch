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

require_once("../../config.php");
global $CFG, $DB, $PAGE, $OUTPUT;
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/mod/klassenbuch/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/mod/klassenbuch/globalsettings.php');
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('managemodules');
echo $OUTPUT->header();

$action = optional_param('action', '', PARAM_ALPHA);

// Handle input.
if ($action == 'addfield') {
    $toinsert = new stdClass();
    $toinsert->title = required_param('title', PARAM_TEXT);
    $toinsert->hidden = optional_param('hidden', 0, PARAM_BOOL);
    $toinsert->title = trim($toinsert->title);
    if ($toinsert->title != '') {
        $DB->insert_record('klassenbuch_globalfields', $toinsert) or die('could not insert record');
    }
} else if ($action == 'existingfields') {
    $checked = optional_param_array('checked', array(), PARAM_BOOL);
    $hidden = optional_param_array('hidden', array(), PARAM_BOOL);
    $title = optional_param_array('title', array(), PARAM_TEXT);
    $withselectedfields = required_param('withselectedfields', PARAM_TEXT);

    foreach ($checked as $fieldid => $ischecked) {
        if ($ischecked) {
            if ($withselectedfields == 'update') {
                $toupdate = new stdClass();
                $toupdate->id = $fieldid;
                if (isset($hidden[$fieldid])) {
                    $toupdate->hidden = $hidden[$fieldid];
                }
                if (isset($title[$fieldid]) && trim($title[$fieldid] != '')) {
                    $toupdate->title = trim($title[$fieldid]);
                }
                $DB->update_record('klassenbuch_globalfields', $toupdate);
            } else if ($withselectedfields == 'delete') {
                // Ensure it has no instances.
                if (!$DB->count_records('klassenbuch_chapter_fields', array('fieldid' => $fieldid))) {
                    $DB->delete_records('klassenbuch_globalfields', array('id' => $fieldid));
                }
            }
        }
    }
}

// Display.
$strmodulename = get_string("modulename", "klassenbuch");

echo $OUTPUT->heading($strmodulename.': '.get_string('manageglobalfields', 'klassenbuch'), 3);
echo $OUTPUT->box_start();

// Add field.
echo '<form method="post" name="addfield" id="addfield"><p>';
echo '<input type="hidden" name="action" value="addfield"/>';
echo '<input type="text" name="title" id="addfield_title" value=""/>&nbsp;';
echo '<input type="checkbox" name="hidden" id="addfield_hidden" value="1"/>'.get_string('hiddenbydefault', 'klassenbuch');
echo '<input type="submit" value="'.get_string('addnewfield', 'klassenbuch').'" />';
echo '</form>';

// Existing fields.
echo '<form method="post" name="existingfields" id="existingfields"><p>';
echo '<input type="hidden" name="action" value="existingfields"/>';
$fields = $DB->get_records('klassenbuch_globalfields');
$table = new html_table();
$table->head = array(
    get_string('fieldtitle', 'klassenbuch'),
    get_string('hiddenbydefault', 'klassenbuch'),
    get_string('newfieldtitle', 'klassenbuch'),
    get_string('check'),
);
$table->size = array('40%', '20%', '30%', '10%');
$table->align = array('left', 'left', 'left', 'center');
foreach ($fields as $field) {
    $row = array();
    $row[] = $field->title;
    $slctno = '';
    $slctyes = '';
    if ($field->hidden) {
        $slctyes = ' selected = "selected"';
    } else {
        $slctno = ' selected = "selected"';;
    }
    $row[] = '<select name="hidden['.$field->id.']"><option value="0"'.$slctno.'>'.get_string('no').
        '</option><option value="1"'.$slctyes.'>'.get_string('yes').'</option></select>';
    $row[] = '<input type="text" name="title['.$field->id.']" value="">';
    $row[] = '<input type="checkbox" name="checked['.$field->id.']" value="1">';
    $table->data[] = $row;
}
echo html_writer::table($table);
echo $OUTPUT->container_start('mdl-right');

echo '<select name="withselectedfields">';
echo '<option selected="selected" value="">'.get_string('withselectedfields', 'klassenbuch').'</option>';
echo '<option value="delete">'.get_string('delete').'</option>';
echo '<option value="update">'.get_string('update').'</option>';
echo '</select>';
echo '<br />'."\n";
echo '<input type="submit" value="'.get_string('savechanges').'" />';
echo $OUTPUT->container_end();

echo '</form>';

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
