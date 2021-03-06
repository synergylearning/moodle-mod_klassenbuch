<?php
// This file is part of Klassenbuch plugin for Moodle - http://moodle.org/
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
 * HTML import lib
 *
 * @package    klassenbuchtool
 * @subpackage importhtml
 * @copyright  2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/lib.php');
global $CFG;
require_once($CFG->dirroot.'/mod/klassenbuch/locallib.php');

function toolklassenbuch_importhtml_import_chapters($package, $type, $klassenbuch, $context, $verbose = true) {
    global $DB, $OUTPUT;

    $fs = get_file_storage();
    $chapterfiles = toolklassenbuch_importhtml_get_chapter_files($package, $type);
    $packer = get_file_packer('application/zip');
    $fs->delete_area_files($context->id, 'mod_klassenbuch', 'importhtmltemp', 0);
    $package->extract_to_storage($packer, $context->id, 'mod_klassenbuch', 'importhtmltemp', 0, '/');

    $chapters = array();

    if ($verbose) {
        echo $OUTPUT->notification(get_string('importing', 'klassenbuchtool_importhtml'), 'notifysuccess');
    }
    if ($type == 0) {
        $chapterfile = reset($chapterfiles);
        if ($file = $fs->get_file_by_hash("$context->id/mod_klassenbuch/importhtmltemp/0/$chapterfile->pathname")) {
            $htmlcontent = toolklassenbuch_importhtml_fix_encoding($file->get_content());
            toolklassenbuch_importhtml_parse_headings(toolklassenbuch_importhtml_parse_body($htmlcontent));
            // TODO: process h1 as main chapter and h2 as subchapters.
        }
    } else {
        foreach ($chapterfiles as $chapterfile) {
            if ($file = $fs->get_file_by_hash(sha1("/$context->id/mod_klassenbuch/importhtmltemp/0/$chapterfile->pathname"))) {
                $chapter = new stdClass();
                $htmlcontent = toolklassenbuch_importhtml_fix_encoding($file->get_content());

                $chapter->klassenbuchid = $klassenbuch->id;
                $chapter->pagenum = $DB->get_field_sql('SELECT MAX(pagenum)
                                                          FROM {klassenbuch_chapters}
                                                         WHERE klassenbuchid = ?', array($klassenbuch->id)) + 1;
                $chapter->importsrc = '/'.$chapterfile->pathname;
                $chapter->content = toolklassenbuch_importhtml_parse_styles($htmlcontent);
                $chapter->content .= toolklassenbuch_importhtml_parse_body($htmlcontent);
                $chapter->title = toolklassenbuch_importhtml_parse_title($htmlcontent, $chapterfile->pathname);
                $chapter->contentformat = FORMAT_HTML;
                $chapter->hidden = 0;
                $chapter->timecreated = time();
                $chapter->timemodified = time();
                if (preg_match('/_sub(\/|\.htm)/i', $chapter->importsrc)) {
                    // If filename or directory ends with *_sub treat as subchapters.
                    $chapter->subchapter = 1;
                } else {
                    $chapter->subchapter = 0;
                }

                $chapter->id = $DB->insert_record('klassenbuch_chapters', $chapter);
                $chapters[$chapter->id] = $chapter;

                \mod_klassenbuch\event\chapter_created::create_from_chapter($klassenbuch, $context, $chapter)->trigger();
            }
        }
    }

    if ($verbose) {
        echo $OUTPUT->notification(get_string('relinking', 'klassenbuchtool_importhtml'), 'notifysuccess');
    }
    $allchapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id), 'pagenum');
    foreach ($chapters as $chapter) {
        // Find references to all files and copy them + relink them.
        $matches = null;
        if (preg_match_all('/(src|codebase|name|href)\s*=\s*"([^"]+)"/i', $chapter->content, $matches)) {
            $filerecord = array(
                'contextid' => $context->id, 'component' => 'mod_klassenbuch',
                'filearea' => 'chapter', 'itemid' => $chapter->id
            );
            foreach ($matches[0] as $i => $match) {
                $filepath = dirname($chapter->importsrc).'/'.$matches[2][$i];
                $filepath = toolklassenbuch_importhtml_fix_path($filepath);

                if (strtolower($matches[1][$i]) === 'href') {
                    // Skip linked html files, we will try chapter relinking later.
                    foreach ($allchapters as $target) {
                        if ($target->importsrc === $filepath) {
                            continue 2;
                        }
                    }
                }

                if ($file = $fs->get_file_by_hash(sha1("/$context->id/mod_klassenbuch/importhtmltemp/0$filepath"))) {
                    if (!$oldfile = $fs->get_file_by_hash(sha1("/$context->id/mod_klassenbuch/chapter/$chapter->id$filepath"))) {
                        $fs->create_file_from_storedfile($filerecord, $file);
                    }
                    $chapter->content = str_replace($match, $matches[1][$i].'="@@PLUGINFILE@@'.$filepath.'"', $chapter->content);
                }
            }
            $DB->set_field('klassenbuch_chapters', 'content', $chapter->content, array('id' => $chapter->id));
        }
    }
    unset($chapters);

    $allchapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id), 'pagenum');
    foreach ($allchapters as $chapter) {
        $newcontent = $chapter->content;
        $matches = null;
        if (preg_match_all('/(href)\s*=\s*"([^"]+)"/i', $chapter->content, $matches)) {
            foreach ($matches[0] as $i => $match) {
                if (strpos($matches[2][$i], ':') !== false or strpos($matches[2][$i], '@') !== false) {
                    // It is either absolute or pluginfile link.
                    continue;
                }
                $chapterpath = dirname($chapter->importsrc).'/'.$matches[2][$i];
                $chapterpath = toolklassenbuch_importhtml_fix_path($chapterpath);
                foreach ($allchapters as $target) {
                    if ($target->importsrc === $chapterpath) {
                        $newcontent = str_replace($match, 'href="'.new moodle_url('/mod/klassenbuch/view.php',
                                                                                  array(
                                                                                       'id' => $context->instanceid,
                                                                                       'chapter' => $target->id
                                                                                  )).'"', $newcontent);
                    }
                }
            }
        }
        if ($newcontent !== $chapter->content) {
            $DB->set_field('klassenbuch_chapters', 'content', $newcontent, array('id' => $chapter->id));
        }
    }

    $fs->delete_area_files($context->id, 'mod_klassenbuch', 'importhtmltemp', 0);

    // Update the revision flag - this takes a long time, better to refetch the current value.
    $klassenbuch = $DB->get_record('klassenbuch', array('id' => $klassenbuch->id));
    $DB->set_field('klassenbuch', 'revision', $klassenbuch->revision + 1, array('id' => $klassenbuch->id));
}

function toolklassenbuch_importhtml_parse_headings($html) {
    // TODO.
}

function toolklassenbuch_importhtml_parse_styles($html) {
    $styles = '';
    if (preg_match('/<head[^>]*>(.+)<\/head>/is', $html, $matches)) {
        $head = $matches[1];
        if (preg_match_all('/<link[^>]+rel="stylesheet"[^>]*>/i', $head, $matches)) { // Dlnsk extract links to css.
            for ($i = 0; $i < count($matches[0]); $i++) {
                $styles .= $matches[0][$i]."\n";
            }
        }
    }
    return $styles;
}

function toolklassenbuch_importhtml_fix_path($path) {
    $path = str_replace('\\', '/', $path); // Anti MS hack.
    $path = '/'.ltrim($path, './'); // Dirname() produces . for top level files + our paths start with /.

    $cnt = substr_count($path, '..');
    for ($i = 0; $i < $cnt; $i++) {
        $path = preg_replace('|[^/]+/\.\./|', '', $path, 1);
    }

    $path = clean_param($path, PARAM_PATH);
    return $path;
}

function toolklassenbuch_importhtml_fix_encoding($html) {
    if (preg_match('/<head[^>]*>(.+)<\/head>/is', $html, $matches)) {
        $head = $matches[1];
        if (preg_match('/charset=([^"]+)/is', $head, $matches)) {
            $enc = $matches[1];
            if (class_exists('core_text')) {
                return core_text::convert($html, $enc, 'utf-8');
            } else {
                return textlib::convert($html, $enc, 'utf-8');
            }
        }
    }
    return iconv('UTF-8', 'UTF-8//IGNORE', $html);
}

function toolklassenbuch_importhtml_parse_body($html) {
    $matches = null;
    if (preg_match('/<body[^>]*>(.+)<\/body>/is', $html, $matches)) {
        return $matches[1];
    } else {
        return '';
    }
}

function  toolklassenbuch_importhtml_parse_title($html, $default) {
    $matches = null;
    if (preg_match('/<title>([^<]+)<\/title>/i', $html, $matches)) {
        return $matches[1];
    } else {
        return $default;
    }
}

function toolklassenbuch_importhtml_get_chapter_files($package, $type) {
    $packer = get_file_packer('application/zip');
    $files = $package->list_files($packer);
    $tophtmlfiles = array();
    $subhtmlfiles = array();
    $topdirs = array();

    foreach ($files as $file) {
        if (empty($file->pathname)) {
            continue;
        }
        if (substr($file->pathname, -1) === '/') {
            if (substr_count($file->pathname, '/') !== 1) {
                // Skip subdirs.
                continue;
            }
            if (!isset($topdirs[$file->pathname])) {
                $topdirs[$file->pathname] = array();
            }

        } else {
            $mime = mimeinfo('icon', $file->pathname);
            if ($mime !== 'html') {
                continue;
            }
            $level = substr_count($file->pathname, '/');
            if ($level === 0) {
                $tophtmlfiles[$file->pathname] = $file;
            } else if ($level === 1) {
                $subhtmlfiles[$file->pathname] = $file;
                $dir = preg_replace('|/.*$|', '', $file->pathname);
                $topdirs[$dir][$file->pathname] = $file;
            } else {
                // Lower levels are not interesting.
                continue;
            }
        }
    }
    // TODO: natural dir sorting would be nice here...
    if (class_exists('core_collator')) {
        core_collator::asort($tophtmlfiles);
        core_collator::asort($subhtmlfiles);
        core_collator::asort($topdirs);
    } else {
        textlib::asort($tophtmlfiles);
        textlib::asort($subhtmlfiles);
        textlib::asort($topdirs);
    }

    $chapterfiles = array();

    if ($type == 2) {
        $chapterfiles = $tophtmlfiles;

    } else if ($type == 1) {
        foreach ($topdirs as $dir => $htmlfiles) {
            if (empty($htmlfiles)) {
                continue;
            }
            if (class_exists('core_collator')) {
                core_collator::asort($htmlfiles);
            } else {
                textlib::asort($htmlfiles);
            }
            if (isset($htmlfiles[$dir.'/index.html'])) {
                $htmlfile = $htmlfiles[$dir.'/index.html'];
            } else if (isset($htmlfiles[$dir.'/index.htm'])) {
                $htmlfile = $htmlfiles[$dir.'/index.htm'];
            } else if (isset($htmlfiles[$dir.'/Default.htm'])) {
                $htmlfile = $htmlfiles[$dir.'/Default.htm'];
            } else {
                $htmlfile = reset($htmlfiles);
            }
            $chapterfiles[$htmlfile->pathname] = $htmlfile;
        }
    } else if ($type == 0) {
        if ($tophtmlfiles) {
            if (isset($tophtmlfiles['index.html'])) {
                $htmlfile = $tophtmlfiles['index.html'];
            } else if (isset($tophtmlfiles['index.htm'])) {
                $htmlfile = $tophtmlfiles['index.htm'];
            } else if (isset($tophtmlfiles['Default.htm'])) {
                $htmlfile = $tophtmlfiles['Default.htm'];
            } else {
                $htmlfile = reset($tophtmlfiles);
            }
        } else {
            $htmlfile = reset($subhtmlfiles);
        }
        $chapterfiles[$htmlfile->pathname] = $htmlfile;
    }

    return $chapterfiles;
}
