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
 * Define all the backup steps that will be used by the backup_klassenbuch_activity_task
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Define the complete klassenbuch structure for backup, with file and id annotations
 */
class backup_klassenbuch_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        global $DB;

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $klassenbuch     = new backup_nested_element('klassenbuch', array('id'),
                                                     array('name', 'intro', 'introformat', 'numbering', 'customtitles',
                                                          'timecreated', 'timemodified', 'collapsesubchapters', 'forcesubscribe'));
        $chapters = new backup_nested_element('chapters');
        $chapter  = new backup_nested_element('chapter', array('id'),
                                              array('pagenum', 'subchapter', 'title', 'content', 'contentformat', 'contenthidden',
                                                   'hidden', 'timemcreated', 'timemodified', 'importsrc', 'mailed', 'mailnow'));
        $chapterfields = new backup_nested_element('chapterfields');
        $chapterfield = new backup_nested_element('chapterfield', array('id'),
                                                  array('fieldid', 'content', 'contentformat', 'hidden'));
        $subscriptions = new backup_nested_element('subscriptions');
        $subscription = new backup_nested_element('subscription', array('id'), array('userid'));

        $klassenbuch->add_child($chapters);
        $chapters->add_child($chapter);
        $chapter->add_child($chapterfields);
        $chapterfields->add_child($chapterfield);
        $klassenbuch->add_child($subscriptions);
        $subscriptions->add_child($subscription);

        // Define sources.
        $klassenbuch->set_source_table('klassenbuch', array('id' => backup::VAR_ACTIVITYID));
        $chapter->set_source_table('klassenbuch_chapters', array('klassenbuchid' => backup::VAR_PARENTID));
        $chapterfield->set_source_table('klassenbuch_chapter_fields', array('chapterid' => backup::VAR_PARENTID));
        if ($userinfo) {
            $subscription->set_source_table('klassenbuch_subscriptions', array('klassenbuch' => backup::VAR_PARENTID));
        }

        // Define ID annotations.
        $subscription->annotate_ids('user', 'userid');

        // Define file annotations.
        $klassenbuch->annotate_files('mod_klassenbuch', 'intro', null); // This file area hasn't itemid.
        $chapter->annotate_files('mod_klassenbuch', 'chapter', 'id');
        $chapter->annotate_files('mod_klassenbuch', 'attachment', 'id');

        $fieldids = $DB->get_fieldset_select('klassenbuch_globalfields', 'id', '');
        foreach ($fieldids as $fieldid) {
            $chapter->annotate_files('mod_klassenbuch', 'customcontent_'.$fieldid, 'id');
        }
        
        // add backup connection for chapter related data.
        $this->add_subplugin_structure('klassenbuchtool', $chapter, true);

        // Return the root element (klassenbuch), wrapped into standard activity structure.
        return $this->prepare_activity_structure($klassenbuch);
    }
}
