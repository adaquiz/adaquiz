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
 * Rest endpoint for ajax editing of adaptive quiz structure.
 *
 * @package    mod_adaquiz
 * @copyright  2015 Maths for More S.L.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/adaquiz/locallib.php');

// Initialise ALL the incoming parameters here, up front.
$adaquizid  = required_param('adaquizid', PARAM_INT);
$class      = required_param('class', PARAM_ALPHA);
$field      = optional_param('field', '', PARAM_ALPHA);
$instanceid = optional_param('instanceId', 0, PARAM_INT);
$sectionid  = optional_param('sectionId', 0, PARAM_INT);
$previousid = optional_param('previousid', 0, PARAM_INT);
$value      = optional_param('value', 0, PARAM_INT);
$column     = optional_param('column', 0, PARAM_ALPHA);
$id         = optional_param('id', 0, PARAM_INT);
$summary    = optional_param('summary', '', PARAM_RAW);
$sequence   = optional_param('sequence', '', PARAM_SEQUENCE);
$visible    = optional_param('visible', 0, PARAM_INT);
$pageaction = optional_param('action', '', PARAM_ALPHA); // Used to simulate a DELETE command.
$maxmark    = optional_param('maxmark', '', PARAM_FLOAT);
$page       = optional_param('page', '', PARAM_INT);
$PAGE->set_url('/mod/adaquiz/edit-rest.php',
        array('adaquizid' => $adaquizid, 'class' => $class));

require_sesskey();
$adaquiz = $DB->get_record('adaquiz', array('id' => $adaquizid), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('adaquiz', $adaquiz->id, $adaquiz->course);
$course = $DB->get_record('course', array('id' => $adaquiz->course), '*', MUST_EXIST);
require_login($course, false, $cm);

$adaquizobj = new \mod_adaquiz\wiris\adaquiz($adaquiz, $cm, $course);
$structure = $adaquizobj->get_structure();
$modcontext = context_module::instance($cm->id);

echo $OUTPUT->header(); // Send headers.

// OK, now let's process the parameters and do stuff
// MDL-10221 the DELETE method is not allowed on some web servers,
// so we simulate it with the action URL param.
$requestmethod = $_SERVER['REQUEST_METHOD'];
if ($pageaction == 'DELETE') {
    $requestmethod = 'DELETE';
}

switch($requestmethod) {
    case 'POST':
    case 'GET': // For debugging.

        switch ($class) {
            case 'section':
                break;

            case 'resource':
                switch ($field) {
                    case 'move':
                        require_capability('mod/adaquiz:manage', $modcontext);
                        // AdaptiveQuiz: one slot per page so move is flip.
                        // $structure->move_slot($id, $previousid, $page);
                        $structure->flip_slots($id, $page);
                        adaquiz_delete_previews($adaquiz);
                        echo json_encode(array('visible' => true));
                        break;

                    case 'getmaxmark':
                        require_capability('mod/adaquiz:manage', $modcontext);
                        $slot = $DB->get_record('adaquiz_slots', array('id' => $id), '*', MUST_EXIST);
                        echo json_encode(array('instancemaxmark' =>
                                adaquiz_format_question_grade($adaquiz, $slot->maxmark)));
                        break;

                    case 'updatemaxmark':
                        require_capability('mod/adaquiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        if ($structure->update_slot_maxmark($slot, $maxmark)) {
                            // Grade has really changed.
                            adaquiz_delete_previews($adaquiz);
                            adaquiz_update_sumgrades($adaquiz);
                            adaquiz_update_all_attempt_sumgrades($adaquiz);
                            adaquiz_update_all_final_grades($adaquiz);
                            adaquiz_update_grades($adaquiz, 0, true);
                        }
                        echo json_encode(array('instancemaxmark' => adaquiz_format_question_grade($adaquiz, $maxmark),
                                'newsummarks' => adaquiz_format_grade($adaquiz, $adaquiz->sumgrades)));
                        break;
                    case 'updatepagebreak':
                        require_capability('mod/adaquiz:manage', $modcontext);
                        $slots = $structure->update_page_break($adaquiz, $id, $value);
                        $json = array();
                        foreach ($slots as $slot) {
                            $json[$slot->slot] = array('id' => $slot->id, 'slot' => $slot->slot,
                                                            'page' => $slot->page);
                        }
                        echo json_encode(array('slots' => $json));
                        break;
                }
                break;

            case 'course':
                break;
        }
        break;

    case 'DELETE':
        switch ($class) {
            case 'resource':
                require_capability('mod/adaquiz:manage', $modcontext);
                if (!$slot = $DB->get_record('adaquiz_slots', array('adaquizid' => $adaquiz->id, 'id' => $id))) {
                    throw new moodle_exception('AJAX commands.php: Bad slot ID '.$id);
                }
                $structure->remove_slot($adaquiz, $slot->slot);
                adaquiz_delete_previews($adaquiz);
                adaquiz_update_sumgrades($adaquiz);
                echo json_encode(array('newsummarks' => adaquiz_format_grade($adaquiz, $adaquiz->sumgrades),
                            'deleted' => true, 'newnumquestions' => $structure->get_question_count()));
                break;
        }
        break;
}
