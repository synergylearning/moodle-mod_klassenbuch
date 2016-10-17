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
    \mod_klassenbuch\event\course_module_viewed::create_from_klassenbuch($klassenbuch, $context)->trigger();

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

$PAGE->set_url('/mod/klassenbuch/view.php', array('id' => $id, 'chapterid' => $chapterid));

// Unset all page parameters.
unset($id);
unset($bid);
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

klassenbuch_add_fake_block($chapters, $chapter, $klassenbuch, $cm, $edit);

$subsdiv = '<div class="subscription">';

if (!isset($klassenbuch->forcesubscribe)) {
    $klassenbuch->forcesubscribe = 1;
}

if (!empty($USER->id) && !isguestuser()) {
    if (klassenbuch_is_forcesubscribed($klassenbuch->id)) {
        $streveryoneisnowsubscribed = get_string('everyoneisnowsubscribed', 'klassenbuch');
        $strallowchoice = get_string('allowchoice', 'klassenbuch');
        $subsdiv .= '<span class="helplink">'.get_string("forcessubscribe", 'klassenbuch').'</span><br />';
        $subsdiv .= '&nbsp;<span class="helplink">';
        if (has_capability('mod/klassenbuch:managesubscriptions', $context)) {
            $subsdiv .= "<a title=\"$strallowchoice\" href=\"subscribe.php?id=$klassenbuch->id&amp;force=no\">$strallowchoice</a>";
        }
        $subsdiv .= '</span><br />';
    } else if ($klassenbuch->forcesubscribe == KLASSENBUCH_DISALLOWSUBSCRIBE) {
        $strsubscriptionsoff = get_string('disallowsubscribe', 'klassenbuch');
        $subsdiv .= $strsubscriptionsoff;
    } else {
        $streveryonecannowchoose = get_string("everyonecannowchoose", "klassenbuch");
        $strforcesubscribe = get_string("forcesubscribe", "klassenbuch");
        $strshowsubscribers = get_string("showsubscribers", "klassenbuch");
        $subsdiv .= '<span class="helplink">'.get_string("allowsallsubscribe", 'klassenbuch').'</span><br />';
        $subsdiv .= '&nbsp;';

        if (has_capability('mod/klassenbuch:managesubscriptions', $context)) {
            $subsdiv .= "<span class=\"helplink\"><a title=\"$strforcesubscribe\" href=".
                "\"subscribe.php?id=$klassenbuch->id&amp;force=yes\">$strforcesubscribe</a></span>";
        }

        if (has_capability('mod/klassenbuch:viewsubscribers', $context)) {
            $subsdiv .= "<br />";
            $subsdiv .= "<span class=\"helplink\"><a href=\"subscribers.php?id=$klassenbuch->id\">$strshowsubscribers</a></span>";
        }

        $subsdiv .= '<div class="helplink" id="subscriptionlink">'.klassenbuch_get_subscribe_link($klassenbuch, $context,
                                                                                                  array(
                                                                                                       'forcesubscribed' => '',
                                                                                                       'cantsubscribe' => ''
                                                                                                  )).'</div>';
    }
}

$subsdiv .= '</div>';

// Prepare chapter navigation icons.
$previd = null;
$nextid = null;
$last = null;
foreach ($chapters as $ch) {
    if (!$edit and $ch->hidden) {
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
    $chnavigation .= '<a title="'.get_string('navprev', 'klassenbuch').'" href="view.php?id='.$cm->id.'&amp;chapterid='.$previd.'">
    <img src="'.$OUTPUT->pix_url('nav_prev', 'mod_klassenbuch').'" class="bigicon" alt="'.get_string('navprev', 'klassenbuch').'"/>
    </a>';
} else {
    $chnavigation .= '<img src="'.$OUTPUT->pix_url('nav_prev_dis', 'mod_klassenbuch').'" class="bigicon" alt="" />';
}
if ($nextid) {
    $chnavigation .= '<a title="'.get_string('navnext', 'klassenbuch').'" href="view.php?id='.$cm->id.'&amp;chapterid='.$nextid.'">
    <img src="'.$OUTPUT->pix_url('nav_next', 'mod_klassenbuch').'" class="bigicon" alt="'.get_string('navnext', 'klassenbuch').'" />
    </a>';
} else {
    $sec = '';
    if ($section = $DB->get_record('course_sections', array('id' => $cm->section))) {
        $sec = $section->section;
    }
    if ($course->id == $SITE->id) {
        $returnurl = "$CFG->wwwroot/";
    } else {
        $returnurl = "$CFG->wwwroot/course/view.php?id=$course->id#section-$sec";
    }
    $chnavigation .= '<a title="'.get_string('navexit', 'klassenbuch').'" href="'.$returnurl.'">
    <img src="'.$OUTPUT->pix_url('nav_exit', 'mod_klassenbuch').'" class="bigicon" alt="'.get_string('navexit', 'klassenbuch').'" />
    </a>';

    // We are cheating a bit here, viewing the last page means user has viewed the whole klassenbuch.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

// Klassenbuch display HTML code.

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($klassenbuch->name));

echo $subsdiv;

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
    // SYNERGY LEARNING - conditionally display class plan link.
        if ($klassenbuch->showclassplan && has_capability('klassenbuchtool/lernschritte:view', $context)) {
        $link = new moodle_url('/mod/klassenbuch/classplan.php', array('id' => $context->instanceid, 'chapterid' => $chapter->id));
        $icon = new pix_icon('i/calendar',
                            get_string('viewclassplan', 'mod_klassenbuch'));
        echo $OUTPUT->action_icon($link, $icon);
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

