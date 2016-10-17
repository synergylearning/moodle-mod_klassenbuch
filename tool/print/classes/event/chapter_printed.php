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
 * klassenbuchtool_print chapter printed event.
 *
 * @package    klassenbuchtool_print
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace klassenbuchtool_print\event;
defined('MOODLE_INTERNAL') || die();

/**
 * klassenbuchtool_print chapter printed event class.
 *
 * @package    klassenbuchtool_print
 * @since      Moodle 2.6
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chapter_printed extends \core\event\base {
    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param \stdClass $klassenbuch
     * @param \context_module $context
     * @param \stdClass $chapter
     * @return chapter_printed
     */
    public static function create_from_chapter(\stdClass $klassenbuch, \context_module $context, \stdClass $chapter) {
        $data = array(
            'context' => $context,
            'objectid' => $chapter->id,
        );
        /** @var chapter_printed $event */
        $event = self::create($data);
        $event->add_record_snapshot('klassenbuch', $klassenbuch);
        $event->add_record_snapshot('klassenbuch_chapters', $chapter);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has printed the chapter with id '$this->objectid' of the klassenbuch with " .
            "course module id '$this->contextinstanceid'.";
    }

    /**
     * Return the legacy event log data.
     *
     * @return array|null
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'klassenbuch', 'print chapter', 'tool/print/index.php?id=' . $this->contextinstanceid .
            '&chapterid=' . $this->objectid, $this->objectid, $this->contextinstanceid);
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventchapterprinted', 'klassenbuchtool_print');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/klassenbuch/tool/print/index.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'klassenbuch';
    }

}
