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

class klassenbuchtool_teacherimportselectchapters_form extends moodleform {

    public function definition() {

        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('hidden', 'confirmed', 0);
        $mform->addElement('hidden', 'chaplist', '');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'kbfrom');
        $mform->setType('kbfrom', PARAM_INT);

        foreach ($data['chapters'] as $chapter) {
            $mform->addElement('header', 'fieldset'.$chapter->id, $chapter->title);
            $chapelname = 'chapter['.$chapter->id.']';
            $mform->addElement('checkbox', $chapelname, get_string('chapter', 'mod_klassenbuch'), '', $chapter->id);
            if (isset($chapter->subchapters) && is_array($chapter->subchapters)) {
                foreach ($chapter->subchapters as $subchapter) {
                    $subchapelname = 'subchapter['.$chapter->id.']['.$subchapter->id.']';
                    $mform->addElement('checkbox', $subchapelname, get_string('subchapter', 'mod_klassenbuch'),
                                       $subchapter->title, $subchapter->id);
                    $mform->disabledIf($subchapelname, $chapelname);
                }
            }
        }

        $this->add_action_buttons(true, get_string('doimport', 'klassenbuchtool_teacherimport'));

        $this->set_data($data);
    }

    public function preconfirm($addchapters, $addsubchapters) {
        $mform = $this->_form;

        $confirmed = $mform->getElement('confirmed');
        $confirmed->setValue(1);

        // Remove checkboxes.
        foreach ($mform->_elements as $element) {
            if ($element->_type == 'header') {
                $mform->removeElement($element->_attributes['name']);
            } else if ($element->_type == 'checkbox') {
                $mform->removeElement($element->_attributes['name']);
            }
        }

        // Now read as hidden.
        $chapliststr = '';
        foreach ($addchapters as $addchapterid => $checked) {
            $chapliststr .= $addchapterid.'|';
            if (isset($addsubchapters[$addchapterid])) {
                $subchapsstr = '';
                foreach ($addsubchapters[$addchapterid] as $addsubid => $subchecked) {
                    $subchapsstr .= $addsubid.',';
                }
                $chapliststr .= trim($subchapsstr, ',');
            }
            $chapliststr .= ',';
        }

        // Add to hidden.
        $chapliststr = trim($chapliststr, ',');
        $chaplist = $mform->getElement('chaplist');
        $chaplist->setValue($chapliststr);
    }
}
