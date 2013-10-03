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
 * Send email notification of pages.
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
global $CFG, $PAGE, $DB, $OUTPUT, $USER;
require_once($CFG->dirroot.'/mod/klassenbuch/lib.php');

$cmid = required_param('id', PARAM_INT); // Klassenbuch Course Module ID.
$chapterid = required_param('chapterid', PARAM_INT); // Chapter ID.
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('klassenbuch', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

$PAGE->set_url('/mod/klassenbuch/send.php', array('id' => $cmid, 'chapterid' => $chapterid));
$PAGE->set_pagelayout('admin');

require_login($course, false, $cm);

$PAGE->set_title(format_string($klassenbuch->name));
$PAGE->add_body_class('mod_klassenbuch');
$PAGE->set_heading(format_string($course->fullname));

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:edit', $context);

require_sesskey();

echo $OUTPUT->header();

// Status arrays.
$mailcount = 0;
$errorcount = 0;

$subscribedusers = array();

$chapter = $DB->get_record('klassenbuch_chapters', array('id' => $chapterid), '*', MUST_EXIST);
if ($chapter->klassenbuchid != $klassenbuch->id) {
    print_error('wrongchapterid', 'klassenbuch');
}

$subscribedusers = klassenbuch_subscribed_users($course, $klassenbuch, 0, $context);

$urlinfo = parse_url($CFG->wwwroot);
$hostname = $urlinfo['host'];
$replytouser = get_config('klassenbuch', 'replytouser');

foreach ($subscribedusers as $userto) {

    if ($userto->emailstop) {
        continue; // Skip users with disabled email.
    }

    @set_time_limit(120); // Terminate if processing of any account takes longer than 2 minutes.

    // Set this so that the capabilities are cached, and environment matches receiving user
    // Make sure we're allowed to see it...
    if (!has_capability('mod/klassenbuch:read', $context, $userto)) {
        continue;
    }

    $viewfullnames = has_capability('moodle/site:viewfullnames', $context);

    // Prepare to actually send the post now, and build up the content.

    $cleanklassenbuchname = str_replace('"', "'", strip_tags(format_string($klassenbuch->name)));

    $userfrom = $USER;

    $userfrom->customheaders = array( // Headers to make emails easier to track.
        'Precedence: Bulk',
        'List-Id: "'.$cleanklassenbuchname.'" <moodleklassenbuch'.$klassenbuch->id.'@'.$hostname.'>',
        'List-Help: '.$CFG->wwwroot.'/mod/klassenbuch/view.php?id='.$cm->id,
        'Message-ID: <moodleklassenbuchchapter'.$chapter->id.'@'.$hostname.'>',
        'X-Course-Id: '.$course->id,
        'X-Course-Name: '.format_string($course->fullname, true)
    );

    $postsubject = "$course->shortname: ".format_string($chapter->title, true);
    $posttext = klassenbuch_make_mail_text($course, $klassenbuch, $chapter, $userfrom, $userto);
    $posthtml = klassenbuch_make_mail_html($course, $klassenbuch, $chapter, $userfrom, $userto);

    // Send the post now!
    if (!$mailresult = email_to_user($userto, $userfrom, $postsubject, $posttext,
                                     $posthtml, '', '', $replytouser)
    ) {
        add_to_log($course->id, 'klassenbuch', 'mail error', "view.php?id={$klassenbuch->id}&chapterid={$chapter->id}",
                   substr(format_string($chapter->subject, true), 0, 30), $cm->id, $userto->id);
        $errorcount++;
    } else if ($mailresult !== 'emailstop') {
        $mailcount++;
    }
}

notice($mailcount." Teilnehmer erhielten $chapter->id, '$chapter->title'", new moodle_url('/mod/klassenbuch/view.php',
                                                                                          array( 'id' => $cm->id,
                                                                                               'chapterid' => $chapter->id)));

echo $OUTPUT->footer();
