<?php
// This file is part of Moodle - http://moodle.org/
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
 * Parent view of klassenbuch content - simplified view.php, with custom access check.
 *
 * @package   mod_klassenbuch
 * @copyright 2016 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

global $CFG, $DB, $USER, $PAGE, $OUTPUT, $SITE;
require_once($CFG->libdir.'/completionlib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$userid = required_param('userid', PARAM_INT); // Child id.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

$url = new moodle_url('/mod/klassenbuch/parent.php', ['id' => $cm->id, 'userid' => $userid]);
if ($chapterid) {
    $url->param('chapterid', $chapterid);
}
$PAGE->set_url($url);

// Need to be the parent of the given child.
require_login();
if (!class_exists('\block_gimychildren\local\gimychildren')) {
    die('block_gimychildren not found');
}
$childids = \block_gimychildren\local\gimychildren::get_mychildrenids();
if (!in_array($userid, $childids)) {
    throw new moodle_exception('notparent', 'mod_klassenbuch');
}

$context = context_module::instance($cm->id);
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);

// The given child must be able to view the klassenbuch.
require_capability('mod/klassenbuch:read', $context, $userid);

// Read chapters.
$chapters = klassenbuch_preload_chapters($klassenbuch);

// Check chapterid and read chapter data.
if ($chapterid == '0') { // Go to first chapter if no given.
    \mod_klassenbuch\event\course_module_viewed::create_from_klassenbuch($klassenbuch, $context)->trigger();

    foreach ($chapters as $ch) {
        if (!$ch->hidden) {
            $chapterid = $ch->id;
            break;
        }
    }
}

$returnurl = new moodle_url('/blocks/gimychildren/childrep.php', ['id' => $userid]);
$chapter = null;
if (!$chapterid or !$chapter = $DB->get_record('klassenbuch_chapters',
                                               array( 'id' => $chapterid, 'klassenbuchid' => $klassenbuch->id)) ) {
    print_error('errorchapter', 'mod_klassenbuch', $returnurl);
}

// Chapter is hidden for students.
if ($chapter->hidden) {
    print_error('errorchapter', 'mod_klassenbuch', $returnurl);
}

// Unset all page parameters.
unset($id);
unset($chapterid);

// Security checks  END.

\mod_klassenbuch\event\chapter_viewed::create_from_chapter($klassenbuch, $context, $chapter)->trigger();

// Read standard strings.
$strklassenbuchs = get_string('modulenameplural', 'mod_klassenbuch');
$strklassenbuch = get_string('modulename', 'mod_klassenbuch');
$strtoc = get_string('toc', 'mod_klassenbuch');

// Prepare header.
$PAGE->set_title(format_string($klassenbuch->name));
$PAGE->add_body_class('mod_klassenbuch');
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');

klassenbuch_add_fake_block($chapters, $chapter, $klassenbuch, $cm, 0, $userid);

// Prepare chapter navigation icons.
$previd = null;
$nextid = null;
$last = null;
foreach ($chapters as $ch) {
    if ($ch->hidden) {
        continue;
    }
    if ($last == $chapter->id) {
        $nextid = $ch->id;
        break;
    }
    if ($ch->id != $chapter->id) {
        $previd = $ch->id;
    }
    $last = $ch->id;
}

$chnavigation = '';
if ($previd) {
    $prevurl = new moodle_url($PAGE->url, ['chapterid' => $previd]);
    $chnavigation .= '<a title="'.get_string('navprev', 'klassenbuch').'" href="'.$prevurl->out().'">
    <img src="'.$OUTPUT->pix_url('nav_prev', 'mod_klassenbuch').'" class="bigicon" alt="'.get_string('navprev', 'klassenbuch').'"/>
    </a>';
} else {
    $chnavigation .= '<img src="'.$OUTPUT->pix_url('nav_prev_dis', 'mod_klassenbuch').'" class="bigicon" alt="" />';
}
if ($nextid) {
    $nexturl = new moodle_url($PAGE->url, ['chapterid' => $nextid]);
    $chnavigation .= '<a title="'.get_string('navnext', 'klassenbuch').'" href="'.$nexturl->out().'">
    <img src="'.$OUTPUT->pix_url('nav_next', 'mod_klassenbuch').'" class="bigicon" alt="'.get_string('navnext', 'klassenbuch').'" />
    </a>';
} else {
    $sec = '';
    if ($section = $DB->get_record('course_sections', array('id' => $cm->section))) {
        $sec = $section->section;
    }
    $chnavigation .= '<a title="'.get_string('navexit', 'klassenbuch').'" href="'.$returnurl->out().'">
    <img src="'.$OUTPUT->pix_url('nav_exit', 'mod_klassenbuch').'" class="bigicon" alt="'.get_string('navexit', 'klassenbuch').'" />
    </a>';
}

// Klassenbuch display HTML code.

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($klassenbuch->name));

// Upper nav.
echo '<div class="navtop">'.$chnavigation.'</div>';

// Chapter itself.
echo $OUTPUT->box_start('generalbox klassenbuch_content');
if (!$klassenbuch->customtitles) {
    $hidden = $chapter->hidden ? 'dimmed_text' : '';
    if (!$chapter->subchapter) {
        $currtitle = klassenbuch_get_chapter_title($chapter->id, $chapters, $klassenbuch, $context);
        echo '<p class="klassenbuch_chapter_title '.$hidden.'">'.$currtitle;
    } else {
        $currtitle = klassenbuch_get_chapter_title($chapters[$chapter->id]->parent, $chapters, $klassenbuch, $context);
        $currsubtitle = klassenbuch_get_chapter_title($chapter->id, $chapters, $klassenbuch, $context);
        echo '<p class="klassenbuch_chapter_title '.$hidden.'">'.$currtitle.'<br />'.$currsubtitle;
    }
    echo '</p>';
}

if (!$chapter->contenthidden) {
    $chaptertext = file_rewrite_pluginfile_urls($chapter->content, 'pluginfile.php', $context->id, 'mod_klassenbuch',
                                                'chapter', $chapter->id);
    echo format_text($chaptertext, $chapter->contentformat, array('noclean' => true, 'context' => $context));
}

// Add custom fields.
$customfields = klassenbuch_get_chapter_customfields($chapter->id);
foreach ($customfields as $customfield) {
    $chaptertext = file_rewrite_pluginfile_urls($customfield->content, 'pluginfile.php', $context->id,
                                                'mod_klassenbuch', 'customcontent_'.$customfield->fieldid, $chapter->id);
    if (!empty($chaptertext)) {
        echo $OUTPUT->heading(s($customfield->title), 3);
        echo format_text($chaptertext, $customfield->contentformat, array('noclean' => true, 'context' => $context));
    }
}

// Add attachments.
echo klassenbuch_output_attachments($chapter->id, $context);

echo $OUTPUT->box_end();

// Lower navigation.
echo '<div class="navbottom">'.$chnavigation.'</div>';

echo klassenbuch_get_subcontent($chapter->id, $context);

echo $OUTPUT->footer();

