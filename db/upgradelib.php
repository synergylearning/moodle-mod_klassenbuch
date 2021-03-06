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
 * Resource module upgrade related helper functions
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Migrate klassenbuch files stored in moddata folders.
 *
 * Please note it was a big mistake to store the files there in the first place!
 *
 * @param stdClass $klassenbuch
 * @param stdClass $context
 * @param string $path
 * @return void
 */
function mod_klassenbuch_migrate_moddata_dir_to_legacy($klassenbuch, $context, $path) {
    global $OUTPUT, $CFG;

    $base = "$CFG->dataroot/$klassenbuch->course/$CFG->moddata/klassenbuch/$klassenbuch->id";
    $fulldir = $base.$path;

    if (!is_dir($fulldir)) {
        // Does not exist.
        return;
    }

    $fs      = get_file_storage();
    $items   = new DirectoryIterator($fulldir);

    foreach ($items as $item) {
        if ($item->isDot()) {
            unset($item); // Release file handle.
            continue;
        }

        if ($item->isLink()) {
            // Do not follow symlinks - they were never supported in moddata, sorry.
            unset($item); // Release file handle.
            continue;
        }

        if ($item->isFile()) {
            if (!$item->isReadable()) {
                echo $OUTPUT->notification(" File not readable, skipping: ".$fulldir.$item->getFilename());
                unset($item); // Release file handle.
                continue;
            }

            $filepath = clean_param("/$CFG->moddata/klassenbuch/$klassenbuch->id".$path, PARAM_PATH);
            $filename = clean_param($item->getFilename(), PARAM_FILE);

            if ($filename === '') {
                // Unsupported chars, sorry.
                unset($item); // Release file handle.
                continue;
            }

            if (textlib::strlen($filepath) > 255) {
                echo $OUTPUT->notification(" File path longer than 255 chars, skipping: ".$fulldir.$item->getFilename());
                unset($item); // Release file handle.
                continue;
            }

            if (!$fs->file_exists($context->id, 'course', 'legacy', '0', $filepath, $filename)) {
                $filerecord = array(
                    'contextid' => $context->id, 'component' => 'course', 'filearea' => 'legacy', 'itemid' => 0,
                    'filepath' => $filepath, 'filename' => $filename,
                    'timecreated' => $item->getCTime(), 'timemodified' => $item->getMTime()
                );
                $fs->create_file_from_pathname($filerecord, $fulldir.$item->getFilename());
            }
            $oldpathname = $fulldir.$item->getFilename();
            unset($item); // Release file handle.
            @unlink($oldpathname);

        } else {
            // Migrate recursively all subdirectories.
            $oldpathname = $base.$item->getFilename().'/';
            $subpath     = $path.$item->getFilename().'/';
            unset($item);  // Release file handle.
            klassenbuch_migrate_moddata_dir_to_legacy($klassenbuch, $context, $subpath);
            @rmdir($oldpathname); // Deletes dir if empty.
        }
    }
    unset($items); // Release file handles.
}

/**
 * Migrate legacy files in intro and chapters
 * @return void
 */
function mod_klassenbuch_migrate_all_areas() {
    global $DB;

    $rsklassenbuchs = $DB->get_recordset('klassenbuch');
    foreach ($rsklassenbuchs as $klassenbuch) {
        upgrade_set_timeout(360); // Set up timeout, may also abort execution.
        $cm = get_coursemodule_from_instance('klassenbuch', $klassenbuch->id);
        $context = context_module::instance($cm->id);
        mod_klassenbuch_migrate_area($klassenbuch, 'intro', 'klassenbuch', $klassenbuch->course, $context,
                                     'mod_klassenbuch', 'intro', 0);

        $rschapters = $DB->get_recordset('klassenbuch_chapters', array('bookid' => $klassenbuch->id));
        foreach ($rschapters as $chapter) {
            mod_klassenbuch_migrate_area($chapter, 'content', 'klassenbuch_chapters', $klassenbuch->course, $context,
                                         'mod_klassenbuch', 'chapter', $chapter->id);
        }
        $rschapters->close();
    }
    $rsklassenbuchs->close();
}

/**
 * Migrate one area, this should be probably part of moodle core...
 * @param $record
 * @param $field
 * @param $table
 * @param $courseid
 * @param $context
 * @param $component
 * @param $filearea
 * @param $itemid
 * @return void
 */
function mod_klassenbuch_migrate_area($record, $field, $table, $courseid, $context, $component, $filearea, $itemid) {
    global $CFG, $DB;

    $fs = get_file_storage();

    foreach (array(get_site()->id, $courseid) as $cid) {
        $matches = null;
        $ooldcontext = context_module::instance($cid);
        if (preg_match_all("|$CFG->wwwroot/file.php(\?file=)?/$cid(/[^\s'\"&\?#]+)|", $record->$field, $matches)) {
            $filerecord = array(
                'contextid' => $context->id, 'component' => $component, 'filearea' => $filearea, 'itemid' => $itemid
            );
            foreach ($matches[2] as $i => $filepath) {
                if (!$file = $fs->get_file_by_hash(sha1("/$ooldcontext->id/course/legacy/0".$filepath))) {
                    continue;
                }
                try {
                    if (!$newfile = $fs->get_file_by_hash(sha1("/$context->id/$component/$filearea/$itemid".$filepath))) {
                        $fs->create_file_from_storedfile($filerecord, $file);
                    }
                    $record->$field = str_replace($matches[0][$i], '@@PLUGINFILE@@'.$filepath, $record->$field);
                } catch (Exception $ex) {
                    // Ignore problems.
                }
                $DB->set_field($table, $field, $record->$field, array('id' => $record->id));
            }
        }
    }
}