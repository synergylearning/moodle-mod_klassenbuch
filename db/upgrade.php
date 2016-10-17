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
 * Klassenbuch module upgrade code
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_klassenbuch_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2004081100) {
        throw new upgrade_exception('mod_klassenbuch', $oldversion,
                                    'Can not upgrade such an old klassenbuch module, sorry, you should have upgraded it long time '.
                                    'ago in 1.9 already.');
    }

    if ($oldversion < 2007052001) {

        // Changing type of field importsrc on table klassenbuch_chapters to char.
        $table = new xmldb_table('klassenbuch_chapters');
        $field = new xmldb_field('importsrc', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'timemodified');

        // Launch change of type for field importsrc.
        $dbman->change_field_type($table, $field);

        upgrade_mod_savepoint(true, 2007052001, 'klassenbuch');
    }

    // ===== 1.9.0 upgrade line ====== //

    // SYNERGY - hack to make the upgrade process work properly.
    if ($oldversion == 2011092000) {
        $oldversion = 2008081404;
        $DB->set_field('modules', 'version', $oldversion, array('name' => 'klassenbuch'));
    }
    // SYNERGY - hack to make the upgrade process work properly.

    if ($oldversion < 2010120801) {
        // Rename field summary on table klassenbuch to intro.
        $table = new xmldb_table('klassenbuch');
        $field = new xmldb_field('summary', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'name');

        // Launch rename field summary.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'intro');
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2010120801, 'klassenbuch');
    }

    if ($oldversion < 2010120802) {
        // Rename field summary on table klassenbuch to intro.
        $table = new xmldb_table('klassenbuch');
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, 'big', null, null, null, null, 'name');

        // Launch rename field summary.
        $dbman->change_field_precision($table, $field);

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2010120802, 'klassenbuch');
    }

    if ($oldversion < 2010120803) {
        // Define field introformat to be added to klassenbuch.
        $table = new xmldb_table('klassenbuch');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'intro');

        // Launch add field introformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conditionally migrate to html format in intro.
        if ($CFG->texteditors !== 'textarea') {
            $rs = $DB->get_recordset('klassenbuch', array('introformat' => FORMAT_MOODLE), '', 'id,intro,introformat');
            foreach ($rs as $b) {
                $b->intro       = text_to_html($b->intro, false, false, true);
                $b->introformat = FORMAT_HTML;
                $DB->update_record('klassenbuch', $b);
                upgrade_set_timeout();
            }
            unset($b);
            $rs->close();
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2010120803, 'klassenbuch');
    }

    if ($oldversion < 2010120804) {
        // Define field introformat to be added to klassenbuch.
        $table = new xmldb_table('klassenbuch_chapters');
        $field = new xmldb_field('contentformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'content');

        // Launch add field introformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->set_field('klassenbuch_chapters', 'contentformat', FORMAT_HTML, array());

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2010120804, 'klassenbuch');
    }

    if ($oldversion < 2010120805) {
        require_once("$CFG->dirroot/mod/klassenbuch/db/upgradelib.php");

        $sqlfrom = "FROM {klassenbuch} b
                    JOIN {modules} m ON m.name = 'klassenbuch'
                    JOIN {course_modules} cm ON (cm.module = m.id AND cm.instance = b.id)";

        $count = $DB->count_records_sql("SELECT COUNT('x') $sqlfrom");

        if ($rs = $DB->get_recordset_sql("SELECT b.id, b.course, cm.id AS cmid $sqlfrom ORDER BY b.course, b.id")) {

            $pbar = new progress_bar('migrateklassenbuchfiles', 500, true);

            $i = 0;
            foreach ($rs as $klassenbuch) {
                $i++;
                upgrade_set_timeout(360); // Set up timeout, may also abort execution.
                $pbar->update($i, $count, "Migrating klassenbuch files - $i/$count.");

                $context = context_module::instance($klassenbuch->cmid);

                mod_klassenbuch_migrate_moddata_dir_to_legacy($klassenbuch, $context, '/');

                // Remove dirs if empty.
                @rmdir("$CFG->dataroot/$klassenbuch->course/$CFG->moddata/klassenbuch/$klassenbuch->id/");
                @rmdir("$CFG->dataroot/$klassenbuch->course/$CFG->moddata/klassenbuch/");
                @rmdir("$CFG->dataroot/$klassenbuch->course/$CFG->moddata/");
                @rmdir("$CFG->dataroot/$klassenbuch->course/");
            }
            $rs->close();
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2010120805, 'klassenbuch');
    }

    if ($oldversion < 2011011600) {
        // Define field disableprinting to be dropped from klassenbuch.
        $table = new xmldb_table('klassenbuch');
        $field = new xmldb_field('disableprinting');

        // Conditionally launch drop field disableprinting.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2011011600, 'klassenbuch');
    }

    if ($oldversion < 2011011601) {
        unset_config('klassenbuch_tocwidth');

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2011011601, 'klassenbuch');
    }

    if ($oldversion < 2011090800) {
        require_once("$CFG->dirroot/mod/klassenbuch/db/upgradelib.php");

        mod_klassenbuch_migrate_all_areas();

        upgrade_mod_savepoint(true, 2011090800, 'klassenbuch');
    }

    if ($oldversion < 2011100900) {

        // Define field revision to be added to klassenbuch.
        $table = new xmldb_table('klassenbuch');
        $field = new xmldb_field('revision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'customtitles');

        // Conditionally launch add field revision.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2011100900, 'klassenbuch');
    }

    // SYNERGY - add new 'collapsesubchapters' field to db.
    if ($oldversion < 2011110902) {
        // Define field collapsesubchapters to be added to klassenbuch.
        $table = new xmldb_table('klassenbuch');
        $field = new xmldb_field('collapsesubchapters', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field collapsesubchapters.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2011110902, 'klassenbuch');
    }
    // SYNERGY - add new 'collapsesubchapters' field to db.

    // SYNERGY - rename 'bookid' field to 'klassenbuchid' (for simplicity).
    if ($oldversion < 2011110903) {
        $table = new xmldb_table('klassenbuch_chapters');
        $field = new xmldb_field('bookid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');

        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'klassenbuchid');
        }

        $table = new xmldb_table('klassenbuch_subscriptions');
        $field = new xmldb_field('book', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');

        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'klassenbuch');
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2011110903, 'klassenbuch');
    }
    // SYNERGY - rename 'bookid' field to 'klassenbuchid' (for simplicity).

    if ($oldversion < 2012053100) {

        // Define table klassenbuch_globalfields to be created.
        $table = new xmldb_table('klassenbuch_globalfields');

        // Adding fields to table klassenbuch_globalfields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('hidden', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table klassenbuch_globalfields.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for klassenbuch_globalfields.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2012053100, 'klassenbuch');
    }

    if ($oldversion < 2012060100) {

        // Define field contenthidden to be added to klassenbuch_chapters.
        $table = new xmldb_table('klassenbuch_chapters');
        $field = new xmldb_field('contenthidden', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'contentformat');

        // Conditionally launch add field contenthidden.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table klassenbuch_chapter_fields to be created.
        $table = new xmldb_table('klassenbuch_chapter_fields');

        // Adding fields to table klassenbuch_chapter_fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('chapterid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('content', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_field('hidden', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table klassenbuch_chapter_fields.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table klassenbuch_chapter_fields.
        $table->add_index('chapteridindex', XMLDB_INDEX_NOTUNIQUE, array('chapterid'));
        $table->add_index('fieldidindex', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for klassenbuch_chapter_fields.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2012060100, 'klassenbuch');
    }

    if ($oldversion < 2012060101) {

        // Define field contentformat to be added to klassenbuch_chapter_fields.
        $table = new xmldb_table('klassenbuch_chapter_fields');
        $field = new xmldb_field('contentformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'content');

        // Conditionally launch add field contentformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2012060101, 'klassenbuch');
    }

    if ($oldversion < 2012102500) {
        // Define index klassenbuchid_pagenum (not unique) to be added to klassenbuch_chapters.
        $table = new xmldb_table('klassenbuch_chapters');
        $index = new xmldb_index('klassenbuchid_pagenum', XMLDB_INDEX_NOTUNIQUE, array('klassenbuchid', 'pagenum'));

        // Conditionally launch add index klassenbuchid_pagenum.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2012102500, 'klassenbuch');
    }

    if ($oldversion < 2012102501) {

        // Define index klassenbuch (not unique) to be added to klassenbuch_subscriptions.
        $table = new xmldb_table('klassenbuch_subscriptions');
        $index = new xmldb_index('klassenbuch', XMLDB_INDEX_NOTUNIQUE, array('klassenbuch'));

        // Conditionally launch add index klassenbuch.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2012102501, 'klassenbuch');
    }

    if ($oldversion < 2014101500) {

        // Define field showclassplan to be added to klassenbuch.
        $table = new xmldb_table('klassenbuch');
        $field = new xmldb_field('showclassplan', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'forcesubscribe');

        // Conditionally launch add field showclassplan.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Klassenbuch savepoint reached.
        upgrade_mod_savepoint(true, 2014101500, 'klassenbuch');
    }

    return true;
}
