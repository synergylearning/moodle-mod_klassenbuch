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
 * Klassenbuch module local lib functions
 *
 * @package    mod_klassenbuch
 * @copyright  2012 Synergy Learning / Davo Smith (based on book module)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/klassenbuch/lib.php');
require_once($CFG->libdir.'/filelib.php');

define('KLASSENBUCH_NUM_NONE', '0');
define('KLASSENBUCH_NUM_NUMBERS', '1');
define('KLASSENBUCH_NUM_BULLETS', '2');
define('KLASSENBUCH_NUM_INDENTED', '3');

/**
 * Preload klassenbuch chapters and fix toc structure if necessary.
 *
 * Returns array of chapters with standard 'pagenum', 'id, pagenum, subchapter, title, hidden'
 * and extra 'parent, number, subchapters, prev, next'.
 * Please note the content/text of chapters is not included.
 *
 * @param  stdClass $klassenbuch
 * @return array of id=>chapter
 */
function klassenbuch_preload_chapters($klassenbuch) {
    global $DB;
    $chapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $klassenbuch->id), 'pagenum',
                                 'id, pagenum, subchapter, title, hidden');
    if (!$chapters) {
        return array();
    }

    $prev = null;
    $prevsub = null;

    $first = true;
    $hidesub = true;
    $parent = null;
    $pagenum = 0; // Chapter sort.
    $i = 0; // Main chapter num.
    $j = 0; // Subchapter num.
    foreach ($chapters as $id => $ch) {
        $oldch = clone($ch);
        $pagenum++;
        $ch->pagenum = $pagenum;
        if ($first) {
            // Klassenbuch can not start with a subchapter.
            $ch->subchapter = 0;
            $first = false;
        }
        if (!$ch->subchapter) {
            $ch->prev = $prev;
            $ch->next = null;
            if ($prev) {
                $chapters[$prev]->next = $ch->id;
            }
            if ($ch->hidden) {
                if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
                    $ch->number = 'x';
                } else {
                    $ch->number = null;
                }
            } else {
                $i++;
                $ch->number = $i;
            }
            $j = 0;
            $prevsub = null;
            $hidesub = $ch->hidden;
            $parent = $ch->id;
            $ch->parent = null;
            $ch->subchpaters = array();
        } else {
            $ch->prev = $prevsub;
            $ch->next = null;
            if ($prevsub) {
                $chapters[$prevsub]->next = $ch->id;
            }
            $ch->parent = $parent;
            $ch->subchpaters = null;
            $chapters[$parent]->subchapters[$ch->id] = $ch->id;
            if ($hidesub) {
                // All subchapters in hidden chapter must be hidden too.
                $ch->hidden = 1;
            }
            if ($ch->hidden) {
                if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
                    $ch->number = 'x';
                } else {
                    $ch->number = null;
                }
            } else {
                $j++;
                $ch->number = $j;
            }
        }
        if ($oldch->subchapter != $ch->subchapter or $oldch->pagenum != $ch->pagenum or $oldch->hidden != $ch->hidden) {
            // Update only if something changed.
            $DB->update_record('klassenbuch_chapters', $ch);
        }
        $chapters[$id] = $ch;
    }

    return $chapters;
}

function klassenbuch_get_chapter_title($chid, $chapters, $klassenbuch, $context) {
    $ch = $chapters[$chid];
    $title = trim(format_string($ch->title, true, array('context' => $context)));
    $numbers = array();
    if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
        if ($ch->parent and $chapters[$ch->parent]->number) {
            $numbers[] = $chapters[$ch->parent]->number;
        }
        if ($ch->number) {
            $numbers[] = $ch->number;
        }
    }

    if ($numbers) {
        $title = implode('.', $numbers).' '.$title;
    }

    return $title;
}

/**
 * General logging to table
 * @param string $str1
 * @param string $str2
 * @param int $level
 * @return void
 */
function klassenbuch_log($str1, $str2, $level = 0) {
    switch ($level) {
        case 1:
            echo '<tr><td><span class="dimmed_text">'.$str1.'</span></td><td><span class="dimmed_text">'.$str2.'</span></td></tr>';
            break;
        case 2:
            echo '<tr><td><span style="color: rgb(255, 0, 0);">'.$str1.'</span></td><td><span style="color: rgb(255, 0, 0);">'.
                $str2.'</span></td></tr>';
            break;
        default:
            echo '<tr><td>'.$str1.'</class></td><td>'.$str2.'</td></tr>';
            break;
    }
}

function klassenbuch_add_fake_block($chapters, $chapter, $klassenbuch, $cm, $edit) {
    global $OUTPUT, $PAGE;

    $toc = klassenbuch_get_toc($chapters, $chapter, $klassenbuch, $cm, $edit, 0);

    if ($edit) {
        $toc .= '<div class="klassenbuch_faq">';
        $toc .= $OUTPUT->help_icon('faq', 'mod_klassenbuch', get_string('faq', 'mod_klassenbuch'));
        $toc .= '</div>';
    }

    $bc = new block_contents();
    $bc->title = get_string('toc', 'mod_klassenbuch');
    $bc->attributes['class'] = 'block';
    $bc->content = $toc;

    $regions = $PAGE->blocks->get_regions();
    $firstregion = reset($regions);
    $PAGE->blocks->add_fake_block($bc, $firstregion);

    // SYNERGY - add javascript to control subchapter collapsing.
    if (!$edit) {
        $jsmodule = array(
            'name' => 'mod_klassenbuch_collapse',
            'fullpath' => new moodle_url('/mod/klassenbuch/collapse.js'),
            'requires' => array('yui2-treeview')
        );

        $PAGE->requires->js_init_call('M.mod_klassenbuch_collapse.init', array(), true, $jsmodule);
    }
    // SYNERGY - add javascript to control subchapter collapsing.
}

/**
 * Generate toc structure
 *
 * @param array $chapters
 * @param stdClass $chapter
 * @param stdClass $klassenbuch
 * @param stdClass $cm
 * @param bool $edit
 * @return string
 */
function klassenbuch_get_toc($chapters, $chapter, $klassenbuch, $cm, $edit) {
    global $USER, $OUTPUT;

    $toc = ''; // Representation of toc (HTML).
    $nch = 0; // Chapter number.
    $ns = 0; // Subchapter number.
    $first = 1;

    $context = context_module::instance($cm->id);

    if (has_capability('mod/klassenbuch:edit', $context)) {
        $addicon = $OUTPUT->pix_icon('add', '', 'mod_klassenbuch');
        $addurl = new moodle_url('/mod/klassenbuch/edit.php', array('cmid' => $cm->id, 'pagenum' => 0));
        $addtext = $addicon.' '.get_string('addafter', 'mod_klassenbuch');
        $toc .= html_writer::link($addurl, $addtext).html_writer::empty_tag('br');
    }

    // SYNERGY - add 'klassenbuch-toc' ID.
    $tocid = ' id="klassenbuch-toc" ';
    switch ($klassenbuch->numbering) {
        case KLASSENBUCH_NUM_NONE:
            $toc .= '<div class="klassenbuch_toc_none" '.$tocid.'>';
            break;
        case KLASSENBUCH_NUM_NUMBERS:
            $toc .= '<div class="klassenbuch_toc_numbered" '.$tocid.'>';
            break;
        case KLASSENBUCH_NUM_BULLETS:
            $toc .= '<div class="klassenbuch_toc_bullets" '.$tocid.'>';
            break;
        case KLASSENBUCH_NUM_INDENTED:
            $toc .= '<div class="klassenbuch_toc_indented" '.$tocid.'>';
            break;
    }
    // SYNERGY - add 'klassenbuch-toc' ID.

    if ($edit) { // Teacher's TOC.
        $toc .= '<ul>';
        $i = 0;
        foreach ($chapters as $ch) {
            $i++;
            $title = trim(format_string($ch->title, true, array('context' => $context)));
            if (!$ch->subchapter) {
                $toc .= ($first) ? '<li>' : '</ul></li><li>';
                if (!$ch->hidden) {
                    $nch++;
                    $ns = 0;
                    if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
                        $title = "$nch $title";
                    }
                } else {
                    if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
                        $title = "x $title";
                    }
                    $title = '<span class="dimmed_text">'.$title.'</span>';
                }
            } else {
                $toc .= ($first) ? '<li><ul><li>' : '<li>';
                if (!$ch->hidden) {
                    $ns++;
                    if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
                        $title = "$nch.$ns $title";
                    }
                } else {
                    if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
                        $title = "x.x $title";
                    }
                    $title = '<span class="dimmed_text">'.$title.'</span>';
                }
            }

            if ($ch->id == $chapter->id) {
                $toc .= '<strong>'.$title.'</strong>';
            } else {
                $toc .= '<a title="'.s($title).'" href="view.php?id='.$cm->id.'&amp;chapterid='.$ch->id.'">'.$title.'</a>';
            }
            $toc .= '&nbsp;&nbsp;';
            if ($i != 1) {
                $toc .= ' <a title="'.get_string('up').'" href="move.php?id='.$cm->id.'&amp;chapterid='.$ch->id.
                    '&amp;up=1&amp;sesskey='.$USER->sesskey.'"><img src="'.$OUTPUT->pix_url('t/up').'" class="iconsmall" alt="'.
                    get_string('up').'" /></a>';
            }
            if ($i != count($chapters)) {
                $toc .= ' <a title="'.get_string('down').'" href="move.php?id='.$cm->id.'&amp;chapterid='.$ch->id.
                    '&amp;up=0&amp;sesskey='.$USER->sesskey.'"><img src="'.$OUTPUT->pix_url('t/down').'" class="iconsmall" alt="'.
                    get_string('down').'" /></a>';
            }
            $toc .= ' <a title="'.get_string('edit').'" href="edit.php?cmid='.$cm->id.'&amp;id='.$ch->id.'"><img src="'.
                $OUTPUT->pix_url('t/edit').'" class="iconsmall" alt="'.get_string('edit').'" /></a>';
            $toc .= ' <a title="'.get_string('delete').'" href="delete.php?id='.$cm->id.'&amp;chapterid='.$ch->id.'&amp;sesskey='.
                $USER->sesskey.'"><img src="'.$OUTPUT->pix_url('t/delete').'" class="iconsmall" alt="'.
                get_string('delete').'" /></a>';
            if ($ch->hidden) {
                $toc .= ' <a title="'.get_string('show').'" href="show.php?id='.$cm->id.'&amp;chapterid='.$ch->id.'&amp;sesskey='.
                    $USER->sesskey.'"><img src="'.$OUTPUT->pix_url('t/show').'" class="iconsmall" alt="'.
                    get_string('show').'" /></a>';
            } else {
                $toc .= ' <a title="'.get_string('hide').'" href="show.php?id='.$cm->id.'&amp;chapterid='.$ch->id.'&amp;sesskey='.
                    $USER->sesskey.'"><img src="'.$OUTPUT->pix_url('t/hide').'" class="iconsmall" alt="'.
                    get_string('hide').'" /></a>';
            }
            $toc .= ' <a title="'.get_string('addafter', 'mod_klassenbuch').'" href="edit.php?cmid='.$cm->id.'&amp;pagenum='.
                $ch->pagenum.'&amp;subchapter='.$ch->subchapter.'"><img src="'.
                $OUTPUT->pix_url('add', 'mod_klassenbuch').'" class="iconsmall" alt="'.get_string('addafter', 'mod_klassenbuch').
                '" /></a>';
            $toc .= ' <a title="'.get_string('send', 'klassenbuch').'" href="send.php?id='.$cm->id.'&amp;chapterid='.$chapter->id.
                '&amp;sesskey='.sesskey().'"><img src="'.$OUTPUT->pix_url('email', 'mod_klassenbuch').
                '" height="11" class="iconsmall" alt="'.get_string('send', 'klassenbuch').'" /></a>';

            $toc .= (!$ch->subchapter) ? '<ul>' : '</li>';
            $first = 0;
        }
        $toc .= '</ul></li></ul>';
    } else { // Normal students view.
        $toc .= '<ul>';
        // SYNERGY - Find the open chapter.
        $currentch = 0;
        $opench = 0;
        foreach ($chapters as $ch) {
            if (!$currentch || !$ch->subchapter) {
                $currentch = $ch->id;
            }
            if ($ch->id == $chapter->id) {
                $opench = $currentch;
                break;
            }
        }
        // SYNERGY - Find the open chapter.
        foreach ($chapters as $ch) {
            $title = trim(format_string($ch->title, true, array('context' => $context)));
            if (!$ch->hidden) {
                if (!$ch->subchapter) {
                    $nch++;
                    $ns = 0;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    $li = '<li>';
                    if ($ch->id == $opench || !$klassenbuch->collapsesubchapters) {
                        $li = '<li class="expanded">';
                    }
                    $toc .= ($first) ? $li : '</ul></li>'.$li;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
                        $title = "$nch $title";
                    }
                } else {
                    $ns++;
                    $toc .= ($first) ? '<li><ul><li>' : '<li>';
                    if ($klassenbuch->numbering == KLASSENBUCH_NUM_NUMBERS) {
                        $title = "$nch.$ns $title";
                    }
                }
                if ($ch->id == $chapter->id) {
                    $toc .= '<strong>'.$title.'</strong>';
                } else {
                    $toc .= '<a title="'.s($title).'" href="view.php?id='.$cm->id.'&amp;chapterid='.$ch->id.'">'.$title.'</a>';
                }
                $toc .= (!$ch->subchapter) ? '<ul>' : '</li>';
                $first = 0;
            }
        }
        $toc .= '</ul></li></ul>';
    }

    $toc .= '</div>';

    $toc = str_replace('<ul></ul>', '', $toc); // Cleanup of invalid structures.

    return $toc;
}

/**
 * File browsing support class
 */
class klassenbuch_file_info extends file_info {
    protected $course;
    protected $cm;
    protected $areas;
    protected $filearea;

    public function __construct($browser, $course, $cm, $context, $areas, $filearea) {
        parent::__construct($browser, $context);
        $this->course = $course;
        $this->cm = $cm;
        $this->areas = $areas;
        $this->filearea = $filearea;
    }

    /**
     * Returns list of standard virtual file/directory identification.
     * The difference from stored_file parameters is that null values
     * are allowed in all fields
     * @return array with keys contextid, filearea, itemid, filepath and filename
     */
    public function get_params() {
        return array(
            'contextid' => $this->context->id,
            'component' => 'mod_klassenbuch',
            'filearea' => $this->filearea,
            'itemid' => null,
            'filepath' => null,
            'filename' => null
        );
    }

    /**
     * Returns localised visible name.
     * @return string
     */
    public function get_visible_name() {
        return $this->areas[$this->filearea];
    }

    /**
     * Can I add new files or directories?
     * @return bool
     */
    public function is_writable() {
        return false;
    }

    /**
     * Is directory?
     * @return bool
     */
    public function is_directory() {
        return true;
    }

    /**
     * Returns list of children.
     * @return array of file_info instances
     */
    public function get_children() {
        global $DB;

        $children = array();
        $chapters = $DB->get_records('klassenbuch_chapters', array('klassenbuchid' => $this->cm->instance),
                                     'pagenum', 'id, pagenum');
        foreach ($chapters as $itemid => $unused) {
            if ($child = $this->browser->get_file_info($this->context, 'mod_klassenbuch', $this->filearea, $itemid)) {
                $children[] = $child;
            }
        }
        return $children;
    }

    /**
     * Returns parent file_info instance
     * @return file_info or null for root
     */
    public function get_parent() {
        return $this->browser->get_file_info($this->context);
    }
}

/*
 * Return an array of custom fields for this chapter, including title
 * @param int chapterid
 */
function klassenbuch_get_chapter_customfields($chapterid) {
    global $DB;

    $sql = "
        SELECT gbf.title, cpf.*
        FROM {klassenbuch_chapter_fields} cpf
        JOIN {klassenbuch_globalfields} gbf ON (gbf.id = cpf.fieldid)
        WHERE cpf.chapterid = :chapterid
        AND cpf.hidden = 0
        ORDER BY gbf.id ASC
        ";
    $params = array('chapterid' => $chapterid);
    return $DB->get_records_sql($sql, $params);
}

/**
 * Return the HTML needed to display links to all subchapters of the current chapter
 * @param $chid
 * @param $chapters
 * @param $klassenbuch
 * @param $context
 * @param $cm
 * @return string - HTML to output
 */
function klassenbuch_get_subchapter_links($chid, $chapters, $klassenbuch, $context, $cm) {
    $ch = $chapters[$chid];
    if ($ch->subchapter) {
        return '';
    }
    $ret = '';
    $found = false;
    // Search for the current chapter, then output links
    // until there are no more subchapters.
    foreach ($chapters as $ch) {
        if ($ch->id == $chid) {
            $found = true;
        } else if ($found) {
            if (!$ch->subchapter) {
                // No more subchapters => return what we have got.
                break;
            }

            $name = Klassenbuch_get_chapter_title($ch->id, $chapters, $klassenbuch, $context);
            $url = new moodle_url('/mod/klassenbuch/view.php', array('id' => $cm->id, 'chapterid' => $ch->id));
            $link = html_writer::link($url, $name);
            $link .= html_writer::empty_tag('br');

            $ret .= $link;
        }
    }

    $ret = html_writer::tag('div', $ret, array('class' => 'klassenbuch_subchapters'));

    return $ret;
}

function klassenbuch_output_attachments($chapterid, $context, $plain = false) {
    global $OUTPUT;

    $fs = get_file_storage();

    $imageoutput = '';
    $output = '';

    $files = $fs->get_area_files($context->id, 'mod_klassenbuch', 'attachment', $chapterid,
                                 "sortorder, timemodified, filename", false);
    if ($files) {
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $path = moodle_url::make_pluginfile_url($context->id, 'mod_klassenbuch', 'attachment', $chapterid, '/', $filename);

            if ($plain) {
                $output .= $path."\n";
            } else {
                $mimetype = $file->get_mimetype();
                $iconimage = '<img src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" class="icon" alt="'.$mimetype.'" />';

                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= format_text("<a href=\"$path\">".s($filename)."</a>", FORMAT_HTML, array('context' => $context));
                $output .= '<br />';
            }
        }
    }

    return $output.$imageoutput;
}