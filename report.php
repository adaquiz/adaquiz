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
 * @package    mod
 * @subpackage adaquiz
 * @copyright  2014 Maths for More S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php'); //Needed to use QUIZ_* constants
require_once($CFG->dirroot . '/mod/adaquiz/locallib.php');
require_once($CFG->dirroot . '/mod/adaquiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/adaquiz/report/default.php');

$id = optional_param('id', 0, PARAM_INT);
$q = optional_param('q', 0, PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);

if ($id) {
    if (!$cm = get_coursemodule_from_id('adaquiz', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    if (!$quizobj = $DB->get_record('adaquiz', array('id' => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

} else {
    if (!$quizobj = $DB->get_record('adaquiz', array('id' => $q))) {
        print_error('invalidquizid', 'adaquiz');
    }
    if (!$course = $DB->get_record('course', array('id' => $quizobj->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("adaquiz", $quizobj->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

$data = new stdClass();
$data->id = $cm->instance;
$adaquiz = new Adaquiz($data);
$quizobj->attempts = 0;
$quizobj->decimalpoints = 2;
$adaquiz->quizobj = $quizobj;
unset($quizobj);

$nodes = $adaquiz->getNodes();
$adaqs = array();
foreach ($nodes as $key => $value) {
    $adaquiz->grades[$value->question] = $value->grade;
    $adaqs[] = $value->question;
}
$adaquiz->quizobj->questions = implode(',', $adaqs) . ',0';

$url = new moodle_url('/mod/adaquiz/report.php', array('id' => $cm->id));
if ($mode !== '') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
$PAGE->set_pagelayout('report');

$reportlist = quiz_report_list($context);
if (empty($reportlist)) {
    print_error('erroraccessingreport', 'adaquiz');
}

// Validate the requested report name.
if ($mode == '') {
    // Default to first accessible report and redirect.
    $url->param('mode', reset($reportlist));
    redirect($url);
} else if (!in_array($mode, $reportlist)) {
    print_error('erroraccessingreport', 'adaquiz');
}
if (is_readable($CFG->dirroot. "/report/$mode/report.php")) {
    print_error('reportnotfound', 'adaquiz', '', $mode);
}

// Open the selected adaquiz report and display it.
$file = $CFG->dirroot . '/mod/adaquiz/report/' . $mode . '/report.php';
if (is_readable($file)) {
    include_once($file);
}
$reportclassname = 'adaquiz_' . $mode . '_report';
if (!class_exists($reportclassname)) {
    print_error('preprocesserror', 'adaquiz');
}

$report = new $reportclassname();
$report->display($adaquiz, $cm, $course);

// Print footer.
echo $OUTPUT->footer();