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
 * PDF class override
 *
 * @package   klassenbuchtool_print
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace klassenbuchtool_print;

use Exception;
use pdf;

defined('MOODLE_INTERNAL') || die();

class klassenbuch_pdf extends pdf {

    // Page header.
    public function Header() {
        $style = array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 64, 128));
        $this->Line(10, 7, 200, 7, $style);
        // Set font.
        $this->SetFont('helvetica', 'B', 20);
    }

    // Page footer.
    public function Footer() {
        $style = array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 64, 128));
        // Position at 15 mm from bottom.
        $this->SetY(-15);
        $this->Line(10, 280, 200, 280, $style);
        // Set font.
        $this->SetFont('helvetica', 'I', 8);
        // Page number.
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    private $ignoreimageerror = false;

    public function Image($file, $x = '', $y = '', $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false, $altimgs = array()) {

        $ret = '';
        try {
            $this->ignoreimageerror = true;
            $ret = parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox, $hidden, $fitonpage, $alt, $altimgs);
        } catch (Exception $e) {
            // Just ignore any exceptions - return a blank string for the broken image.
        }

        $this->ignoreimageerror = false;
        return $ret;
    }

    public function Error($msg) {
        if ($this->ignoreimageerror) {
            // Just throw an exception for image errors, rather than doing any clean-up.
            // The exception will be caught in the above function.
            throw new Exception();
        }
        // All other errors should be handled as normal.
        parent::Error($msg);
    }

}
