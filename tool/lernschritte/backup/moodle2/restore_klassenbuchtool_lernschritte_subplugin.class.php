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

class restore_klassenbuchtool_lernschritte_subplugin extends restore_subplugin {

    /**
     * Returns the paths to be handled by the subplugin at chapter level
     */
    protected function define_chapter_subplugin_structure() {

        $paths = array();

        $elename = $this->get_namefor('chapter');
        $elepath = $this->get_pathfor('/klassenbuchtool_lernschritte'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths
    }

    /**
     * Processes the klassenbuchtool_lernschritte_chapter element
     */
    public function process_klassenbuchtool_lernschritte_chapter($data) {
        global $DB;

        $data = (object)$data;
        $data->chapterid = $this->get_mappingid('klassenbuch_chapter', $data->chapterid);
        $data->courseid = $this->task->get_courseid();
        
        $DB->insert_record('klassenbuchtool_lernschritte', $data);
    }
  
}