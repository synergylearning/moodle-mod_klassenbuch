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
 * Klassenbuch printing
 *
 * @package    klassenbuchtool
 * @subpackage print
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;

$id = required_param('id', PARAM_INT); // Course Module ID.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.

// Security checks START - teachers and students view.

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:read', $context);
require_capability('klassenbuchtool/print:print', $context);

// Check all variables.
if ($chapterid) {
    // Single chapter printing - only visible!
    $chapter = $DB->get_record('klassenbuch_chapters', array(
                                                            'id' => $chapterid, 'klassenbuchid' => $klassenbuch->id
                                                       ), '*', MUST_EXIST);
} else {
    // Complete klassenbuch.
    $chapter = false;
}

$PAGE->set_url('/mod/klassenbuch/print.php', array('id' => $id, 'chapterid' => $chapterid));

unset($id);
unset($chapterid);

// Security checks END.

// Read chapters.
$chapters = klassenbuch_preload_chapters($klassenbuch);

$strklassenbuchs = get_string('modulenameplural', 'mod_klassenbuch');
$strklassenbuch = get_string('modulename', 'mod_klassenbuch');
$strtop = get_string('top', 'mod_klassenbuch');

@header('Cache-Control: private, pre-check=0, post-check=0, max-age=0');
@header('Pragma: no-cache');
@header('Expires: ');
@header('Accept-Ranges: none');
@header('Content-type: text/html; charset=utf-8');

if ($chapter) {

    if ($chapter->hidden) {
        require_capability('mod/klassenbuch:viewhiddenchapters', $context);
    }

    add_to_log($course->id, 'klassenbuch', 'print', 'tool/print/index.php?id='.$cm->id.'&chapterid='.$chapter->id,
               $klassenbuch->id, $cm->id);

    // Page header.
    ?>
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
    <html>
    <head>
        <title><?php echo format_string($klassenbuch->name, true, array('context' => $context)) ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <meta name="description" content="<?php echo s(format_string($klassenbuch->name, true, array('context' => $context))) ?>"/>
        <link rel="stylesheet" type="text/css" href="print.css"/>
    </head>
    <body onload="window.print()">
    <a name="top"></a>
    <div class="chapter">
    <?php


    if (!$klassenbuch->customtitles) {
        if (!$chapter->subchapter) {
            $currtitle = klassenbuch_get_chapter_title($chapter->id, $chapters, $klassenbuch, $context);
            echo '<p class="klassenbuch_chapter_title">'.$currtitle.'</p>';
        } else {
            $currtitle = klassenbuch_get_chapter_title($chapters[$chapter->id]->parent, $chapters, $klassenbuch, $context);
            $currsubtitle = klassenbuch_get_chapter_title($chapter->id, $chapters, $klassenbuch, $context);
            echo '<p class="klassenbuch_chapter_title">'.$currtitle.'<br />'.$currsubtitle.'</p>';
        }
    }

    $content = '';
    if (!$chapter->contenthidden) {
        $content .= $chapter->content;
        $content = file_rewrite_pluginfile_urls($content, 'pluginfile.php', $context->id, 'mod_klassenbuch',
                                                'chapter', $chapter->id);
        $content = format_text($content, $chapter->contentformat, array('noclean' => true, 'context' => $context));
    }

    // Add custom fields.
    $customfields = klassenbuch_get_chapter_customfields($chapter->id);
    foreach ($customfields as $customfield) {
        $chaptertext = file_rewrite_pluginfile_urls($customfield->content, 'pluginfile.php', $context->id,
                                                    'mod_klassenbuch', 'customcontent_'.$customfield->fieldid, $chapter->id);
        if (!empty($chaptertext)) {
            $content .= $OUTPUT->heading(s($customfield->title), 3);
            $content .= format_text($chaptertext, $customfield->contentformat, array('noclean' => true, 'context' => $context));
        }
    }

    $content .= klassenbuch_output_attachments($chapter->id, $context);
    echo $content;
    echo '</div>';
    echo '</body> </html>';

} else {
    add_to_log($course->id, 'klassenbuch', 'print', 'tool/print/index.php?id='.$cm->id, $klassenbuch->id, $cm->id);
    $allchapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id), 'pagenum');

    // Page header.
    ?>
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
    <html>
    <head>
        <title><?php echo format_string($klassenbuch->name, true, array('context' => $context)) ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <meta name="description"
              content="<?php echo s(format_string($klassenbuch->name, true, array('noclean' => true, 'context' => $context))) ?>"/>
        <link rel="stylesheet" type="text/css" href="print.css"/>
    </head>
<body onload="window.print()">
<a name="top"></a>

<p class="klassenbuch_title"><?php echo format_string($klassenbuch->name, true, array('context' => $context)) ?></p>

<p class="klassenbuch_summary"><?php echo format_text($klassenbuch->intro, $klassenbuch->introformat, array(
                                                                                                           'noclean' => true,
                                                                                                           'context' => $context
                                                                                                      )) ?></p>

<div class="klassenbuch_info">
    <table>
        <tr>
            <td><?php echo get_string('site') ?>:</td>
            <td>
                <a href="<?php echo $CFG->wwwroot ?>"><?php echo format_string($SITE->fullname, true,
                                                                               array('context' => $context)) ?></a>
            </td>
        </tr>
        <tr>
            <td><?php echo get_string('course') ?>:</td>
            <td><?php echo format_string($course->fullname, true, array('context' => $context)) ?></td>
        </tr>
        <tr>
            <td><?php echo get_string('modulename', 'mod_klassenbuch') ?>:</td>
            <td><?php echo format_string($klassenbuch->name, true, array('context' => $context)) ?></td>
        </tr>
        <tr>
            <td><?php echo get_string('printedby', 'klassenbuchtool_print') ?>:</td>
            <td><?php echo fullname($USER, true) ?></td>
        </tr>
        <tr>
            <td><?php echo get_string('printdate', 'klassenbuchtool_print') ?>:</td>
            <td><?php echo userdate(time()) ?></td>
        </tr>
    </table>
</div>

    <?php
    list($toc, $titles) = klassenbuchtool_print_get_toc($chapters, $klassenbuch, $cm);
    echo $toc;
    // Chapters.
    $link1 = $CFG->wwwroot.'/mod/klassenbuch/view.php?id='.$course->id.'&chapterid=';
    $link2 = $CFG->wwwroot.'/mod/klassenbuch/view.php?id='.$course->id;
    foreach ($chapters as $ch) {
        $chapter = $allchapters[$ch->id];
        if ($chapter->hidden) {
            continue;
        }
        echo '<div class="klassenbuch_chapter"><a name="ch'.$ch->id.'"></a>';
        if (!$klassenbuch->customtitles) {
            echo '<p class="klassenbuch_chapter_title">'.$titles[$ch->id].'</p>';
        }

        $content = '';
        if (!$chapter->contenthidden) {
            $content .= $chapter->content;
            $content = file_rewrite_pluginfile_urls($content, 'pluginfile.php', $context->id, 'mod_klassenbuch',
                                                    'chapter', $ch->id);
            $content = format_text($content, $chapter->contentformat, array('noclean' => true, 'context' => $context));
        }

        // Add custom fields.
        $customfields = klassenbuch_get_chapter_customfields($ch->id);
        foreach ($customfields as $customfield) {
            $chaptertext = file_rewrite_pluginfile_urls($customfield->content, 'pluginfile.php', $context->id,
                                                        'mod_klassenbuch', 'customcontent_'.$customfield->fieldid, $chapter->id);
            if (!empty($chaptertext)) {
                $content .= $OUTPUT->heading(s($customfield->title), 3);
                $content .= format_text($chaptertext, $customfield->contentformat, array('noclean' => true, 'context' => $context));
            }
        }

        $content .= klassenbuch_output_attachments($chapter->id, $context);

        $content = str_replace($link1, '#ch', $content);
        $content = str_replace($link2, '#top', $content);

        echo $content;
        echo '</div>';
    }
    echo '</body> </html>';
}

