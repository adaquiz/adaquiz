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

require_once('../../config.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/adaquiz/locallib.php');
require_once($CFG->dirroot . '/mod/adaquiz/editlib.php');

function clean_all_previews($adaquiz){
    adaquiz_delete_previews($adaquiz);
    $attempts = Attempt::getAllAttempts($adaquiz);
    foreach($attempts as $att){
        if($att->preview){//delete last previews when previewing.
        $att->delete();
        }
    }
}

/**
 * Callback function called from question_list() function
 * (which is called from showbank())
 * Displays button in form with checkboxes for each question.
 */
function module_specific_buttons($cmid, $cmoptions) {
    global $OUTPUT;
    $params = array(
        'type' => 'submit',
        'name' => 'add',
        'value' => $OUTPUT->larrow() . ' ' . get_string('addtoquiz', 'adaquiz'),
    );
    if ($cmoptions->hasattempts) {
        $params['disabled'] = 'disabled';
    }
    return html_writer::empty_tag('input', $params);
}


$quiz_qbanktool = optional_param('qbanktool', -1, PARAM_BOOL);
$savechanges = optional_param('savechanges', false, PARAM_BOOL);
$remove = optional_param('remove', false, PARAM_INT);
$down = optional_param('down', false, PARAM_INT);
$up = optional_param('up', false, PARAM_INT);
$add = optional_param('add', false, PARAM_BOOL);
        
list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) =
        question_edit_setup('editq', '/mod/adaquiz/edit.php', true);

$data = new stdClass();
$data->id = $cm->instance;
$adaquiz = new Adaquiz($data);
$adaquiz->cmid = $cmid;
// AQ-21
$adaquiz->preferredbehaviour = !empty($adaquiz->options['preferredbehaviour']) ? $adaquiz->options['preferredbehaviour'] : null;
$adaquiz->decimalpoints = 2;
$adaquiz->questiondecimalpoints = 2;
$adaquiz->questionsperpage = 1;
$adaquiz->reviewattempt = 0;
$adaquiz->reviewcorrectness = 0;
$adaquiz->reviewspecificfeedback = 0;
$adaquiz->reviewgeneralfeedback = 0;
$adaquiz->reviewrightanswer = 0;
$adaquiz->reviewoverallfeedback = 0;
$adaquiz->reviewmarks = 0;
$adaquiz->grades = array();
$adaquiz->shufflequestions = false;

if ($quiz_qbanktool > -1) {
    $thispageurl->param('qbanktool', $quiz_qbanktool);
    set_user_preference('quiz_qbanktool_open', $quiz_qbanktool);
} else {
    $quiz_qbanktool = get_user_preferences('quiz_qbanktool_open', 0);
}

if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    error('Course is misconfigured');
}

//Set node mark 
$nodes = $adaquiz->getNodes();
$sumgrades = 0;
$adaqs = array();
foreach ($nodes as $key => $value) {
    $adaquiz->grades[$value->question] = $value->grade;
    $sumgrades += $value->grade;
    $adaqs[] = $value->question;
}
$adaquiz->sumgrades = $sumgrades;
$adaquiz->questions = implode(',', $adaqs) . ',0';

// Check login and get context.
require_login($course, false, $cm);
$context = context_module::instance($_REQUEST['cmid']);

// Get the list of question ids had their check-boxes ticked.
$selectedquestionids = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedquestionids[] = $matches[1];
    }
}

$quizhasattempts = adaquiz_has_attempts($quiz->id);

$afteractionurl = new moodle_url($thispageurl);
if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    $adaquiz->addQuestion($addquestion);
    clean_all_previews($adaquiz);
    redirect($afteractionurl);
}

if ($remove && confirm_sesskey()) {
    $nodeid = $adaquiz->getNodesFromQuestions(array($remove));
    $adaquiz->deleteNode(array_shift($nodeid)->id);
    clean_all_previews($adaquiz);
    redirect($afteractionurl);
}

if ($up && confirm_sesskey()) {
    $nodeid = $adaquiz->getNodesFromQuestions(array($up));
    $adaquiz->moveUp(array_shift($nodeid)->id);
    clean_all_previews($adaquiz);
    redirect($afteractionurl);
}

if ($down && confirm_sesskey()) {
    $nodeid = $adaquiz->getNodesFromQuestions(array($down));
    $adaquiz->moveDown(array_shift($nodeid)->id);
    clean_all_previews($adaquiz);
    redirect($afteractionurl);
}

if ($savechanges && confirm_sesskey()) {
    $rawdata = (array) data_submitted();
    
    if (isset($rawdata['maxgrade'])){
        $adaquiz->grade = $rawdata['maxgrade'];
        $adaquiz->save();        
    }
    
    foreach ($rawdata as $key => $value) {
        if (preg_match('!^g([0-9]+)$!', $key, $matches)) {
            $questionid = $matches[1];
            $nid = $adaquiz->getNodesFromQuestions(array($questionid));
            $nid = array_keys($nid);
            $n = $adaquiz->getNode($nid[0]);
            $n->grade = $value;
            $n->save();
        }
    }
    redirect($afteractionurl);
}

if ($add && confirm_sesskey()) {
    // Add selected questions to the current quiz.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            $adaquiz->addQuestion($key);
        }
    }
    clean_all_previews($adaquiz);
    redirect($afteractionurl);
}

$questionbank = new quiz_question_bank_view($contexts, $thispageurl, $course, $cm, $adaquiz);
$questionbank->set_quiz_has_attempts($quizhasattempts);

$PAGE->set_url('/mod/adaquiz/edit.php', array('cmid' => $cmid));

$PAGE->requires->skip_link_to('questionbank', get_string('skipto', 'access', get_string('questionbank', 'question')));
$PAGE->requires->skip_link_to('quizcontentsblock', get_string('skipto', 'access', get_string('questionsinthisquiz', 'adaquiz')));
$PAGE->set_title(get_string('editingquizx', 'adaquiz', format_string($adaquiz->name)));
$PAGE->set_heading($course->fullname);

$node = $PAGE->settingsnav->find('mod_adaquiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}

echo $OUTPUT->header();

// Initialise the JavaScript.
$quizeditconfig = new stdClass();
$quizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$quizeditconfig->dialoglisteners = array();
$numberoflisteners = max(adaquiz_number_of_pages($adaquiz->questions), 1);
for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $quizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}
$PAGE->requires->data_for_js('adaquiz_edit_config', $quizeditconfig);
$PAGE->requires->js('/question/qengine.js');
$PAGE->requires->js('/mod/adaquiz/edit.js');

$module = array(
    'name'      => 'mod_adaquiz_edit',
    'fullpath'  => '/mod/adaquiz/edit.js',
    'requires'  => array('yui2-dom', 'yui2-event', 'yui2-container'),
    'strings'   => array(),
    'async'     => false,
);

$PAGE->requires->js_init_call('adaquiz_edit_init', null, false, $module);

$currenttab = 'edit';
$tabs = array(array(
    new tabobject('edit', new moodle_url($thispageurl,
            array('reordertool' => 0)), get_string('editingquiz', 'adaquiz'))
));

print_tabs($tabs, $currenttab);

// QBANK START
if ($quiz_qbanktool) {
    $bankclass = '';
    $quizcontentsclass = '';
} else {
    $bankclass = 'collapsed ';
    $quizcontentsclass = 'quizwhenbankcollapsed';
}

echo '<div class="questionbankwindow ' . $bankclass . 'block">';
echo '<div class="header"><div class="title"><h2>';
echo get_string('questionbankcontents', 'adaquiz') .
        ' <a href="' . $thispageurl->out(true, array('qbanktool' => '1')) .
       '" id="showbankcmd">[' . get_string('show').
       ']</a>
       <a href="' . $thispageurl->out(true, array('qbanktool' => '0')) .
       '" id="hidebankcmd">[' . get_string('hide').
       ']</a>';
echo '</h2></div></div><div class="content">';

echo '<span id="questionbank"></span>';
echo '<div class="container">';
echo '<div id="module" class="module">';
echo '<div class="bd">';
$questionbank->display('editq',
        $pagevars['qpage'],
        $pagevars['qperpage'],
        $pagevars['cat'], $pagevars['recurse'], $pagevars['showhidden'],
        $pagevars['qbshowtext']);
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div></div>';

echo '<div class="quizcontents ' . $quizcontentsclass . '" id="quizcontentsblock">';
// QBANK END

echo $OUTPUT->heading(get_string('editingquizx', 'adaquiz', format_string($adaquiz->name)), 2);

//Total mark, total number questions
adaquiz_print_status_bar($adaquiz);

$tabindex = 0;
quiz_print_grading_form($adaquiz, $thispageurl, $tabindex);

$notifystrings = array();
if ($quizhasattempts) {
    $reviewlink = adaquiz_attempt_summary_link_to_reports($quiz, $cm, $contexts->lowest());
    $notifystrings[] = get_string('cannoteditafterattempts', 'adaquiz', $reviewlink);
    echo $OUTPUT->box('<p>' . implode('</p><p>', $notifystrings) . '</p>', 'statusdisplay');
}

echo '<div class="editq">';
$canaddquestion = (bool) $contexts->having_add_and_use();;
$canaddrandom = false;
$quiz_reordertool = false;

$defaultcategoryobj = question_make_default_categories($contexts->all());
adaquiz_print_question_list($adaquiz, $thispageurl, true, $quiz_reordertool, $quiz_qbanktool,
        $quizhasattempts, $defaultcategoryobj, $canaddquestion, $canaddrandom);
echo '</div>';
// Close <div class="quizcontents">.
echo '</div>';

echo $OUTPUT->footer();
