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
 * Klassenbuch plugin settings
 *
 * @package    mod_klassenbuch
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
global $CFG, $OUTPUT;

if ($ADMIN->fulltree) {
    require_once("$CFG->dirroot/mod/klassenbuch/lib.php");

    // General settings.

    if ($CFG->branch < 29) {
        $settings->add(new admin_setting_configcheckbox('klassenbuch/requiremodintro',
                       get_string('requiremodintro', 'admin'), get_string('configrequiremodintro', 'admin'), 1));
    }

    $options = klassenbuch_get_numbering_types();

    $settings->add(new admin_setting_configmultiselect('klassenbuch/numberingoptions',
                    get_string('numberingoptions', 'mod_klassenbuch'), get_string('numberingoptions_help', 'mod_klassenbuch'),
                    array_keys($options), $options));


    $settings->add(new admin_setting_configcheckbox('klassenbuch/replytouser', get_string('replytouser', 'klassenbuch'),
                    get_string('configreplytouser', 'klassenbuch'), 1));
    // Modedit defaults.
    $settings->add(new admin_setting_heading('klassenbuchmodeditdefaults', get_string('modeditdefaults', 'admin'),
                                             get_string('condifmodeditdefaults', 'admin')));

    $settings->add(new admin_setting_configselect('klassenbuch/numbering',
                    get_string('numbering', 'mod_klassenbuch'), '', KLASSENBUCH_NUM_NUMBERS, $options));

    $settings->add(new admin_setting_configtext('klassenbuch/autosaveseconds',
                    get_string('autosaveseconds', 'klassenbuch'), '', 60, PARAM_INT));

    $editurl = new moodle_url('/mod/klassenbuch/globalfields.php');
    $eicon = $OUTPUT->pix_icon('i/edit', '', 'moodle', array('class' => 'iconsmall')).
        get_string('manageglobalfields', 'mod_klassenbuch');
    $eicon = html_writer::link($editurl, $eicon);
    $strman = html_writer::tag('p', $eicon);
    $settings->add(new admin_setting_heading('klassenbuch/globalfields_header',
                                             get_string('globalfields', 'klassenbuch'), $strman));
    
    // include the settings of klassenbuchtool subplugins
    $tools = core_component::get_plugin_list('klassenbuchtool');
    foreach ($tools as $tool => $path) {
        if (file_exists($settingsfile = $path . '/settings.php')) {
            $settings->add(new admin_setting_heading('klassenbuchtool'.$tool,
                    get_string('pluginname', 'klassenbuchtool_' . $tool), ''));
            include($settingsfile);
        }
    }
}
