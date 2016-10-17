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
defined('MOODLE_INTERNAL') || die;

function xmldb_klassenbuchtool_lernschritte_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014030202) {

        // Define field sortorder to be added to klassenbuchtool_lernschritte.
        $table = new xmldb_table('klassenbuchtool_lernschritte');
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'homework');

        // Conditionally launch add field sortorder.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Lernschritte savepoint reached.
        upgrade_plugin_savepoint(true, 2014030202, 'klassenbuchtool', 'lernschritte');
    }
    
    if ($oldversion < 2014030203) {

        // Changing type of field starttime on table klassenbuchtool_lernschritte to char.
        $table = new xmldb_table('klassenbuchtool_lernschritte');
        $field = new xmldb_field('starttime', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, 'attendancetype');

        // Launch change of type for field starttime.
        $dbman->change_field_type($table, $field);

        // Lernschritte savepoint reached.
        upgrade_plugin_savepoint(true, 2014030203, 'klassenbuchtool', 'lernschritte');
    }

    if ($oldversion < 2015061700) {

        // Define field module to be added to klassenbuchtool_lernschritte.
        $table = new xmldb_table('klassenbuchtool_lernschritte');
        $field = new xmldb_field('module', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, 'klassenbuch', 'sortorder');

        // Conditionally launch add field module.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index module (not unique) to be added to klassenbuchtool_lernschritte.
        $index = new xmldb_index('module', XMLDB_INDEX_NOTUNIQUE, array('module'));

        // Conditionally launch add index module.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Lernschritte savepoint reached.
        upgrade_plugin_savepoint(true, 2015061700, 'klassenbuchtool', 'lernschritte');
    }

    if ($oldversion < 2015061800) {

        // Define field userid to be added to klassenbuchtool_lernschritte.
        $table = new xmldb_table('klassenbuchtool_lernschritte');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'module');

        // Conditionally launch add field userid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key userid (foreign) to be added to klassenbuchtool_lernschritte.
        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Launch add key userid.
        $dbman->add_key($table, $key);

        // Lernschritte savepoint reached.
        upgrade_plugin_savepoint(true, 2015061800, 'klassenbuchtool', 'lernschritte');
    }

    return true;
}
