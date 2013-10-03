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
 * Subscribe to a Klassenbuch (to receive emails when the teacher clicks send)
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__)."/../../config.php");
global $CFG, $DB, $PAGE, $USER, $OUTPUT;
require_once($CFG->dirroot.'/mod/klassenbuch/lib.php');

$klassenbuchid = required_param('id', PARAM_INT); // Klassenbuch Course Module ID.
$force = optional_param('force', '', PARAM_ALPHA); // Force everyone to be subscribed to this klassenbuch?
$userid = optional_param('user', 0, PARAM_INT);

$klassenbuch = $DB->get_record('klassenbuch', array('id' => $klassenbuchid), '*', MUST_EXIST);
if (!isset($klassenbuch->forcesubscribe)) {
    $klassenbuch->forcesubscribe = 1;
}

$course = $DB->get_record('course', array('id' => $klassenbuch->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('klassenbuch', $klassenbuch->id, $course->id, false, MUST_EXIST);

$url = new moodle_url('/mod/klassenbuch/subscribe.php', array('id' => $klassenbuch->id));
if ($force) {
    $url->param('force', $force);
}
if ($userid) {
    $url->param('user', $userid);
}
$PAGE->set_url($url);

require_login($course->id, false, $cm);

$context = context_module::instance($cm->id);
if ($userid) {
    require_capability('mod/klassenbuch:managesubscriptions', $context);
    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
} else {
    $user = $USER;
}

if (groupmode($course, $cm)
    and !klassenbuch_is_subscribed($user->id, $klassenbuch->id)
    and !has_capability('moodle/site:accessallgroups', $context)
) {
    if (!mygroupid($course->id)) {
        error('Sorry, but you must be a group member to subscribe.');
    }
}

if (empty($force) && !is_enrolled($context, $USER, '', true)) { // Guests and visitors can't subscribe - only enrolled.
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    if (isguestuser()) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('subscribeenrolledonly', 'klassenbuch').'<br /><br />'.get_string('liketologin'),
                              get_login_url(), new moodle_url('/mod/klassenbuch/view.php', array('id' => $cm->id)));
        echo $OUTPUT->footer();
        exit;
    } else {
        // There should not be any links leading to this place, just redirect.
        redirect(new moodle_url('/mod/klassenbuch/view.php', array('id' => $cm->id)),
                 get_string('subscribeenrolledonly', 'klassenbuch'));
    }
}

$returnto = optional_param('backtoindex', 0, PARAM_INT)
    ? "index.php?id=".$course->id
    : "view.php?id=$cm->id";

if ($force and has_capability('mod/klassenbuch:managesubscriptions', $context)) {
    if (klassenbuch_is_forcesubscribed($klassenbuch->id)) {
        klassenbuch_forcesubscribe($klassenbuch->id, 0);
        redirect($returnto, get_string("everyonecannowchoose", "klassenbuch"), 2);
    } else {
        klassenbuch_forcesubscribe($klassenbuch->id, 1);
        redirect($returnto, get_string("everyoneisnowsubscribed", "klassenbuch"), 2);
    }
}

if (klassenbuch_is_forcesubscribed($klassenbuch->id)) {
    redirect($returnto, get_string("everyoneisnowsubscribed", "klassenbuch"), 2);
}

$info = new stdClass();
$info->name = fullname($user);
$info->book = format_string($klassenbuch->name);

if (klassenbuch_is_subscribed($user->id, $klassenbuch->id)) {
    if (klassenbuch_unsubscribe($user->id, $klassenbuch->id)) {
        add_to_log($course->id, "klassenbuch", "unsubscribe", "view.php?id=$cm->id", $klassenbuch->id, $cm->id);
        redirect($returnto, get_string("nownotsubscribed", "klassenbuch", $info), 1);
    } else {
        error("Could not unsubscribe you from that klassenbuch", $_SERVER["HTTP_REFERER"]);
    }

} else { // Subscribe.
    if ($klassenbuch->forcesubscribe == KLASSENBUCH_DISALLOWSUBSCRIBE &&
        !has_capability('mod/klassenbuch:managesubscriptions', $context)
    ) {
        error(get_string('disallowsubscribe', 'klassenbuch'), $_SERVER["HTTP_REFERER"]);
    }
    if (!has_capability('mod/klassenbuch:read', $context)) {
        error("Could not subscribe you to that klassenbuch", $_SERVER["HTTP_REFERER"]);
    }
    if (klassenbuch_subscribe($user->id, $klassenbuch->id)) {
        add_to_log($course->id, "klassenbuch", "subscribe", "view.php?id=$cm->id", $klassenbuch->id, $cm->id);
        redirect($returnto, get_string("nowsubscribed", "klassenbuch", $info), 2);
    } else {
        error("Could not subscribe you to that klassenbuch", $_SERVER["HTTP_REFERER"]);
    }
}
