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
 * Chapter edit form
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class klassenbuch_chapter_edit_form extends moodleform {

    public function definition() {
        global $PAGE;

        $options = $this->_customdata['options'];
        $attachoptions = $this->_customdata['attachoptions'];
        $customfields = $this->_customdata['customfields'];

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('edit'));

        $mform->addElement('text', 'title', get_string('chaptertitle', 'mod_klassenbuch'), array('size' => '60'));
        $mform->setType('title', PARAM_RAW);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'subchapter', get_string('subchapter', 'mod_klassenbuch'));

        $mform->addElement('editor', 'content_editor', get_string('content', 'mod_klassenbuch'), null, $options);
        $mform->setType('content_editor', PARAM_RAW);
        $mform->addRule('content_editor', get_string('required'), 'required', null, 'client');

        // Custom fields.
        foreach ($customfields as $customfield) {
            $fieldname = $customfield->fieldname.'_editor';
            $mform->addElement('editor', $fieldname , $customfield->title, null, $options);
            $mform->setType($fieldname, PARAM_RAW);

            $mform->addElement('checkbox', 'customcontenthidden[' . $customfield->id . ']',
                               get_string('hiddentostudent', 'klassenbuch'));
            $mform->setDefault('customcontenthidden[' . $customfield->id . ']', $customfield->hidden);
        }

        if (empty($customfields)) {

            $settingslink = html_writer::link(new moodle_url('/admin/settings.php', array('section' => 'modsettingklassenbuch')),
                                              get_string('globalsettingspage', 'mod_klassenbuch'));
            $globalfieldslink = html_writer::link(new moodle_url('/mod/klassenbuch/globalfields.php'),
                                                  get_string('globalfieldspage', 'mod_klassenbuch'));
            $info = (object)array(
                'settingslink' => $settingslink,
                'globalfieldslink' => $globalfieldslink
            );
            $warning = get_string('customfieldswarning', 'mod_klassenbuch', $info);
            $mform->addElement('static', 'customfieldswarning', '', $warning);
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'pagenum');
        $mform->setType('pagenum', PARAM_INT);

        $mform->addElement('hidden', 'uniqueeditingid', time() . '_' . random_string());
        $mform->setType('uniqueeditingid', PARAM_TEXT);
        $mform->addElement('hidden', 'thisisautosave', 0);
        $mform->setType('thisisautosave', PARAM_INT);
        $mform->addElement('hidden', 'newchapter', 0);
        $mform->setType('newchapter', PARAM_INT);

        $mform->addElement('html', '<div id="autosaveddisplay"></div>');

        $mform->addElement('filemanager', 'attachments', get_string('attachment', 'mod_klassenbuch'), null, $attachoptions);

        $this->add_action_buttons(true);

        // Get autosave settings.
        $config = get_config('klassenbuch');
        $autosaveseconds = isset($config->autosaveseconds) ? $config->autosaveseconds : 0;

        // Autosave javascript.
        $jsmodule = array(
            'name' => 'mod_klassenbuch_autosave',
            'fullpath' => new moodle_url('/mod/klassenbuch/autosave.js'),
            'strings' => array(
                array('autosavedon', 'mod_klassenbuch'),
            ),
            'requires' => array('io-base', 'io-form', 'json-parse'),
        );
        $opts = array(
            'autosaveseconds' => $autosaveseconds,
            'formid' => $mform->_attributes['id'],
        );
        $PAGE->requires->js_init_call('M.mod_klassenbuch_autosave.init', array($opts), true, $jsmodule);
    }

    public function definition_after_data() {
        $mform = $this->_form;
        $customfields = $this->_customdata['customfields'];
        $content = $mform->getElementValue('content_editor');
        if (empty($content['text'])) {
            // No content => hide the content field.
            if ($mform->elementExists('content_editor')) {
                $mform->removeElement('content_editor');
            }
        } else {
            // Content exits => hide the custom fields.
            foreach ($customfields as $customfield) {
                $elname = $customfield->fieldname.'_editor';
                if ($mform->elementExists($elname)) {
                    $mform->removeElement($elname);
                }
                $chkname = "customcontenthidden[{$customfield->id}]";
                if ($mform->elementExists($chkname)) {
                    $mform->removeElement($chkname);
                }
            }
        }
    }

    /*
     * Bypass the test for submit button when autosave
     */
    public function is_validated() {
        $mform = $this->_form;
        $thisisautosave = $mform->getElement('thisisautosave');
        if (isset($thisisautosave->_attributes['value']) && $thisisautosave->_attributes['value'] == 1) {
            return true;
        } else {
            return parent::is_validated();
        }
    }

}
