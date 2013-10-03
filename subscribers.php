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
 * List current subscribers to a Klassenbuch
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__)."/../../config.php");
global $CFG, $DB, $PAGE, $USER, $OUTPUT;
require_once($CFG->dirroot.'/mod/klassenbuch/lib.php');

$klassenbuchid = required_param('id', PARAM_INT); // Klassenbuch Course Module ID.
$group = optional_param('group', 0, PARAM_INT);
$edit = optional_param('edit', -1, PARAM_BOOL);

$klassenbuch = $DB->get_record('klassenbuch', array('id' => $klassenbuchid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $klassenbuch->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('klassenbuch', $klassenbuch->id, $course->id, false, MUST_EXIST);

$url = new moodle_url('/mod/klassenbuch/subscribers.php', array('id' => $klassenbuch->id));
if ($group) {
    $url->param('group', $group);
}
$PAGE->set_url($url);

require_login($course->id, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:viewsubscribers', $context);

if (has_capability('mod/klassenbuch:managesubscriptions', $context)) {
    if ($edit != -1) {
        require_sesskey();
        $USER->subscriptionsediting = $edit;
    }
} else {
    unset($USER->subscriptionsediting);
}

echo $OUTPUT->header();

$url->remove_params('group');
groups_print_activity_menu($cm, $url);
$currentgroup = groups_get_activity_group($cm);
$groupmode = groups_get_activity_groupmode($cm);

if (empty($USER->subscriptionsediting)) { // Display an overview of subscribers.
    if (!$users = klassenbuch_subscribed_users($course, $klassenbuch, $currentgroup)) {
        echo $OUTPUT->heading(get_string("nosubscribers", "klassenbuch"));

    } else {
        echo $OUTPUT->heading(get_string("subscribersto", "klassenbuch", "'".format_string($klassenbuch->name)."'"));

        echo '<table align="center" cellpadding="5" cellspacing="5">';
        foreach ($users as $user) {
            echo '<tr><td>';
            echo $OUTPUT->user_picture($user);
            echo '</td><td>';
            echo fullname($user);
            echo '</td><td>';
            echo $user->email;
            echo '</td></tr>';
        }
        echo "</table>";
    }

    echo $OUTPUT->footer($course);
    exit;
}

// We are in editing mode.

$strexistingsubscribers = get_string("existingsubscribers", 'klassenbuch');
$strpotentialsubscribers = get_string("potentialsubscribers", 'klassenbuch');
$straddsubscriber = get_string("addsubscriber", 'klassenbuch');
$strremovesubscriber = get_string("removesubscriber", 'klassenbuch');
$strsearch = get_string("search");
$strsearchresults = get_string("searchresults");
$strshowall = get_string("showall", 'klassenbuch');
$strsubscribers = get_string("subscribers", "klassenbuch");
$strklassenbuchs = get_string("klassenbuchs", "klassenbuch");

if ($frm = data_submitted()) {
    // A form was submitted so process the input.
    if (!empty($frm->add) and !empty($frm->addselect)) {

        foreach ($frm->addselect as $addsubscriber) {
            if (!klassenbuch_subscribe($addsubscriber, $klassenbuch->id)) {
                error("Could not add subscriber with id $addsubscriber to this klassenbuch!");
            }
        }
    } else if (!empty($frm->remove) and !empty($frm->removeselect)) {
        foreach ($frm->removeselect as $removesubscriber) {
            if (!klassenbuch_unsubscribe($removesubscriber, $klassenbuch->id)) {
                error("Could not remove subscriber with id $removesubscriber from this klassenbuch!");
            }
        }
    } else if (!empty($frm->showall)) {
        unset($frm->searchtext);
        $frm->previoussearch = 0;
    }
}

$previoussearch = (!empty($frm->search) or (!empty($frm->previoussearch) && $frm->previoussearch == 1));

// Get all existing subscribers for this klassenbuch.
if (!$subscribers = klassenbuch_subscribed_users($course, $klassenbuch, $currentgroup)) {
    $subscribers = array();
}

$subscriberarray = array();
foreach ($subscribers as $subscriber) {
    $subscriberarray[] = $subscriber->id;
}

// Get search results excluding any users already subscribed.

if (!empty($frm->searchtext) and $previoussearch) {
    $searchusers = search_users($course->id, $currentgroup, $frm->searchtext, 'firstname ASC, lastname ASC', $subscriberarray);
}

// If no search results then get potential subscribers for this klassenbuch excluding users already subscribed.
if (empty($searchusers)) {
    $users = get_enrolled_users($context, 'mod/klassenbuch:read', $currentgroup, 'u.*', 'firstname ASC, lastname ASC');
    foreach ($users as $user) {
        if (in_array($user->id, $subscriberarray)) {
            unset($users[$user->id]);
        }
    }
}

$searchtext = (isset($frm->searchtext)) ? $frm->searchtext : "";
$previoussearch = ($previoussearch) ? '1' : '0';

echo $OUTPUT->box_start('center');

require('subscriber.html');

echo $OUTPUT->box_end();

echo $OUTPUT->footer($course);

