<?php

/**
 * External oMbiel web service api
 *
 * @copyright 2016 ExLibris
 * @author ExLibris
 * @package oMbiel_webservices
 * @version 1.0
 */


/** @var $CFG  */
require_once("{$CFG->libdir}/externallib.php");
require_once("{$CFG->libdir}/filelib.php");
require_once("{$CFG->dirroot}/repository/lib.php");

/**
 * Webservices for oMbiel Apps
 */
class local_ombiel_webservices extends external_api {

    /**
     * Get info for user dashboard
     *
     * @param int $numberofitems The number of items in each list zero means all default is 10
     * @return array
     */
    public static function get_user_dashboard($numberofitems = 10) {
        global $CFG, $USER, $DB;

        $params = self::validate_parameters(self::get_user_dashboard_parameters(), array('numberofitems'=>$numberofitems));

        $userid = $USER->id;

        require_once($CFG->dirroot .'/mod/forum/lib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $result = array();
        /**
         * Get courses and find due activities and unread forum posts
         *
         * Activities are held in an array 'indexed' by time due concatenated with
         * the cm id to prevent duplicates
         *
         */

        $time = time();
        $activitiesdue = array();
        $unreadposts = array();
        $courseswithviewgrades= array();
        $courseList = enrol_get_users_courses($userid, true, 'id, fullname');
        $result['courses'] = array();

        if (!empty($courseList)) {
            foreach ($courseList as $course) {
                // course for output
                $courseout = array();
                $courseout['id'] = $course->id;
                $courseout['fullname'] = $course->fullname;
                $courseout['grade'] = '';
                // Get grades for courses
                if (has_capability('moodle/grade:view', context_course::instance($course->id))) {
                    $courseswithviewgrades[] = $course->id;

                    // Get course grade_item.
                    $course_item = grade_item::fetch_course_item($course->id);

                    // Get the stored grade.
                    $course_grade = new grade_grade(array('itemid' => $course_item->id, 'userid' => $userid), true);
                    if (!$course_grade->is_hidden()) {
                        $courseout['grade'] =
                                grade_format_gradevalue($finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_PERCENTAGE);
                    }
                }
                // Get upcoming activites
                foreach(get_course_mods($course->id) as $cm) {

                    if ($cm->visible) {
                        switch ($cm->modname) {
                            case 'assign':
                                $assign = $DB->get_record('assign', array('id'=>$cm->instance));
                                if (!empty($assign->duedate)) {
                                    if ($assign->allowsubmissionsfromdate <= $time) {
                                        if (empty($assign->cutoffdate) or $assign->cutoffdate > $time) {
                                        // assignment is open
                                            if (!$DB->record_exists('assign_submission', array('assignment'=>$assign->id, 'userid'=>$userid))) {
                                                $activitiesdue[$assign->duedate.'-'.$cm->id] = array(
                                                    'courseid'=>$course->id,
                                                    'coursename'=>$course->fullname,
                                                    'cmid'=>$cm->id,
                                                    'section'=>$cm->section,
                                                    'modname'=>$cm->modname,
                                                    'name'=>$assign->name,
                                                    'type'=>get_string('assignment', 'local_ombiel_webservices'),
                                                    'duedate'=>$assign->duedate,
                                                    'cutoffdate'=>$assign->cutoffdate,
                                                    );
                                            }
                                        }
                                    }
                                }
                                break;
                            case 'assignment':
                                $assign = $DB->get_record('assignment', array('id'=>$cm->instance));
                                if (!empty($assign->duedate)) {
                                    if ($assign->timeavailable <= $time) {
                                        if (empty($assign->preventlate) or $assign->timedue > $time) {
                                        // assignment is open
                                            if (!$DB->record_exists('assignment_submission', array('assignment'=>$assign->id, 'userid'=>$userid))) {
                                                $activitiesdue[$assign->timedue.'-'.$cm->id] = array(
                                                    'courseid'=>$course->id,
                                                    'coursename'=>$course->fullname,
                                                    'cmid'=>$cm->id,
                                                    'section'=>$cm->section,
                                                    'modname'=>$cm->modname,
                                                    'name'=>$assign->name,
                                                    'type'=>get_string('assignment', 'local_ombiel_webservices'),
                                                    'duedate'=>$assign->timedue,
                                                    'cutoffdate'=>empty($assign->preventlate)?false:$assign->timedue,
                                                    );
                                            }
                                        }
                                    }
                                }
                                break;
                            case 'chat':
                                $chat = $DB->get_record('chat', array('id'=>$cm->instance));
                                if ($chat->schedule and $chat->chattime) {
                                    $activitiesdue[$chat->chattime.'-'.$cm->id] = array(
                                        'courseid'=>$course->id,
                                        'coursename'=>$course->fullname,
                                        'cmid'=>$cm->id,
                                        'section'=>$cm->section,
                                        'modname'=>$cm->modname,
                                        'name'=>$chat->name,
                                        'type'=>get_string('scheduledchat', 'local_ombiel_webservices'),
                                        'duedate'=>$chat->chattime,
                                        'cutoffdate'=>$chat->chattime,
                                        );
                                }
                                break;
                            case 'forum':
                                $forum = $DB->get_record('forum', array('id'=>$cm->instance));
                                if ($forum->assessed and $forum->assesstimestart <= $time and $forum->assesstimefinish > $time) {
                                    $activitiesdue[$forum->assesstimefinish.'-'.$cm->id] = array(
                                        'courseid'=>$course->id,
                                        'coursename'=>$course->fullname,
                                        'cmid'=>$cm->id,
                                        'section'=>$cm->section,
                                        'modname'=>$cm->modname,
                                        'name'=>$forum->name,
                                        'type'=>get_string('assessedforum', 'local_ombiel_webservices'),
                                        'duedate'=>$forum->assesstimefinish,
                                        'cutoffdate'=>$forum->assesstimefinish,
                                        );
                                }

                                $count = forum_tp_count_forum_unread_posts($cm, $course);
                                if (!empty($count)) {
                                    $unreadposts[$forum->name] = array(
                                        'courseid'=>$course->id,
                                        'coursename'=>$course->fullname,
                                        'cmid'=>$cm->id,
                                        'section'=>$cm->section,
                                        'name'=>$forum->name,
                                        'count'=>$count,
                                        );
                                }
                                break;
                            case 'lesson':
                                $lesson = $DB->get_record('lesson', array('id'=>$cm->instance));
                                if (empty($lesson->available) or $lesson->available <= $time) {
                                    if ($lesson->deadline >= $time) {
                                        $activitiesdue[$lesson->deadline.'-'.$cm->id] = array(
                                            'courseid'=>$course->id,
                                            'coursename'=>$course->fullname,
                                            'cmid'=>$cm->id,
                                            'section'=>$cm->section,
                                            'modname'=>$cm->modname,
                                            'name'=>$lesson->name,
                                            'type'=>get_string('lesson', 'local_ombiel_webservices'),
                                            'duedate'=>$lesson->deadline,
                                            'cutoffdate'=>$lesson->deadline,
                                            );
                                    }
                                }
                                break;
                            case 'scorm':
                                $scorm = $DB->get_record('scorm', array('id'=>$cm->instance));
                                if ($scorm->timeopen and $scorm->timeopen <= $time){
                                    if ($scorm->timeclose > $time) {
                                        $activitiesdue["{$scorm->timeclose}-{$cm->id}"] = array(
                                            'courseid'=>$course->id,
                                            'coursename'=>$course->fullname,
                                            'cmid'=>$cm->id,
                                            'section'=>$cm->section,
                                            'modname'=>$cm->modname,
                                            'name'=>$scorm->name,
                                            'type'=>get_string('lesson', 'local_ombiel_webservices'),
                                            'duedate'=>$scorm->timeclose,
                                            'cutoffdate'=>$scorm->timeclose,
                                            );
                                    }
                                }
                                break;
                            case 'quiz':
                                $quiz = $DB->get_record('quiz', array('id'=>$cm->instance));
                                if ($quiz->timeopen and $quiz->timeopen <= $time){
                                    $deadline = $quiz->timeclose + ($quiz->graceperiod * 60);
                                    if ($deadline > $time) {
                                        $activitiesdue["{$scorm->timeclose}-{$cm->id}"] = array(
                                            'courseid'=>$course->id,
                                            'coursename'=>$course->fullname,
                                            'cmid'=>$cm->id,
                                            'section'=>$cm->section,
                                            'modname'=>$cm->modname,
                                            'name'=>$scorm->name,
                                            'type'=>get_string('lesson', 'local_ombiel_webservices'),
                                            'duedate'=>$scorm->timeclose,
                                            'cutoffdate'=>$scorm->timeclose,
                                            );
                                    }
                                }
                                break;
                        }
                    }
                }
                $result['courses'][] = $courseout;
            }
        }
        ksort($activitiesdue);

        krsort($unreadposts);
        if (empty($numberofitems)) {
            $result['activitiesdue'] = $activitiesdue;
            $result['unreadposts'] =  $unreadposts;
        } else {
            $result['activitiesdue'] = array_slice($activitiesdue, 0 , $numberofitems);
            $result['unreadposts'] =  array_slice($unreadposts, 0 , $numberofitems);
        }
        /**
         * Get recent grades
         */
        $recentgrades = array();
        $gradelist =  array();
        foreach ($courseswithviewgrades as $courseID) {
            $gradesql = "SELECT i.id, g.finalgrade, g.feedback, g.feedbackformat "
                    . "FROM {grade_items} i, {grade_grades} g "
                    . "WHERE g.itemid = i.id AND g.timemodified IS NOT null AND g.userid = ? AND i.courseid = ? "
                    . "ORDER BY g.timemodified DESC";
            $grade =  $DB->get_record_sql($gradesql, array($userid, $courseId));
            if (!empty($grade)) {
                $gradelist[] = $grade;
            }
            if (!empty($numberofitems) and count($gradelist) == $numberofitems) {
                break;
            }
        }
        foreach($gradelist as $grade) {
            $item = new grade_item(array('id'=>$grade->id), true);
            if (empty($item->itemmodule) ) {
                $cmid = 0;
                $name = $courseList[$item->courseid]->fullname;
            } else {
                $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance);
                $cmid = $cm->id;
                $name = $item->itemname;
            }
            $recentgrades[] = array(
                'courseid'=>$item->courseid,
                'coursename'=>$courseList[$item->courseid]->fullname,
                'cmid'=>$cmid,
                'name'=>$name,
                'grade'=>grade_format_gradevalue($grade->finalgrade, $item, true),
                'range'=>number_format($item->grademin, 0) . "-" . number_format($item->grademax, 0),
                'percentage'=>grade_format_gradevalue($grade->finalgrade, $item, true, GRADE_DISPLAY_TYPE_PERCENTAGE),
                'feedback'=>format_text($grade->feedback, $grade->feedbackformat)
            );
        }

        $result['recentgrades'] = $recentgrades;
        return $result;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_user_dashboard_parameters() {
        return new external_function_parameters(
            array(
                'numberofitems' => new external_value(PARAM_INT, 'Number of items to get - all if not set.')
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_user_dashboard_returns() {
        return new external_single_structure(
            array(
                'courses' =>
                    new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'course id'),
                                'fullname' => new external_value(PARAM_TEXT, 'name of course'),
                                'grade' => new external_value(PARAM_TEXT, 'grade'),
                            )
                        ), 'List of all enroled courses
                        .'
                    ),
                'activitiesdue' =>
                    new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'courseid' => new external_value(PARAM_INT, 'course id'),
                                'coursename' => new external_value(PARAM_TEXT, 'name of course'),
                                'cmid' => new external_value(PARAM_INT, 'course module id'),
                                'section' => new external_value(PARAM_INT, 'section id'),
                                'modname' => new external_value(PARAM_TEXT, 'module name'),
                                'name' => new external_value(PARAM_TEXT, 'name of activity'),
                                'type' => new external_value(PARAM_TEXT, 'type of activity'),
                                'duedate' => new external_value(PARAM_INT, 'date the activity is due'),
                                'cutoffdate' => new external_value(PARAM_INT, 'last time the activity can be submitted'),
                            )
                        ), 'List of upcoming activities sorted in descending date due order
                        .'
                    ),
                'unreadposts' =>
                    new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'courseid' => new external_value(PARAM_INT, 'course id'),
                                'coursename' => new external_value(PARAM_TEXT, 'name of course'),
                                'cmid' => new external_value(PARAM_INT, 'course module id'),
                                'name' => new external_value(PARAM_TEXT, 'name of forum'),
                                'count' => new external_value(PARAM_INT, 'number of unread messages in forum'),
                            )
                        ), 'List of forums with unread posts in forum name order
                        .'
                    ),
                'recentgrades' =>
                    new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'courseid' => new external_value(PARAM_INT, 'course id'),
                                'coursename' => new external_value(PARAM_TEXT, 'name of course'),
                                'cmid' => new external_value(PARAM_INT, 'course module id (zero if grade is at course level)'),
                                'name' => new external_value(PARAM_TEXT, 'name of activity'),
                                'grade' => new external_value(PARAM_TEXT, 'grade'),
                                'range' => new external_value(PARAM_TEXT, 'range of grade'),
                                'percentage' => new external_value(PARAM_TEXT, 'grade as percentage'),
                                'feedback' => new external_value(PARAM_RAW, 'feedback from marker'),
                            )
                        ), 'List of recent grades sorted in descending date order
                        .'
                    )
                )
            );
    }
    /**
     * Get courses for the user
     *
     * @param int $userid
     * @return array
     */
    public static function  get_user_courses($userid = null) {
        global $CFG, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        } elseif ($userid != $USER->id) {
            $usercontext = context_user::instance($userid, MUST_EXIST);
            if (!has_capability('moodle/user:viewdetails', $usercontext)) {
                throw new moodle_exception('errornoaccess', 'webservice');
            }
        }

        $my_courses = enrol_get_users_courses($userid, true, 'id, fullname', 'sortorder ASC, fullname ASC');

        $settings = external_settings::get_instance();
        $settings->set_filter(true);

        $usercourses = array();
        if (!empty($my_courses)) {
            foreach ($my_courses as $course) {
                $context = context_course::instance($course->id, IGNORE_MISSING);
                $usercourses[] = array(
                    'id'=>$course->id,
                    'fullname'=>external_format_string(
                        get_course_display_name_for_list($course),
                        $context->id,
                        true,
                        array('escape'=>0)
                    )
                );
            }
        }

        return $usercourses;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_user_courses_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'User ID')
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_user_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'course id'),
                    'fullname' => new external_value(PARAM_TEXT, 'course full name')
                )
            ), 'List of user courses.'
        );
    }

    /*****
     * Returns the sections and their contents within the course with the given id
     *
     * @static
     * @param int $courseid
     * @throws moodle_exception
     * @return array
     */
    public static function get_course_sections($courseid) {

        global $CFG, $DB, $USER, $PAGE;
        
        if ($CFG->version >= 2017051500) {
            //From Moodle 3.3 onwards this is deprecated
            // use function get_course_contents in course/externallib.php
            // service core_course_get_contents
            throw new moodle_exception('Depreciated use core_course_get_contents', 'webservice');
        }

        require_once($CFG->dirroot . "/course/lib.php");

        // Validate the given parameter.
        $params = self::validate_parameters(self::get_course_sections_parameters(), array('courseid' => $courseid));

        $retvalue = array();

        $course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
        $coursecontext = context_course::instance($courseid);

        $token = optional_param('wstoken', '', PARAM_ALPHANUM);

        $retvalue['sections'] = array();
        require_once($CFG->dirroot . "/course/format/lib.php");
        $modinfo = get_fast_modinfo($course);
        $courseformat = course_get_format($course);
        $course = $courseformat->get_course();

        $retvalue['courseformat'] = $course->format;
        if ($course->format == 'grid') {
            require_once($CFG->dirroot . "/course/format/grid/lib.php");
            $retvalue['firstsectionvisible'] = $courseformat->get_summary_visibility($courseid)->showsummary;
        }

        if (!is_enrolled($coursecontext, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }
        foreach ($modinfo->get_section_info_all() as $section => $sectioninfo) {
            if ($section <= $course->numsections and $sectioninfo->uservisible) {
                $sectionvalues = array();
                $sectionvalues['id'] = $sectioninfo->id;
                $sectionvalues['name'] = get_section_name($course, $sectioninfo);
                $summary = file_rewrite_pluginfile_urls($sectioninfo->summary, 'webservice/pluginfile.php', $coursecontext->id, 'course',
                    'section', $sectioninfo->id);
                $sectionvalues['summary'] = format_text($summary, $sectioninfo->summaryformat, array('filter'=>false));
                $sectionvalues['baselink'] = "{$CFG->wwwroot}/local/ombiel_webservices/login.php?wstoken={$token}&userid={$USER->id}&cmid=";
                $sectionvalues['coursemodules'] = array();
                if (!empty($modinfo->sections[$sectioninfo->section])) {
                    foreach ($modinfo->sections[$sectioninfo->section] as $cmid) {
                        $cm = $modinfo->cms[$cmid];
                        if ($cm->uservisible) {
                            $module = array();

                            // Common info
                            $module['id'] = $cm->id;
                            $module['name'] = format_string($cm->name, true);
                            $module['modname'] = $cm->modname;
                            $module['modplural'] = $cm->modplural;
                            $module['modicon'] = $cm->get_icon_url()->out(false);
                            $module['indent'] = $cm->indent;
                            $instance = $DB->get_record($cm->modname, array('id'=>$cm->instance));
                            if (!empty($cm->showdescription) or $cm->modname == 'label') {
                                $cmcontext = context_module::instance($cm->id);
                                $options = array('noclean' => true, 'para' => false, 'filter' => true, 'context' => $cmcontext, 'overflowdiv' => true);
                                $intro = file_rewrite_pluginfile_urls($instance->intro, 'webservice/pluginfile.php', $cmcontext->id, 'mod_'.$cm->modname, 'intro', null);
                                $module['description'] = trim(format_text($intro, $instance->introformat, $options, null));
                            }
                            $baseurl = 'webservice/pluginfile.php';
                            if ($cm->modname == 'panopto') {
                                require_once($CFG->dirroot . '/mod/panopto/locallib.php');
                                $module['contents'][0]['type'] = 'panopto';
                                $module['contents'][0]['content'] = urlencode(mod_panopto_get_full_panopto($instance, $cm, $course));
                                $module['contents'][0]['timemodified'] = $instance->timemodified;
                            } elseif($cm->modname == 'equella') {
                                require_once($CFG->dirroot . '/mod/equella/common/lib.php');
                                $module['contents'][0]['type'] = 'equella';
                                $module['contents'][0]['content'] = urlencode(equella_appendtoken($instance->url));
                                $module['contents'][0]['timemodified'] = $instance->timemodified;
                            } else {
                                require_once($CFG->dirroot . '/mod/' . $cm->modname . '/lib.php');
                                $getcontentfunction = $cm->modname . '_export_contents';
                                if (function_exists($getcontentfunction)) {
                                    if ($contents = $getcontentfunction($cm, $baseurl)) {
                                        $module['contents'] = $contents;
                                    }
                                }
                            }
                            // Assign result to $sectioncontents.
                            $sectionvalues['coursemodules'][] = $module;

                        }
                    }
                }
                $retvalue['sections'][] = $sectionvalues;
            }
        }

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $params = array(
                'context' => $coursecontext
            );
            $event = \core\event\course_viewed::create($params);
            $event->trigger();
        }
        // If there is an echo 360 block on this course build a link to the echocenter
        if ($DB->record_exists('block_instances', array('blockname'=>'echo360_echocenter', 'parentcontextid'=>$coursecontext->id))) {
            $retvalue['echo360link'] = "{$CFG->wwwroot}/local/ombiel_webservices/login.php?wstoken={$token}&userid={$USER->id}&echo360id={$course->id}";
        }
        $retvalue['courselink'] = "{$CFG->wwwroot}/local/ombiel_webservices/login.php?wstoken={$token}&userid={$USER->id}&courseid={$course->id}";
        $retvalue['language'] = current_language();

        return $retvalue;

    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_course_sections_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID')
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_course_sections_returns() {
        return new external_single_structure(
            array(
                'courselink' => new external_value(PARAM_URL, 'Link to native course page'),
                'courseformat' => new external_value(PARAM_TEXT, 'Format of course'),
                'firstsectionvisible' => new external_value(PARAM_BOOL, 'True to hide the first section when using the grid format', VALUE_OPTIONAL),
                'echo360link' => new external_value(PARAM_URL, 'Link to echocenter', VALUE_OPTIONAL),
                'language' => new external_value(PARAM_ALPHA, 'prefered language - put at this level for backward compatibility', VALUE_OPTIONAL),
                'sections' =>
                    new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'section id'),
                                'name' => new external_value(PARAM_TEXT, 'name of section'),
                                'summary' => new external_value(PARAM_RAW, 'Summary of section content', VALUE_OPTIONAL),
                                'imagefile' => new external_value(PARAM_URL, 'URL of image for grid layout', VALUE_OPTIONAL),
                                'baselink' => new external_value(PARAM_URL, 'First part of link to native module page'),
                                'coursemodules' =>
                                    new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'id' => new external_value(PARAM_INT, 'course module id'),
                                                'name' => new external_value(PARAM_TEXT, 'activity module name'),
                                                'description' => new external_value(PARAM_RAW, 'activity description', VALUE_OPTIONAL),
                                                'modicon' => new external_value(PARAM_URL, 'activity icon url'),
                                                'modname' => new external_value(PARAM_PLUGIN, 'activity module type'),
                                                'modplural' => new external_value(PARAM_TEXT, 'activity module plural name'),
                                                'indent' => new external_value(PARAM_INT, 'number of identations in the site'),
                                                'contents' => new external_multiple_structure(
                                                    new external_single_structure(
                                                        array(
                                                            // Content info.
                                                            'type' => new external_value(PARAM_TEXT, 'a file or a folder or external link'),
                                                            'filename' => new external_value(PARAM_FILE, 'filename', VALUE_OPTIONAL),
                                                            'filepath' => new external_value(PARAM_PATH, 'filepath', VALUE_OPTIONAL),
                                                            'filesize' => new external_value(PARAM_INT, 'filesize', VALUE_OPTIONAL),
                                                            'fileurl' => new external_value(PARAM_URL, 'downloadable file url', VALUE_OPTIONAL),
                                                            'content' => new external_value(PARAM_RAW, 'Raw content, will be used when type is content',
                                                                VALUE_OPTIONAL),
                                                            'timecreated' => new external_value(PARAM_INT, 'Time created', VALUE_OPTIONAL),
                                                            'timemodified' => new external_value(PARAM_INT, 'Time modified', VALUE_OPTIONAL),
                                                            'sortorder' => new external_value(PARAM_INT, 'Content sort order', VALUE_OPTIONAL),

                                                            // Copyright related info.
                                                            'userid' => new external_value(PARAM_INT, 'User who added this content to moodle', VALUE_OPTIONAL),
                                                            'author' => new external_value(PARAM_TEXT, 'Content owner', VALUE_OPTIONAL),
                                                            'license' => new external_value(PARAM_TEXT, 'Content license', VALUE_OPTIONAL),
                                                        )
                                                    )
                                                , VALUE_DEFAULT, array())
                                            )
                                        )
                                    , VALUE_DEFAULT, array())
                                )
                            )
                        , 'List of section objects. ')
                )
            );
    }

    /*********
     * Gets the course modules within a section
     *
     * @todo Depreciate in next release as this is now part of get_course_sections
     *
     * @param $courseid
     * @throws moodle_exception
     * @return array
     */
    public static function get_section_content($sectionid) {

        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/course/lib.php");
        require_once("$CFG->libdir/weblib.php");

        // Validate the given parameter.
        $params = self::validate_parameters(self::get_section_content_parameters(), array('sectionid' => $sectionid));

        $sectionRecord = $DB->get_record('course_sections', array('id'=>$sectionid), '*', MUST_EXIST);

        $course = $DB->get_record('course', array('id'=>$sectionRecord->course), '*', MUST_EXIST);

        $coursecontext = context_course::instance($course->id);

        $modinfo = get_fast_modinfo($course);

        if (!is_enrolled($coursecontext, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $sectionvalues = array();

        require_once($CFG->dirroot . "/course/format/lib.php");
        $sectioninfo =  $modinfo->get_section_info($sectionRecord->section, MUST_EXIST);

        $sectionvalues['id'] = $sectionid;
        $sectionvalues['name'] = get_section_name($course, $sectioninfo);
        $summary = file_rewrite_pluginfile_urls($sectioninfo->summary, 'webservice/pluginfile.php', $coursecontext->id, 'course',
                    'section', $sectioninfo->id);
        $sectionvalues['summary'] = format_text($summary, $sectioninfo->summaryformat, array('filter'=>false));

        $token = optional_param('wstoken', '', PARAM_ALPHANUM);
        $sectionvalues['baselink'] = "{$CFG->wwwroot}/local/ombiel_webservices/login.php?wstoken={$token}&userid={$USER->id}&cmid=";

        $sectionvalues['contents'] = array();
        foreach ($modinfo->sections[$sectionRecord->section] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if ($cm->uservisible) {
                $module = array();

                // Common info
                $module['id'] = $cm->id;
                $module['name'] = format_string($cm->name, true);
                $module['modname'] = $cm->modname;
                $module['modplural'] = $cm->modplural;
                $module['modicon'] = $cm->get_icon_url()->out(false);
                $module['indent'] = $cm->indent;
                $instance = $DB->get_record($cm->modname, array('id'=>$cm->instance));
                if (!empty($cm->showdescription) or $cm->modname == 'label') {
                    $cmcontext = context_module::instance($cm->id);
                    $module['description'] = format_text(
                        file_rewrite_pluginfile_urls($instance->intro, 'webservice/pluginfile.php', $cmcontext->id, 'mod_'.$cm->modname, 'intro', null)
                    );
                }
                $baseurl = 'webservice/pluginfile.php';
                if ($cm->modname == 'panopto') {
                    require_once($CFG->dirroot . '/mod/panopto/locallib.php');
                    $module['contents'][0]['type'] = 'panopto';
                    $module['contents'][0]['content'] = urlencode(mod_panopto_get_full_panopto($instance, $cm, $course));
                    $module['contents'][0]['timemodified'] = $instance->timemodified;
                } else {
                    require_once($CFG->dirroot . '/mod/' . $cm->modname . '/lib.php');
                    // Call $modulename_export_contents
                    // ...(each module callback take care about checking the capabilities).
                    $getcontentfunction = $cm->modname . '_export_contents';
                    if (function_exists($getcontentfunction)) {
                        if ($contents = $getcontentfunction($cm, $baseurl)) {
                            $module['contents'] = $contents;
                        }
                    }
                }
                // Assign result to $sectioncontents.
                $sectionvalues['contents'][] = $module;

            }
        }
        return $sectionvalues;

    }

    /**
     * @return external_function_parameters
     */
    public static function get_section_content_parameters() {
        return new external_function_parameters(
            array(
                'sectionid' => new external_value(PARAM_INT, 'Section ID')
            )
        );
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_section_content_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'section id'),
                'name' => new external_value(PARAM_TEXT, 'name of section'),
                'summary' => new external_value(PARAM_RAW, 'Summary of section content', VALUE_OPTIONAL),
                'baselink' => new external_value(PARAM_URL, 'First part of link to native module page'),
                'contents' =>
                    new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'activity id'),
                                'name' => new external_value(PARAM_TEXT, 'activity module name'),
                                'description' => new external_value(PARAM_RAW, 'activity description', VALUE_OPTIONAL),
                                'modicon' => new external_value(PARAM_URL, 'activity icon url'),
                                'modname' => new external_value(PARAM_PLUGIN, 'activity module type'),
                                'modplural' => new external_value(PARAM_TEXT, 'activity module plural name'),
                                'indent' => new external_value(PARAM_INT, 'number of identation in the site'),
                                'contents' => new external_multiple_structure(
                                    new external_single_structure(
                                        array(
                                            // Content info.
                                            'type' => new external_value(PARAM_TEXT, 'a file or a folder or external link'),
                                            'filename' => new external_value(PARAM_FILE, 'filename', VALUE_OPTIONAL),
                                            'filepath' => new external_value(PARAM_PATH, 'filepath', VALUE_OPTIONAL),
                                            'filesize' => new external_value(PARAM_INT, 'filesize', VALUE_OPTIONAL),
                                            'fileurl' => new external_value(PARAM_URL, 'downloadable file url', VALUE_OPTIONAL),
                                            'content' => new external_value(PARAM_RAW, 'Raw content, will be used when type is content',
                                                VALUE_OPTIONAL),
                                            'timecreated' => new external_value(PARAM_INT, 'Time created', VALUE_OPTIONAL),
                                            'timemodified' => new external_value(PARAM_INT, 'Time modified', VALUE_OPTIONAL),
                                            'sortorder' => new external_value(PARAM_INT, 'Content sort order', VALUE_OPTIONAL),

                                            // Copyright related info.
                                            'userid' => new external_value(PARAM_INT, 'User who added this content to moodle', VALUE_OPTIONAL),
                                            'author' => new external_value(PARAM_TEXT, 'Content owner', VALUE_OPTIONAL),
                                            'license' => new external_value(PARAM_TEXT, 'Content license', VALUE_OPTIONAL),
                                        )
                                    ), VALUE_DEFAULT, array()
                                )
                            )
                        ), 'List of section objects. A section has an id, a name, visible,a summary
                        .'
                    )
                )
        );
    }

    /**
     * Returns assignment information based on the course module id provided.
     *
     * @param int $cmid the course module id of the assignment
     *
     * @return array|\stdClass
     */
    public static function  get_cm_assignment($cmid) {

        global $USER, $CFG, $DB;

        $params = self::validate_parameters(self::get_cm_assignment_parameters(), array('cmid' => $cmid));

        $cm = $DB->get_record('course_modules', array('id' => $cmid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
        $section = $DB->get_record('course_sections', array('id'=>$cm->section), '*', MUST_EXIST);

        require_once ($CFG->dirroot . '/mod/assign/locallib.php');

        if (!$DB->record_exists('modules', array('id'=>$cm->module, 'name'=>'assign'))){
            throw new moodle_exception('notanassignment', 'webservice');
        }
        $instance = $DB->get_record('assign', array('id'=>$cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cmid);

        if (!is_enrolled($context, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $context = context_module::instance($cm->id);
        $assign = new assign($context, $cm, $course);

        $assignout = array();
        $assignout['id'] = $cm->instance;
        $assignout['name'] = format_string($instance->name);
        $assignout['sectionname'] = $section->name;

        $assignout['description'] = format_text(
            file_rewrite_pluginfile_urls($instance->intro, 'webservice/pluginfile.php', $context->id, 'mod_assign', 'intro', null)
        );

        $assignout['deadline'] = $instance->duedate;

        $assignout['language'] = current_language();
        return $assignout;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_cm_assignment_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value(PARAM_INT, 'Course module id')
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_cm_assignment_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'assign_id'),
                'name' => new external_value(PARAM_TEXT, 'name of assignment'),
                'description' => new external_value(PARAM_RAW, 'intro used for assignment', VALUE_OPTIONAL),
                'sectionname' => new external_value(PARAM_TEXT, 'name of the section, used to put files in the correct place', VALUE_OPTIONAL),
                'deadline' => new external_value(PARAM_INT, 'timestamp of the deadline for the course'),
                'language' => new external_value(PARAM_ALPHA, 'prefered language - put at this level for backward compatibility', VALUE_OPTIONAL),
            )
        );
    }
     /**
     * @static returns a array containing student grades
     * @param int $userid the id of the user whose grades will be returned
     * @return array
     */
    public static function  get_user_grades($userid = null) {

        global $CFG, $USER;

        $params = self::validate_parameters(self::get_user_grades_parameters(), array('userid' => $userid));

        if (empty($userid)) {
            $userid = $USER->id;
        }

        require_once($CFG->libdir . '/gradelib.php');

        // Get all user courses.
        $my_courses = enrol_get_users_courses($userid, false, 'id, shortname, fullname, showgrades, visible');

        $coursegradesout = array();

        if (!empty($my_courses)) {
            foreach ($my_courses as $course) {
                if ($course->showgrades) {

                    $coursecontext = context_course::instance($course->id);
                    if ($userid != $USER->id){
                        if (!has_capability('moodle/grade:viewall', $coursecontext)) {
                            continue;
                        }
                    } else {
                        if (!has_capability('moodle/grade:view', $coursecontext)) {
                            continue;
                        }
                    }

                    // Get course grade_item.
                    $course_item = grade_item::fetch_course_item($course->id);

                    // Get the stored grade.
                    $course_grade = new grade_grade(array('itemid' => $course_item->id, 'userid' => $userid), true);
                    if (!$course_grade->is_hidden()) {

                        $course_grade->grade_item =& $course_item;
                        $finalgrade = $course_grade->finalgrade;
                        $grademax = $course_grade->grade_item->grademax;
                        $grademin = $course_grade->grade_item->grademin;

                        $gradeout = new stdClass();
                        $gradeout->id = $course->id;
                        $gradeout->fullname = $course->fullname;
                        $gradeout->grade = grade_format_gradevalue($finalgrade, $course_item, true);
                        $gradeout->range = number_format($grademin, 0) . "&ndash;" . number_format($grademax, 0);
                        $gradeout->percentage = grade_format_gradevalue($finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_PERCENTAGE);
                        $gradeout->feedback = format_text($course_grade->feedback, $course_grade->feedbackformat);

                        $coursegradesout[] = (array)$gradeout;
                    }

                }
            }
        }

        return $coursegradesout;

    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_user_grades_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'User ID')
            )
        );
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_user_grades_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'course id'),
                    'fullname' => new external_value(PARAM_TEXT, 'course fullname'),
                    'grade' => new external_value(PARAM_TEXT, 'course grade '),
                    'range' => new external_value(PARAM_TEXT, 'course range '),
                    'percentage' => new external_value(PARAM_TEXT, 'course percentage '),
                    'feedback' => new external_value(PARAM_RAW, 'course feedback '),
                )
            ), 'List of courses the user is in and the grades the user has achieved.'
        );
    }


    /**
     * @static returns a array containing student grades
     * @param $courseid
     * @param int $userid the id of the user whose grades will be returned
     * @return array
     */
    public static function get_course_grades($courseid, $userid = null) {

        global $CFG, $USER;
        // If user id is empty pass the global user id.
        $userid = (empty($userid)) ? $USER->id : $userid;
        $params = self::validate_parameters(self::get_course_grades_parameters(), array('courseid' => $courseid,
            'userid' => $userid));

        $context = context_course::instance($courseid);

        if ($userid != $USER->id){
            if (!has_capability('moodle/grade:viewall', $context)) {
                throw new moodle_exception('errornoaccess', 'webservice');
            }
        } else {
            if (!has_capability('moodle/grade:view', $context)) {
                throw new moodle_exception('errornoaccess', 'webservice');
            }
        }
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/user/lib.php');

        $gpr = new grade_plugin_return(array('type' => 'report', 'plugin' => 'user', 'courseid' => $courseid, 'userid' => $userid));

        // Grab the grade_tree for this course.
        $report = new grade_report_user($courseid, $gpr, $context, $userid);
        $report->fill_table();
        $grades = array();

        foreach ($report->tabledata as $data) {
            if (isset($data['grade'])) {
                $grade['gradeitem'] = strip_tags($data['itemname']['content']);
                $grade['grade'] = ($data['grade']['content'] == 'Error') ? '-' : $data['grade']['content'];

                $grade['range'] = $data['range']['content'];
                $grade['percentage'] = ($data['percentage']['content'] == 'Error') ? '-' : $data['percentage']['content'];
                $grade['feedback'] = $data['feedback']['content'];
                $grades[] = $grade;

            }
        }

        return $grades;

    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_course_grades_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'userid' => new external_value(PARAM_INT, 'User ID')
            )
        );
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_course_grades_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'gradeitem' => new external_value(PARAM_TEXT, 'grade item name'),
                    'grade' => new external_value(PARAM_TEXT, 'course grade '),
                    'range' => new external_value(PARAM_TEXT, 'course range '),
                    'percentage' => new external_value(PARAM_TEXT, 'course percentage '),
                    'feedback' => new external_value(PARAM_RAW, 'course feedback ')

                )
            ), 'List of grades achieved for individual work in a course.'
        );
    }

    /**
     *
     * @param int $userid the id of the user whose forums will be returned
     *
     *
     * return int id of post or false
     * @return array
     */
    public static function  get_user_forums($userid = null) {

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_user_forums_parameters(), array('userid' => $userid));

        if (empty($userid)) {
            $userid = $USER->id;
        } elseif ($userid != $USER->id) {
            $usercontext = context_user::instance($userid, MUST_EXIST);
            if (!has_capability('moodle/user:viewdetails', $usercontext)) {
                throw new moodle_exception('errornoaccess', 'webservice');
            }
        }
                // Get all user courses.
        $my_courses = enrol_get_users_courses($userid, true);
        $userforums = array();
        if (!empty($my_courses)) {
            foreach ($my_courses as $course) {
                $modinfo = get_fast_modinfo($course);
                $forums = $modinfo->get_instances_of('forum');

                // Create.
                if (!empty($forums)) {
                    foreach ($forums as $cm) {
                        $instance = $DB->get_record('forum', array('id'=>$cm->instance), '*', MUST_EXIST);
                        $forumout = array();
                        $forumout['id'] = $instance->id;
                        $forumout['name'] = format_string($instance->name);
                        if (!empty($cm->showdescription)) {
                            $context = context_module::instance($cm->id);
                            $forumout['description'] = format_text(
                                file_rewrite_pluginfile_urls($instance->intro, 'webservice/pluginfile.php', $context->id, 'mod_forum', 'intro', null)
                            );
                        }
                        $forumout['courseid'] = $cm->course;
                        $forumtracked = forum_tp_is_tracked($instance);
                        if (!empty($forumtracked)) {
                            $forumout['unreadposts'] = forum_tp_count_forum_unread_posts($cm, $course);
                        } else {
                            $forumout['unreadposts'] = 0;
                        }
                        $userforums[] = $forumout;
                    }
                }
            }
        }

        return $userforums;
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_user_forums_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, 'user id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_user_forums_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id'),
                    'name' => new external_value(PARAM_TEXT, 'name of forum'),
                    'description' => new external_value(PARAM_RAW, 'intro', VALUE_OPTIONAL),
                    'courseid' => new external_value(PARAM_INT, 'courseid'),
                    'unreadposts' => new external_value(PARAM_INT, 'number of unread posts (if forum tracked)'),
                )
            ), 'List of forums that the user can post in'
        );
    }

    /**
     * @static returns a array containing all forums within a course
     * @param $coursemoduleid
     * @return array
     * @internal param int $courseid the id of the course whose forums will be retrieved
     */
    public static function  get_cm_forum($coursemoduleid) {

        global $USER, $CFG, $DB;

        $params = self::validate_parameters(self::get_cm_forum_parameters(), array('coursemoduleid' => $coursemoduleid));

        require_once ($CFG->dirroot . '/mod/forum/lib.php');

        $cm = $DB->get_record('course_modules', array('id' => $coursemoduleid),'*', MUST_EXIST);

        if (!$DB->record_exists('modules', array('id'=>$cm->module, 'name'=>'forum'))){
            throw new moodle_exception('notaforum', 'webservice');
        }
        $context = context_module::instance($coursemoduleid);

        if (!is_enrolled($context, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $groupmode    = groups_get_activity_groupmode($cm, $cm->course);
        $currentgroup = groups_get_activity_group($cm);
        $courseforum = array();
        $f = $DB->get_record('forum', array('id' => $cm->instance), '*', MUST_EXIST);
        if (!empty($f)) {
            $forum = new stdClass();
            $forum->id = $f->id;
            $forum->coursemoduleid = $cm->id;
            $forum->name = $f->name;
            $context = context_module::instance($cm->id);
            $forum->description = format_text(
                file_rewrite_pluginfile_urls($f->intro, 'webservice/pluginfile.php', $context->id, 'mod_forum', 'intro', null)
            );
            $forum->canpost = forum_user_can_post_discussion($f, $currentgroup, $groupmode, $cm, $context);
            $courseforum = (array)$forum;

            if ($CFG->version >= 2014051200) { // Moodle 2.7
                $params = array(
                    'context' => $context,
                    'objectid' => $forum->id
                );
                $event = \mod_forum\event\course_module_viewed::create($params);
                $event->add_record_snapshot('course_modules', $cm);
                $event->add_record_snapshot('course', $DB->get_record("course", array("id" => $cm->course)));
                $event->add_record_snapshot('forum', $f);
                $event->trigger();
            }
        }

        $courseforum['language'] = current_language();

        return $courseforum;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_cm_forum_parameters() {
        return new external_function_parameters(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'Course module id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_cm_forum_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_TEXT, 'forum id for add forum discussion'),
                'name' => new external_value(PARAM_TEXT, 'name'),
                'description' => new external_value(PARAM_RAW, 'intro used for forum', VALUE_OPTIONAL),
                'canpost' => new external_value(PARAM_BOOL, 'Whether this user can add a post'),
                'language' => new external_value(PARAM_ALPHA, 'prefered language - put at this level for backward compatibility', VALUE_OPTIONAL)
            )
        );
    }

    /**
     * @static returns a array containing all discussions within a forum
     * @param int $coursemoduleid the coursemodule id of the forum whose discussions will be returned
     * will be returned
     */
    public static function get_forum_discussions($coursemoduleid) {

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_forum_discussions_parameters(), array('coursemoduleid' => $coursemoduleid));

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $coursemodule = $DB->get_record('course_modules', array('id' => $coursemoduleid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $coursemodule->course), '*', MUST_EXIST);
        $forum = $DB->get_record('forum', array('id' => $coursemodule->instance), '*', MUST_EXIST);

        if (!$DB->record_exists('modules', array('id'=>$coursemodule->module, 'name'=>'forum'))){
            throw new moodle_exception('notaforum', 'webservice');
        }

        $context = context_module::instance($coursemoduleid);
        if (!is_enrolled($context, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $records = forum_get_discussions($coursemodule);

        $recordsreplies = forum_count_discussion_replies($coursemodule->instance);

        $discussionrecords = array();

        if (!empty($records)) {
            foreach ($records as $d) {
                $discussion = new stdClass();
                $discussion->id = $d->id;
                $discussion->name = $d->name;
                $discussion->author = $d->firstname . ' ' . $d->lastname;
                $discussion->content = $d->message;
                $discussion->discussion = $d->discussion;
                $discussion->canreply = forum_user_can_post($forum, $d, $USER, $coursemodule, $course, $context);

                if (isset($recordsreplies[$d->discussion])) {
                    $discussion->replies = $recordsreplies[$d->discussion]->replies;
                    $post = forum_get_post_full($recordsreplies[$d->discussion]->lastpostid);
                    $discussion->lastreply = $post->modified;
                } else {
                    $discussion->replies = 0;
                    $discussion->lastreply = 0;
                }

                $discussionrecords[] = (array)$discussion;
            }

        }

        return $discussionrecords;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_forum_discussions_parameters() {
        return new external_function_parameters(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'Course module id of the discussion'),
            )
        );
    }


    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_forum_discussions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'post id'),
                    'name' => new external_value(PARAM_TEXT, 'name of discussion'),
                    'discussion' => new external_value(PARAM_INT, 'discussion id '),
                    'canreply' => new external_value(PARAM_BOOL, 'true if user can reply in this discussion'),
                    'author' => new external_value(PARAM_TEXT, 'author of discussion'),
                    'content' => new external_value(PARAM_RAW, 'content of discussion'),
                    'replies' => new external_value(PARAM_INT, 'the number of replies to this discussion'),
                    'lastreply' => new external_value(PARAM_INT,
                        'timestamp of the date & time of the last reply to this discussion'),
                )
            ), 'List of discussion in the given forum'
        );
    }


    /**
     * @static returns a array containing all posts within a discussion
     * @param int $discussionid the id of the discussion whose psts will be returned
     * will be returned
     * @return array
     */
    public static function  get_discussion_posts($discussionid) {

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_discussion_posts_parameters(), array('discussionid' => $discussionid));

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));

        $forum      = $DB->get_record('forum', array('id' => $discussion->forum));
        $cm         = get_coursemodule_from_instance('forum', $forum->id);
        $context    = context_module::instance($cm->id);

        if (!has_capability('mod/forum:viewdiscussion', $context)) { /// User must have perms to view discussions
            throw new moodle_exception('errornoaccess', 'webservice');
        }

        $forumtracked = forum_tp_is_tracked($forum);
        $records = forum_get_all_discussion_posts($discussionid, 'modified', $discussion->forum);
        $postrecords = array();

        if (!empty($records)) {
            foreach ($records as $p) {
                if ($p->parent) {
                    $post = new stdClass();
                    $post->id = $p->id;
                    $post->parent = $p->parent;
                    $post->subject = $p->subject;
                    $post->content = $p->message;
                    $post->author = $p->firstname . " " . $p->lastname;
                    $post->date = $p->modified;

                    $postrecords[] = (array)$post;
                    if ($CFG->forum_usermarksread && forum_tp_can_track_forums($forum) && forum_tp_is_tracked($forum)) {
                        forum_tp_add_read_record($USER->id, $post->id);
                    }
                }
            }
        }

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $params = array(
                'context' => $context,
                'objectid' => $discussionid,
            );
            $event = \mod_forum\event\discussion_viewed::create($params);
            $event->add_record_snapshot('forum_discussions', $discussion);
            $event->add_record_snapshot('forum', $forum);
            $event->trigger();
        }
        return $postrecords;

    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_discussion_posts_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'Disscussion id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_discussion_posts_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id'),
                    'parent' => new external_value(PARAM_INT, 'parent post id'),
                    'subject' => new external_value(PARAM_TEXT, 'subject of post'),
                    'author' => new external_value(PARAM_TEXT, 'author of discussion'),
                    'content' => new external_value(PARAM_RAW, 'content of discussion'),
                    'date' => new external_value(PARAM_INT, 'timestamp of the date & time of the post was last modified'),
                )
            ), 'List of discussion in the given forum'
        );
    }


    /**
     * @static allows a discussion to be added to the forum with the given id
     * @param int $forumid the id of the forum that the discussion will be added to
     * @param int $userid  the id fo the user who is creating the discussion
     * @param string $subject the subject of the discussion
     * @param string $message the message in the discussion post
     * @param int $mailnow should all other forum users be mailed about this post
     *
     * return int id of post or false
     * @return array
     */
    public static function  add_forum_discussion($forumid, $subject, $message, $mailnow = 0) {

        global $CFG, $DB, $USER, $SESSION;

        $params = self::validate_parameters(self::add_forum_discussion_parameters(), array('forumid' => $forumid,
            'subject' => $subject, 'message' => $message, 'mailnow' => $mailnow));

        $result = array();

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $forum = $DB->get_record('forum', array('id' => $forumid));
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course);
        $context    = context_module::instance($cm->id);

        $result['result'] = 0;

        $groupid = null;

        // Get group
        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groups = groups_get_activity_allowed_groups($cm);
            if (count($groups) == 1) {
                $group = end($groups);
                $groupid = $group->id;
            }
        }

        if (forum_user_can_post_discussion($forum, $groupid, -1, $cm)) {

            $discussion = new stdClass();
            $discussion->forum = $forumid;
            $discussion->name = $subject;
            $discussion->message = $message;
            // Need to check the other possible values for these two.
            $discussion->messageformat = 1;
            $discussion->messagetrust = 0;
            $discussion->mailnow = $mailnow;
            $discussion->course = $forum->course;
            $discussion->groupid = empty($groupid)?-1:$groupid;
            $discussion->timestart = 0;
            $discussion->timeend = 0;
            $discussion->pinned = 0;
            $message = '';

            if ($discussion->id = forum_add_discussion($discussion, null, $message)) {

                if ($CFG->version >= 2014051200) { // Moodle 2.7
                    $params = array(
                        'context' => $context,
                        'objectid' => $result['result'],
                        'other' => array(
                            'forumid' => $forum->id,
                        )
                    );
                    $event = \mod_forum\event\discussion_created::create($params);
                    $event->add_record_snapshot('forum_discussions', $discussion);
                    $event->trigger();
                }
                $result['result'] = $discussion->id;
            }
        }

        return $result;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function add_forum_discussion_parameters() {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'Forum id'),
                'subject' => new external_value(PARAM_TEXT, 'subject id'),
                'message' => new external_value(PARAM_RAW, 'discussion message'),
                'mailnow' => new external_value(PARAM_INT, 'should all users be mailed now to inform them of this post'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function add_forum_discussion_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_INT, 'id of discussion inserted or 0 if failed'),
            )
        );
    }

    /**
     * @static allows a discussion to be added to the forum with the given id
     * @param int $discussionid the id of the discussion the post will be added to
     * @param int $parentid the id of the post that the post is in reply should be 0 if not in reply to
     *            a post
     * @param string $subject the subject of the post
     * @param string $message the message in the post
     *
     * return int id of post or false
     * @return array
     */
    public static function  add_discussion_post($discussionid, $subject, $message, $parentid = 0) {

        global $CFG, $DB;

        $params = self::validate_parameters(self::add_discussion_post_parameters(), array('discussionid' => $discussionid,
            'subject' => $subject, 'message' => $message, 'parentid' => $parentid));

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $result = array();

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));

        $forum = $DB->get_record('forum', array('id' => $discussion->forum));
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course);
        $context    = context_module::instance($cm->id);
        $course = $DB->get_record('course', array('id' =>  $forum->course));

        if (forum_user_can_post($forum, $discussion, null, $cm, $course)) {
            if (empty($parentid)) {
                $parentid = (!empty($discussion)) ? $discussion->firstpost : 0;
            }

            $post = new stdClass();
            $post->course = $discussion->course;
            $post->discussion = $discussionid;
            $post->subject = $subject;
            $post->message = $message;
            $post->messageformat = 1;
            $post->parent = $parentid;
            $post->itemid = 0;
            $post->messagetrust = 0;
            $message = '';
            $mform = null;

            $result['result'] = forum_add_new_post($post, $mform, $message);
            $params = array(
                'context' => $context,
                'objectid' => $result['result'],
                'other' => array(
                    'discussionid' => $discussion->id,
                    'forumid' => $forum->id,
                    'forumtype' => $forum->type,
                )
            );
            if ($CFG->version >= 2014051200) { // Moodle 2.7
                $event = \mod_forum\event\post_created::create($params);
                $event->add_record_snapshot('forum_posts', $post);
                $event->add_record_snapshot('forum_discussions', $discussion);
                $event->trigger();
            }
        } else {
            $result['result'] = 0;
        }



        return $result;
    }


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function add_discussion_post_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'Discussion id'),
                'subject' => new external_value(PARAM_TEXT, 'subject id'),
                'message' => new external_value(PARAM_RAW, 'post message'),
                'parentid' => new external_value(PARAM_INT, 'parent post'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function add_discussion_post_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_INT, 'id of post inserted or 0 if failed'),
            )
        );
    }

    /**
     *
     * @param int $courseid the id of the course that you want the news from
     * @return array course module id.
     */
    public static function get_coursenews($courseid = null) {
        global $CFG, $USER;

        $courseid = (empty($courseid))?SITEID:$courseid;

        $params = self::validate_parameters(self::get_coursenews_parameters(), array('courseid' => $courseid));

        $context = context_course::instance($courseid);

        if (!is_enrolled($context, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        require_once($CFG->dirroot .'/mod/forum/lib.php');

        $newsforum = forum_get_course_forum($courseid, 'news');
        $cm = get_coursemodule_from_instance('forum', $newsforum->id);

        return array('coursemoduleid'=>$cm->id);

    }


    /**
     * @return external_function_parameters
     */
    public static function get_coursenews_parameters(){
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'course id'),
            )
        );

    }

    /**
     * @return external_multiple_structure
     */
    public static function get_coursenews_returns() {
        return new external_single_structure(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'Course module id')
            ), 'Course news coursemodule '
        );

    }


    /**
     * @param $coursemoduleid
     * @return array
     */
    public static function  get_cm_book($coursemoduleid) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_cm_book_parameters(), array('coursemoduleid' => $coursemoduleid));

        require_once($CFG->dirroot . '/mod/book/locallib.php');

        $cm = $DB->get_record('course_modules', array('id' => $coursemoduleid), '*', MUST_EXIST);

        if (!$DB->record_exists('modules', array('id'=>$cm->module, 'name'=>'book'))){
            throw new moodle_exception('notabook', 'webservice');
        }
        $context = context_module::instance($coursemoduleid);

        if (!is_enrolled($context, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $bookrecord = $DB->get_record('book', array('id'=>$cm->instance), '*', MUST_EXIST);
        $options = empty($bookrecord->displayoptions) ? array() : unserialize($bookrecord->displayoptions);

        // Update 'viewed' state if required by completion system
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new completion_info($DB->get_record('course',array('id'=>$cm->course)));
        $completion->set_module_viewed($cm);
        $bookout = array();

        $bookout['id'] = $bookrecord->id;
        $bookout['name'] = format_string($bookrecord->name);
        switch ($bookrecord->numbering) {
            case BOOK_NUM_NONE:
                $bookout['numbering'] = 'none';
                break;
            case BOOK_NUM_NUMBERS:
                $bookout['numbering'] = 'numbers';
                break;
            case BOOK_NUM_BULLETS:
                $bookout['numbering'] = 'bullets';
                break;
            case BOOK_NUM_INDENTED:
                $bookout['numbering'] = 'indented';
                break;
        }

        $chapters = book_preload_chapters($bookrecord);
        $bookout['chapters'] = array();
        $chapternumber = 1;
        foreach ($chapters as $chapter) {
            if (empty($chapter->hidden)) {
                $chapterout = array();
                $chapterout['chapterid'] = $chapter->id;
                if ($bookrecord->numbering == BOOK_NUM_NUMBERS) {
                    if ($chapter->subchapter) {
                        $chapterout['title'] = "{$chapternumber}.{$chapter->number} ";
                    } else {
                        $chapternumber = $chapter->number;
                        $chapterout['title'] = "{$chapter->number} ";
                    }
                } else {
                    $chapterout['title'] = '';
                }
                $chapterout['title'] .= $chapter->title;
                $chapterout['subchapter'] = $chapter->subchapter;
                $chapterout['parent'] = $chapter->parent;
                $bookout['chapters'][] = $chapterout;
            }
        }

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            \mod_book\event\course_module_viewed::create_from_book($bookrecord, $context)->trigger();
        }
        return $bookout;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_cm_book_parameters() {
        return new external_function_parameters(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'course module id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_cm_book_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'instance id'),
                'name' => new external_value(PARAM_TEXT, 'title of book'),
                'numbering' => new external_value(PARAM_TEXT, 'none, numbers, bullets or indented'),
                'chapters' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'chapterid' => new external_value(PARAM_INT, 'chapter id'),
                            'title' => new external_value(PARAM_TEXT, 'chapter title'),
                            'subchapter' => new external_value(PARAM_BOOL, 'true if this is a subchapter'),
                        )
                    )
                , 'List of chapters')
            )
        );
    }

    /**
     *
     * @global type $CFG
     * @global type $DB
     * @global type $USER
     * @param type $bookid
     * @param type $chapterid
     * @return type array
     * @throws moodle_exception
     */
    public static function  get_book_chapter($bookid, $chapterid) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/book/locallib.php');

        $params = self::validate_parameters(self::get_book_chapter_parameters(), array('bookid'=>$bookid, 'chapterid'=>$chapterid));

        require_once($CFG->dirroot . '/mod/book/lib.php');

        $bookrecord = $DB->get_record('book', array('id'=>$bookid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('book', $bookid);

        $context = context_module::instance($cm->id);

        if (!is_enrolled($context, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $chapterrecord = $DB->get_record('book_chapters', array('id'=>$chapterid), '*', MUST_EXIST);

        if ($chapterrecord->hidden) {
            throw new moodle_exception('invalidaccess', 'webservice');
        }

        $chapters = book_preload_chapters($bookrecord);
        $chapterout = array();

        if (!$bookrecord->customtitles) {
            if (!$chapterrecord->subchapter) {
                $chapterout['title'] = book_get_chapter_title($chapterid, $chapters, $bookrecord, $context);
            } else {
                $chapterout['title'] = book_get_chapter_title($chapterid, $chapters, $bookrecord, $context);
            }
        }
        $chaptertext = file_rewrite_pluginfile_urls($chapterrecord->content, 'webservice/pluginfile.php', $context->id, 'mod_book', 'chapter', $chapterid);
        $chapterout['content'] = format_text($chaptertext, $chapterrecord->contentformat, array('noclean'=>true, 'overflowdiv'=>true, 'context'=>$context));

        $previd = false;
        $nextid = false;
        $last = null;
        foreach ($chapters as $ch) {
            if ($ch->hidden) {
                continue;
            }
            if ($last == $chapterid) {
                $nextid = $ch->id;
                break;
            }
            if ($ch->id != $chapterid) {
                $previd = $ch->id;
            }
            $last = $ch->id;
        }
        $chapterout['previd'] = (int) $previd;
        $chapterout['nextid'] = (int) $nextid;
        $chapterout['language'] = current_language();

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            \mod_book\event\chapter_viewed::create_from_chapter($bookrecord, $context, $chapterrecord)->trigger();
        }

        return $chapterout;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_book_chapter_parameters() {
        return new external_function_parameters(
            array(
                'bookid' => new external_value(PARAM_INT, 'book instance id'),
                'chapterid' => new external_value(PARAM_INT, 'chapter id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_book_chapter_returns() {
        return new external_single_structure(
            array(
                'title' => new external_value(PARAM_TEXT, 'title of chapter unless customtitles set', VALUE_OPTIONAL),
                'content' => new external_value(PARAM_RAW, 'content'),
                'previd' => new external_value(PARAM_INT, 'chapter id of the previous chapter or zero if first chapter'),
                'nextid' => new external_value(PARAM_INT, 'chapter id of the next chapter or zero if last chapter'),
                'language' => new external_value(PARAM_ALPHA, 'prefered language - put at this level for backward compatibility', VALUE_OPTIONAL)
            )
        );
    }

    /**
     * @param $coursemoduleid
     * @return array
     *
     */
    public static function  get_cm_page($coursemoduleid) {

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_cm_page_parameters(), array('coursemoduleid' => $coursemoduleid));

        require_once($CFG->dirroot . '/mod/page/lib.php');

        $cm = $DB->get_record('course_modules', array('id' => $coursemoduleid), '*', MUST_EXIST);

        if (!$DB->record_exists('modules', array('id'=>$cm->module, 'name'=>'page'))){
            throw new moodle_exception('notapage', 'webservice');
        }
        $context = context_module::instance($coursemoduleid);

        if (!is_enrolled($context, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $pagerecord = $DB->get_record('page', array('id'=>$cm->instance), '*', MUST_EXIST);
        $options = empty($pagerecord->displayoptions) ? array() : unserialize($pagerecord->displayoptions);

        // Update 'viewed' state if required by completion system
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new completion_info($DB->get_record('course',array('id'=>$cm->course)));
        $completion->set_module_viewed($cm);

        $page = array();

        $page['id'] = $pagerecord->id;
        if (!empty($options['printheading'])) {
            $page['name'] = format_string($pagerecord->name);
        }
        if (!empty($options['printintro'])) {
            $page['description'] = format_text(
                file_rewrite_pluginfile_urls($pagerecord->intro,
                    'webservice/pluginfile.php', $context->id, 'mod_page', 'intro', null)
            );
        }
        $page['content'] = file_rewrite_pluginfile_urls($pagerecord->content,
                'webservice/pluginfile.php', $context->id, 'mod_page', 'content', $pagerecord->revision);

        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->overflowdiv = true;
        $formatoptions->context = $context;
        $page['content'] = format_text($page['content'], $pagerecord->contentformat, $formatoptions);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $event = \mod_page\event\course_module_viewed::create(array(
                'objectid' => $pagerecord->id,
                'context' => $context
            ));
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('course', $course);
            $event->add_record_snapshot('page', $pagerecord );
            $event->trigger();
        }
        $page['language'] = current_language();

        return $page;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_cm_page_parameters() {
        return new external_function_parameters(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'course module id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_cm_page_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'id'),
                'name' => new external_value(PARAM_TEXT, 'name of page', VALUE_OPTIONAL),
                'description' => new external_value(PARAM_RAW, 'description', VALUE_OPTIONAL),
                'content' => new external_value(PARAM_RAW, 'content'),
                'language' => new external_value(PARAM_ALPHA, 'prefered language - put at this level for backward compatibility', VALUE_OPTIONAL)
            )
        );
    }


    /**
     * @param $coursemoduleid
     * @return array
     *
     *
     * return int id of post or false
     */
    public static function  get_cm_choice($coursemoduleid) {

        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::get_cm_choice_parameters(), array('coursemoduleid' => $coursemoduleid));

        require_once($CFG->dirroot . '/mod/choice/lib.php');

        $cm = $DB->get_record('course_modules', array('id' => $coursemoduleid), '*', MUST_EXIST);

        if (!$DB->record_exists('modules', array('id'=>$cm->module, 'name'=>'choice'))){
            throw new moodle_exception('notachoice', 'webservice');
        }
        $context = context_module::instance($coursemoduleid);

        if (!is_enrolled($context, $USER, '', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $choiceout = array();
        $choicerecord = choice_get_choice($cm->instance);

        $choiceout['id'] = $cm->instance;
        $choiceout['name'] = format_string($choicerecord->name);

        $choiceout['description'] = format_text(
            file_rewrite_pluginfile_urls($choicerecord->intro, 'webservice/pluginfile.php', $context->id, 'mod_choice', 'intro', null)
        );
        $now = time();

        $choiceout['allowanswer'] = false;
        if (empty($choicerecord->timeopen) || $choicerecord->timeopen < $now ) {
            if (empty($choicerecord->timeclose) || $choicerecord->timeclose > $now) {
                if ($choicerecord->allowupdate ||
                        !$DB->record_exists('choice_answers', array("choiceid" => $choicerecord->id, "userid" => $USER->id))) {
                    $choiceout['allowanswer'] = true;
                }
            }
        }
        $choiceout['showresults'] = false;
        switch ($choicerecord->showresults) {
            case CHOICE_SHOWRESULTS_AFTER_ANSWER:
                if ($DB->record_exists('choice_answers', array("choiceid" => $choicerecord->id, "userid" => $USER->id))) {
                    $choiceout['showresults'] = true;
                }
                break;
            case CHOICE_SHOWRESULTS_AFTER_CLOSE:
                if (empty($choicerecord->timeclose) || $choicerecord->timeclose < $now) {
                    $choiceout['showresults'] = true;
                }
                break;
            case CHOICE_SHOWRESULTS_ALWAYS:
                $choiceout['showresults'] = true;
                break;
        }
        $groupmode = groups_get_activity_groupmode($cm);

        if ($CFG->version >= 2015051100) { // Moodle 2.8
            $allresponses = choice_get_response_data($choicerecord, $cm, $groupmode, true);
        } else {
            $allresponses = choice_get_response_data($choicerecord, $cm, $groupmode);
        }
        $options = choice_prepare_options($choicerecord, $USER, $cm, $allresponses);

        foreach ($options['options'] as $option) {
            $optionout['id'] = $option->attributes->value;
            $optionout['option'] = $option->text;
            if ($choiceout['showresults']) {
                $optionout['count'] = $option->countanswers;
            }
            if (empty($choicerecord->limitanswers) || $option->countanswers < $option->maxanswers) {
                $optionout['allowanswer'] = true;
            } else {
                $optionout['allowanswer'] = false;
            }
            $choiceout['options'][] = $optionout;
        }
        $choiceout['language'] = current_language();

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $eventdata = array();
            $eventdata['objectid'] = $choiceout['id'];
            $eventdata['context'] = $context;
            $event = \mod_choice\event\course_module_viewed::create($eventdata);
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('course', $DB->get_record('course',array('id' => $cm->course) ));
            $event->trigger();
        }
        return $choiceout;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_cm_choice_parameters() {
        return new external_function_parameters(
            array(
                'coursemoduleid' => new external_value(PARAM_INT, 'course module id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_cm_choice_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'id'),
                'name' => new external_value(PARAM_TEXT, 'name of choice'),
                'description' => new external_value(PARAM_RAW, 'intro'),
                'allowanswer' => new external_value(PARAM_BOOL, 'true if the choice is open for answers from this user'),
                'showresults' => new external_value(PARAM_BOOL, 'true if the results should be shown'),
                'language' => new external_value(PARAM_ALPHA, 'prefered language - put at this level for backward compatibility', VALUE_OPTIONAL),
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'option id'),
                            'option' => new external_value(PARAM_RAW, 'the option'),
                            'count' => new external_value(PARAM_INT, 'count of responses (only if showresults is true)', VALUE_OPTIONAL),
                            'allowanswer' => new external_value(PARAM_BOOL, 'true if the option is open for answers. i.e. count is less than limit', VALUE_OPTIONAL),
                        )
                    )
                , 'List of options for the choice')
            )
        );
    }

    /**
     * @static submits a user response to a choice
     * @param int $optionid the id of option selected by the user
     * @param int $coursemoduleid id of the coursemodule relating to the  choice record
     *
     * return array  of options or false
     */
    public static function  user_choice_response($optionid, $coursemoduleid) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::user_choice_response_parameters(), array('optionid' => $optionid,
            'coursemoduleid' => $coursemoduleid));
        require_once($CFG->dirroot . '/mod/choice/lib.php');

        $cm = $DB->get_record("course_modules", array("id" => $coursemoduleid), '*', MUST_EXIST);

        $context = context_module::instance($coursemoduleid);

        if (!is_enrolled($context, NULL, 'mod/choice:choose', true)) {
            throw new moodle_exception('userisnotenrolled', 'webservice');
        }

        $choice = choice_get_choice($cm->instance);
        $course = $DB->get_record("course", array("id" => $cm->course), '*', MUST_EXIST);
        choice_user_submit_response($optionid, $choice, $USER->id, $course, $cm);
        return array('result'=>true);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function user_choice_response_parameters() {
        return new external_function_parameters(
            array(
                'optionid' => new external_value(PARAM_INT, 'option id'),
                'coursemoduleid' => new external_value(PARAM_INT, 'course module id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function user_choice_response_returns() {
        return new external_single_structure(array(
                'result' => new external_value(PARAM_BOOL, 'result'),));
    }

    /**
     * Returns all messages either received or sent by the current user
     * @param int $read Are read or unread messages to be returned default
     *                  is null 'unread' 1 = read. is disregarded if sent
     *                  param is set
     * @param int $sent return all messages sent by the current user
     * @return array containing messages
     */
    public static function get_user_messages($read=null,$sent=null) {

        global $USER, $DB;

        $params = self::validate_parameters(self::get_user_messages_parameters(),array('read' => $read,
            'sent' => $sent));
        $userid = $USER->id;


        //if the sent flag is not sent then we will be retrieving messages
        //received by the user
        if (empty($sent)) {

            $messagetable = (!empty($read)) ? "message_read" : "message" ;
            $messages = $DB->get_records($messagetable,array('useridto'=>$USER->id));
            $msg = array();
            if (!empty($messages)) {

                foreach ($messages as $message) {
                    $act = new stdClass();
                    $act->id = $message->id;
                    $act->useridfrom = $message->useridfrom;
                    $act->useridto = $message->useridto;
                    $act->message = $message->fullmessage;
                    $act->timecreated = $message->timecreated;
                    $act->timeread = (isset($message->timeread))?$message->timeread:'';
                    $msg[] = (array)$act;
                }
            }
        } else {

            $read_messages = $DB->get_records("message_read",array('useridfrom'=>$USER->id));
            $read_msg = array();
            if (!empty($read_messages)) {

                foreach ($read_messages as $read_message) {
                    $act = new stdClass();
                    $act->id = $read_message->id;
                    $act->useridfrom = $read_message->useridfrom;
                    $act->useridto = $read_message->useridto;
                    $act->message = $read_message->fullmessage;
                    $act->timecreated = $read_message->timecreated;
                    $act->timeread = $read_message->timeread;
                    $read_msg[] = (array)$act;
                }
            }

            $unread_messages = $DB->get_records("message",array('useridfrom'=>$USER->id));
            $unread_msg = array();
            if (!empty($unread_messages)) {

                foreach ($unread_messages as $unread_message) {
                    $act = new stdClass();
                    $act->id = $unread_message->id;
                    $act->useridfrom = $unread_message->useridfrom;
                    $act->useridto = $unread_message->useridto;
                    $act->message = $unread_message->fullmessage;
                    $act->timecreated = $unread_message->timecreated;
                    $act->timeread = '';
                    $unread_msg[] = (array)$act;
                }
            }
            $msg = array_merge($read_msg, $unread_msg);

        }

        return $msg;
    }

    /**
     * @return external_function_parameters
     */
    public static function get_user_messages_parameters(){
        return new external_function_parameters(
            array(
                'read' => new external_value(PARAM_BOOL, 'read'),
                'sent' => new external_value(PARAM_BOOL, 'sent'),
            )
        );

    }


    /**
     * @return external_multiple_structure
     */
    public static function get_user_messages_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'id'),
                    'useridfrom'=> new external_value(PARAM_INT, 'useridfrom'),
                    'useridto' => new external_value(PARAM_INT, 'useridto'),
                    'message'=> new external_value(PARAM_RAW, 'message'),
                    'timecreated'=> new external_value(PARAM_TEXT, 'timecreated'),
                    'timeread'=> new external_value(PARAM_TEXT, 'timeread'),

                ), 'List of messages'
            )
        );

    }

    /**
     * Returns a link to log in to the course or front page of Moodle
     * @param courseid if null front page
     * @return string link to login to native moodle.
     */
    public static function get_native_moodle_link($courseid = null) {
        global $CFG, $DB, $USER;

        // Validate the given parameter.
        $params = self::validate_parameters(self::get_native_moodle_link_parameters(), array('courseid' => $courseid));

        $courseparam = '';
        if (!empty($courseid)) {

            $context = context_course::instance($courseid);
            if (!is_enrolled($context, $USER, '', true)) {
                throw new moodle_exception('userisnotenrolled', 'webservice');
            }
            $courseparam = "&courseid={$courseid}";
        }

        $result = array();
        $token = optional_param('wstoken', '', PARAM_ALPHANUM);
        $result['link'] = "{$CFG->wwwroot}/local/ombiel_webservices/login.php?wstoken={$token}&userid={$USER->id}{$courseparam}";

        return $result;
    }

    /**
     * @return external_function_parameters
     */
    public static function get_native_moodle_link_parameters() {
        return new external_function_parameters(array(
                'courseid' => new external_value(PARAM_INT, 'courseid'),
            )
        );
    }
    /**
     * @return external_multiple_structure
     */
    public static function get_native_moodle_link_returns() {
        return new external_single_structure(
            array(
                'link' => new external_value(PARAM_TEXT, 'link to login to message settings'),
            )
        );
    }
    /**
     * Returns a link to log in to the message setting page of Moodle
     * @return string the link
     */
    public static function get_message_settings_link() {
        global $CFG, $DB, $USER;

        $result = array();
        $token = optional_param('wstoken', '', PARAM_ALPHANUM);
        $result['link'] = "{$CFG->wwwroot}/local/ombiel_webservices/login.php?wstoken={$token}&userid={$USER->id}&messages=true";

        return $result;
    }

    /**
     * @return external_function_parameters
     */
    public static function get_message_settings_link_parameters() {
        return new external_function_parameters(array(
            )
        );
    }
    /**
     * @return external_multiple_structure
     */
    public static function get_message_settings_link_returns() {
        return new external_single_structure(
            array(
                'link' => new external_value(PARAM_TEXT, 'link to login to message settings'),
            )
        );
    }
    /**
     * Returns a list of the message settings for the oMbiel Alerts
     * @return a list of grouped settings
     */
    public static function get_message_settings() {
        global $USER;
        // check access control
        $systemcontext   = context_system::instance();
        if (!has_capability('moodle/user:editownmessageprofile', $systemcontext)) {
            throw new moodle_exception('invalidaccess', 'webservice');
        }
        $providers = message_get_providers_for_user($USER->id);

        $providerlist = array();
        foreach ($providers as $provider) {
            $provout = $provider;
            if ($provider->component != 'moodle') {
                $provout->displaycomponent = get_string('pluginname', $provider->component);
            } else {
                $provout->displaycomponent =  get_string('coresystem');
            }
            $provout->label = get_string('messageprovider:'.$provider->name, $provider->component);
            $providerlist[] = $provout;
        }

        usort($providerlist, array('self','sort_providers' )) ;

        $defaultpreferences = get_message_output_default_preferences();

        $components = array();
        foreach ($providerlist as $provideritem) {

            $defaultpreference = 'ombiel_alerts_provider_'.$provideritem->component.'_'.$provideritem->name.'_permitted';

            if (empty($defaultpreferences->{$defaultpreference}) || $defaultpreferences->{$defaultpreference} == 'permitted') { // User is allowed to change the preferences
                $settingout = array();
                $preferencename = 'message_provider_'.$provideritem->component.'_'.$provideritem->name;
                $settingout['name'] = $provideritem->name;
                $settingout['component'] = $provideritem->component;
                $settingout['label'] = $provideritem->label;

                $defaultsetting = $preferencename.'_loggedin';
                $loggedinpref = get_user_preferences($defaultsetting, false, $USER->id);
                if ($loggedinpref === false) {
                    // User has not set this preference yet, using site default preferences set by admin
                    if (empty($defaultpreferences->{$defaultsetting})) {
                        $loggedinpref = '';
                    } else {
                        $loggedinpref = $defaultpreferences->{$defaultsetting};
                    }
                }
                $settingout['online'] = in_array('ombiel_alerts', explode(',', $loggedinpref));

                $defaultsetting = $preferencename.'_loggedoff';
                $loggedoffpref = get_user_preferences($defaultsetting, '', $USER->id);
                if ($loggedoffpref === false) {
                    // User has not set this preference yet, using site default preferences set by admin
                    if (empty($defaultpreferences->{$defaultsetting})) {
                        $loggedoffpref = '';
                    } else {
                        $loggedoffpref = $defaultpreferences->{$defaultsetting};
                    }
                }
                $settingout['offline'] = in_array('ombiel_alerts', explode(',', $loggedoffpref));

                if (!isset($components[$provideritem->displaycomponent])) {
                    $components[$provideritem->displaycomponent] = array();
                }
                $components[$provideritem->displaycomponent][] = $settingout;
            }
        }

        $settingsout = array();
        foreach ($components as $label => $component) {
            $componentout = array();
            $componentout['componentlabel'] = $label;
            $componentout['component'] = $component[0]['component'];
            $componentout['settings'] = $component;
            $settingsout[] = $componentout;
        }

        return $settingsout;
    }
    private static function sort_providers($a, $b) {

        $result = strcmp($a->displaycomponent, $b->displaycomponent);
        if ($result == 0) {
            $result = strcmp($a->label, $b->label);
        }

        return $result;
    }

    /**
     * @return external_function_parameters
     */
    public static function get_message_settings_parameters() {
        return new external_function_parameters(array(
            )
        );
    }
    /**
     * @return external_multiple_structure
     */
    public static function get_message_settings_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'component' => new external_value(PARAM_TEXT, 'component name'),
                    'componentlabel' => new external_value(PARAM_TEXT, 'component display name'),
                    'settings'=>
                        new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'name' => new external_value(PARAM_TEXT, 'name of setting'),
                                    'label' => new external_value(PARAM_TEXT, 'label for setting'),
                                    'online' => new external_value(PARAM_BOOL, 'true if messages are to be sent when online'),
                                    'offline' => new external_value(PARAM_BOOL, 'true if messages are to be sent when offline'),
                                    )
                            )
                        , 'List of settings')
                ), 'List of components'
            )
        );
    }
    /**
     * Updates the oMbiel Alerts setting $name to $value
     * @param string name
     * @param boolean online
     * @param boolean offline
     * @return boolean success
     */
    public static function update_message_setting($component,$name, $online, $offline) {
        global $USER;

        // Validate the given parameters.
        $params = self::validate_parameters(self::update_message_setting_parameters(),
                array('component' =>$component, 'name' =>$name, 'online' =>$online, 'offline' =>$offline));

        // check access control
        $systemcontext   = context_system::instance();
        if (!has_capability('moodle/user:editownmessageprofile', $systemcontext)) {
            throw new moodle_exception('invalidaccess', 'webservice');
        }

        $invalidprovider = true;
        $providers = get_message_providers();
        foreach ($providers as $provider) {
            if ($provider->component == $component && $provider->name == $name) {
                $invalidprovider = false;
                continue;
            }
        }
        if ($invalidprovider) {
            throw new moodle_exception('invalidprovider', 'webservice');
        }

        $preferences = array();
        foreach(array('_loggedin','_loggedoff') as $type) {
            $preferencename = 'message_provider_'.$component.'_'.$name;
            $existingpref = get_user_preferences($name.$type, false, $USER->id);

            if ($existingpref === false) {
                $defaultpreferences = get_message_output_default_preferences();
                // User has not set this preference yet, using site default preferences set by admin
                $defaultsetting = $preferencename.$type;
                if (empty($defaultpreferences->{$defaultsetting})) {
                    $existingpref = '';
                } else {
                    $existingpref = $defaultpreferences->{$defaultsetting};
                }
            }

            $prefarray = explode(',', $existingpref);
            if (($type == '_loggedin' && $online) || ($type == '_loggedoff' && $offline)) {
                if (!in_array('ombiel_alerts', $prefarray)) {
                    $prefarray[] = 'ombiel_alerts';
                }
            } else {
                $key = array_search('ombiel_alerts', $prefarray);
                if ($key) {
                    unset($prefarray[$key]);
                }
            }
            sort($prefarray);
            $preferences[$preferencename.$type] = implode(',', $prefarray);
        }

        $result = array();
        $result['result'] = set_user_preferences($preferences, $USER->id);
        return $result;
    }

    /**
     * @return external_function_parameters
     */
    public static function update_message_setting_parameters() {
        return new external_function_parameters(
            array(
                'component' => new external_value(PARAM_TEXT, 'component the setting belongs to'),
                'name' => new external_value(PARAM_TEXT, 'name of the setting to change'),
                'online' => new external_value(PARAM_BOOL, 'new value'),
                'offline' => new external_value(PARAM_BOOL, 'new value'),
            )
        );
    }
    /**
     * @return external_single_structure
     */
    public static function update_message_setting_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_BOOL, 'true if successful'),
            )
        );
    }
    /**
     * Get a block's content from its name and course id
     *
     * @param int $userid
     * @return array title (text) and content (html)
     */
    public static function get_block_content($blockname, $courseid = 0) {
        global $PAGE,$DB;

        $params = self::validate_parameters(self::get_block_content_parameters(), array('blockname' => $blockname, 'courseid' => $courseid));

        if (empty($courseid)) {
            $course = $DB->get_record('course', array('id'=>SITEID), '*', MUST_EXIST);
            $PAGE->set_pagetype('site-index');
            $PAGE->set_pagelayout('frontpage');
        } else {
            $course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

            $PAGE->set_pagelayout('course');
            $PAGE->set_pagetype('course-view-topics');
        }

        require_login($course);
        $PAGE->theme->init_page($PAGE);
        $PAGE->set_state(moodle_page::STATE_PRINTING_HEADER);

        $result = array();
        $result['title'] = '';
        $result['content'] = '';

        $blockmanager = $PAGE->blocks;
        foreach ($blockmanager->get_regions() as $region) {
            foreach ($blockmanager->get_blocks_for_region($region) as $instance) {
                if (empty($instance->instance->blockname)) {
                    continue;
                }
                if ($instance->instance->blockname == $blockname) {
                    $blockobject = block_instance($blockname, $instance->instance, $PAGE);
                    $content = $blockobject->get_content_for_output(null);
                    $result['title'] = $content->title;
                    $result['content'] = $content->content;
                    break 2;
                }
            }
        }

        return $result;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_block_content_parameters() {
        return new external_function_parameters(
            array(
                'blockname' => new external_value(PARAM_TEXT, 'The name of the block'),
                'courseid' => new external_value(PARAM_INT, 'Course id defaults to site id'),
            )
        );
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_block_content_returns() {
        return new external_single_structure(
            array(
                'title' => new external_value(PARAM_TEXT, 'block title'),
                'content' => new external_value(PARAM_RAW, 'block content')
                )
        );
    }

}
