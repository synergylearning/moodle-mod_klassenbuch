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
 * Extra Behat steps for Klassenbuch module
 *
 * @package   mod_klassenbuch
 * @copyright 2015 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode as TableNode;

class behat_mod_klassenbuch extends behat_base {
    /**
     * @Given /^the following klassenbuch global fields exist:$/
     * @param TableNode $data
     */
    public function the_following_klassenbuch_global_fields_exist(TableNode $data) {
        global $DB;
        $datahash = $data->getHash();

        $requiredparams = array('title');
        $optionalparams = array('hidden' => 0);

        // Check the required / optional fields.
        $firstrow = reset($datahash);
        $keys = array_keys($firstrow);
        foreach ($requiredparams as $requiredparam) {
            if (!in_array($requiredparam, $keys)) {
                throw new Exception('Klassenbuch global fields require the field '.$requiredparam.' to be set');
            }
        }
        foreach ($keys as $key) {
            if (!in_array($key, $requiredparams) && !array_key_exists($key, $optionalparams)) {
                throw new Exception('Klassenbuch global fields unknown field '.$key);
            }
        }

        // Create the global fields.
        foreach ($datahash as $row) {
            $newitem = $optionalparams;
            foreach ($row as $fieldname => $value) {
                if ($value === '') {
                    if (in_array($fieldname, $requiredparams)) {
                        throw new Exception('Klassenbuch global fields, '.$fieldname.' cannot be blank');
                    } else {
                        continue; // Leave empty optional items at their default.
                    }
                }
                $newitem[$fieldname] = $value;
            }
            $DB->insert_record('klassenbuch_globalfields', (object)$newitem);
        }
    }
}
