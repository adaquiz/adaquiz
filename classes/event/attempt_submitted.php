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
 * The mod_quiz attempt submitted event.
 *
 * @package   mod_adaquiz
 * @copyright 2015 Maths for More S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_adaquiz\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_adaquiz attempt submitted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int submitterid: id of submitter (null when trigged by CLI script).
 *      - int adaquizid: (optional) the id of the adaptive quiz.
 * }
 *
 */
class attempt_submitted extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'adaquiz_attempts';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->relateduserid' has submitted the attempt with id '$this->objectid' for the " .
            "adaptive quiz with course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventquizattemptsubmitted', 'mod_adaquiz');
    }

    /**
     * Does this event replace a legacy event?
     *
     * @return string legacy event name
     */
    static public function get_legacy_eventname() {
        return 'adaquiz_attempt_submitted';
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/adaquiz/review.php', array('attempt' => $this->objectid));
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * @return \stdClass
     */
    protected function get_legacy_eventdata() {
        $attempt = $this->get_record_snapshot('adaquiz_attempts', $this->objectid);

        $legacyeventdata = new \stdClass();
        $legacyeventdata->component = 'mod_adaquiz';
        $legacyeventdata->attemptid = $this->objectid;
        $legacyeventdata->timestamp = $attempt->timefinish;
        $legacyeventdata->userid = $this->relateduserid;
        $legacyeventdata->adaquizid = $attempt->quiz;
        $legacyeventdata->cmid = $this->contextinstanceid;
        $legacyeventdata->courseid = $this->courseid;
        $legacyeventdata->submitterid = $this->other['submitterid'];
        $legacyeventdata->timefinish = $attempt->timefinish;

        return $legacyeventdata;
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!array_key_exists('submitterid', $this->other)) {
            throw new \coding_exception('The \'submitterid\' value must be set in other.');
        }
    }
}
