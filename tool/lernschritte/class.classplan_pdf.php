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
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/lib/pdflib.php');

class classplan_pdf extends PDF {

    protected $coursename;

    /** sets the name of the Course for displaying in header
     * 
     * @param string $coursename
     */
    public function set_info_data($coursename) {
        $this->coursename = $coursename;
    }

    /** overrides the Header() - Method to display Page-Number. Coursename must
     *  be set before!
     */
    public function Header() {
        global $CFG;

        $this->SetFont('helvetica', '', 11);
        $this->Cell(0, 20, userdate(time()), '0', 0, 'R', false, '', 0, false, 'T', 'L');
        $this->SetFont('helvetica', 'B', 20);
    }

    /** overrides the Footer() - Method to display Page-Number */
    public function Footer() {

        $this->SetY(-20);
        $this->SetFont('helvetica', '', 11);
        $this->Cell(0, 10, get_string('page') . ' ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 'T', 0, 'C', 0, '', 0, true, 'T', 'R');
    }
}