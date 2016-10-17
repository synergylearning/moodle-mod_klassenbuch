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
 * Functions to support PDF creation.
 *
 * @package   klassenbuchtool_print
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace klassenbuchtool_print;

defined('MOODLE_INTERNAL') || die();

class pdf_helper {
    public static function replace_special($str) {
        // Rescue some special fonts first.
        $str = htmlentities($str, null, 'UTF-8', false);
        $str = str_replace(array('&amp;', '&quot;', '&#039;', '&lt;', '&gt;'), array('&', '"', "'", '<', '>'), $str);
        $chunked = str_split($str, 1);
        $str = "";
        foreach ($chunked as $chunk) {
            $num = ord($chunk);
            // Remove non-ascii & non html characters.
            if ($num >= 32 && $num <= 127) {
                $str .= $chunk;
            }
        }
        return $str;
    }

    /**
     * Find all image tags that are linked to the current Klassenbuch (component = 'mod_klassenbuch',
     * context = module context) and replace the image URL with the encoded file content.
     * @param $content
     * @param $contextid
     * @return mixed
     */
    public static function fix_images($content, $contextid) {
        global $CFG;
        $baseurl = $CFG->wwwroot.'/pluginfile.php/'.$contextid.'/mod_klassenbuch/';
        $baseurlq = preg_quote($baseurl);
        $regex = "|<img[^>]*src=['\"]$baseurlq([^'\"]*?)['\"]|";
        if (preg_match_all($regex, $content, $matches)) {
            $fs = get_file_storage();
            foreach ($matches[1] as $imgurl) {
                $details = urldecode($imgurl);
                $parts = explode('/', $details);
                $area = array_shift($parts);
                $itemid = array_shift($parts);
                $name = array_pop($parts);
                if (empty($parts)) {
                    $path = '/';
                } else {
                    $path = '/'.implode('/', $parts).'/';
                }
                if ($file = $fs->get_file($contextid, 'mod_klassenbuch', $area, $itemid, $path, $name)) {
                    $find = $baseurl.$imgurl;
                    $replace = '@'.base64_encode($file->get_content());
                    $content = str_replace($find, $replace, $content);
                }
            }
        }
        return $content;
    }

    /**
     * Remove styles (from copy+paste) that mess up the PDF export.
     * @param string $content
     * @return string
     */
    public static function tidy_styles($content) {
        $regex = '#<[^>]*style="([^"]*)"#i';
        $content = preg_replace_callback($regex, array('self', 'remove_bad_styles'), $content);
        return $content;
    }

    private static function remove_bad_styles($matches) {
        $allowedstyles = array('color');

        $oldstyles = $matches[1];
        $newstyles = array();

        // Loop through all the styles, keeping those that are allowed.
        $styles = explode(';', $oldstyles);
        foreach ($styles as $style) {
            $style = explode(':', $style);
            if (count($style) != 2) {
                continue; // Invalid style - skip it.
            }
            $style = array_map('trim', $style);
            list($stylename, $stylevalue) = $style;

            if (!in_array($stylename, $allowedstyles)) {
                continue; // Not a whitelisted style - skip it.
            }
            $newstyles[] = $stylename.':'.$stylevalue;
        }

        $newstyles = implode(';', $newstyles);
        return str_replace($oldstyles, $newstyles, $matches[0]);
    }
}