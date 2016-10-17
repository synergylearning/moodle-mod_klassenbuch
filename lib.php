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
 * Klassenbuch module core interaction API
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

// SYNERGY - add extra subscription definitions.
define('KLASSENBUCH_DISALLOWSUBSCRIBE', 3);
define('KLASSENBUCH_TRACKING_OPTIONAL', 1);
define('KLASSENBUCH_TRACKING_ON', 2);
define('KLASSENBUCH_FORCESUBSCRIBE', 1); // No idea why the '1' values are duplicated (copying from old site).
// SYNERGY - add extra subscription definitions.

/**
 * Returns list of available numbering types
 * @return array
 */
function klassenbuch_get_numbering_types() {
    require_once(dirname(__FILE__) . '/locallib.php');

    return array(
        KLASSENBUCH_NUM_NONE => get_string('numbering0', 'mod_klassenbuch'),
        KLASSENBUCH_NUM_NUMBERS => get_string('numbering1', 'mod_klassenbuch'),
        KLASSENBUCH_NUM_BULLETS => get_string('numbering2', 'mod_klassenbuch'),
        KLASSENBUCH_NUM_INDENTED => get_string('numbering3', 'mod_klassenbuch')
    );
}

/**
 * Returns all other caps used in module
 * @return array
 */
function klassenbuch_get_extra_capabilities() {
    // Used for group-members-only.
    return array('moodle/site:accessallgroups');
}

/**
 * Add klassenbuch instance.
 *
 * @param stdClass $data
 * @param stdClass $mform
 * @return int new klassenbuch instance id
 */
function klassenbuch_add_instance($data, $mform) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    if (!isset($data->customtitles)) {
        $data->customtitles = 0;
    }

    return $DB->insert_record('klassenbuch', $data);
}

/**
 * Update klassenbuch instance.
 *
 * @param stdClass $data
 * @param stdClass $mform
 * @return bool true
 */
function klassenbuch_update_instance($data, $mform) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    if (!isset($data->customtitles)) {
        $data->customtitles = 0;
    }

    // SYNERGY LEARNING - add 'show classplan' checkbox START.
    if (!isset($data->showclassplan)) {
        $data->showclassplan = 0;
    }
    // SYNERGY LEARNING - add 'show classplan' checkbox END.

    $DB->update_record('klassenbuch', $data);

    $klassenbuch = $DB->get_record('klassenbuch', array('id' => $data->id));
    $DB->set_field('klassenbuch', 'revision', $klassenbuch->revision + 1, array('id' => $klassenbuch->id));

    return true;
}

/**
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id
 * @return bool success
 */
function klassenbuch_delete_instance($id) {
    global $DB;

    if (!$klassenbuch = $DB->get_record('klassenbuch', array('id' => $id))) {
        return false;
    }

    // ... give plugins a chance to clean up some things before the module is deleted.
    $plugins = get_plugin_list('klassenbuchtool');
    foreach ($plugins as $plugin => $dir) {
        if (file_exists("$dir/lib.php")) {
            require_once("$dir/lib.php");
        }
        $function = 'klassenbuchtool_' . $plugin . '_delete_instance';
        if (function_exists($function)) {
            $function($klassenbuch);
        }
    }

    $DB->delete_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id));
    $DB->delete_records('klassenbuch', array('id' => $klassenbuch->id));

    return true;
}

/**
 * Return use outline
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param object $klassenbuch
 * @return object|null
 */
function klassenbuch_user_outline($course, $user, $mod, $klassenbuch) {
    global $DB;

    if ($logs = $DB->get_records('log', array(
        'userid' => $user->id, 'module' => 'klassenbuch',
        'action' => 'view', 'info' => $klassenbuch->id
            ), 'time ASC')
    ) {

        $numviews = count($logs);
        $lastlog = array_pop($logs);

        $result = new stdClass();
        $result->info = get_string('numviews', '', $numviews);
        $result->time = $lastlog->time;

        return $result;
    }
    return null;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param $course
 * @param $user
 * @param $mod
 * @param $klassenbuch
 * @return bool
 */
function klassenbuch_user_complete($course, $user, $mod, $klassenbuch) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in klassenbuch activities and print it out.
 * Return true if there was output, or false is there was none.
 * @param $course
 * @param $isteacher
 * @param $timestart
 * @return bool
 */
function klassenbuch_print_recent_activity($course, $isteacher, $timestart) {
    return false; // True if anything was printed, otherwise false.
}

/**
 * No cron in klassenbuch.
 *
 * @return bool
 */
function klassenbuch_cron() {
    return true;
}

/**
 * No grading in klassenbuch.
 *
 * @param $klassenbuchid
 * @return null
 */
function klassenbuch_grades($klassenbuchid) {
    return null;
}

function klassenbuch_get_participants($klassenbuchid) {
    // Must return an array of user records (all data) who are participants
    // for a given instance of klassenbuch. Must include every user involved
    // in the instance, independent of his role (student, teacher, admin...)
    // See other modules as example.

    return false;
}

/**
 * This function returns if a scale is being used by one klassenbuch
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See klassenbuch, glossary or journal modules
 * as reference.
 *
 * @param $klassenbuchid int
 * @param $scaleid int
 * @return boolean True if the scale is used by any journal
 */
function klassenbuch_scale_used($klassenbuchid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of klassenbuch
 *
 * This is used to find out if scale used anywhere
 *
 * @param $scaleid int
 * @return boolean True if the scale is used by any journal
 */
function klassenbuch_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Return read actions.
 * @return array
 */
function klassenbuch_get_view_actions() {
    $return = array('view', 'view all');

    $plugins = core_component::get_plugin_list('klassenbuchtool');
    foreach ($plugins as $plugin => $dir) {
        if (file_exists("$dir/lib.php")) {
            require_once("$dir/lib.php");
        }
        $function = 'klassenbuchtool_' . $plugin . '_get_view_actions';
        if (function_exists($function)) {
            if ($actions = $function()) {
                $return = array_merge($return, $actions);
            }
        }
    }

    return $return;
}

/**
 * Return write actions.
 * @return array
 */
function klassenbuch_get_post_actions() {
    $return = array('update');

    $plugins = core_component::get_plugin_list('klassenbuchtool');
    foreach ($plugins as $plugin => $dir) {
        if (file_exists("$dir/lib.php")) {
            require_once("$dir/lib.php");
        }
        $function = 'klassenbuchtool_' . $plugin . '_get_post_actions';
        if (function_exists($function)) {
            if ($actions = $function()) {
                $return = array_merge($return, $actions);
            }
        }
    }

    return $return;
}

/**
 * Supported features
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function klassenbuch_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;

        default:
            return null;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settingsnav The settings navigation object
 * @param navigation_node $klassenbuchnode The node to add module settings to
 * @return void
 */
function klassenbuch_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $klassenbuchnode) {
    global $USER, $PAGE, $OUTPUT;

    if ($PAGE->cm->modname !== 'klassenbuch') {
        return;
    }

    $plugins = core_component::get_plugin_list('klassenbuchtool');
    foreach ($plugins as $plugin => $dir) {
        if (file_exists("$dir/lib.php")) {
            require_once("$dir/lib.php");
        }
        $function = 'klassenbuchtool_' . $plugin . '_extend_settings_navigation';
        if (function_exists($function)) {
            $function($settingsnav, $klassenbuchnode);
        }
    }

    // Add PDF link.
    $url = new moodle_url('/mod/klassenbuch/tool/print/pdfklassenbuch.php', array('id' => $PAGE->cm->id, 'sesskey' => sesskey()));
    $action = new action_link($url, get_string('exportpdf', 'mod_klassenbuch'), new popup_action('click', $url));
    // Open as new window.
    $klassenbuchnode->add(get_string('exportpdf', 'mod_klassenbuch'), $action, navigation_node::TYPE_SETTING,
                            null, null, new pix_icon('pdf', '', 'klassenbuchtool_print', array('class' => 'icon')));

    $params = $PAGE->url->params();
    if (!empty($params['id']) and !empty($params['chapterid']) and
            has_capability('mod/klassenbuch:edit', context_module::instance($PAGE->cm->id))
    ) {
        if (!empty($USER->editing)) {
            $string = get_string("turneditingoff");
            $edit = '0';
        } else {
            $string = get_string("turneditingon");
            $edit = '1';
        }
        $url = new moodle_url('/mod/klassenbuch/view.php', array(
                    'id' => $params['id'], 'chapterid' => $params['chapterid'],
                    'edit' => $edit, 'sesskey' => sesskey()
                ));
        $klassenbuchnode->add($string, $url, navigation_node::TYPE_SETTING);
        $PAGE->set_button($OUTPUT->single_button($url, $string) . ' ' . $PAGE->button);
    }

    // SYNERGY - add 'edit subscribers' link.
    if (substr($PAGE->url->out_omit_querystring(), -15) == 'subscribers.php') {
        if (has_capability('mod/klassenbuch:managesubscriptions', context_module::instance($PAGE->cm->id))) {
            $url = new moodle_url($PAGE->url);
            if (isset($USER->subscriptionsediting) && $USER->subscriptionsediting) {
                $url->param('edit', 0);
                $string = get_string('editsubscribersoff', 'klassenbuch');
            } else {
                $url->param('edit', 1);
                $string = get_string('editsubscriberson', 'klassenbuch');
            }
            $url->param('sesskey', sesskey());
            $klassenbuchnode->add($string, $url, navigation_node::TYPE_SETTING);
        }
    }
    // SYNERGY - add 'edit subscribers' link.
}

/**
 * Lists all browsable file areas
 * @param object $course
 * @param object $cm
 * @param object $context
 * @return array
 */
function klassenbuch_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['chapter'] = get_string('chapters', 'mod_klassenbuch');
    $areas['attachment'] = get_string('attachments', 'mod_klassenbuch');
    return array_merge($areas, klassenbuch_get_custom_file_areas());
}

function klassenbuch_get_custom_file_areas() {
    global $DB;
    static $customareas = null;
    if (is_null($customareas)) {
        $customfields = $DB->get_records('klassenbuch_globalfields', array(), 'id', 'id, title');
        foreach ($customfields as $customfield) {
            $customareas['customcontent_' . $customfield->id] = $customfield->title;
        }
    }

    return $customareas;
}

/**
 * File browsing support for klassenbuch module ontent area.
 * @param file_browser $browser
 * @param object $areas
 * @param object $course
 * @param object $cm
 * @param context $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return object file_info instance or null if not found
 */
function klassenbuch_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB;

    // Note: 'intro' area is handled in file_browser automatically.

    if (!has_capability('mod/klassenbuch:read', $context)) {
        return null;
    }

    $customareas = klassenbuch_get_custom_file_areas();
    if ($filearea !== 'chapter' && $filearea !== 'attachment' && !array_key_exists($filearea, $customareas)) {
        return null;
    }

    require_once("$CFG->dirroot/mod/klassenbuch/locallib.php");

    if (is_null($itemid)) {
        return new klassenbuch_file_info($browser, $course, $cm, $context, $areas, $filearea, $itemid);
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!$storedfile = $fs->get_file($context->id, 'mod_klassenbuch', $filearea, $itemid, $filepath, $filename)) {
        return null;
    }

    // Modifications may be tricky - may cause caching problems.
    $canwrite = has_capability('mod/klassenbuch:edit', $context);

    $chaptername = $DB->get_field('klassenbuch_chapters', 'title', array('klassenbuchid' => $cm->instance, 'id' => $itemid));
    $chaptername = format_string($chaptername, true, array('context' => $context));

    $urlbase = $CFG->wwwroot . '/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $chaptername, true, true, $canwrite, false);
}

/**
 * Serves the klassenbuch attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - just send the file
 */
function klassenbuch_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login();

    if ($filearea !== 'chapter' && $filearea !== 'attachment') {
        // Check to see if this is a customfield area.
        $expl = explode('_', $filearea);
        if (count($expl) == 2 && $expl[0] == 'customcontent') {
            $fieldid = intval($expl[1]);
            if (!$DB->record_exists('klassenbuch_globalfields', array('id' => $fieldid))) {
                return false;
            }
        } else {
            return false;
        }
    }

    if (!has_capability('mod/klassenbuch:read', $context)) {
        // Check for parent access to the file.
        $childids = \block_gimychildren\local\gimychildren::get_mychildrenids();
        if (!$childids) {
            return false;
        }
        $users = get_users_by_capability($context, 'mod/klassenbuch:read', 'u.id');
        $childwithaccessids = array_intersect($childids, array_keys($users));
        if (!$childwithaccessids) {
            return false;
        }
    }

    $chid = (int) array_shift($args);

    if (!$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance))) {
        return false;
    }

    if (!$chapter = $DB->get_record('klassenbuch_chapters', array('id' => $chid, 'klassenbuchid' => $klassenbuch->id))) {
        return false;
    }

    if ($chapter->hidden and !has_capability('mod/klassenbuch:viewhiddenchapters', $context)) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_klassenbuch/$filearea/$chid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Finally send the file.
    send_stored_file($file, 360, 0, $forcedownload);
}

/**
 * Return a list of page types
 *
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function klassenbuch_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array('mod-klassenbuch-*' => get_string('page-mod-klassenbuch-x', 'mod_klassenbuch'));
    return $modulepagetype;
}

// SYNERGY - adding extra functions at the end.

function klassenbuch_subscribed_users($course, $book, $groupid = 0, $context = null) {
    global $DB;

    $params = array();
    if ($groupid) {
        $grouptables = ", {groups_members} gm ";
        $groupselect = "AND gm.groupid = :groupid AND u.id = gm.userid";
        $params['groupid'] = $groupid;
    } else {
        $grouptables = '';
        $groupselect = '';
    }

    $namefields = get_all_user_name_fields(true, 'u');
    if (klassenbuch_is_forcesubscribed($book)) {
        if (empty($context)) {
            $cm = get_coursemodule_from_instance('klassenbuch', $book->id, $course->id);
            $context = context_module::instance($cm->id);
        }
        $sort = "u.email ASC";
        $fields = "u.id, u.username, {$namefields}, u.maildisplay, u.mailformat, u.maildigest, u.emailstop, u.imagealt,
                    u.email, u.city, u.country, u.lastaccess, u.lastlogin, u.picture, u.timezone, u.theme, u.lang, u.trackforums,
                     u.mnethostid";
        $results = klassenbuch_get_potential_subscribers($context, $groupid, $fields, $sort);
    } else {
        $params['bookid'] = $book->id;
        $results = $DB->get_records_sql("
             SELECT u.id, u.username, {$namefields}, u.maildisplay, u.mailformat, u.maildigest, u.emailstop, u.imagealt,
                    u.email, u.city, u.country, u.lastaccess, u.lastlogin, u.picture, u.timezone, u.theme, u.lang, u.trackforums,
                    u.mnethostid
               FROM {user} u,
                    {klassenbuch_subscriptions} s $grouptables
              WHERE s.klassenbuch = :bookid
                AND s.userid = u.id
                AND u.deleted = 0  $groupselect
              ORDER BY u.email ASC", $params);
    }

    static $guestid = null;

    if (is_null($guestid)) {
        if ($guest = guest_user()) {
            $guestid = $guest->id;
        } else {
            $guestid = 0;
        }
    }

    // Guest user should never be subscribed to a forum.
    unset($results[$guestid]);

    return $results;
}

function klassenbuch_is_forcesubscribed($klassenbuch) {
    global $DB;

    if (isset($klassenbuch->id)) {
        return ($DB->get_field('klassenbuch', 'forcesubscribe', array('id' => $klassenbuch->id)) == KLASSENBUCH_FORCESUBSCRIBE);
    } else {
        return ($DB->get_field('klassenbuch', 'forcesubscribe', array('id' => $klassenbuch)) == KLASSENBUCH_FORCESUBSCRIBE);
    }
}

function klassenbuch_make_mail_text($course, $book, $chapter, $userfrom, $userto, $bare = false) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/klassenbuch/locallib.php');

    if (!$cm = get_coursemodule_from_instance('klassenbuch', $book->id, $course->id)) {
        print_error('incorrectcourseid', 'klassenbuch');
    }
    $modcontext = context_module::instance($cm->id);

    $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);

    $by = new stdClass();
    $by->name = fullname($userfrom, $viewfullnames);
    $by->date = userdate($chapter->timemodified, "", $userto->timezone);

    $strbynameondate = get_string('bynameondate', 'klassenbuch', $by);

    $strforums = get_string('klassenbuch', 'klassenbuch');

    $canunsubscribe = !klassenbuch_is_forcesubscribed($book);

    $chaptertext = '';
    if (!$bare) {
        $chaptertext = "$course->shortname -> $strforums -> " . format_string($book->name, true);

        if ($chapter->title != $book->name) {
            $chaptertext .= " -> " . format_string($chapter->title, true);
        }
    }

    $chaptertext .= "\n---------------------------------------------------------------------\n";
    $chaptertext .= format_string($chapter->title, true);
    if ($bare) {
        $chaptertext .= " ($CFG->wwwroot/mod/klassenbuch/discuss.php?d=$book->id#p$chapter->id)";
    }
    $chaptertext .= "\n" . $strbynameondate . "\n";
    $chaptertext .= "---------------------------------------------------------------------\n";
    $content = $chapter->content;
    if (empty($content)) {
        // Add custom fields instead.
        $customfields = klassenbuch_get_chapter_customfields($chapter->id);
        foreach ($customfields as $customfield) {
            if (!empty($customfield->content)) {
                $content .= $customfield->title . "\n";
                $content .= format_text($customfield->content, $customfield->contentformat) . "\n";
            }
        }
    }
    $chaptertext .= format_text_email(trusttext_strip($content), FORMAT_MOODLE);
    $chaptertext .= "\n";
    $attachments = klassenbuch_output_attachments($chapter->id, $modcontext, true);
    if ($attachments) {
        $chaptertext .= "\n" . get_string('attachment', 'klassenbuch') . "\n";
        $chaptertext .= $attachments;
    }
    if (!$bare && $canunsubscribe) {
        $chaptertext .= "\n---------------------------------------------------------------------\n";
        $chaptertext .= get_string("unsubscribe", "klassenbuch");
        $chaptertext .= ": $CFG->wwwroot/mod/klassenbuch/subscribe.php?id=$book->id\n";
    }

    return $chaptertext;
}

function klassenbuch_make_mail_html($course, $klassenbuch, $post, $userfrom, $userto) {
    global $CFG, $OUTPUT;

    if (!$cm = get_coursemodule_from_instance('klassenbuch', $klassenbuch->id, $course->id)) {
        print_error('nocoursemodule', 'klassenbuch');
    }

    if ($userto->mailformat != 1) { // Needs to be HTML.
        return '';
    }

    $strklassenbuchs = get_string('klassenbuchs', 'klassenbuch');
    $canunsubscribe = !klassenbuch_is_forcesubscribed($klassenbuch);

    $posthtml = '<head>';
    $posthtml .= '</head>';
    $posthtml .= "\n<body id=\"email\">\n\n";

    $posthtml .= '<div class="navbar">' . "\n" .
            '<a target="_blank" href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '">' . $course->shortname . '</a> &raquo; ' . "\n" .
            '<a target="_blank" href="' . $CFG->wwwroot . '/mod/klassenbuch/index.php?id=' . $course->id . '">' . $strklassenbuchs .
            '</a> &raquo; ' . "\n" .
            '<a target="_blank" href="' . $CFG->wwwroot . '/mod/klassenbuch/view.php?id=' . $cm->id . '">' .
            format_string($klassenbuch->name, true) . '</a> ' . "\n";
    $posthtml .= '</div>';
    $posthtml .= klassenbuch_make_mail_post($course, $klassenbuch, $post, $userfrom, $userto) . "\n";

    $context = context_module::instance($cm->id);
    $attachments = klassenbuch_output_attachments($post->id, $context);
    if ($attachments) {
        $posthtml .= $OUTPUT->heading(get_string('attachment', 'klassenbuch'), 3);
        $posthtml .= $attachments;
    }

    if ($canunsubscribe) {
        $posthtml .= '<hr /><div class="mdl-align unsubscribelink">
                        <a href="' . $CFG->wwwroot . '/mod/klassenbuch/subscribe.php?id=' . $klassenbuch->id . '">' .
                get_string('unsubscribe', 'klassenbuch') . '</a>
                        </div>' . "\n";
    }

    $posthtml .= '</body>';

    return $posthtml;
}

function klassenbuch_make_mail_post($course, $klassenbuch, $post, $userfrom, $userto, $footer = "") {

    global $CFG, $OUTPUT;

    require_once($CFG->dirroot . '/mod/klassenbuch/locallib.php');

    if (!$cm = get_coursemodule_from_instance('klassenbuch', $klassenbuch->id, $course->id)) {
        print_error('Course Module ID was incorrect');
    }
    $modcontext = context_module::instance($cm->id);
    $viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);

    // Format the post body.
    $options = new stdClass();
    $options->para = true;
    $content = $post->content;
    if (empty($content)) {
        // Add custom fields instead.
        $customfields = klassenbuch_get_chapter_customfields($post->id);
        foreach ($customfields as $customfield) {
            if (!empty($customfield->content)) {
                $content .= $OUTPUT->heading($customfield->title, 3) . "\n";
                $chcontent = file_rewrite_pluginfile_urls($customfield->content, 'pluginfile.php',
                    $modcontext->id, 'mod_klassenbuch', 'customcontent_' . $customfield->fieldid, $post->id);
                $content .= format_text($chcontent, $customfield->contentformat) . "\n";
            }
        }
    }

    $formattedtext = format_text(trusttext_strip($content), FORMAT_MOODLE, $options, $course->id);

    $output = '<table border="0" cellpadding="3" cellspacing="0" class="klassenbuchpost">';

    $output .= '<tr class="header"><td width="35" valign="top" class="picture left">';
    $output .= $OUTPUT->user_picture($userfrom, array('courseid' => $course->id));
    $output .= '</td>';

    $output .= '<td class="topic starter">';
    $output .= '<div class="subject">' . format_string($post->title) . '</div>';

    $fullname = fullname($userfrom, $viewfullnames);
    $by = new stdClass();
    $by->name = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $userfrom->id . '&amp;course=' . $course->id . '">' . $fullname . '</a>';
    $by->date = userdate($post->timemodified, '', $userto->timezone);
    $output .= '<div class="author">' . get_string('bynameondate', 'klassenbuch', $by) . '</div>';

    $output .= '</td></tr>';

    $output .= '<tr><td class="left side" valign="top">';

    if (isset($userfrom->groups)) {
        $groups = $userfrom->groups[$klassenbuch->id];
    } else {
        if (!$cm = get_coursemodule_from_instance('klassenbuch', $klassenbuch->id, $course->id)) {
            error('Course Module ID was incorrect');
        }
        $groups = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
    }

    if (isset($groups)) {
        $output .= print_group_picture($groups, $course->id, false, true, true);
    } else {
        $output .= '&nbsp;';
    }

    $output .= '</td><td class="content">';

    $output .= $formattedtext;

    // Commands.
    $commands = array();

    $output .= '<div class="commands">';
    $output .= implode(' | ', $commands);
    $output .= '</div>';

    // Context link to post if required.
    if ($footer) {
        $output .= '<div class="footer">' . $footer . '</div>';
    }
    $output .= '</td></tr></table>' . "\n\n";

    return $output;
}

function klassenbuch_get_subscribe_link($klassenbuch, $context, $messages = array(), $cantaccessagroup = false,
                                        $fakelink = true, $backtoindex = false, $subscribedklassenbuchs = null) {
    global $CFG, $USER;
    $defaultmessages = array(
        'subscribed' => get_string('unsubscribe', 'klassenbuch'),
        'unsubscribed' => get_string('subscribe', 'klassenbuch'),
        'cantaccessgroup' => get_string('no'),
        'forcesubscribed' => get_string('everyoneissubscribed', 'klassenbuch'),
        'cantsubscribe' => get_string('disallowsubscribe', 'klassenbuch')
    );
    $messages = $messages + $defaultmessages;

    if (klassenbuch_is_forcesubscribed($klassenbuch)) {
        return $messages['forcesubscribed'];
    } else if ($klassenbuch->forcesubscribe == KLASSENBUCH_DISALLOWSUBSCRIBE &&
            !has_capability('mod/klassenbuch:managesubscriptions', $context)
    ) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (is_null($subscribedklassenbuchs)) {
            $subscribed = klassenbuch_is_subscribed($USER->id, $klassenbuch->id);
        } else {
            $subscribed = !empty($subscribedklassenbuchs[$klassenbuch->id]);
        }
        if ($subscribed) {
            $linktext = $messages['subscribed'];
        } else {
            $linktext = $messages['unsubscribed'];
        }

        $options = array();
        if ($backtoindex) {
            $backtoindexlink = '&amp;backtoindex=1';
            $options['backtoindex'] = 1;
        } else {
            $backtoindexlink = '';
        }
        $link = '';

        if ($fakelink) {
            $link .= '<script type="text/javascript">';
            $link .= '//<![CDATA[' . "\n";
            $link .= 'document.getElementById("subscriptionlink").innerHTML = "<a href=\"' .
                    $CFG->wwwroot . '/mod/klassenbuch/subscribe.php?id=' . $klassenbuch->id . $backtoindexlink . '\">' .
                    $linktext . '<\/a>";';
            $link .= '//]]>';
            $link .= '</script>';
            // Use <noscript> to print button in case javascript is not enabled.
            $link .= '<noscript>';
        }

        $options ['id'] = $klassenbuch->id;
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}

function klassenbuch_is_subscribed($userid, $klassenbuchid) {
    global $DB;

    if (klassenbuch_is_forcesubscribed($klassenbuchid)) {
        return true;
    }
    return $DB->record_exists("klassenbuch_subscriptions", array("userid" => $userid, "klassenbuch" => $klassenbuchid));
}

function klassenbuch_unsubscribe($userid, $bookid) {
    global $DB;

    $retid = $DB->get_field("klassenbuch_subscriptions", 'id', array("userid" => $userid, "klassenbuch" => $bookid));
    if (!$retid) {
        return $retid;
    }

    $DB->delete_records("klassenbuch_subscriptions", array("userid" => $userid, "klassenbuch" => $bookid));
    return $retid;
}

function klassenbuch_subscribe($userid, $klassenbuchid) {
    global $DB;

    if ($DB->record_exists("klassenbuch_subscriptions", array("userid" => $userid, "klassenbuch" => $klassenbuchid))) {
        return true;
    }

    $sub = new stdClass();
    $sub->userid = $userid;
    $sub->klassenbuch = $klassenbuchid;

    return $DB->insert_record("klassenbuch_subscriptions", $sub);
}

/**
 * Prints the editing button on subscribers page
 */
function klassenbuch_update_subscriptions_button($courseid, $klassenbuchid) {
    global $CFG, $USER;

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form $CFG->frametarget method=\"get\" action=\"$CFG->wwwroot/mod/klassenbuch/subscribers.php\">" .
            "<input type=\"hidden\" name=\"id\" value=\"$klassenbuchid\" />" .
            "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />" .
            "<input type=\"submit\" value=\"$string\" /></form>";
}

function klassenbuch_forcesubscribe($klassenbuchid, $value = 1) {
    global $DB;
    return $DB->set_field("klassenbuch", "forcesubscribe", $value, array("id" => $klassenbuchid));
}

function klassenbuch_get_potential_subscribers($klassenbuchcontext, $groupid, $fields, $sort) {
    global $CFG;
    $users = get_users_by_capability($klassenbuchcontext, 'mod/klassenbuch:initialsubscriptions', $fields, $sort, '', '', $groupid, '', false, true);
    if (file_exists($CFG->dirroot . '/local/gitemplate')) {
        \local_gitemplate\local\anonymous::remove_anonymous_user($users);
    }

    return $users;
}

function klassenbuch_role_unassign($userid, $context) {
    if (empty($context->contextlevel)) {
        return false;
    }

    klassenbuch_remove_user_subscriptions($userid, $context);

    return true;
}

function klassenbuch_remove_user_subscriptions($userid, $context) {
    global $DB;

    if (empty($context->contextlevel)) {
        return false;
    }

    switch ($context->contextlevel) {

        case CONTEXT_SYSTEM: // For the whole site.
            // Find all courses in which this user has a klassenbuch subscription.
            if ($courses = $DB->get_records_sql("SELECT c.id
                                              FROM {course} c,
                                                   {klassenbuch_subscriptions} ks,
                                                   {klassenbuch} k
                                                   WHERE c.id = k.course AND k.id = ks.klassenbuch AND ks.userid = ?
                                                   GROUP BY c.id", array($userid))
            ) {

                foreach ($courses as $course) {
                    $subcontext = context_course::instance($course->id);
                    klassenbuch_remove_user_subscriptions($userid, $subcontext);
                }
            }
            break;

        case CONTEXT_COURSECAT: // For a whole category.
            if ($courses = $DB->get_records('course', array('category' => $context->instanceid), '', 'id')) {
                foreach ($courses as $course) {
                    $subcontext = context_course::instance($course->id);
                    klassenbuch_remove_user_subscriptions($userid, $subcontext);
                }
            }
            if ($categories = $DB->get_records('course_categories', array('parent' => $context->instanceid), '', 'id')) {
                foreach ($categories as $category) {
                    $subcontext = context_coursecat::instance($category->id);
                    klassenbuch_remove_user_subscriptions($userid, $subcontext);
                }
            }
            break;

        case CONTEXT_COURSE: // For a whole course.
            if ($course = $DB->get_record('course', array('id' => $context->instanceid), '', '', '', '', 'id')) {
                // Find all klassenbuchs in which this user has a subscription, and its coursemodule id.
                if ($klassenbuchs = $DB->get_records_sql("SELECT k.id, cm.id as coursemodule
                                                 FROM {klassenbuch} k,
                                                      {modules} m,
                                                      {course_modules} cm,
                                                      {klassenbuch_subscriptions} ks
                                                WHERE ks.userid = $userid AND k.course = ?
                                                      AND ks.book = k.id AND cm.instance = k.id
                                                      AND cm.module = m.id AND m.name = 'klassenbuch'", array($context->instanceid))
                ) {

                    foreach ($klassenbuchs as $klassenbuch) {
                        if ($modcontext = context_module::instance($klassenbuch->coursemodule)) {
                            if (!has_capability('mod/klassenbuch:viewdiscussion', $modcontext, $userid)) {
                                klassenbuch_unsubscribe($userid, $klassenbuch->id);
                            }
                        }
                    }
                }
            }
            break;

        case CONTEXT_MODULE: // Just one klassenbuch.
            if ($cm = get_coursemodule_from_id('klassenbuch', $context->instanceid)) {
                if ($klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance))) {
                    if (!has_capability('mod/klassenbuch:viewdiscussion', $context, $userid)) {
                        klassenbuch_unsubscribe($userid, $klassenbuch->id);
                    }
                }
            }
            break;
    }

    return true;
}

/**
 * Return content delivered from subplugins.
 * @return String html content from subplugins;
 */
function klassenbuch_get_subcontent($chapterid, $context) {
    $output = '';

    $plugins = get_plugin_list('klassenbuchtool');
    foreach ($plugins as $plugin => $dir) {
        if ($plugin == 'lernschritte') {
            continue;
        }
        if (file_exists("$dir/lib.php")) {
            require_once("$dir/lib.php");
        }
        $function = 'klassenbuchtool_' . $plugin . '_get_subcontent';
        if (function_exists($function)) {
            $output .= $function($chapterid, $context);
        }
    }

    return $output;
}
