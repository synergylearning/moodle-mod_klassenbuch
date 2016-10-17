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

/** get the content provided by this subplugin
 *
 * @param int $chapterid , the id of the chapter from klassenbuch
 * @param context $context , the context of the module klassenbuch
 * @param string $module
 * @return String the HTML String to add below the content of klassenbuch in view.php
 * @throws coding_exception
 */
function klassenbuchtool_lernschritte_get_subcontent($chapterid, $context, $module) {
    klassenbuchtool_lernschritte::check_supported_module($module);

    if (!klassenbuchtool_lernschritte::can_view($module, $context)) {
        return "";
    }

    $lernschritte = klassenbuchtool_lernschritte::instance();
    return $lernschritte->render_lernschritte_table($chapterid, $context, $module);
}

/** is called, before an instance of klassenbuch is deleted, can be used to
 *  clean up the database entries correctly.
 *
 * @param object $instance
 * @param string $module
 * @return bool
 */
function klassenbuchtool_lernschritte_delete_instance($instance, $module = 'klassenbuch') {
    global $DB;

    // ... get all the chapterids to delete.
    if (!$chapters = $DB->get_records($module.'_chapters', array($module.'id' => $instance->id))) {
        return true;
    }

    list($inchapterids, $inparams) = $DB->get_in_or_equal(array_keys($chapters), SQL_PARAMS_NAMED);
    $inparams['module'] = $module;

    $sql = "DELETE FROM {klassenbuchtool_lernschritte} WHERE chapterid {$inchapterids} AND module = :module";

    $DB->execute($sql, $inparams);
    return true;
}

/**
 * main class for lernschritte
 */
class klassenbuchtool_lernschritte {

    private static $modules = array(
        'klassenbuch' => array(
            'viewcap' => 'klassenbuchtool/lernschritte:view',
            'editcap' => 'klassenbuchtool/lernschritte:edit',
            'printcap' => 'klassenbuchtool/lernschritte:print',
            'peruser' => false,
        ),
        'giportfolio' => array(
            'viewcap' => 'mod/giportfolio:view',
            'editcap' => 'mod/giportfolio:submitportfolio',
            'printcap' => 'mod/giportfolio:printclassplan',
            'peruser' => true,
        )
    );

    public static function check_supported_module($module) {
        if (!isset(self::$modules[$module])) {
            throw new moodle_exception('unsupportedmodule', 'klassenbuchtool_lernschritte');
        }
    }

    /**
     * @param string $module
     * @param context $context
     * @return bool
     */
    public static function can_view($module, $context) {
        return has_capability(self::$modules[$module]['viewcap'], $context);
    }

    /**
     * @param string $module
     * @param context $context
     * @return bool
     */
    public static function can_edit($module, $context, $required = false) {
        $capname = self::$modules[$module]['editcap'];
        $hascap = has_capability($capname, $context);
        if ($required && !$hascap) {
            throw new required_capability_exception($context, $capname, 'nopermissions', '');
        }
        return $hascap;
    }

    public static function require_edit($module, $context) {
        self::can_edit($module, $context, true);
    }

    /**
     * @param string $module
     * @param context $context
     * @return bool
     */
    public static function can_print($module, $context) {
        return has_capability(self::$modules[$module]['printcap'], $context);
    }

    public static function per_user($module) {
        return self::$modules[$module]['peruser'];
    }

    // ...colunmkeys to send by a ajax request from the lernschritte table.
    public static $columnkeys = array(
        'id', 'attendancetype', 'starttime', 'duration', 'learninggoal',
        'learningcontent', 'collaborationtype', 'learnersactivity',
        'teachersactivity', 'usedmaterials', 'homework', 'options'
    );

    // Create a instance of this class.
    public static function instance() {
        global $lernschritte;

        if (isset($lernschritte)) {
            return $lernschritte;
        }

        $lernschritte = new klassenbuchtool_lernschritte();
        return $lernschritte;
    }

    /** get all the columns data to display initially in the lernschritte table
     *
     * @param int $chapterid , the id of the chapter from klassenbuch
     * @param context $context , the context of the module klassenbuch
     * @param $module
     * @return array , list of columnsdata.
     */
    public function get_columns_data($chapterid, $context, $module) {
        global $DB, $USER;

        $params = array('chapterid' => $chapterid, 'module' => $module);
        if (self::per_user($module)) {
            $params['userid'] = $USER->id;
        }
        $lernschritte = $DB->get_records('klassenbuchtool_lernschritte', $params, 'sortorder');
        if (!$lernschritte) {
            return array();
        }

        /*$options = "";

        if (self::can_edit($module, $context)) {

            $strsave = get_string('savechanges');
            $saveicon = $OUTPUT->pix_icon('save', $strsave, 'klassenbuchtool_lernschritte', array('title' => $strsave, 'class' => 'iconsmall'));

            $strdelete = get_string('delete');
            $delicon = $OUTPUT->pix_icon('t/delete', $strdelete, 'moodle', array('title' => $strdelete, 'class' => 'iconsmall'));

            $strsortorder = get_string('sortorderrow', 'klassenbuchtool_lernschritte');
            $ordericon = $OUTPUT->pix_icon('i/move_2d', $strsortorder, 'moodle', array('title' => $strsortorder, 'class' => 'iconsmall'));

            $options .= html_writer::link('#', $saveicon, array('id' => "save-{$chapterid}"));
            $options .= html_writer::tag('span', $ordericon, array('class' => 'sorticon'));
            $options .= html_writer::link('#', $delicon, array('id' => "delete-{$chapterid}"));
        }*/

        foreach ($lernschritte as $lernschritt) {
            $lernschritt->options = "";
        }

        return array_values($lernschritte);
    }

    /** save or update a lernschritt from an ajax request. if an error occurs, return a
     *  appropriate error message.
     *
     * @param int $courseid, id of course we are in.
     * @param int $chapterid, the id of the chapter from klassenbuch
     * @return array, array to convert in JSON object for AJAX response
     */
    public function save_ajax($courseid, $chapterid, $module) {
        global $DB, $USER;

        $lernschritt = new stdClass();

        $lernschritt->courseid = $courseid;
        $lernschritt->chapterid = $chapterid;
        $lernschritt->module = $module;

        // ...getting more params form submitted data.
        $lernschritt->attendancetype = optional_param('attendancetype', '', PARAM_TEXT);
        $lernschritt->starttime = optional_param('starttime', '', PARAM_TEXT);
        $lernschritt->duration = optional_param('duration', '', PARAM_TEXT);
        $lernschritt->learninggoal = optional_param('learninggoal', '', PARAM_TEXT);
        $lernschritt->learningcontent = optional_param('learningcontent', '', PARAM_TEXT);
        $lernschritt->collaborationtype = optional_param('collaborationtype', '', PARAM_TEXT);
        $lernschritt->learnersactivity = optional_param('learnersactivity', '', PARAM_TEXT);
        $lernschritt->teachersactivity = optional_param('teachersactivity', '', PARAM_TEXT);
        $lernschritt->usedmaterials = optional_param('usedmaterials', '', PARAM_TEXT);
        $lernschritt->homework = optional_param('homework', '', PARAM_TEXT);
        $lernschritt->userid = self::per_user($module) ? $USER->id : 0;

        $id = optional_param('id', 0, PARAM_INT);

        if ($id > 0) {
            // Make sure we're not changing one of the fields we shouldn't mess with.
            $existing = $DB->get_record('klassenbuchtool_lernschritte', array('id' => $id), '*', MUST_EXIST);
            foreach (array('courseid', 'chapterid', 'module', 'userid') as $field) {
                if ($existing->$field != $lernschritt->$field) {
                    throw new moodle_exception('mustnotchangefield', 'klassenbuchtool_lernschritte', null, $field);
                }
            }

            $lernschritt->id = $id;
            $DB->update_record('klassenbuchtool_lernschritte', $lernschritt);
        } else {

            $lernschritt->id = $DB->insert_record('klassenbuchtool_lernschritte', $lernschritt);

            // ... adjust the sort order, when a new record is inserted.
            $ids = optional_param('sortorder', "", PARAM_TEXT);

            if (!empty($ids)) {

                $sortorder = explode(",", $ids);

                // ... replace old id with insertedid.
                foreach ($sortorder as $val => $sortid) {
                    if ($id == $sortid) {
                        $sortorder[$val] = $lernschritt->id;
                    }
                }
                $this->save_sortorder_ajax($sortorder, $chapterid, $module);
            }
        }

        return array('lernschritt' => $lernschritt);
    }

    /** delete a lernschritt from the table
     *
     * @param int $chapterid
     * @param string $module
     * @return array , array to convert in JSON object for AJAX response
     * @throws coding_exception
     */
    public function delete_ajax($chapterid, $module) {
        global $DB, $USER;

        $id = optional_param('id', 0, PARAM_INT);
        if ($id > 0) {
            $params = array('id' => $id, 'chapterid' => $chapterid, 'module' => $module);
            if (self::per_user($module)) {
                $params['userid'] = $USER->id;
            }
            $DB->delete_records('klassenbuchtool_lernschritte', $params);
        }
        return array('success' => 1);
    }

    /** save the sortorder (which is normally sent by ajax-request)
     *
     * @param array $sortorder , array of ids of lernschritte object in correct order.
     * @param int $chapterid
     * @param string $module
     * @return array , array to convert in JSON object for AJAX response
     */
    public function save_sortorder_ajax($sortorder, $chapterid, $module) {
        global $DB, $USER;

        $params = array('chapterid' => $chapterid, 'module' => $module);
        if (self::per_user($module)) {
            $params['userid'] = $USER->id;
        }
        foreach ($sortorder as $val => $id) {
            $params['id'] = $id;
            $DB->set_field('klassenbuchtool_lernschritte', 'sortorder', $val, $params);
        }
        return array('success' => 1);
    }

    /** render the html part (a div and some buttons) and add the edittable.js to
     * create the table with yui.
     *
     * @param int $chapterid , the id of the chapter from klassenbuch
     * @param context $context , the context of the module klassenbuch
     * @param $module
     * @return string , the HTML-part of rendering.
     * @throws coding_exception
     */
    public function render_lernschritte_table($chapterid, $context, $module) {
        global $PAGE, $OUTPUT;

        // Set up columns.
        $args = array();

        $args['ajaxurl'] = '/mod/klassenbuch/tool/lernschritte/ajax.php';
        $args['colkeys'] = self::$columnkeys;

        $args['data'] = $this->get_columns_data($chapterid, $context, $module);
        $args['chapterid'] = $chapterid;
        $args['module'] = $module;

        $args['options'] = new stdClass();
        $args['options']->collaborationtype = array(
            "",
            get_string('teamwork', 'klassenbuchtool_lernschritte'),
            get_string('job', 'klassenbuchtool_lernschritte'),
            get_string('pairwork', 'klassenbuchtool_lernschritte'),
            get_string('plenum', 'klassenbuchtool_lernschritte'));

        $args['options']->attendancetype = array(
            "",
            get_string('facetoface', 'klassenbuchtool_lernschritte'),
            get_string('online', 'klassenbuchtool_lernschritte')
        );

        $output = html_writer::tag('div', '', array('id' => 'dtable'));

        if (self::can_edit($module, $context)) {

            $stradd = get_string('addrow', 'klassenbuchtool_lernschritte');
            $addicon = $OUTPUT->pix_icon('t/add', $stradd, 'moodle', array('title' => $stradd));
            $addlink = html_writer::link('#', $addicon . " " . $stradd, array('id' => "addrow"));
            $output .= html_writer::tag('div', $addlink, array('class' => 'addrow'));

            // Html for the options column.
            $strsave = get_string('savechanges');
            $saveicon = $OUTPUT->pix_icon('save', $strsave, 'klassenbuchtool_lernschritte', array('title' => $strsave,
                                                                                                  'class' => 'iconsmall'));

            $strdelete = get_string('delete');
            $delicon = $OUTPUT->pix_icon('t/delete', $strdelete, 'moodle', array('title' => $strdelete, 'class' => 'iconsmall'));

            $strsortorder = get_string('sortorderrow', 'klassenbuchtool_lernschritte');
            $ordericon = $OUTPUT->pix_icon('i/move_2d', $strsortorder, 'moodle', array('title' => $strsortorder,
                                                                                       'class' => 'iconsmall'));

            $actions = html_writer::link('#', $saveicon, array('id' => "save"));
            $actions .= html_writer::tag('span', $ordericon, array('class' => 'sorticon'));
            $actions .= html_writer::link('#', $delicon, array('id' => "delete"));

            $args['actions'] = $actions;
            $args['nooptions'] = false;

        } else {

            $args['actions'] = '';
            $args['nooptions'] = true;
        }

        if (self::can_print($module, $context)) {
            $url = new moodle_url('/mod/klassenbuch/tool/lernschritte/createpdf.php',
                                  array('chapterid' => $chapterid, 'module' => $module));
            $output .= $OUTPUT->single_button($url, get_string('createpdf', 'klassenbuchtool_lernschritte'));
        }

        $PAGE->requires->yui_module('moodle-klassenbuchtool_lernschritte-edittable', 'M.klassenbuch_lernschritte.init',
                                    array($args), null, true);
        $PAGE->requires->strings_for_js(self::$columnkeys, 'klassenbuchtool_lernschritte');
        $PAGE->requires->strings_for_js(array('confirmdelete'), 'klassenbuchtool_lernschritte');

        return $output;
    }

    /** render a row of a global field for the chapter information table
     *
     * @param string $title, title of the global field
     * @param string $content, content of the global field
     * @return \html_table_row
     */
    private function render_pdf_globalfield_row($title, $content) {
        $row = new html_table_row();

        $cell = new html_table_cell();
        $cell->attributes['width'] = '60mm';
        $cell->attributes['align'] = 'left';
        $cell->text = $title;
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = $content;
        $row->cells[] = $cell;

        return $row;
    }

    /** render the chapter information for printing pdf
     *
     * @param record $chapter, the current chapter
     * @return string, HTML for the chapter information.
     */
    protected function render_pdf_chapter_information($chapter, $module) {
        global $DB;

        $table = new html_table();
        $table->attributes['border'] = "0.1";
        $table->cellpadding = '3';

        $table->data[] = $this->render_pdf_globalfield_row(get_string('title', 'klassenbuchtool_lernschritte'), $chapter->title);

        // ...retrieve global fields.
        $config = get_config('klassenbuchtool_lernschritte');

        if ($module == 'klassenbuch' && !empty($config->globalfieldrows)) {

            $globalfieldstitles = explode(",", $config->globalfieldrows);

            if (!empty($globalfieldstitles)) {

                list($intitles, $params) = $DB->get_in_or_equal($globalfieldstitles);
                $params[] = $chapter->id;

                $sql = "SELECT gf.id, gf.title, cf.content FROM {klassenbuch_globalfields} gf
                        JOIN {klassenbuch_chapter_fields} cf ON cf.fieldid = gf.id
                        WHERE gf.title {$intitles} and cf.chapterid = ?";

                if ($fieldsdata = $DB->get_records_sql($sql, $params)) {

                    foreach ($fieldsdata as $data) {
                        $table->data[] = $this->render_pdf_globalfield_row($data->title, $data->content);
                    }
                }
            }
        }

        return html_writer::table($table);
    }

    /** render a table cell for the pdf lernschritte table.
     *
     * @param string $key, key of column
     * @param array $colwidths, list of columnswidth per key
     * @param array $colaligns, list of columnsaligns per key
     * @param string $text
     * @param string $defaultwidth
     * @return \html_table_cell
     */
    private function render_pdf_lernschritte_cell($key, $colwidths, $colaligns, $text, $defaultwidth = '30mm') {

        $cell = new html_table_cell();
        $cell->attributes['width'] = (isset($colwidths[$key])) ? $colwidths[$key] : $defaultwidth;
        $cell->attributes['align'] = (isset($colaligns[$key])) ? $colaligns[$key] : 'left';
        $cell->text = $text;

        return $cell;
    }

    /** render the $chapters lernschritte table for pdf
     *
     * @param object $chapter
     * @return string the HTML for pdf-printing.
     */
    protected function render_pdf_lernschritte_table($chapter, $module) {
        global $DB, $USER;

        $params = array('chapterid' => $chapter->id, 'module' => $module);
        if (self::per_user($module)) {
            $params['userid'] = $USER->id;
        }
        $lernschritte = $DB->get_records('klassenbuchtool_lernschritte', $params, 'sortorder');
        if (!$lernschritte) {
            $lernschritte = array();
        }

        $config = get_config('klassenbuchtool_lernschritte');

        $table = new html_table();
        $table->attributes['border'] = "0.1";
        $table->cellpadding = '3';

        // ...setup table with settings.
        $colwidths = explode(',', $config->columnswidth);
        $pdfcolumns = explode(',', $config->pdfcolumns);
        $colaligns = explode(',', $config->columnsalign);

        $i = 0;
        foreach ($pdfcolumns as $key) {
            $table->head[$key] = html_writer::tag('b', get_string($key, 'klassenbuchtool_lernschritte'));
            $table->size[$key] = (!empty($colwidths[$i])) ? $colwidths[$i] : '30mm';
            $table->align[$key] = (!empty($colaligns[$i])) ? $colaligns[$i] : 'left';
            $i++;
        }

        $i = 0;
        foreach ($lernschritte as $lernschritt) {
            $i++;

            $cells = (array) $lernschritt;
            $cells['duration'] = $cells['duration'] . " mins";

            $cells = array_intersect_key($cells, array_flip(array_keys($table->head)));

            // attendanceext_addfields_render_user_session_log($row, $sess, $userdata);
            $row = new html_table_row();

            foreach ($cells as $key => $cell) {
                $row->cells[] = $this->render_pdf_lernschritte_cell($key, $table->size, $table->align, $cell);
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /** get the content of the pdf to print
     *
     * @param object $course
     * @param object $instance
     * @param object $chapter
     * @return string content of the pdf (overview and lernschritte table)
     */
    protected function get_pdf_content($course, $instance, $chapter, $module) {

        $output = html_writer::tag('h2', get_string('classplan', 'klassenbuchtool_lernschritte') . ": " . $course->fullname);
        $output .= $this->render_pdf_chapter_information($chapter, $module);
        $output .= "<br/><br/>";
        $output .= $this->render_pdf_lernschritte_table($chapter, $module);
        return $output;
    }

    /** generate and output the chapter as a PDF document
     * @param object $course
     * @param object $instance
     * @param object $chapter
     * @param string $module
     */
    public function output_pdf($course, $instance, $chapter, $module) {
        global $USER, $SITE;

        require_once(dirname(__FILE__) . '/class.classplan_pdf.php');
        $config = get_config('klassenbuchtool_lernschritte');

        // ...create new PDF document.
        $pdf = new classplan_pdf($config->pageorientation, 'mm', 'A4', true, 'UTF-8', false);

        $pdf->set_info_data($course->fullname);

        // ...set some Metainformations.
        $pdf->SetCreator($SITE->fullname);
        $pdf->SetAuthor(fullname($USER));
        $pdf->SetTitle(get_string('classplan', 'klassenbuchtool_lernschritte'));
        $pdf->SetSubject($course->fullname . " (" . userdate(time()) . ")");
        $pdf->SetKeywords($course->fullname);
        $pdf->setHeaderMargin(5);
        $pdf->SetMargins($config->marginleftright, 20);

        // $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont("helvetica", "", 11);

        // ...generate PDF.
        $pdf->writeHTML($this->get_pdf_content($course, $instance, $chapter, $module), true, false, true, false, '');
        $pdf->Output("participants.pdf", "I");
    }

}