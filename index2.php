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
 * User Grade Report .
 *
 * @package    report_ncmusergrades
 * @author     Nicolas Jourdain <nicolas.jourdain@navitas.com>
 * @copyright  2018 Nicolas Jourdain <nicolas.jourdain@navitas.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot.'/grade/export/lib.php');
require_once $CFG->dirroot . '/grade/report/overview/lib.php';
require_once $CFG->dirroot . '/grade/lib.php';
require_once $CFG->dirroot . '/report/ncmusergrades/lib.php';
require_once $CFG->dirroot . '/report/ncmusergrades/locallib.php';

require_once $CFG->dirroot . '/user/lib.php';

global $DB;

// $pagecontextid = required_param('pagecontextid', PARAM_INT);
// $context = context::instance_by_id($pagecontextid);
$context = context_system::instance();

var_dump($context);

require_login();
// \core_competency\api::require_enabled();

// if (!\core_competency\template::can_read_context($context)) {
//     throw new required_capability_exception($context, 'moodle/competency:templateview', 'nopermissions', '');
// }

// $urlparams = array('pagecontextid' => $pagecontextid);

// $url = new moodle_url('/report/ncmusergrades/index.php', $urlparams);
$url = new moodle_url('/report/ncmusergrades/index.php');

$title = get_string('pluginname', 'report_ncmusergrades');

if ($context->contextlevel == CONTEXT_SYSTEM) {
    $heading = $SITE->fullname;
} else if ($context->contextlevel == CONTEXT_COURSECAT) {
    $heading = $context->get_context_name();
} else {
    throw new coding_exception('Unexpected context!');
}

// Protect page based on capability
// require_capability('report/siteoutcomes:view', $context);
// Creating the form.
$mform = new \report_ncmusergrades\filter_form(null);

// Set css.
// $PAGE->requires->css('/report/ncmusergrades/style/checkbox.css');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($heading);
$PAGE->set_pagelayout('admin'); // OR report

$PAGE->navbar->add(get_string('pluginname', 'report_ncmusergrades'));

$output = $PAGE->get_renderer('report_ncmusergrades');

echo $output->header();
echo $output->heading($title);


// Form processing and displaying is done here.
if ($mform->is_cancelled()) {
    // Handle form cancel operation, if cancel button is present on form.
} else if ($fromform = $mform->get_data()) {
    // In this case you process validated data. $mform->get_data() returns data posted in form.
    // Set default data (if any)
    $mform->set_data($fromform);
    //displays the form
    $mform->display();

    $courses = enrol_get_users_courses($fromform->userid, false, 'id, shortname, showgrades');
    // echo "<pre>";
    // var_dump($courses);
    // echo "</pre>";

    $user = $DB->get_record('user', array('id' => $fromform->userid), '*', MUST_EXIST);

    $userdetails = user_get_user_details($user);

    // echo "<pre>";
    // var_dump($userdetails);
    // echo "</pre>";
    

    // echo "<h1><i class='fa fa-user-circle' aria-hidden='true'></i> {$userdetails['fullname']}</h1>";
    echo ncmusergrades_user_desc($userdetails);

    foreach ($courses as $course) {
        // echo "<pre>";
        // var_dump($course);
        // echo "</pre>";    

        if (!$course->showgrades) {
            // continue;
        }
        $grade_items = grade_item::fetch_all(array('courseid' => $course->id));

        // echo "<pre>grade_items";
        // var_dump($grade_items);
        // echo "</pre>";    

        // Get the category
        $mycategory = coursecat::get($course->category); // ->get_children();

        // echo "<pre>mycategory";
        // var_dump($mycategory);
        // echo "</pre>";    

        // Get course grade_item
        // $course_item = grade_item::fetch_course_item($course->id);

        // Get the stored grade
        // $course_grade = new grade_grade(array('itemid'=>$course_item->id, 'userid'=>$fromform->userid));
          
        // $course_grade->grade_item =& $course_item;
        // $finalgrade = $course_grade->finalgrade;
        

        $geub = new grade_export_update_buffer();
        $gui = new ncm_graded_users_iterator($fromform->userid, $course, $grade_items, 0);

        // echo "<pre>GUI";
        // var_dump($gui);
        // echo "</pre>";

        // $gui->require_active_enrolment(onlyactive);
        // $gui->allow_user_custom_fields($this->usercustomfields);
        $gui->init();

        $listgrades = array();
        $mygrades = array();

        // $output->html('<h4 class="card-title">'.$course->name .' ('.$course->id.')</h4>');
        echo '<h4 class="card-title">'.$course->shortname .' / '.$course->fullname .' / '.$course->id.' ('.$mycategory->name.')</h4>';
        echo ncmusergrades_grade_table_open();
        while ($userdata = $gui->next_user()) {

            // echo "<pre>";
            // var_dump($userdata);
            // echo "</pre>";
            
            foreach ($userdata->grades as $itemid => $grade) {
                // echo "<pre>";
                // print_r($grade);
                // echo "</pre>";

                $mygrade = array();
                $mygrade['itemid'] = $itemid;

                $gradeitem = $grade_items[$itemid];
                $grade->gradeitem =& $gradeitem;

                $listgrades[$itemid] = array(
                    'itemid' => $itemid,
                    'itemname' => $gradeitem->itemname,
                    'itemtype' => $gradeitem->itemtype,
                    'grademax' => $gradeitem->grademax,
                    'gradepass' => $gradeitem->gradepass,
                    'multfactor' => $gradeitem->multfactor,
                    'weight' => $gradeitem->grademax * $gradeitem->multfactor,
                    'order' => ($gradeitem->itemtype == 'course') ? 9 : 1,
                    'myfinalgrade' => $grade->finalgrade,
                    'myrawgrademax' => $grade->rawgrademax,
                    'score' => array(
                        GRADE_DISPLAY_TYPE_REAL => grade_format_gradevalue($grade->finalgrade, $gradeitem, false, GRADE_DISPLAY_TYPE_REAL),
                        GRADE_DISPLAY_TYPE_LETTER => grade_format_gradevalue($grade->finalgrade, $gradeitem, false, GRADE_DISPLAY_TYPE_LETTER),
                        GRADE_DISPLAY_TYPE_PERCENTAGE => grade_format_gradevalue($grade->finalgrade, $gradeitem, false, GRADE_DISPLAY_TYPE_PERCENTAGE),
                    )
                );
                $mygrade['finalgrade'] = $grade->finalgrade;
                $mygrade['rawgrademax'] = $grade->rawgrademax;
                $mygrades[$itemid] = $mygrade;
                
            }
            echo ncmusergrades_grade_table_content($listgrades);
            // echo "<pre>";
            // print_r($listgrades);
            // echo "</pre>";

            // echo "<pre>";
            // print_r($mygrades);
            // echo "</pre>";
            
        }
        // $output->html(ncmusergrades_grade_table_close());
        echo ncmusergrades_grade_table_close();
        // Close.
        $gui->close();
        $geub->close();

        // $data[] = array(grade_format_gradevalue($finalgrade, $course_item, true));
    }  

} else {
  // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
  // or on the first display of the form.
 
  //Set default data (if any)
  $mform->set_data($toform = array());
  //displays the form
  $mform->display();
}

// if ($mform->is_submitted()) {
//     echo "<pre>Hello World!</pre>";
//     $mform->display();
// }


// $page = new \report_ncmusergrades\output\report($context);
// echo $output->render($page);
echo $output->footer();