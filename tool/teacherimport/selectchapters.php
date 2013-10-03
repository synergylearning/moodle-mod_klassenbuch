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
require_once(dirname(__FILE__).'/selectchapters_form.php');
global $DB, $PAGE, $OUTPUT, $CFG;

$id = required_param('id', PARAM_INT); // Course Module ID.
$kbfrom = required_param('kbfrom', PARAM_INT); // Chapter ID.

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('klassenbuchtool/teacherimport:import', $context);

$PAGE->set_url('/mod/klassenbuch/tool/teacherimport/selectchapters.php', array('id' => $id));

$PAGE->set_title(format_string($klassenbuch->name));
$PAGE->add_body_class('mod_klassenbuch');
$PAGE->set_heading(format_string($course->fullname));

// Prepare the page header.
$strklassenbuch = get_string('modulename', 'mod_klassenbuch');
$strklassenbuchs = get_string('modulenameplural', 'mod_klassenbuch');

$kborig = $DB->get_record('klassenbuch', array('id' => $kbfrom));

// Ensure I have the right capability on the original Klassenbuch.
require_capability('mod/klassenbuch:edit', context_course::instance($kborig->course));

// Build chapter list.
$chapters = klassenbuch_preload_chapters($kborig);
foreach ($chapters as $chapterid => $chapter) {
    if (isset($chapter->subchapters) && is_array($chapter->subchapters)) {
        foreach ($chapter->subchapters as &$subchapter) {
            $subchapterid = $subchapter;
            $subchapter = clone($chapters[$subchapterid]);
            unset($chapters[$subchapterid]);
        }
    }
}
$mform = new klassenbuchtool_teacherimportselectchapters_form(null,
                                                              array('id' => $id, 'kbfrom' => $kbfrom, 'chapters' => $chapters));

// If data submitted, then process and store.
$redirecturl = $CFG->wwwroot.'/mod/klassenbuch/view.php?id='.$cm->id;
if ($mform->is_cancelled()) {
    redirect($redirecturl);
} else if ($data = $mform->get_data()) {
    if (!$data->confirmed) {
        if (!isset($data->chapter)) {
            $data->chapter = array();
        }
        if (!isset($data->subchapter)) {
            $data->subchapter = array();
        }
        $mform->preconfirm($data->chapter, $data->subchapter);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('confirmchapters', 'klassenbuchtool_teacherimport', $klassenbuch->name));
        echo '<ul>';
        foreach ($data->chapter as $chapterid => $checked) {
            echo '<li>'.s($chapters[$chapterid]->title).'</li>';
            if (isset($data->subchapter[$chapterid])) {
                echo '<ul>';
                foreach ($data->subchapter[$chapterid] as $subchapterid => $ignored) {
                    echo '<li>'.s($chapters[$chapterid]->subchapters[$subchapterid]->title).'</li>';
                }
                echo '</ul>';
            }
        }
        echo '</ul>';
        $mform->display();
        echo $OUTPUT->footer();
    } else {
        if (isset($data->chaplist)) {
            $cmsrc = get_coursemodule_from_instance('klassenbuch', $kborig->id, $kborig->course, false, MUST_EXIST);
            $ctxsrc = context_module::instance($cmsrc->id);
            $chlist = explode(',', $data->chaplist);
            foreach ($chlist as $chimport) {
                $chapandsubs = explode('|', $chimport);
                $chid = $chapandsubs[0];
                klassenbuch_teacherimport_importchapter($klassenbuch, $kbfrom, $chid, $context, $ctxsrc);
                if (isset($chapandsubs[1]) && $chapandsubs[1] != '') {
                    $subs = explode(',', $chapandsubs[1]);
                    foreach ($subs as $subid) {
                        klassenbuch_teacherimport_importchapter($klassenbuch, $kbfrom, $subid, $context, $ctxsrc);
                    }
                }
            }
        }
        redirect($redirecturl);
    }
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('choosechapters', 'klassenbuchtool_teacherimport', $klassenbuch->name));
    $mform->display();
    echo $OUTPUT->footer();
}



