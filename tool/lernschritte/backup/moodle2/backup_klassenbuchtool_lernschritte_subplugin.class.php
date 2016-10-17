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
 * @package    klassenbuchtool
 * @subpackage lernschritte
 * @copyright  2014 Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information to backup accumulative grading strategy information
 */
class backup_klassenbuchtool_lernschritte_subplugin extends backup_subplugin {

    protected function define_chapter_subplugin_structure() {

        // XML nodes declaration
        $subplugin = $this->get_subplugin_element(); // virtual optigroup element
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginlernschritt = new backup_nested_element(
                        'klassenbuchtool_lernschritte', array('id'), array('chapterid',
                    'attendancetype', 'starttime', 'duration', 'learninggoal',
                    'learningcontent', 'collaborationtype', 'learnersactivity',
                    'teachersactivity', 'usedmaterials', 'homework', 'sortorder', 'module'
                ));

        // connect XML elements into the tree
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginlernschritt);

        // Set source to populate the data.
        $subpluginlernschritt->set_source_table('klassenbuchtool_lernschritte',
                                                array('chapterid' => backup::VAR_PARENTID,
                                                      'module' => array('sqlparam' => 'klassenbuch')));
        return $subplugin;
    }

}