
<?php
//require_once('config.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/course/dnduploadlib.php');
require_once($CFG->dirroot.'/course/format/lib.php');
require_once($CFG->libdir.'/coursecatlib.php');

define('LESSON_MODULE_ID', 11);
define('ASSIGNMENT_MODULE_ID', 1);
define('QUIZ_MODULE_ID', 12);
define('CONTEXT_LEVEL', 50);
if (!defined('STUDENT_ROLE_ID')) {
  define('STUDENT_ROLE_ID', 5);
}
if (!defined('FRONTPAGECOURSELIMIT')) {
  define('FRONTPAGECOURSELIMIT',    205);       // maximum number of courses displayed on the frontpage
}
if (!defined('MAX_MODINFO_CACHE_SIZE')) { 
    define('MAX_MODINFO_CACHE_SIZE', 10);
}

function print_course_list(){
       
    global $CFG, $PAGE;
    global $completed, $content_not_viewed, $quiz_not_ans, $category_list, $quiz_exist, $quiz_course_count;
    
    echo '<script type="text/javascript" src="custom_files/js/frontpagecourselist.js"></script>';
    
    $content = '<div class="categorybox box">
                    <table cellpading="0" cellspacing="0" border="0" class="categorylist" width="100%">
                        <tr>
                            <td class="course-detail" >
                                <table cellpading="0" cellspacing="0" border="0" class="course-table overAllDescription-table" width="96%">
                                    <tr>';
                                            $content .='<td class="not_viewed_td" width="12px"><input type = "checkbox" id="content_check" class="content_not_viewed_check" value = 0 /></td>
                                                        <td class="not_viewed_td" width=10px ><img  src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/red_button.png" height="15" width="15" title="Content not viewed"/></td>
                                                        <td nowrap class="course-legends not_viewed_td">Content not viewed <span id="content_not_viewed"></span></td>';
                                            $content .='<td class="quiz_td" width="12px"><input type = "checkbox" id="quiz_check" class="quiz_not_ans_check" value = 0 /></td>
                                                        <td class="quiz_td" width="10px"><img title="Quiz not completed" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/orange_button.png" height="15" width="15" title="Quiz not completed"/></td>
                                                        <td nowrap class="course-legends quiz_td">Quiz not completed <span id="quiz_not_ans"></span></td>';
                                            $content .='<td class="completed_td" width="12px"><input type = "checkbox" id="complete_check" class="completed_check" value = 0 /></td>
                                                        <td class="completed_td" width="10px"><img src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/green_button.png" height="15" width="15" title="Learning objective complete"/></td>
                                                        <td nowrap class="course-legends completed_td">Learning objective complete <span id="completed"></span></td>';
                                            $content .='<td class="quiz_exist_td" width="12px"><input type = "checkbox" id="quiz_exist_check" class="quiz_exist_check" value = 0 /></td>
                                                        <td class="quiz_exist_td" width="10px"><img title="Quiz exist in course" src="' . $CFG->wwwroot . '/quiz.jpg"   height="15" width="15" /></td>
                                                        <td nowrap class="course-legends quiz_exist_td">Courses with Quiz <span id="quiz_exist_span"></span></td>';
                                    $content .='</tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>';
    echo  $content;

    //print_box_start('categorybox');
    print_whole_category_list_custom();

    if(empty($content_not_viewed)){
        $content_not_viewed = 0;
    }
    if(empty($quiz_not_ans)){
        $quiz_not_ans = 0;
    }
    if(empty($completed)){
        $completed = 0;
    }
    if(empty($quiz_course_count)){
        $quiz_course_count = 0;
    }
    $cat_name_list = explode(',',$category_list);
    $cat_name = array();
    foreach($cat_name_list as $cat) {
            if($cat != '') {    
                //$cat_name[] = str_replace(' ','_',strtolower($cat));
                $cat_name[] = preg_replace('/[^a-zA-Z0-9_ -]/s', '',str_replace(' ','_',strtolower(format_string($cat))));
            }
    }
    $cat_name_var = implode($cat_name,',');
    
    ?>
    <script>
        var customparams = {
            content_not_viewed: '<?php echo $content_not_viewed; ?>',
            quiz_not_ans: '<?php echo $quiz_not_ans; ?>',
            completed: '<?php echo $completed; ?>',
            quiz_course_count: '<?php echo $quiz_course_count; ?>',
            CFGwwwroot: '<?php echo $CFG->wwwroot; ?>'
        }
    </script>
    <?php

    echo '<span class="cat_list" val = "'.$cat_name_var.'" />';
    
}

function print_whole_category_list_custom($category=NULL, $displaylist=NULL, $parentslist=NULL, $depth=-1, $showcourses = true) {
    global $CFG;

    if (!empty($CFG->maxcategorydepth) && $depth >= $CFG->maxcategorydepth) {
        return;
    }

    if (!$displaylist) {
        //make_categories_list($displaylist, $parentslist);
        coursecat::make_categories_list();
    }

    if ($category) {
        if ($category->visible or has_capability('moodle/category:viewhiddencategories', context_system::instance())) {
            print_category_info_custom($category, $depth, $showcourses);
        } else {
            return;  // Don't bother printing children of invisible categories
        }

    } else {
        $category = new stdClass();
        $category->id = "0";
    }

    //if ($categories = get_child_categories($category->id)) {   // Print all the children recursively
    if ($categories = coursecat::get($category->id)->get_children()) {   // Print all the children recursively
        $countcats = count($categories);
        $count = 0;
        $first = true;
        $last = false;
        foreach ($categories as $cat) {
            $count++;
            if ($count == $countcats) {
                $last = true;
            }
            $up = $first ? false : true;
            $down = $last ? false : true;
            $first = false;

            print_whole_category_list_custom($cat, $displaylist, $parentslist, $depth + 1, $showcourses);
        }
    }
}


function print_category_info_custom($category, $depth, $showcourses = false) {

    global $CFG, $DB, $OUTPUT, $USER, $PAGE;    
    global $completed, $content_not_viewed, $quiz_not_ans, $category_list, $quiz_exist, $quiz_course_count;
    static $strallowguests, $strrequireskey, $strsummary;
    $quiz_exist = 0;    $cat_flag = 0;
    
    if (empty($strsummary)) {
        $strallowguests = 'This course allows guest users to enter';
        $strrequireskey = 'This course requires an enrolment key';
        $strsummary = get_string('summary');
    }

    $catlinkcss = $category->visible ? '' : ' class="dimmed" ';

    static $coursecount = null;
    if (null === $coursecount) {
        // only need to check this once
        $coursecount = $DB->count_records('course');
    }
    
    $catimage = "&nbsp;";   

    $category_list .= ','.format_string($category->name);
    $id = '';
    if ($depth && $category->parent) {
      $id = "id='sub-cat-trainings-$category->id' ";
    }

    $courses = get_courses($category->id, 'c.fullname ASC', ' c.id,c.sortorder,c.visible,c.fullname,c.shortname,c.summary,c.timecreated');
//    if($category->id == 35){
//        var_dump($courses);
//    }
    
    $current_user_courses = enrol_get_my_courses();
    $user_allowed_courses = array();
    foreach($current_user_courses as $cuc){
        if($is_university_course = $DB->get_record('university_course',array('courseid'=>$cuc->id))){
            continue;
        }
        $user_allowed_courses[] = $cuc->id;
    }

    $dummy_course_id = 0;
    $dummy_course_quiz_id = 0;
    foreach ($courses as $key => $course) {
//        if($is_university_course = $DB->get_record('university_course',array('courseid'=>$course->id))){
//            unset($courses[$key]);
//            continue;
//        }
        $context = $DB->get_record('context', array('contextlevel'=>CONTEXT_COURSE, 'instanceid'=>$course->id));
        $course_self_enroll = $DB->get_record('enrol',array('courseid' => $course->id, 'enrol' => 'self'));
        if($course_self_enroll){
            $course->enrollable = 1;
        }else{
            $course->enrollable = 0;
        }

        if($course->enrollable || in_array($course->id, $user_allowed_courses) || $USER->id == 2 || (is_array(@$mycourses) && array_key_exists($course->id, $mycourses))) {
                $cat_flag = 1;
        }
        $dummy_course_quiz_id = 0;
        $quiz_id = array();
        if ($DB->record_exists('quiz', array('course' => $course->id))) { // will return true or false
          $quiz_id = array_keys($DB->get_records_select("quiz", "course=$course->id" , array(), 'id', 'id')); // returns the quiz_id of the course          
          if($quiz_id) {
            $courses[$key]->quiz_exist = 1;
          }else{
              $courses[$key]->quiz_exist = 0;
          }
        }else{                    
            $courses[$key]->quiz_exist = 0;
        }
        if (!$dummy_course_id && $course->visible && $DB->record_exists('dummy_course', array('course' => $course->id)) && ($DB->record_exists('role_assignments', array('contextid' => $context->id, 'userid' => $USER->id)) || is_primary_admin($USER->id))) {
          $dummy_course_id = $course->id;
          $dummy_course_quiz_id = isset($quiz_id[0]) ? $quiz_id[0] : 0;
          $courses[$key]->dummy_course = 1;
          unset($courses[$key]);
        }
        elseif ($DB->record_exists('dummy_course', array('course' => $course->id))) {
          unset($courses[$key]);
        }
    }
    $cor_flag = 0;
    if($category->coursecount == 0) {
        $cor_flag = 1;
        $courses_id_category = $DB->get_records_sql("select mdl_course.id from mdl_course where category in (select id from mdl_course_categories where parent = $category->id)");
        foreach ($courses_id_category as $course) {
            $course_self_enroll = $DB->get_record('enrol',array('courseid' => $course->id, 'enrol' => 'self'));
            if($course_self_enroll){
                $course->enrollable = 1;
            }else{
                $course->enrollable = 0;
            }
                if($course->enrollable || in_array($course->id, $user_allowed_courses) || $USER->id == 2 || (is_array(@$mycourses) && array_key_exists($course->id, $mycourses))) // hack for enrollable course
                {
                        $cat_flag = 1;
                }
        }
    }

    if($cat_flag == 1 && $cor_flag == 1) {
            $cat_flag = 1;
    }
	
    echo "\n\n";
    if($cat_flag == 1) {
            echo '<table ' . $id . 'class="categorylist" border=0>';
            if ($showcourses and $coursecount) {
                echo '<tr class="cat_main_'.preg_replace('/[^a-zA-Z0-9_ -]/s', '',str_replace(' ','_',strtolower(format_string($category->name)))).'" val = "'.count($courses).'" >';
                //echo '<td valign="top" class="category image" style="width:4%">'.$catimage.'</td>';
                if ($depth) {
                    $child_categories = '';
                        echo '<td valign="top" class="sub_category" width="104%">';
                        echo '<span id="td-span-' . $category->id . '" onclick="elementToggle(' . $category->id . ', \'hide\', \'' . $child_categories . '\');"><img id="span-img-' . $category->id . '" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/t/switch_minus_black.gif" /></span>';
                        echo '&nbsp;';
                        $dummy_course_quiz = '';                            
                        if ( $PAGE->user_is_editing() && !$dummy_course_id && is_primary_admin($USER->id)) {
                          echo $create_dummy_course = "<span style='float:right;'>&nbsp;<a href=\"$CFG->wwwroot/custom_files/dummy_course.php?category=$category->id\" title=\"Create dummy course\"><img src=\"$CFG->wwwroot/theme/knowledge-library/pix/book.png\" alt=\"book\" width='15' height='15' /></a></span>";
                        }
                        if ($dummy_course_id && $dummy_course_quiz_id) {
                          $course_module_details = $DB->get_record('course_modules', array('module' => QUIZ_MODULE_ID, 'instance' => $dummy_course_quiz_id),'id, visible');
                          $edit = "";
                          if ($PAGE->user_is_editing()) {
                            $edit = "&nbsp;<a href='$CFG->wwwroot/course/mod.php?update=$course_module_details->id'><img src='$CFG->wwwroot/theme/knowledge-library/pix/t/edit.gif'/></a>";
                          }
                          $dummy_course_quiz = $course_module_details->visible ? "<span style='float:right;'><a href=\"$CFG->wwwroot/mod/quiz/attempt.php?id=$course_module_details->id\"><img title='Quiz exist in category' src='" . $CFG->wwwroot . "/quiz.jpg' height='15' width='15' /></a>&nbsp;<a></a>$edit</span>" : '';
                        }
                        echo '<a '.$catlinkcss.' href="'.$CFG->wwwroot.'/course/category.php?id='.$category->id.'">'. format_string($category->name).'</a>' . $dummy_course_quiz;
                        echo ' </td>';
                } else {
                        $child_categories = '';
                        if ($category->id) {
                          //$child_categories_arr = get_child_categories($category->id);
                          $child_categories_arr = coursecat::get($category->id)->get_children();
                          if (count($child_categories_arr)) {
                                foreach($child_categories_arr as $key => $details) {
                                  $child_categories = $child_categories ? "$child_categories," . $details->id : $details->id;
                                }
                          }
                        }
                        echo '<td valign="top" class="category name main_category" >';
                        echo '<span id="td-span-' . $category->id . '" onclick="elementToggle(' . $category->id . ', \'hide\', \'' . $child_categories . '\');"><img id="span-img-' . $category->id . '" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/t/switch_minus_black.gif" /></span>';
                        echo '&nbsp;';
                        $dummy_course_quiz = '';
                        if ($PAGE->user_is_editing() && !$dummy_course_id && is_primary_admin($USER->id)) {
                          echo $create_dummy_course = "<span style='float:right;'>&nbsp;<a href=\"$CFG->wwwroot/custom_files/dummy_course.php?category=$category->id\" title=\"Create dummy course\"><img src=\"$CFG->wwwroot/theme/knowledge-library/pix/book.png\" alt=\"book\" width='15' height='15' /></a></span>";
                        }
                        if ($dummy_course_id && $dummy_course_quiz_id) {
                          $course_module_details = get_record('course_modules', 'module', QUIZ_MODULE_ID, 'instance', $dummy_course_quiz_id, '', '', 'id, visible');
                          if ($PAGE->user_is_editing()) {
                            $edit = "&nbsp;<a href='$CFG->wwwroot/course/mod.php?update=$course_module_details->id'><img src='$CFG->wwwroot/theme/knowledge-library/pix/t/edit.gif'/></a>";
                          }
                          $dummy_course_quiz = $course_module_details->visible ? "<span style='float:right; padding-right:5px;'><a href=\"$CFG->wwwroot/mod/quiz/attempt.php?id=$course_module_details->id\"><img title='Quiz exist in category' src='" . $CFG->wwwroot . "/quiz.jpg' height='15' width='15' /></a>$edit</span>" : '';
                        }
                        echo '<a '.$catlinkcss.' href="'.$CFG->wwwroot.'/course/category.php?id='.$category->id.'">'. format_string($category->name).'</a>' . $dummy_course_quiz;
                        echo '</td>';
                }

                //echo '<td class="category info" style="width:4%">&nbsp;</td>';
                echo '</tr>';

                $limit = !(isset($CFG->maxcategorydepth) && ($depth >= $CFG->maxcategorydepth-1));

                if ($courses && ($limit || $CFG->maxcategorydepth == 0)) {
                    echo '<tr id="courses-' . $category->id . '" class="cat_main_'.preg_replace('/[^a-zA-Z0-9_ -]/s', '',str_replace(' ','_',strtolower(format_string($category->name)))).'" >';
                    //echo '<td class="category info" style="width:4%">&nbsp;</td>';
                    echo "<td style='padding:0.1em;'><table border=1 width=100% class='course-table cat_".preg_replace('/[^a-zA-Z0-9_ -]/s', '',str_replace(' ','_',strtolower(format_string($category->name))))."'>";
                    
                    $current_time = time();
                    foreach ($courses as $course) {
                        $context = $DB->get_record('context', array('contextlevel'=>CONTEXT_COURSE, 'instanceid'=>$course->id));
                        
                        if($course->enrollable || in_array($course->id, $user_allowed_courses) || $USER->id == 2 || (is_array(@$mycourses) && array_key_exists($course->id, $mycourses))) {
                            $linkcss = $course->visible ? '' : ' class="dimmed" ';
                            $status_image = '';
                            $attempt_quiz = '';
                            $course_status = print_course_status($course, $USER->id);

                            $tr_class = '';
                            if ($course_status == 'completed') {
                                    ++$completed;
                                    $tr_class = "green_btn";
                                    $status_image = '<td class="first_col"><img title = "Learning objective complete" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/green_button.png"  height="15" width="15" /></td>';
                            }
                            else if ($course_status == 'no_attempt') {
                                    ++$quiz_not_ans;
                                    $tr_class = "orange_btn";
                                    $quiz_ids   =   $DB->get_records_sql('select instance from {course_modules} where module=? and course=?',array('12',$course->id));
                                    $module_id = 0;
                                    foreach($quiz_ids as $quiz_id)
                                    {
                                     
                                     $max_grade =   $DB->get_records_sql('select grade from {quiz} where id=?',array($quiz_id->instance));
                                     $attempt_grade   =   $DB->get_records_sql('select grade  from {quiz_grades} where userid=? and quiz=?',array($USER->id,$quiz_id->instance));
                                     if($attempt_grade<$max_grade)
                                     {
                                     
                                            if($module_id  =   $DB->get_records_sql('select id from {course_modules} where course=? and instance=? and module=?',array($course->id,$quiz_id->instance,12))){
                                                foreach($module_id as $mid){
                                                    $module_id = $mid->id;break;
                                                }
                                            }
                                            break;
                                     }
                                     
                                    }
                                    if($module_id){
                                        $status_image = '<td class="first_col"><a href="' . $CFG->wwwroot . '/mod/quiz/view.php?id=' . $module_id . '"><img title="Quiz not completed" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/orange_button.png"  height="15" width="15" /><img title="View details..." src="' . $CFG->wwwroot . '/custom_files/custom_images/view_list_details.jpg"   height="15" width="15" /></a></td>';
                                    } else {
                                        $status_image = '<td class="first_col">&nbsp;</td>';
                                    }
                            }
                            else if($course_status == 'incompleted'){
                                    ++$content_not_viewed;
                                    $tr_class = "red_btn";
                                    $not_viewed_link = "";
                                    $not_viewed_link .= "javascript:void(0); ";
                                    $fullurl = $CFG->wwwroot."/report/customuserreport/content_not_viewed.php?cid=$course->id&uid=$USER->id";
                                    $wh = "width=600,height=400, false";
                                    $not_viewed_link .= "window.open('$fullurl', '', '$wh'); return false;";
                                    $status_image = '<td class="first_col"><a href="'.$not_viewed_link.'"><img title="Content not viewed" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/red_button.png"   height="15" width="15" /><img title="View details..." src="' . $CFG->wwwroot . '/custom_files/custom_images/view_list_details.jpg"   height="15" width="15" /></a></td>';
                            } else{
                                    $status_image = '<td class="first_col">&nbsp;</td>';
                            }

                            if($course->quiz_exist){
                                    ++$quiz_course_count;
                                    $quiz_q_col = '<td class="third_col"><img title="Quiz exist in course" src="' . $CFG->wwwroot . '/quiz.jpg" height="15" width="15" /></td>';
                                    $tr_class .= " quiz_exist";
                            }
                            else {
                                    $quiz_q_col = '<td class="third_col">&nbsp;</td>';
                                    $tr_class .= " quiz_not_exist";
                            }
                            echo '<tr class="'.$tr_class.'" val="1" >';
                            $enrolled = $DB->record_exists('role_assignments', array('roleid' => STUDENT_ROLE_ID, 'contextid' => $context->id, 'userid' => $USER->id));
                            $enrollable = !$course->enrollable ? '<td><img title="Mandatory" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/red_star.png" height="15" width="15" /></td>' : ($enrolled ? '<td><img title="Enrolled" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/green_star.png"   height="15" width="15" /></td>' : '<td><img title="Self Enrollable" src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/yellow_star.png"   height="15" width="15" /></td>');
                            //Hack: new flag functionality added.
                            $new_flag = $current_time < $course->timecreated + 604800 ? '&nbsp;<img src="' . $CFG->wwwroot . '/theme/knowledge-library/pix/new.png" />' : '';
                            echo $status_image . $enrollable . $quiz_q_col . '<td class="second_col"><a '.$linkcss.' href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'. format_string($course->fullname).'</a>' . $new_flag . '</td>';

                            if ($course->summary) {
                                    $description = "";
                                    if(strlen($course->summary) > 200){
                                    //	$description .= '<div id="description_content_'.$course->id.'" style="display:none"><p>'.$course->summary.'</p></div>';
                                            $description .= $course->summary;//substr($course->summary, 0, 200). '.....<a href="#TB_inline?height=200&width=400&inlineId=description_content_'.$course->id.'" class="thickbox readmorelink">&nbsp;More</a>';
                                    } else {
                                            $description = $course->summary;
                                    }
                                    echo '<td><div class="course-legends">'.$description.'</div></td>';
                                    //Hack: Code commented.
                                    //link_to_popup_window ('/course/info.php?id='.$course->id, 'courseinfo', '<img alt="'.$strsummary.'" src="'.$CFG->pixpath.'/i/info.gif" />', 400, 500, $strsummary);
                            } else {
                                    echo '<td><img alt="" class="spacer_img" src="'.$CFG->wwwroot.'/custom_files/images/spacer.gif" /></td>';
                            }
                                echo '</tr>';
                            }
                    }
                    echo "</table></td></tr>";
                }
            } else {
                echo '<tr>';

                if ($depth) {
                        $indent = $depth*20;
                        echo '<td class="category indentation" valign="top">';
                        print_spacer(10, $indent);
                        echo '</td>';
                }

                echo '<td valign="top" class="category name">';
                echo '<a '.$catlinkcss.' href="'.$CFG->wwwroot.'/course/category.php?id='.$category->id.'">'. format_string($category->name).'</a>';
                echo '</td>';
                echo '<td valign="top" class="category number">';
                if (count($courses)) {
                   echo count($courses);
                }
                echo '</td></tr>';
        }
            echo '</table>';
    }
}


/**
 * Hack: Function defination added.
 * Function returns the status of a course for a user.
 */
function print_course_status($mycourse, $user_id) {
    global $CFG,$DB;
    $course_data = array();
    $course_fullname = format_string($mycourse->fullname); 
    $resource_count = $DB->count_records_select('resource', "course=$mycourse->id") . "      ";

    $resource_status_count = $DB->count_records_select('resource_status', "resource in (SELECT id FROM mdl_resource m WHERE course = $mycourse->id) AND userid = $user_id AND status = 1");

      if ((int)$resource_count == (int)$resource_status_count) { // will return true or false
                $course_data[$course_fullname]['resourcestatus'] = "completed";
      } else {
              $course_data[$course_fullname]['resourcestatus'] = "incompleted";
      }

      $course_data[$course_fullname]['quizstatus'] = "completed";
      if ($DB->record_exists('quiz', array('course' => $mycourse->id))) { // will return true or false
          //$quiz_id = array_keys(get_records_select("quiz", "course=$mycourse->id", 'id')); // returns the quiz_id of the course
          $sql = "SELECT q.id, q.grade FROM {$CFG->prefix}quiz q INNER JOIN {$CFG->prefix}course_modules cm ON (q.id = cm.instance AND cm.module = " . QUIZ_MODULE_ID . ") WHERE q.course = $mycourse->id AND cm.visible = 1";
          $quiz_id = $DB->get_records_sql($sql);
          $flag = 0;
          foreach($quiz_id as $val) {
                  if ($DB->get_records_select('quiz_grades', "quiz=$val->id AND userid=$user_id AND (grade + 0.01) >= $val->grade", array(), "id DESC", 'id', 0, 1) != false) {
                      $flag = 1;				
                  } else {
                      $flag = 2;	break;
                  }
          }
          if($flag == 1){
                  $course_data[$course_fullname]['quizstatus'] = "completed";
          }elseif($flag == 2){
                  $course_data[$course_fullname]['quizstatus'] = "incompleted";
          }else{
                  $course_data[$course_fullname]['quizstatus'] = "completed";
          }
    }
    
    $course_data[$course_fullname]['lessonstatus'] = "completed";
    if ($DB->record_exists('lesson', array('course' => $mycourse->id))) {
      //$lesson_id = array_keys(get_records_select("lesson", "course=$mycourse->id", 'id')); // returns the lesson_id of the course
          $sql = "SELECT l.id, l.grade FROM {$CFG->prefix}lesson l INNER JOIN {$CFG->prefix}course_modules cm ON (l.id = cm.instance AND cm.module = " . LESSON_MODULE_ID . ") WHERE l.course = $mycourse->id AND cm.visible = 1";
          $lesson_id = get_records_sql($sql);
          $flag = 0;
          foreach($lesson_id as $val) {
                  if (count(get_records_select('lesson_grades', "lessonid=$val->id AND userid=$user_id AND grade>=$val->grade", "id DESC", 'id', 0, 1))) {
                          $flag = 1;		  
                  } else {
                    $flag = 2;  break;
                  }
          }
          if($flag == 1 || $flag == 0){
                  $course_data[$course_fullname]['lessonstatus'] = "completed";
          }else {
                  $course_data[$course_fullname]['lessonstatus'] = "incompleted";
          }
    }
    $course_data[$course_fullname]['assignstatus'] = "completed";
    if ($DB->record_exists('assignment', array('course' => $mycourse->id))) {
      //$assig_id = array_keys(get_records_select("assignment", "course=$mycourse->id", 'id'));
          $sql = "SELECT a.id FROM {$CFG->prefix}assignment a INNER JOIN {$CFG->prefix}course_modules cm ON (a.id = cm.instance AND cm.module = " . ASSIGNMENT_MODULE_ID . ") WHERE a.course = $mycourse->id AND cm.visible = 1";
          $assig_id = $DB->get_records_sql($sql);
          $flag = 0;
          foreach($assig_id as $val) {
                  if ($DB->record_exists('assignment_submissions', array('userid' => $user_id, 'assignment' => $val->id))) {
                          $flag = 1;
                  } else {
                          $flag = 2;  break;
                  }
          }

          if($flag == 1 || $flag == 0){
                  $course_data[$course_fullname]['assignstatus'] = "completed";
          }else {
                  $course_data[$course_fullname]['assignstatus'] = "incompleted";
          }
    }

    $resource_status = $course_data[$course_fullname]['resourcestatus'] ? $course_data[$course_fullname]['resourcestatus'] : 'completed';
    $quiz_status = $course_data[$course_fullname]['quizstatus'] ? $course_data[$course_fullname]['quizstatus'] : 'completed';

    $lesson_status = $course_data[$course_fullname]['lessonstatus'] ? $course_data[$course_fullname]['lessonstatus'] : 'completed';
    $assignment_status = $course_data[$course_fullname]['assignstatus'] ? $course_data[$course_fullname]['assignstatus'] : 'completed';

    if(($lesson_status == 'incompleted') || ($assignment_status == 'incompleted') || ($quiz_status == 'incompleted')){
      $other_status = 'incompleted';
    } else if(($lesson_status == 'completed') && ($assignment_status == 'completed') && ($quiz_status == 'completed')){
      $other_status = 'completed';
    }

    $status = 'completed';
    if ($resource_status == 'completed' && $other_status == 'completed') {
      $status = 'completed';
    }
    elseif ($resource_status == 'incompleted' && $other_status == 'incompleted') {
      $status = 'incompleted';
    }
    elseif ($resource_status == 'completed' && $other_status == 'incompleted') {
      $status = 'no_attempt';
    }
    elseif ($resource_status == 'incompleted' && $other_status == 'completed') {
      $status = 'incompleted';
    }
    
 return $status;
 
}