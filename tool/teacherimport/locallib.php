<?php
// This file is part of Klassenbuch plugin for Moodle - http://moodle.org/
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
 * HTML import lib
 *
 * @package    klassenbuchtool
 * @subpackage teacherimport
 * @copyright  2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/lib.php');
global $CFG;
require_once($CFG->dirroot.'/mod/klassenbuch/locallib.php');

/*
 * Import chapter into Klassenbuch
 * @param object kbto - target Klassenbuch
 * @param object kbfromid - id of origin Klassenbuch
 * @param int chid - chapter id to import
 * @param obj $ctxdst - the context of the destination Klassenbuch
 * @param obj $ctxsrc - the context of the source Klassenbuch
 */
function klassenbuch_teacherimport_importchapter($kbto, $kbfromid, $chid, $ctxdst, $ctxsrc) {
    global $DB;

    // Find original chapter.
    if (!$origchapter = $DB->get_record('klassenbuch_chapters', array('id' => $chid))) {
        return false;
    }

    // Ensure chapter belongs to said original kb.
    if ($origchapter->klassenbuchid != $kbfromid) {
        return false;
    }

    // Get pagenum.
    $sql = "SELECT MAX(pagenum) AS highest FROM {klassenbuch_chapters} WHERE klassenbuchid = :klassenbuchid";
    $params = array('klassenbuchid' => $kbto->id);
    $pagenum = $DB->get_field_sql($sql, $params, 'highest');
    if (!is_number($pagenum)) {
        $pagenum = 0;
    }
    $pagenum++;

    // Save chapter.
    $clonechapter = clone($origchapter);
    unset($clonechapter->id);
    $clonechapter->klassenbuchid = $kbto->id;
    $clonechapter->pagenum = $pagenum;
    $clonechapter->timecreated = time();
    $clonechapter->timemodified = time();
    if (!$newchid = $DB->insert_record('klassenbuch_chapters', $clonechapter)) {
        return false;
    }

    // Get additional fields.
    $fields = $DB->get_records('klassenbuch_chapter_fields', array('chapterid' => $chid));
    foreach ($fields as $field) {
        $clonefield = clone($field);
        unset($clonefield->id);
        $clonefield->chapterid = $newchid;
        if (!$DB->insert_record('klassenbuch_chapter_fields', $clonefield)) {
            return false;
        }
    }

    // Copy across any files needed.
    klassenbuch_teacherimport_copyfiles($ctxsrc, $ctxdst, $chid, $newchid, 'attachment');
    klassenbuch_teacherimport_copyfiles($ctxsrc, $ctxdst, $chid, $newchid, 'chapter');
    foreach ($fields as $field) {
        $filearea = 'customcontent_'.$field->fieldid;
        klassenbuch_teacherimport_copyfiles($ctxsrc, $ctxdst, $chid, $newchid, $filearea);
    }
}

function klassenbuch_teacherimport_copyfiles($ctxsrc, $ctxdst, $oldchid, $newchid, $filearea) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($ctxsrc->id, 'mod_klassenbuch', $filearea, $oldchid, "sortorder, timemodified, filename", false);
    if ($files) {
        foreach ($files as $file) {
            $rec = array(
                'contextid' => $ctxdst->id,
                'itemid' => $newchid
                // All other fields are inherited from the original.
            );
            $fs->create_file_from_storedfile($rec, $file);
        }
    }
}