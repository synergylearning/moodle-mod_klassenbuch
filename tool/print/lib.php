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
 * Print lib
 *
 * @package    klassenbuchtool
 * @subpackage print
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function klassenbuchtool_print_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $klassenbuchnode) {
    global $PAGE;

    if ($PAGE->cm->modname !== 'klassenbuch') {
        return;
    }

    $params = $PAGE->url->params();

    if (empty($params['id']) or empty($params['chapterid'])) {
        return;
    }

    if (has_capability('klassenbuchtool/print:print', $PAGE->context)) {
        $url1 = new moodle_url('/mod/klassenbuch/tool/print/index.php', array('id' => $params['id']));
        $url2 = new moodle_url('/mod/klassenbuch/tool/print/index.php', array(
                                                                             'id' => $params['id'],
                                                                             'chapterid' => $params['chapterid']
                                                                        ));
        $action = new action_link($url1, get_string('printklassenbuch', 'klassenbuchtool_print'), new popup_action('click', $url1));
        $klassenbuchnode->add(get_string('printklassenbuch', 'klassenbuchtool_print'), $action, navigation_node::TYPE_SETTING,
                              null, null, new pix_icon('klassenbuch', '', 'klassenbuchtool_print', array('class' => 'icon')));
        $action = new action_link($url2, get_string('printchapter', 'klassenbuchtool_print'), new popup_action('click', $url2));
        $klassenbuchnode->add(get_string('printchapter', 'klassenbuchtool_print'), $action, navigation_node::TYPE_SETTING,
                              null, null, new pix_icon('chapter', '', 'klassenbuchtool_print', array('class' => 'icon')));
    }
}

/**
 * Return read actions.
 * @return array
 */
function klassenbuchtool_print_get_view_actions() {
    return array('print');
}
