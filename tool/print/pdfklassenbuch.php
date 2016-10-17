<?php
// This file is part of klassenbuch module for Moodle - http://moodle.org/
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
 * klassenbuch export pdf
 *
 * @package    klassenbuchtool
 * @subpackage print
 * @copyright  2012 Synergy Learning / Manolescu Dorel based on book module and TCPDF library
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use klassenbuchtool_print\pdf_helper;

require(dirname(__FILE__).'/../../../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
global $CFG, $DB, $USER, $PAGE, $SITE;
require_once($CFG->libdir.'/pdflib.php');

$id = required_param('id', PARAM_INT); // Course Module ID.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.

// Security checks START - teachers and students view.

$cm = get_coursemodule_from_id('klassenbuch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$klassenbuch = $DB->get_record('klassenbuch', array('id' => $cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/klassenbuch:read', $context);
require_capability('klassenbuchtool/print:print', $context);

// Check all variables.
if ($chapterid) {
    // Single chapter printing - only visible!
    $chapter = $DB->get_record('klassenbuch_chapters', array(
                                                            'id' => $chapterid, 'klassenbuchid' => $klassenbuch->id
                                                       ), '*', MUST_EXIST);
} else {
    // Complete klassenbuch.
    $chapter = false;
}

$PAGE->set_url('/mod/klassenbuch/pdfklassenbuch.php', array('id' => $id, 'chapterid' => $chapterid));

unset($id);
unset($chapterid);

// Security checks END.

// Read chapters.
$chapters = klassenbuch_preload_chapters($klassenbuch);

// SYNERGY.

$strklassenbuchs = get_string('modulenameplural', 'mod_klassenbuch');
$strklassenbuch = get_string('modulename', 'mod_klassenbuch');
$strtop = get_string('top', 'mod_klassenbuch');

$stylev = array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(255, 0, 0));

$allchapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id), 'pagenum');

// If there is a file called 'glogo.jpg' in the pix/ subfolder, it will be added to the page.
// If there is a file called 'pdfklassenbuch_details.php' in this folder, it will be loaded to find the
// 'author' name for the PDF ($pdfauthorname) and the URL to link the logo to ($pdflogolink).
$pdfauthorname = $SITE->fullname;
$pdflogolink = '';
$pdfdetailsfile = $CFG->dirroot.'/mod/klassenbuch/tool/print/pdfklassenbuch_details.php';
if (file_exists($pdfdetailsfile)) {
    require_once($pdfdetailsfile);
}

// Create new PDF document.
$pdf = new \klassenbuchtool_print\klassenbuch_pdf();

// Set document information.
$pdf->SetAuthor($pdfauthorname);
$pdf->SetTitle('Export Klassenbuch');
$pdf->SetSubject('Export Klassenbuch');
$pdf->SetKeywords('Export Klassenbuch');

// Set default font subsetting mode.
$pdf->setFontSubsetting(true);
$pdf->SetFont('helvetica', 'B', 20);

// Add a page.
// This method has several options, check the source code documentation for more information.
$pdf->AddPage();

// Set cell padding.
$pdf->setCellPaddings(1, 1, 1, 1);

// Set cell margins.
$pdf->setCellMargins(1, 1, 1, 1);

$pdf->SetFillColor(256, 256, 256);

if (file_exists($CFG->dirroot.'/mod/klassenbuch/tool/print/pix/glogo.jpg')) {
    $pdf->Image('pix/glogo.jpg', 170, 10, 30, 15, 'JPG', $pdflogolink, '', true, 170, '', false, false,
                0, false, false, false);
}
$pdf->MultiCell(155, 4, $klassenbuch->name, 0, 'C', 1, 0, '', '', true);
$pdf->Ln(20);
$pdf->SetFont('helvetica', '', 8);

$intro = file_rewrite_pluginfile_urls($klassenbuch->intro, 'pluginfile.php', $context->id, 'mod_klassenbuch', 'intro', '');

$fs = get_file_storage();
preg_match_all('/<img[^>]+>/i', $intro, $result);

$pdf->Ln(5);
$site = '<a href="'.$CFG->wwwroot.'">'.format_string($SITE->fullname, true, array('context' => $context)).'</a>';
$pdf->MultiCell(35, 4, get_string('site'), 0, 'L', 0, 0, '', '', true);
$pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', $site, $border = 0, $ln = 1, $fill = 0, $reseth = true,
                    $align = '', $autopadding = true);
$pdf->MultiCell(35, 4, get_string('course'), 0, 'L', 0, 0, '', '', true);
$pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', $course->fullname, $border = 0, $ln = 1, $fill = 0, $reseth = true,
                    $align = '', $autopadding = true);
$pdf->MultiCell(35, 4, get_string('modulename', 'mod_klassenbuch'), 0, 'L', 0, 0, '', '', true);
$pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', $klassenbuch->name, $border = 0, $ln = 1, $fill = 0, $reseth = true,
                    $align = '', $autopadding = true);
$pdf->MultiCell(35, 4, get_string('printedby', 'klassenbuchtool_print'), 0, 'L', 0, 0, '', '', true);
$pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', fullname($USER, true), $border = 0, $ln = 1, $fill = 0, $reseth = true,
                    $align = '', $autopadding = true);
$pdf->MultiCell(35, 4, get_string('printdate', 'klassenbuchtool_print'), 0, 'L', 0, 0, '', '', true);
$pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', userdate(time()), $border = 0, $ln = 1, $fill = 0, $reseth = true,
                    $align = '', $autopadding = true);
$pdf->Ln(5);

list($toc, $titles) = klassenbuchtool_print_get_toc($chapters, $klassenbuch, $cm);

$link1 = $CFG->wwwroot.'/mod/klassenbuch/viewklassenbuch.php?id='.$course->id.'&chapterid=';
$link2 = $CFG->wwwroot.'/mod/klassenbuch/viewklassenbuch.php?id='.$course->id;

foreach ($chapters as $ch) {
    $chapter = $allchapters[$ch->id];
    if ($chapter->hidden) {
        continue;
    }
    $pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', '<div class="klassenbuch_chapter"><a name="ch'.$ch->id.'"></a>',
                        $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);
    if (!$klassenbuch->customtitles) {
        $pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', '<h2 class="klassenbuch_chapter_title">'.$titles[$ch->id].'</h2>',
                            $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = '', $autopadding = true);
    }
    $content = '';
    if (!$chapter->contenthidden) {
        $content = $chapter->content;
        $content = file_rewrite_pluginfile_urls($content, 'pluginfile.php', $context->id, 'mod_klassenbuch', 'chapter', $ch->id);
        $content = format_text($content, $chapter->contentformat, array('noclean' => true, 'context' => $context));
    }

    // Add custom fields.
    $customfields = klassenbuch_get_chapter_customfields($ch->id);
    foreach ($customfields as $customfield) {
        $chaptertext = file_rewrite_pluginfile_urls($customfield->content, 'pluginfile.php', $context->id,
                                                    'mod_klassenbuch', 'customcontent_'.$customfield->fieldid, $chapter->id);
        if (!empty($chaptertext)) {
            $content .= '<h3>'.s($customfield->title).'</h3>';
            $content .= format_text($chaptertext, $customfield->contentformat, array('noclean' => true, 'context' => $context));
        }
    }

    // Add attachments.
    $content .= klassenbuch_output_attachments($chapter->id, $context);

    // Remove problem characters.
    $content = pdf_helper::replace_special($content);

    // Tidy styles.
    $content = pdf_helper::tidy_styles($content);

    $content = str_replace($link1, '#ch', $content);
    $content = str_replace($link2, '#top', $content);

    // Embed the content of any images that are part of this Klassenbuch.
    $content = pdf_helper::fix_images($content, $context->id);

    $pdf->writeHTMLCell($w = 0, $h = 0, $x = '', $y = '', $content, $border = 0, $ln = 1, $fill = 0, $reseth = true, $align = '',
                        $autopadding = true);
}

$filename = "pdfklassenbuch-{$klassenbuch->name}.pdf";
clean_filename($filename);
$pdf->Output($filename, 'I');

