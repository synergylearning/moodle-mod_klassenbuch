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
 * Klassenbuch import form
 *
 * @package    klassenbuchtool
 * @subpackage teacherimport
 * @copyright  2004-2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once($CFG->libdir.'/formslib.php');

class klassenbuchtool_teacherimport_form extends moodleform {

    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('header', 'general', get_string('import'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'chapterid');
        $mform->setType('chapterid', PARAM_INT);

        foreach ($data['courses'] as $crs) {
            $mform->addElement('static', 'crs'.$crs->id, $OUTPUT->heading($crs->name, 3));
            foreach ($crs->klassenbuecher as $kb) {
                $mform->addElement('radio', 'chosenkb', $kb->link, get_string('select'), $kb->id);
            }
        }

        $this->add_action_buttons(true, get_string('doimport', 'klassenbuchtool_teacherimport'));

        $this->set_data($data);
    }
}
