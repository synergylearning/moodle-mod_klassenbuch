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
 *
 * @package   klassenbuchtool_lernschritte
 * @copyright 2014 Andreas Wagner, SYNERGY LEARNING
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/klassenbuch/tool/lernschritte/lib.php');


$settings->add(new admin_setting_heading('klassenbuchtool_lernschritte/pdfsettings',
                new lang_string('pdfsettings', 'klassenbuchtool_lernschritte'), ''));


$choices = array();
if ($globalfields = $DB->get_records('klassenbuch_globalfields')) {
    foreach ($globalfields as $key) {
        $choices[$key->title] = $key->title;
    }
}

// ...globalfield rows for the pdf report.
if ($choices) {
    $settings->add(new admin_setting_configmultiselect('klassenbuchtool_lernschritte/globalfieldrows',
                                                       new lang_string('globalfieldrows', 'klassenbuchtool_lernschritte'),
                                                       new lang_string('globalfieldrows_desc', 'klassenbuchtool_lernschritte'),
                                                       array(), $choices));
} else {
    $settings->add(new admin_setting_configempty('klassenbuchtool_lernschritte/globalfieldrows',
                                                 new lang_string('globalfieldrows', 'klassenbuchtool_lernschritte'),
                                                 new lang_string('globalfieldrows_desc', 'klassenbuchtool_lernschritte')));
}


$choices = array();
foreach (klassenbuchtool_lernschritte::$columnkeys as $key) {
    $choices[$key] = get_string($key, 'klassenbuchtool_lernschritte');
}

$defaultoptions = array_diff(klassenbuchtool_lernschritte::$columnkeys, array('id', 'options'));

// ...columns for the pdf report.
$settings->add(new admin_setting_configmultiselect('klassenbuchtool_lernschritte/pdfcolumns',
                new lang_string('pdfcolumns', 'klassenbuchtool_lernschritte'),
                new lang_string('pdfcolumns_desc', 'klassenbuchtool_lernschritte'),
                $defaultoptions, $choices));

// ...margins.
$settings->add(new admin_setting_configtext('klassenbuchtool_lernschritte/columnswidth',
                new lang_string('columnswidth', 'klassenbuchtool_lernschritte'),
                new lang_string('columnswidth_desc', 'klassenbuchtool_lernschritte'),
                '25mm,20mm,15mm,30mm,30mm,35mm,30mm,30mm,35mm,30mm', PARAM_RAW, '80'));

// ...aligns.
$settings->add(new admin_setting_configtext('klassenbuchtool_lernschritte/columnsalign',
                new lang_string('columnsalign', 'klassenbuchtool_lernschritte'),
                new lang_string('columnsalign_desc', 'klassenbuchtool_lernschritte'), 'center,center', PARAM_RAW, '80'));

// ...page orientation to print participants report.
$options = array('P' => new lang_string('portrait', 'klassenbuchtool_lernschritte'),
    'L' => new lang_string('landscape', 'klassenbuchtool_lernschritte')
);

$settings->add(new admin_setting_configselect('klassenbuchtool_lernschritte/pageorientation',
                new lang_string('pageorientation', 'klassenbuchtool_lernschritte'),
                new lang_string('pageorientation_desc', 'klassenbuchtool_lernschritte'), 'L', $options));

// ...margins.
$settings->add(new admin_setting_configtext('klassenbuchtool_lernschritte/marginleftright',
                new lang_string('marginleftright', 'klassenbuchtool_lernschritte'),
                new lang_string('marginleftright_desc', 'klassenbuchtool_lernschritte'), 10, PARAM_INT));

