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
 * Klassenbuch imscp export lib
 *
 * @package    klassenbuchtool
 * @subpackage exportimscp
 * @copyright  2001-3001 Antonio Vicent          {@link http://ludens.es}
 * @copyright  2001-3001 Eloy Lafuente (stronk7) {@link http://contiento.com}
 * @copyright  2011 Petr Skoda                   {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot.'/mod/klassenbuch/locallib.php');

function klassenbuchtool_exportimscp_build_package($klassenbuch, $context) {
    global $DB;

    $fs = get_file_storage();

    if ($packagefile = $fs->get_file($context->id, 'klassenbuchtool_exportimscp', 'package', $klassenbuch->revision,
                                     '/', 'imscp.zip')
    ) {
        return $packagefile;
    }

    // Fix structure and test if chapters present.
    if (!klassenbuch_preload_chapters($klassenbuch)) {
        print_error('nochapters', 'klassenbuchtool_exportimscp');
    }

    // Prepare temp area with package contents.
    klassenbuchtool_exportimscp_prepare_files($klassenbuch, $context);

    $packer = get_file_packer('application/zip');
    $areafiles = $fs->get_area_files($context->id, 'klassenbuchtool_exportimscp', 'temp', $klassenbuch->revision,
                                     "sortorder, itemid, filepath, filename", false);
    $files = array();
    foreach ($areafiles as $file) {
        $path = $file->get_filepath().$file->get_filename();
        $path = ltrim($path, '/');
        $files[$path] = $file;
    }
    unset($areafiles);
    $packagefile = $packer->archive_to_storage($files, $context->id, 'klassenbuchtool_exportimscp', 'package',
                                               $klassenbuch->revision, '/', 'imscp.zip');

    // Drop temp area.
    $fs->delete_area_files($context->id, 'klassenbuchtool_exportimscp', 'temp', $klassenbuch->revision);

    // Delete older versions.
    $sql = "SELECT DISTINCT itemid
              FROM {files}
             WHERE contextid = :contextid AND component = 'klassenbuchtool_exportimscp' AND itemid < :revision";
    $params = array('contextid' => $context->id, 'revision' => $klassenbuch->revision);
    $revisions = $DB->get_records_sql($sql, $params);
    foreach ($revisions as $rev => $unused) {
        $fs->delete_area_files($context->id, 'klassenbuchtool_exportimscp', 'temp', $rev);
        $fs->delete_area_files($context->id, 'klassenbuchtool_exportimscp', 'package', $rev);
    }

    return $packagefile;
}

function klassenbuchtool_exportimscp_prepare_files($klassenbuch, $context) {
    global $CFG, $DB;

    $fs = get_file_storage();

    $tempfilerecord = array(
        'contextid' => $context->id, 'component' => 'klassenbuchtool_exportimscp', 'filearea' => 'temp',
        'itemid' => $klassenbuch->revision
    );
    $chapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id), 'pagenum');
    $chapterresources = array();
    foreach ($chapters as $chapter) {
        $chapterresources[$chapter->id] = array();
        $files = $fs->get_area_files($context->id, 'mod_klassenbuch', 'chapter', $chapter->id,
                                     "sortorder, itemid, filepath, filename", false);
        foreach ($files as $file) {
            $tempfilerecord['filepath'] = '/'.$chapter->pagenum.$file->get_filepath();
            $fs->create_file_from_storedfile($tempfilerecord, $file);
            $chapterresources[$chapter->id][] = $chapter->pagenum.$file->get_filepath().$file->get_filename();
        }
        if ($file = $fs->get_file($context->id, 'klassenbuchtool_exportimscp', 'temp', $klassenbuch->revision,
                                  "/$chapter->pagenum/", 'index.html')
        ) {
            // This should not exist.
            $file->delete();
        }
        $content = klassenbuchtool_exportimscp_chapter_content($chapter, $context);
        $indexfilerecord = array(
            'contextid' => $context->id, 'component' => 'klassenbuchtool_exportimscp', 'filearea' => 'temp',
            'itemid' => $klassenbuch->revision, 'filepath' => "/$chapter->pagenum/", 'filename' => 'index.html'
        );
        $fs->create_file_from_string($indexfilerecord, $content);
    }

    $cssfilerecord = array(
        'contextid' => $context->id, 'component' => 'klassenbuchtool_exportimscp', 'filearea' => 'temp',
        'itemid' => $klassenbuch->revision, 'filepath' => "/css/", 'filename' => 'styles.css'
    );
    $fs->create_file_from_pathname($cssfilerecord, dirname(__FILE__).'/imscp.css');

    // Init imsmanifest and others.
    $imsmanifest = '';
    $imsitems = '';
    $imsresources = '';

    // Moodle and Klassenbuch version.
    $moodlerelease = $CFG->release;
    $moodleversion = $CFG->version;
    $klassenbuchversion = get_config('mod_klassenbuch', 'version');
    $klassenbuchname = format_string($klassenbuch->name, true, array('context' => $context));

    // Load manifest header.
    $imsmanifest .= '<?xml version="1.0" encoding="UTF-8"?>
<!-- This package has been created with Moodle '.$moodlerelease.' ('.$moodleversion.') http://moodle.org/,'.
        ' Klassenbuch module version '.$klassenbuchversion.' - https://github.com/skodak/moodle-mod_klassenbuch -->
<!-- One idea and implementation by Eloy Lafuente (stronk7) and Antonio Vicent (C) 2001-3001 -->
<manifest xmlns="http://www.imsglobal.org/xsd/imscp_v1p1" xmlns:imsmd="http://www.imsglobal.org/xsd/imsmd_v1p2" '.
        'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" identifier="MANIFEST-'.
        md5($CFG->wwwroot.'-'.$klassenbuch->course.'-'.$klassenbuch->id).
        '" xsi:schemaLocation="http://www.imsglobal.org/xsd/imscp_v1p1 imscp_v1p1.xsd '.
        'http://www.imsglobal.org/xsd/imsmd_v1p2 imsmd_v1p2p2.xsd">
  <organizations default="MOODLE-'.$klassenbuch->course.'-'.$klassenbuch->id.'">
    <organization identifier="MOODLE-'.$klassenbuch->course.'-'.$klassenbuch->id.'" structure="hierarchical">
      <title>'.htmlspecialchars($klassenbuchname).'</title>';

    // To store the prev level (klassenbuch only have 0 and 1).
    $prevlevel = null;
    foreach ($chapters as $chapter) {
        // Calculate current level ((klassenbuch only have 0 and 1).
        $currlevel = empty($chapter->subchapter) ? 0 : 1;
        // Based upon prevlevel and current one, decide what to close.
        if ($prevlevel !== null) {
            // Calculate the number of spaces (for visual xml-text formating).
            $prevspaces = substr('                ', 0, $currlevel * 2);

            // Same level, simply close the item.
            if ($prevlevel == $currlevel) {
                $imsitems .= $prevspaces.'        </item>'."\n";
            }
            // Bigger currlevel, nothing to close.
            // Smaller currlevel, close both the current item and the parent one.
            if ($prevlevel > $currlevel) {
                $imsitems .= '          </item>'."\n";
                $imsitems .= '        </item>'."\n";
            }
        }
        // Update prevlevel.
        $prevlevel = $currlevel;

        // Calculate the number of spaces (for visual xml-text formating).
        $currspaces = substr('                ', 0, $currlevel * 2);

        $chaptertitle = format_string($chapter->title, true, array('context' => $context));

        // Add the imsitems.
        $imsitems .= $currspaces.'        <item identifier="ITEM-'.$klassenbuch->course.'-'.$klassenbuch->id.'-'.
            $chapter->pagenum.'" isvisible="true" identifierref="RES-'.$klassenbuch->course.'-'.$klassenbuch->id.'-'.
            $chapter->pagenum.'">
 '.$currspaces.'         <title>'.htmlspecialchars($chaptertitle).'</title>'."\n";

        // Add the imsresources.
        // First, check if we have localfiles.
        $localfiles = array();
        foreach ($chapterresources[$chapter->id] as $localfile) {
            $localfiles[] = "\n".'      <file href="'.$localfile.'" />';
        }
        // Now add the dependency to css.
        $cssdependency = "\n".'      <dependency identifierref="RES-'.$klassenbuch->course.'-'.
            $klassenbuch->id.'-css" />';
        // Now build the resources section.
        $imsresources .= '    <resource identifier="RES-'.$klassenbuch->course.'-'.$klassenbuch->id.'-'.
            $chapter->pagenum.'" type="webcontent" xml:base="'.$chapter->pagenum.'/" href="index.html">
      <file href="'.$chapter->pagenum.'/index.html" />'.implode($localfiles).$cssdependency.'
    </resource>'."\n";
    }

    // Close items (the latest chapter).
    // Level 1, close 1.
    if ($currlevel == 0) {
        $imsitems .= '        </item>'."\n";
    }
    // Level 2, close 2.
    if ($currlevel == 1) {
        $imsitems .= '          </item>'."\n";
        $imsitems .= '        </item>'."\n";
    }

    // Define the css common resource.
    $cssresource = '    <resource identifier="RES-'.$klassenbuch->course.'-'.$klassenbuch->id.
        '-css" type="webcontent" xml:base="css/" href="styles.css">
      <file href="css/styles.css" />
    </resource>'."\n";

    // Add imsitems to manifest.
    $imsmanifest .= "\n".$imsitems;
    // Close the organization.
    $imsmanifest .= "    </organization>
  </organizations>";
    // Add resources to manifest.
    $imsmanifest .= "\n  <resources>\n".$imsresources.$cssresource."  </resources>";
    // Close manifest.
    $imsmanifest .= "\n</manifest>\n";

    $manifestfilerecord = array(
        'contextid' => $context->id, 'component' => 'klassenbuchtool_exportimscp', 'filearea' => 'temp',
        'itemid' => $klassenbuch->revision, 'filepath' => "/", 'filename' => 'imsmanifest.xml'
    );
    $fs->create_file_from_string($manifestfilerecord, $imsmanifest);
}

function klassenbuchtool_exportimscp_chapter_content($chapter, $context) {

    $options = new stdClass();
    $options->noclean = true;
    $options->context = $context;

    $chaptercontent = str_replace('@@PLUGINFILE@@/', '', $chapter->content);
    $chaptercontent = format_text($chaptercontent, $chapter->contentformat, $options);

    $chaptertitle = format_string($chapter->title, true, array('context' => $context));

    $content = '';
    $content .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">'."\n";
    $content .= '<html>'."\n";
    $content .= '<head>'."\n";
    $content .= '<meta http-equiv="content-type" content="text/html; charset=utf-8" />'."\n";
    $content .= '<link rel="stylesheet" type="text/css" href="../css/styles.css" />'."\n";
    $content .= '<title>'.$chaptertitle.'</title>'."\n";
    $content .= '</head>'."\n";
    $content .= '<body>'."\n";
    $content .= '<h1 id="header">'.$chaptertitle.'</h1>'."\n";
    $content .= $chaptercontent."\n";
    $content .= '</body>'."\n";
    $content .= '</html>'."\n";

    return $content;
}
