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
 * Instance add/edit form
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
global $CFG;
require_once($CFG->dirroot.'/mod/klassenbuch/locallib.php');
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_klassenbuch_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $config = get_config('klassenbuch');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        if ($CFG->branch < 29) {
            $this->add_intro_editor($config->requiremodintro, get_string('summary'));
        } else {
            $this->standard_intro_elements(get_string('summary'));
        }

        $alloptions = klassenbuch_get_numbering_types();
        $allowed = explode(',', $config->numberingoptions);
        $options = array();
        foreach ($allowed as $type) {
            if (isset($alloptions[$type])) {
                $options[$type] = $alloptions[$type];
            }
        }
        if ($this->current->instance) {
            if (!isset($options[$this->current->numbering])) {
                if (isset($alloptions[$this->current->numbering])) {
                    $options[$this->current->numbering] = $alloptions[$this->current->numbering];
                }
            }
        }
        $mform->addElement('select', 'numbering', get_string('numbering', 'klassenbuch'), $options);
        $mform->addHelpButton('numbering', 'numbering', 'mod_klassenbuch');
        $mform->setDefault('numbering', $config->numbering);

        $mform->addElement('checkbox', 'customtitles', get_string('customtitles', 'klassenbuch'));
        $mform->addHelpButton('customtitles', 'customtitles', 'mod_klassenbuch');
        $mform->setDefault('customtitles', 0);

        // SYNERGY - add collapsesubchapters option to settings.
        $mform->addElement('selectyesno', 'collapsesubchapters', get_string('collapsesubchapters', 'klassenbuch'));
        // SYNERGY - add collapsesubchapters option to settings.

        // -------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();

        // -------------------------------------------------------------------------------
        $this->add_action_buttons();
    }


}
