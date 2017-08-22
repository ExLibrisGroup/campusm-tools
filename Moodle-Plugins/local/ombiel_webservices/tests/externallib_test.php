<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/ombiel_webservices/externallib.php');

class local_ombiel_webservices_testcase extends advanced_testcase {

    public function test_get_user_courses() {
        global $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        // test with login user

        // empty return
        $courses = local_ombiel_webservices::get_user_courses();
        $courses = external_api::clean_returnvalue(local_ombiel_webservices::get_user_courses_returns(), $courses);

        $this->assertSame(array(), $courses);

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course2->id);

        // Call the external function without a userid
        $courses = local_ombiel_webservices::get_user_courses();
        $courses = external_api::clean_returnvalue(local_ombiel_webservices::get_user_courses_returns(), $courses);

        $this->assertEquals(2, count($courses));

        // Call the external function with loggedin userid
        $courses = local_ombiel_webservices::get_user_courses($user1->id);
        $courses = external_api::clean_returnvalue(local_ombiel_webservices::get_user_courses_returns(), $courses);

        // Check we retrieve the good total number of courses.
        $this->assertEquals(2, count($courses));

        // Call the external function with other userid
        $usercontext = context_user::instance($user2->id, MUST_EXIST);

        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, $usercontext);
        role_assign($roleid, $USER->id, $usercontext);
        accesslib_clear_all_caches_for_unit_testing();
        $courses = local_ombiel_webservices::get_user_courses($user2->id);
        $courses = external_api::clean_returnvalue(local_ombiel_webservices::get_user_courses_returns(), $courses);
        // Check we retrieve the good total number of courses.
        $this->assertEquals(1, count($courses));
        $course = current($courses);
        $this->assertEquals($course2->id, $course['id']);


        // Call the external function with other userid - no access
        $this->setExpectedException('moodle_exception');
        $courses = local_ombiel_webservices::get_user_courses($user3->id);

     }
    public function test_get_course_sections() {
        global $CFG, $DB, $USER;

        if ($CFG->version >= 2017051500) {
            //From Moodle 3.3 onwards this is deprecated
            // use function get_course_contents in course/externallib.php
            // service core_course_get_contents
            return true;
        }


        $this->resetAfterTest(true);

        $CFG->debug = 0; // Don't run with debug it creates spurious output from the grid format plugin

        $course = self::getDataGenerator()->create_course(array('format'=>'grid'));
        // grid format first section visiblity
        $newstatus = new stdClass();
        $newstatus->courseid = $course->id;
        $newstatus->showsummary = true;

        $newstatus->id = $DB->insert_record('format_grid_summary', $newstatus);


        $user1 = self::getDataGenerator()->create_user();
        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        $coursecontext = context_course::instance($course->id);
        /**
         * Create visible section
         */

        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');

        $options = array(
            'course'=>$course->id,
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'section'=>2
        );
        $visiblelabelinstance = $labelgenerator->create_instance($options);

        if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
            course_add_cm_to_section($course, $visiblelabelinstance->cmid, 2);
        }
        $options = array(
            'course'=>$course->id,
            'section'=>2
        );
        $hiddenlabelinstance = $labelgenerator->create_instance($options);

        if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
            course_add_cm_to_section($course, $hiddenlabelinstance->cmid, 2);
        }
        $cm1 = $DB->get_record('course_modules', array('id'=>$hiddenlabelinstance->cmid));
        $cm1->visible = 0;
        $DB->update_record('course_modules', $cm1);

        $resourcegenerator = $this->getDataGenerator()->get_plugin_generator('mod_resource');

        $options = array(
                'course'=>$course->id,
                'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
                'section'=>2,
        );

        $resourceinstance = $resourcegenerator->create_instance($options);

        if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
                course_add_cm_to_section($course, $resourceinstance->cmid, 2);
        }
        $cm2 = $DB->get_record('course_modules', array('id'=>$resourceinstance->cmid));
        $cm2->showdescription = 1;
        $DB->update_record('course_modules', $cm2);

        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course->id,
            'name'=>'Forum One',
            'section'=>2,
        );

        $foruminstance = $forumgenerator->create_instance($options);

        if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
            course_add_cm_to_section($course, $foruminstance->cmid, 2);
        }
        $cm3 = $DB->get_record('course_modules', array('id'=>$foruminstance->cmid));
        $cm3->indent = 2;
        $DB->update_record('course_modules', $cm3);

        $visiblesection = $DB->get_record('course_sections', array('course'=>$course->id, 'section'=>2));

        $visiblesection->summary = '<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />';
        $visiblesection->summaryformat = 1;
        $visiblesection->name = 'Test topic';

        $DB->update_record('course_sections', $visiblesection);

        /**
         * Create hidden section
         */
        $hiddenforuminstance = $forumgenerator->create_instance(array('course'=>$course->id, 'section'=>3));

        if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
            course_add_cm_to_section($course, $hiddenforuminstance->cmid, 3);
        }
        $hiddensection = $DB->get_record('course_sections', array('course'=>$course->id, 'section'=>3));

        $hiddensection->visible = 0;

        $DB->update_record('course_sections', $hiddensection);

        $_GET['wstoken'] = md5('test');

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }

        $sections = local_ombiel_webservices::get_course_sections($course->id);
        $sections = external_api::clean_returnvalue(local_ombiel_webservices::get_course_sections_returns(), $sections);

        // should be an automatically generated section as well as the two created above
        $this->assertEquals(2, count($sections['sections']));
        $lastsection = end($sections['sections']);
        $this->assertEquals($visiblesection->id, $lastsection['id']);
        $this->assertEquals('<img src="'.$CFG->wwwroot.'/webservice/pluginfile.php/'.
                $coursecontext->id.'/course/section/'.$visiblesection->id.'/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" />',
                $lastsection['summary']);
        $this->assertEquals($visiblesection->name, $lastsection['name']);
        $this->assertEquals($CFG->wwwroot.'/local/ombiel_webservices/login.php?wstoken='.md5('test').'&userid='.$user1->id.'&courseid='.$course->id, $sections['courselink']);
        $this->assertEquals('grid', $sections['courseformat']);
        $this->assertEquals(true, $sections['firstsectionvisible']);
        $this->assertArrayNotHasKey('echo360link', $sections);
        $this->assertEquals('en', $sections['language']);

        $this->assertEquals($visiblesection ->id, $lastsection['id']);
        $this->assertEquals('<img src="'.$CFG->wwwroot.'/webservice/pluginfile.php/'.
                $coursecontext->id.'/course/section/'.$visiblesection->id.'/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" />',
                $lastsection['summary']);
        $this->assertEquals($visiblesection->name, $lastsection['name']);
        $this->assertEquals($CFG->wwwroot.'/local/ombiel_webservices/login.php?wstoken='.md5('test').'&userid='.$user1->id.'&cmid=',$lastsection['baselink']);

        $this->assertEquals(3, count($lastsection['coursemodules']));

        $labelout = current($lastsection['coursemodules']);

        $cmcontext = context_module::instance($visiblelabelinstance->cmid);

        $this->assertEquals($visiblelabelinstance->cmid, $labelout['id']);
        $this->assertEquals('<div class="no-overflow"><img src="'.$CFG->wwwroot.'/webservice/pluginfile.php/'.
            $cmcontext->id.'/mod_label/intro/_dummy.jpg" height="20" width="20" /></div>',
            $labelout['description']);

        $cmcontext = context_module::instance($resourceinstance->cmid);
        $resourceout = next($lastsection['coursemodules']);

        $this->assertEquals($CFG->wwwroot.'/webservice/pluginfile.php/'.
                $cmcontext->id.'/mod_resource/content/0/resource1.txt?forcedownload=1',
                $resourceout['contents'][0]['fileurl']);
        $this->assertEquals('<div class="no-overflow"><img src="'.$CFG->wwwroot.'/webservice/pluginfile.php/'.
            $cmcontext->id.'/mod_resource/intro/_dummy.jpg" height="20" width="20" /></div>',
            $resourceout['description']);

        $forumout = next($lastsection['coursemodules']);
        $this->assertEquals($foruminstance->cmid, $forumout['id']);
        $this->assertEquals('Forum One', $forumout['name']);
        $this->assertEquals('forum', $forumout['modname']);
        $this->assertEquals(2, $forumout['indent']);


        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                  if (is_a($event,'\core\event\course_viewed')) {
                    $eventstotest[] = $event;
                }
            }
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('core',$eventstotest[0]->component);
            $this->assertEquals('viewed',$eventstotest[0]->action);
            $this->assertEquals($coursecontext->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course->id,$eventstotest[0]->courseid);
        }

        /**
         * echo360
         */

        $course2 = self::getDataGenerator()->create_course(array('format'=>'topics'));
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id);
        $context2 = context_course::instance($course2->id);

        $blockinstance = new stdClass();
        $blockinstance->blockname = 'echo360_echocenter';
        $blockinstance->parentcontextid = $context2->id;
        $blockinstance->showinsubcontexts = 0;
        $blockinstance->pagetypepattern = 'course-view-*';
        $blockinstance->defaultregion = 'side-post';
        $blockinstance->defaultweight = 1;

        $DB->insert_record('block_instances', $blockinstance);

        $sections = local_ombiel_webservices::get_course_sections($course2->id);
        $sections = external_api::clean_returnvalue(local_ombiel_webservices::get_course_sections_returns(), $sections);

        $this->assertEquals($CFG->wwwroot.'/local/ombiel_webservices/login.php?wstoken='.md5('test').'&userid='.$user1->id.'&echo360id='.$course2->id,  $sections['echo360link']);

        $this->assertArrayNotHasKey('firstsectionvisible', $sections);

        // not enrolled on course
        $user2 = self::getDataGenerator()->create_user();
        $this->setUser($user2);
        $this->setExpectedException('moodle_exception');
        $sections = local_ombiel_webservices::get_course_sections($course->id);

     }
     public function test_get_section_content() {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $user1 = self::getDataGenerator()->create_user();
        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        $coursecontext = context_course::instance($course->id);

        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');

        $options = array(
            'course'=>$course->id,
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'section'=>2
        );
        $visiblelabelinstance = $labelgenerator->create_instance($options);

        if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
            course_add_cm_to_section($course, $visiblelabelinstance->cmid, 2);
        }
        $options = array(
            'course'=>$course->id,
            'section'=>2
        );
        $hiddenlabelinstance = $labelgenerator->create_instance($options);

        if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
            course_add_cm_to_section($course, $hiddenlabelinstance->cmid, 2);
        }
        $cm1 = $DB->get_record('course_modules', array('id'=>$hiddenlabelinstance->cmid));
        $cm1->visible = 0;
        $DB->update_record('course_modules', $cm1);

        if ($CFG->version >= 2013051400) { // resource generator added in 2.5
            $resourcegenerator = $this->getDataGenerator()->get_plugin_generator('mod_resource');

            $options = array(
                'course'=>$course->id,
                'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
                'section'=>2,
            );

            $resourceinstance = $resourcegenerator->create_instance($options);

            if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
                course_add_cm_to_section($course, $resourceinstance->cmid, 2);
            }
            $cm2 = $DB->get_record('course_modules', array('id'=>$resourceinstance->cmid));
            $cm2->showdescription = 1;
            $DB->update_record('course_modules', $cm2);
        }
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course->id,
            'name'=>'Forum 1',
            'section'=>2,
        );

        $foruminstance = $forumgenerator->create_instance($options);

        if ($CFG->version < 2013111800) { // Generator adds cm to section in 2.6 onwards
            course_add_cm_to_section($course, $foruminstance->cmid, 2);
        }
        $cm3 = $DB->get_record('course_modules', array('id'=>$foruminstance->cmid));
        $cm3->indent = 2;
        $DB->update_record('course_modules', $cm3);

        $sectionin = $DB->get_record('course_sections', array('course'=>$course->id,'section'=>2));

        $sectionin->summary = '<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />';
        $sectionin->summaryformat = 1;
        $sectionin->name = 'Test topic';

        $DB->update_record('course_sections', $sectionin);

        $_GET['wstoken'] = md5('test');

        $sectionout = local_ombiel_webservices::get_section_content($sectionin->id);
        $sectionout = external_api::clean_returnvalue(local_ombiel_webservices::get_section_content_returns(), $sectionout);

        $this->assertEquals($sectionin->id, $sectionout['id']);
        $this->assertEquals('<img src="'.$CFG->wwwroot.'/webservice/pluginfile.php/'.
                $coursecontext->id.'/course/section/'.$sectionin->id.'/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" />',
                $sectionout['summary']);
        $this->assertEquals($sectionin->name, $sectionout['name']);
        $this->assertEquals($CFG->wwwroot.'/local/ombiel_webservices/login.php?wstoken='.md5('test').'&userid='.$user1->id.'&cmid=',$sectionout['baselink']);

        if ($CFG->version >= 2013051400) { // resource generator added in 2.5
            $this->assertEquals(3, count($sectionout['contents']));
        } else {
            $this->assertEquals(2, count($sectionout['contents']));
        }
        $labelout = current($sectionout['contents']);

        $cmcontext = context_module::instance($visiblelabelinstance->cmid);

        $this->assertEquals($visiblelabelinstance->cmid, $labelout['id']);
        $this->assertEquals('<div class="text_to_html"><img src="'.$CFG->wwwroot.'/webservice/pluginfile.php/'.
                $cmcontext->id.'/mod_label/intro/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" /></div>',
                $labelout['description']);

        if ($CFG->version >= 2013051400) { // resource generator added in 2.5
            $cmcontext = context_module::instance($resourceinstance->cmid);
            $resourceout = next($sectionout['contents']);

            $this->assertEquals($CFG->wwwroot.'/webservice/pluginfile.php/'.
                    $cmcontext->id.'/mod_resource/content/0/resource1.txt?forcedownload=1',
                    $resourceout['contents'][0]['fileurl']);
            $this->assertEquals('<div class="text_to_html"><img src="'.$CFG->wwwroot.'/webservice/pluginfile.php/'.
                    $cmcontext->id.'/mod_resource/intro/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" /></div>',
                    $resourceout['description']);
        }
        $forumout = next($sectionout['contents']);
        $this->assertEquals($foruminstance->cmid, $forumout['id']);
        $this->assertEquals('Forum 1', $forumout['name']);
        $this->assertEquals('forum', $forumout['modname']);
        $this->assertEquals(2, $forumout['indent']);


        // not enrolled on course
        $user2 = self::getDataGenerator()->create_user();
        $this->setUser($user2);
        $this->setExpectedException('moodle_exception');
        $sectionout = local_ombiel_webservices::get_section_content($course->id);


     }

     public function test_get_cm_assignment() {
         global $CFG, $DB, $USER;

        require_once ($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest(true);

        $lastweek = strtotime('last week');
        $yesterday = strtotime('yesterday');
        $tomorrow = strtotime('tomorrow');
        $nextweek = strtotime('next week');

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();
        $this->setUser($student);
        $this->getDataGenerator()->enrol_user($student->id, $course1->id);

        $coursecontext = context_course::instance($course1->id);
        /**
         * Set up label for negative test
         */
        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');
        $options = array(
            'course'=>$course1->id,
        );
        $labelinstance = $labelgenerator->create_instance($options);
         /**
         * Set up assignment with no submission
         */
        $assignmentgenerator = $this->getDataGenerator()->get_plugin_generator('mod_assign');

        $sitecontext = context_system::instance();
        $roleid = create_role('Calendar', 'Calendar', 'dummy role description');
        assign_capability('moodle/calendar:manageentries', CAP_ALLOW, $roleid, $sitecontext);
        role_assign($roleid, $USER->id, $sitecontext);

        $options = array(
            'name'=>'Test assignment',
            'course'=>$course1->id,
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'duedate'=>1642550401,
            'markingworkflow'=>true,
        );
        $assignmentinstance = $assignmentgenerator->create_instance($options);
        $cmcontext = context_module::instance($assignmentinstance->cmid);

        $submission = new stdClass;
        $submission->assignment = $assignmentinstance->id;
        $submission->userid = $student->id;
        $DB->insert_record('assign_submission', $submission);

        $assignmentresult = local_ombiel_webservices::get_cm_assignment($assignmentinstance->cmid);
        $assignmentresult = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_assignment_returns(), $assignmentresult);

        $this->assertEquals('Test assignment', $assignmentresult['name']);
        $this->assertEquals('', $assignmentresult['sectionname']);
        $this->assertEquals('<div class="text_to_html"><img src="'.$CFG->wwwroot.
                '/webservice/pluginfile.php/'.$cmcontext->id
                .'/mod_assign/intro/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" /></div>',
                $assignmentresult['description']);
        $this->assertEquals(1642550401, $assignmentresult['deadline']);

        /**
         * assignment overdue but extension granted
         */
        $options = array(
            'course'=>$course1->id,
            'allowsubmissionsfromdate'=>$lastweek,
            'duedate'=>$yesterday,
            'cutoffdate'=>$yesterday
        );
        $assignmentinstance5 = $assignmentgenerator->create_instance($options);

        /**
         * cm is a label - user is enrolled
         */
        $this->setExpectedException('moodle_exception');
        $assignment5 = local_ombiel_webservices::get_cm_assignment($labelinstance->cmid);


     }
     public function test_get_cm_assignment_not_enrolled() {
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $coursecontext = context_course::instance($course->id);
         /**
         * Set up assignment
         */
        $assignmentgenerator = $this->getDataGenerator()->get_plugin_generator('mod_assign');

        $options = array(
            'course'=>$course->id,
        );
        $assignmentinstance = $assignmentgenerator->create_instance($options);

        $this->setExpectedException('moodle_exception');
        $assignment = local_ombiel_webservices::get_cm_assignment($assignmentinstance->cmid);


     }
     public function test_get_user_grades() {
        global $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        $this->getDataGenerator()->enrol_user($user1->id, $course2->id);

        $this->setUser($user1);

        $assignmentgenerator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
         /**
         * First course
         */

        $options = array(
            'course'=>$course1->id,
        );
        $assignmentinstance = $assignmentgenerator->create_instance($options);

        $course_item = $DB->get_record('grade_items', array('itemtype'=>'course', 'courseid'=>$course1->id));
        $course_item->grademax = 90;
        $course_item->grademin = 0;
        $DB->update_record('grade_items', $course_item);

        $grade_grade = new grade_grade();

        $grade_grade->itemid = $course_item->id;
        $grade_grade->userid = $user1->id;
        $grade_grade->finalgrade = 45;
        $grade_grade->feedback = 'Feedback 1';

        $grade_grade->insert();

         /**
         * Second course
         */

        $options = array(
            'course'=>$course2->id,
        );
        $assignmentinstance = $assignmentgenerator->create_instance($options);

        $course_item = $DB->get_record('grade_items', array('itemtype'=>'course', 'courseid'=>$course2->id));

        $grade_grade = new grade_grade();

        $grade_grade->itemid = $course_item->id;
        $grade_grade->userid = $user1->id;
        $grade_grade->finalgrade = 55.01;
        $grade_grade->feedback = 'Feedback 2';

        $grade_grade->insert();

        /*
         * Test with logged in user
         */
        $grades = local_ombiel_webservices::get_user_grades();
        $grades = external_api::clean_returnvalue(local_ombiel_webservices::get_user_grades_returns(), $grades);

        $this->assertEquals(2, count($grades));
        $this->assertEquals("Test course 2", $grades[0]['fullname']);
        $this->assertEquals("55.01", $grades[0]['grade']);
        $this->assertEquals("0&ndash;100", $grades[0]['range']);
        $this->assertEquals("55.01 %", $grades[0]['percentage']);
        $this->assertEquals('<div class="text_to_html">Feedback 2</div>', $grades[0]['feedback']);

        $this->assertEquals("Test course 1", $grades[1]['fullname']);
        $this->assertEquals("45.00", $grades[1]['grade']);
        $this->assertEquals("0&ndash;90", $grades[1]['range']);
        $this->assertEquals("50.00 %", $grades[1]['percentage']);
        $this->assertEquals('<div class="text_to_html">Feedback 1</div>', $grades[1]['feedback']);

        /*
         * Test with other user
         */

        $this->setUser($user2);
        $coursecontext = context_course::instance($course2->id);
        $roleid = create_role('Dummy', 'Dummy', 'dummy role description');
        assign_capability('moodle/grade:viewall', CAP_ALLOW, $roleid, $coursecontext);
        role_assign($roleid, $USER->id, $coursecontext);

        $grades = local_ombiel_webservices::get_user_grades($user1->id);
        $grades = external_api::clean_returnvalue(local_ombiel_webservices::get_user_grades_returns(), $grades);
        $this->assertEquals(1, count($grades));

     }

     public function test_get_course_grades() {
        global $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        $this->setUser($user1);

        $assignmentgenerator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
         $options = array(
            'name'=>'Assignment 1',
            'course'=>$course1->id,
        );
        $assignmentinstance = $assignmentgenerator->create_instance($options);

        /**
         * set aggregation type
         */
        $grade_category = $DB->get_record('grade_categories', array('courseid'=>$course1->id));
        $grade_category->aggregation = GRADE_AGGREGATE_SUM;
        $DB->update_record('grade_categories', $grade_category);


        $grade_item = (array) $DB->get_record('grade_items', array('itemmodule'=>'assign', 'iteminstance'=>$assignmentinstance->id));
        $grade_item['grademax'] = 90;
        $grade_item['grademin'] = 0;
        $grade_grade = new grade_grade();

        $grade_grade->userid = $user1->id;
        $grade_grade->rawgrade = 45.00;
        $grade_grade->finalgrade = 45.00;
        $grade_grade->feedback = 'Feedback 1';
        grade_update('mod/assignment', $course1->id, 'mod', 'assign', $assignmentinstance->id, 0, (array) $grade_grade, (array) $grade_item);

        $options = array(
            'name'=>'Assignment 2',
            'course'=>$course1->id,
        );
        $assignmentinstance2 = $assignmentgenerator->create_instance($options);

        $grade_grade = new grade_grade();

        $grade_grade->userid = $user1->id;
        $grade_grade->rawgrade = 50.00;
        $grade_grade->finalgrade = 50.00;
        $grade_grade->feedback = 'Feedback 2';
        grade_update('mod/assignment', $course1->id, 'mod', 'assign', $assignmentinstance2->id, 0, (array) $grade_grade);

        grade_regrade_final_grades($course1->id);

        /*
         * Test with logged in user
         */
        $grades = local_ombiel_webservices::get_course_grades($course1->id);
        $grades = external_api::clean_returnvalue(local_ombiel_webservices::get_course_grades_returns(), $grades);

        $this->assertEquals(3, count($grades));
        $this->assertEquals("Assignment 1", $grades[0]['gradeitem']);
        $this->assertEquals("45.00", $grades[0]['grade']);
        $this->assertEquals("0&ndash;90", $grades[0]['range']);
        $this->assertEquals("50.00 %", $grades[0]['percentage']);
        $this->assertEquals('<div class="text_to_html">Feedback 1</div>', $grades[0]['feedback']);

        $this->assertEquals("Assignment 2", $grades[1]['gradeitem']);
        $this->assertEquals("50.00", $grades[1]['grade']);
        $this->assertEquals("0&ndash;100", $grades[1]['range']);
        $this->assertEquals("50.00 %", $grades[1]['percentage']);
        $this->assertEquals('<div class="text_to_html">Feedback 2</div>', $grades[1]['feedback']);

        $this->assertEquals("Course total", $grades[2]['gradeitem']);
        $this->assertEquals("95.00", $grades[2]['grade']);
        $this->assertEquals("0&ndash;190", $grades[2]['range']);
        $this->assertEquals("50.00 %", $grades[2]['percentage']);

        /*
         * Test with other user access allowed
         */

        $this->setUser($user2);
        $coursecontext = context_course::instance($course1->id);
        $roleid = create_role('Dummy', 'Dummy', 'dummy role description');
        assign_capability('moodle/grade:viewall', CAP_ALLOW, $roleid, $coursecontext);
        role_assign($roleid, $USER->id, $coursecontext);

        $grades = local_ombiel_webservices::get_course_grades($course1->id, $user1->id);
        $grades = external_api::clean_returnvalue(local_ombiel_webservices::get_course_grades_returns(), $grades);

        $this->assertEquals(1, count($grades));

        $this->assertEquals("Course total", $grades[0]['gradeitem']);
        $this->assertEquals("95.00", $grades[0]['grade']);
        $this->assertEquals("0&ndash;190", $grades[0]['range']);
        $this->assertEquals("50.00 %", $grades[0]['percentage']);

        /*
         * Test with other user no access
         */

        $this->setUser($user3);

        $this->setExpectedException('moodle_exception');
        $grades = local_ombiel_webservices::get_course_grades($course1->id, $user1->id);

     }
     public function test_get_course_grades_noaccess() {
        global $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        $this->setUser($user1);

        $assignmentgenerator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
         $options = array(
            'name'=>'Assignment 1',
            'course'=>$course1->id,
        );
        $assignmentinstance = $assignmentgenerator->create_instance($options);

        /**
         * set aggregation type
         */
        $grade_category = $DB->get_record('grade_categories', array('courseid'=>$course1->id));
        $grade_category->aggregation = GRADE_AGGREGATE_SUM;
        $DB->update_record('grade_categories', $grade_category);


        $grade_item = (array) $DB->get_record('grade_items', array('itemmodule'=>'assign', 'iteminstance'=>$assignmentinstance->id));
        $grade_item['grademax'] = 90;
        $grade_item['grademin'] = 0;
        $grade_grade = new grade_grade();

        $grade_grade->userid = $user1->id;
        $grade_grade->rawgrade = 45.00;
        $grade_grade->finalgrade = 45.00;
        $grade_grade->feedback = 'Feedback 1';
        grade_update('mod/assignment', $course1->id, 'mod', 'assign', $assignmentinstance->id, 0, (array) $grade_grade, (array) $grade_item);


        $coursecontext = context_course::instance($course1->id);
        $roleid = create_role('Dummy', 'Dummy', 'dummy role description');
        assign_capability('moodle/grade:view', CAP_PROHIBIT, $roleid, $coursecontext);
        role_assign($roleid, $user1->id, $coursecontext);

        $this->setExpectedException('moodle_exception');
        $grades = local_ombiel_webservices::get_course_grades($course1->id, $user1->id);

     }
     public function test_get_course_grades_not_enrolled() {
        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $this->setUser($user1);

        $this->setExpectedException('moodle_exception');
        $grades = local_ombiel_webservices::get_course_grades($course1->id, $user1->id);

     }

     public function test_get_cm_forum() {
         global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        /**
         * Set up label for negative test
         */
        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');
        $options = array(
            'course'=>$course1->id,
        );
        $labelinstance = $labelgenerator->create_instance($options);
         /**
         * Set up forum
         */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course1->id,
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'type'=>'single',
            'showdescription'=>true
        );
        $forum1instance = $forumgenerator->create_instance($options);

        $cm1 = $DB->get_record('course_modules', array('id'=>$forum1instance->cmid));
        $DB->update_record('course_modules', $cm1);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }
        /**
         * cm is a forum - user is enrolled - can't post
         */
        $forum = local_ombiel_webservices::get_cm_forum($forum1instance->cmid);
        $forum = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_forum_returns(), $forum);

        $this->assertEquals('Forum 1', $forum['name']);
        $this->assertEquals($forum1instance->id, $forum['id']);
        $cmcontext = context_module::instance($forum1instance->cmid);
        $this->assertEquals('<div class="text_to_html"><img src="'.$CFG->wwwroot.
                '/webservice/pluginfile.php/'.$cmcontext->id
                .'/mod_forum/intro/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" /></div>',
                $forum['description']);
        $this->assertEquals(false, $forum['canpost']);
        $this->assertEquals('en', $forum['language']);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_forum\event\course_module_viewed')) {
                    $eventstotest[] = $event;
                }
            }
            $cmcontext = context_module::instance($forum1instance->cmid);
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_forum',$eventstotest[0]->component);
            $this->assertEquals('viewed',$eventstotest[0]->action);
            $this->assertEquals($cmcontext->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
        }
        /**
         * cm is a forum - user is enrolled - can post
         */
        $options = array(
            'course'=>$course1->id,
            'intro'=>'dummy'
        );
        $forum2instance = $forumgenerator->create_instance($options);

        $forum = local_ombiel_webservices::get_cm_forum($forum2instance->cmid);
        $forum = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_forum_returns(), $forum);

        $this->assertEquals('Forum 2', $forum['name']);

        $this->assertEquals(true, $forum['canpost']);
        /**
         * cm is a label - user is enrolled
         */
        $this->setExpectedException('moodle_exception');
        $forum = local_ombiel_webservices::get_cm_forum($labelinstance->cmid);


     }
     public function test_get_cm_forum_not_enrolled() {
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $coursecontext = context_course::instance($course->id);
         /**
         * Set up forum
         */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course->id,
        );
        $foruminstance = $forumgenerator->create_instance($options);

        $this->setExpectedException('moodle_exception');
        $forum = local_ombiel_webservices::get_cm_forum($foruminstance->cmid);


     }
     public function test_get_forum_discussions() {
         global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user(array('firstname'=>'First', 'lastname'=>'Last'));

        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        $coursecontext = context_course::instance($course1->id);
        /**
         * Set up label for negative test
         */
        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');
        $options = array(
            'course'=>$course1->id,
        );
        $labelinstance = $labelgenerator->create_instance($options);
         /**
         * Set up forum
         */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        /**
         * Single type forum user can reply
         */
        $options = array(
            'course'=>$course1->id,
            'type'=>'general'
        );
        $forum1instance = $forumgenerator->create_instance($options);

        /**
         * cm is a forum - user is enrolled no discussions
         */
        $discussions = local_ombiel_webservices::get_forum_discussions($forum1instance->cmid);
        $discussions = external_api::clean_returnvalue(local_ombiel_webservices::get_forum_discussions_returns(), $discussions);

        $this->assertSame(array(), $discussions);

        /**
         * Discussion with no replies
         */
        $discussion1 = new stdClass();
        $discussion1->forum = $forum1instance->id;
        $discussion1->name = 'subject';
        $discussion1->message = '<div>message</div>';
        $discussion1->messagetrust = false;
        $discussion1->messageformat = 1;
        $discussion1->mailnow = false;
        $discussion1->course = $course1->id;

        $discussion1id = forum_add_discussion($discussion1);
        $discussion1postid = $DB->get_field('forum_posts', 'id', array('discussion'=>$discussion1id));
        /**
         * Discussion with two replies
         */
        $discussion2 = new stdClass();
        $discussion2->forum = $forum1instance->id;
        $discussion2->name = 'subject2';
        $discussion2->message = '<div>message2</div>';
        $discussion2->messagetrust = false;
        $discussion2->messageformat = 1;
        $discussion2->mailnow = false;
        $discussion2->course = $course1->id;

        $discussion2id = forum_add_discussion($discussion2);
        $discussion2postid = $DB->get_field('forum_posts', 'id', array('discussion'=>$discussion2id));

        $post1 = new stdClass();
        $post1->discussion = $discussion2id;
        $post1->subject = 'Reply 1';
        $post1->message = 'Reply message 1';
        $post1->messageformat = 1;
        $post1->parent = $discussion2id;
        $post1->itemid = 0;
        $post1->course = $course1->id;


        if ($CFG->version >= 2014111000) { // Moodle 2.8
            $post1id = forum_add_new_post($post1, null);
        } else {
            $post1id = forum_add_new_post($post1, null, $message);
        }


        $post2 = new stdClass();
        $post2->discussion = $discussion2id;
        $post2->subject = 'Reply 2';
        $post2->message = 'Reply message 2';
        $post2->messageformat = 2;
        $post2->parent = $discussion2id;
        $post2->itemid = 0;
        $post2->course = $course1->id;

        if ($CFG->version >= 2014111000) { // Moodle 2.8
            $post2id = forum_add_new_post($post2, null);
        } else {
            $post2id = forum_add_new_post($post2, null, $message);
        }
        $lastreply = $DB->get_field('forum_posts', 'modified', array('id'=>$post2id));

        /**
         * cm is a forum - user is enrolled 2 discussions
         */
        $discussions = local_ombiel_webservices::get_forum_discussions($forum1instance->cmid);
        $discussions = external_api::clean_returnvalue(local_ombiel_webservices::get_forum_discussions_returns(), $discussions);

        $assertDiscussion1 = array('id'=>$discussion1postid, 'name'=>"subject", "discussion"=>$discussion1id, 'canreply'=>true, "author"=>"First Last", "content"=>"<div>message</div>", "replies"=>0, "lastreply"=>0);
        $assertDiscussion2 = array("id"=>$discussion2postid, "name"=>"subject2", "discussion"=>$discussion2id, 'canreply'=>true, "author"=>"First Last", "content"=>"<div>message2</div>", "replies"=>2, "lastreply"=>$lastreply);

        $this->assertEquals(2, count($discussions));
        $this->assertContains($assertDiscussion1, $discussions);
        $this->assertContains($assertDiscussion2, $discussions);

        /**
         * cm is a label - user is enrolled
         */
        $this->setExpectedException('moodle_exception');
        $discussions = local_ombiel_webservices::get_forum_discussions($labelinstance->cmid);


        /**
         * News forum user can't reply
         */
        $options = array(
            'course'=>$course1->id,
            'type'=>'news'
        );
        $forum2instance = $forumgenerator->create_instance($options);

        /**
         * Discussion with no replies
         */
        $discussion3 = new stdClass();
        $discussion3->forum = $forum2instance->id;
        $discussion3->name = 'subject';
        $discussion3->message = '<div>message</div>';
        $discussion3->messagetrust = false;
        $discussion3->messageformat = 1;
        $discussion3->mailnow = false;
        $discussion3->course = $course1->id;

        $discussion1id = forum_add_discussion($discussion3);

        /**
         * cm is a news forum - user can't reply
         */
        $discussions = local_ombiel_webservices::get_forum_discussions($forum1instance->cmid);
        $discussions = external_api::clean_returnvalue(local_ombiel_webservices::get_forum_discussions_returns(), $discussions);


        $this->assertEquals(false, $assertDiscussion1['canreply']);


     }
     public function test_get_forum_discussions_not_enrolled() {
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $coursecontext = context_course::instance($course->id);
         /**
         * Set up forum
         */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course->id,
        );
        $foruminstance = $forumgenerator->create_instance($options);

        $this->setExpectedException('moodle_exception');
        $forum = local_ombiel_webservices::get_forum_discussions($foruminstance->cmid);


     }
     public function test_get_discussion_posts() {
         global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $student = self::getDataGenerator()->create_user(array('firstname'=>'Stuart', 'lastname'=>'Dent', 'trackforums'=>true));
        $teacher = self::getDataGenerator()->create_user(array('firstname'=>'Miss', 'lastname'=>'Teach'));
        $usernoaccess = self::getDataGenerator()->create_user();

        $this->setUser($teacher); // set up as teacher
        $this->getDataGenerator()->enrol_user($student->id, $course1->id);
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id);

         /**
         * Set up forum
         */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course1->id,
            'trackingtype'=>FORUM_TRACKING_ON
        );
        $forum1instance = $forumgenerator->create_instance($options);

        $cm = $DB->get_record('course_modules', array('id'=>$forum1instance->cmid));
        /**
         * Create discussion
         */
        $discussion = new stdClass();
        $discussion->forum = $forum1instance->id;
        $discussion->name = 'subject2';
        $discussion->message = '<div>message2</div>';
        $discussion->messagetrust = false;
        $discussion->messageformat = 1;
        $discussion->mailnow = false;
        $discussion->course = $course1->id;

        $discussionid = forum_add_discussion($discussion);
        $discussionpostid = $DB->get_field('forum_posts', 'id', array('discussion'=>$discussionid));

        /**
         * No posts
         */
        $posts = local_ombiel_webservices::get_discussion_posts($discussionid);
        $posts = external_api::clean_returnvalue(local_ombiel_webservices::get_discussion_posts_returns(), $posts);

        $this->assertSame(array(), $posts);

        $CFG->forum_usermarksread = true;
        /**
         * Add posts
         */
        $post1 = new stdClass();
        $post1->discussion = $discussionid;
        $post1->subject = 'Reply 1';
        $post1->message = 'Reply message 1';
        $post1->messageformat = 1;
        $post1->parent = $discussionpostid;
        $post1->itemid = 0;
        $post1->course = $course1->id;
        $post1->forum = $forum1instance->id;

        if ($CFG->version >= 2014111000) { // Moodle 2.8
            $post1id = forum_add_new_post($post1, null);
        } else {
            $post1id = forum_add_new_post($post1, null, $message);
        }

        $post1time = $DB->get_field('forum_posts', 'modified', array('id'=>$post1id));

        $post2 = new stdClass();
        $post2->discussion = $discussionid;
        $post2->subject = 'Reply 2';
        $post2->message = 'Reply message 2';
        $post2->messageformat = 2;
        $post2->parent = $discussionpostid;
        $post2->itemid = 0;
        $post2->course = $course1->id;
        $post2->forum = $forum1instance->id;

        if ($CFG->version >= 2014111000) { // Moodle 2.8
            $post2id = forum_add_new_post($post2, null);
        } else {
            $post2id = forum_add_new_post($post2, null, $message);
        }
        $post2time = $DB->get_field('forum_posts', 'modified', array('id'=>$post2id));



        $this->setUser($student);
        $postsbefore = forum_tp_get_course_unread_posts($student->id, $course1->id);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }
        $posts = local_ombiel_webservices::get_discussion_posts($discussionid);
        $posts = external_api::clean_returnvalue(local_ombiel_webservices::get_discussion_posts_returns(), $posts);

	$assertPost1 = array("id"=>$post1id, "parent"=>$discussionpostid, "subject"=>"Reply 1", "author"=>"Miss Teach", "content"=>"Reply message 1", "date"=>$post1time);
	$assertPost2 = array("id"=>$post2id, "parent"=>$discussionpostid, "subject"=>"Reply 2", "author"=>"Miss Teach", "content"=>"Reply message 2", "date"=>$post2time);

        $this->assertEquals(2, count($posts));
        $postsafter = forum_tp_get_course_unread_posts($student->id, $course1->id);
        $this->assertEquals(($postsbefore[$forum1instance->id]->unread-2), $postsafter[$forum1instance->id]->unread);

        $this->assertContains($assertPost1, $posts);
        $this->assertContains($assertPost2, $posts);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_forum\event\discussion_viewed')) {
                    $eventstotest[] = $event;
                }
            }
            $cmcontext = context_module::instance($forum1instance->cmid);
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_forum',$eventstotest[0]->component);
            $this->assertEquals('viewed',$eventstotest[0]->action);
            $this->assertEquals($cmcontext->id,$eventstotest[0]->contextid);
            $this->assertEquals($student->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
            $this->assertCount(1, $events);
        }
        /**
         * this user does not have access
         */
        $this->setUser($usernoaccess);
        $this->setExpectedException('moodle_exception');
        $posts = local_ombiel_webservices::get_discussion_posts($discussionid);


     }
     public function test_add_forum_discussion() {
         global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course(array('groupmode' => SEPARATEGROUPS,'groupmodeforce'=>1));

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $user1 = self::getDataGenerator()->create_user(array('firstname'=>'First', 'lastname'=>'Last'));
        $user2 = self::getDataGenerator()->create_user(array('firstname'=>'First 2', 'lastname'=>'Last'));
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $user1->id, 'groupid' => $group1->id));

        $this->setUser($user1);
         /**
         * Set up forum
         */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course1->id,
            'type'=>'single'
        );
        $forum1instance = $forumgenerator->create_instance($options);

        /**
         * Can't add - Single discussion forum
         */
        $result = local_ombiel_webservices::add_forum_discussion($forum1instance->id, 'subject', 'message');
        $result = external_api::clean_returnvalue(local_ombiel_webservices::add_forum_discussion_returns(), $result);

        $this->assertEquals(0, $result['result']);

        $count = $DB->count_records('forum_discussions', array('forum'=>$forum1instance->id));
        $this->assertEquals(1, $count);

        $options = array(
            'course'=>$course1->id,
        );
        $forum2instance = $forumgenerator->create_instance($options);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }
        /**
         * Can add
         */
        $result = local_ombiel_webservices::add_forum_discussion($forum2instance->id, 'subject', 'message');
        $result = external_api::clean_returnvalue(local_ombiel_webservices::add_forum_discussion_returns(), $result);
        $eventstotest = array();
        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_forum\event\discussion_created')) {
                    $eventstotest[] = $event;
                }
            }
            $cmcontext = context_module::instance($forum2instance->cmid);
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_forum',$eventstotest[0]->component);
            $this->assertEquals('created',$eventstotest[0]->action);
            $this->assertEquals($cmcontext->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);

            $this->assertEquals($forum2instance->id,$eventstotest[0]->other['forumid']);
        }

        $discussions = $DB->get_records('forum_discussions', array('forum'=>$forum2instance->id));

        $latestdiscussion = end($discussions);

        $this->assertEquals($latestdiscussion->id, $result['result']);
        $this->assertEquals('subject',$latestdiscussion->name);
        $this->assertEquals($group1->id,$latestdiscussion->groupid);
        $firstpost = $DB->get_record('forum_posts', array('id'=>$latestdiscussion->firstpost));

        $this->assertEquals('message',$firstpost->message);

        /**
        * Can add no groups
        */

        $course2 = self::getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id);

        $options = array(
            'course'=>$course2->id,
        );
        $forum3instance = $forumgenerator->create_instance($options);

        $result = local_ombiel_webservices::add_forum_discussion($forum3instance->id, 'subject', 'message');
        $result = external_api::clean_returnvalue(local_ombiel_webservices::add_forum_discussion_returns(), $result);
        $discussions = $DB->get_records('forum_discussions', array('forum'=>$forum3instance->id));

        $latestdiscussion = end($discussions);
        $this->assertEquals('-1',$latestdiscussion->groupid);
     }
     public function test_add_discussion_post() {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user(array('firstname'=>'First', 'lastname'=>'Last'));
        $user2 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

         /**
         * Set up forum
         */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course1->id,
        );
        $forum1instance = $forumgenerator->create_instance($options);

        /**
         * Create discussion
         */
        $discussion = new stdClass();
        $discussion->forum = $forum1instance->id;
        $discussion->name = 'discussion subject';
        $discussion->message = 'discussion message';
        $discussion->messagetrust = false;
        $discussion->messageformat = 1;
        $discussion->mailnow = false;
        $discussion->course = $course1->id;

        $discussionid = forum_add_discussion($discussion);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }

        /**
         * Post with out a parent
         */
        $results = local_ombiel_webservices::add_discussion_post($discussionid, 'subject1', 'message1');
        $results = external_api::clean_returnvalue(local_ombiel_webservices::add_discussion_post_returns(), $results);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_forum\event\post_created')) {
                    $eventstotest[] = $event;
                }
            }
            $cmcontext = context_module::instance($forum1instance->cmid);
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_forum',$eventstotest[0]->component);
            $this->assertEquals('created',$eventstotest[0]->action);
            $this->assertEquals($cmcontext->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
        }

        $posts = $DB->get_records('forum_posts', array('discussion'=>$discussionid), 'id ASC');

        $post1 = end($posts);

        $this->assertEquals($results['result'], $post1->id);
        $this->assertEquals("subject1", $post1->subject);
        $this->assertEquals("message1", $post1->message);

        /**
         * Post with a parent (reply)
         */
        $results = local_ombiel_webservices::add_discussion_post($discussionid, 'subject2', 'message2', $results['result']);
        $results = external_api::clean_returnvalue(local_ombiel_webservices::add_discussion_post_returns(), $results);

        $post2 = $DB->get_record('forum_posts', array('discussion'=>$discussionid, 'parent'=>$post1->id));

        $this->assertEquals($results['result'], $post2->id);
        $this->assertEquals("subject2", $post2->subject);
        $this->assertEquals("message2", $post2->message);

        /**
         * this user does not have access
         */
        $this->setUser($user2);

        $countb4 = $DB->count_records('forum_posts', array('discussion'=>$discussionid));
        $results = local_ombiel_webservices::add_discussion_post($discussionid, 'subject3', 'message3');

        $this->assertEquals(0, $results['result']);

        $count = $DB->count_records('forum_posts', array('discussion'=>$discussionid));
        $this->assertEquals($countb4, $count);

     }

     public function test_get_user_forums() {
        global $CFG, $DB, $USER;
        $CFG->forum_trackreadposts = true;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $user1 = self::getDataGenerator()->create_user(array('trackforums'=>true));
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id);

        $coursecontext = context_course::instance($course1->id);

        // stop cmid being the same as instance id (better test)
        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');
        $options = array(
            'course'=>$course1->id,
        );
        $labelgenerator->create_instance($options);
        /**
         * Create forums
         */
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        $options = array(
            'course'=>$course1->id,
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'showdescription'=>false
        );
        $forum1instance = $forumgenerator->create_instance($options);

        $options = array(
            'course'=>$course2->id,
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'showdescription'=>true,
            'duedate'=>1642550401,
            'trackingtype'=>2
        );
        $forum2instance = $forumgenerator->create_instance($options);

        /**
         * Create discussion
         */
        $discussion = new stdClass();
        $discussion->forum = $forum2instance->id;
        $discussion->name = 'subject2';
        $discussion->message = 'message2';
        $discussion->messagetrust = false;
        $discussion->messageformat = 1;
        $discussion->mailnow = false;
        $discussion->course = $course1->id;

        $discussionid = forum_add_discussion($discussion);

        /**
         * Add post
         */
        $post1 = new stdClass();
        $post1->userid = $user2->id;
        $post1->forum = $forum2instance->id;
        $post1->discussion = $discussionid;
        $post1->subject = 'Reply 1';
        $post1->message = 'Reply message 1';
        $post1->messageformat = 1;
        $post1->parent = $discussionid;
        $post1->itemid = 0;
        $post1->course = $course1->id;

        $this->setUser($user2); # post as user 2 so that the post is unread for user1
        if ($CFG->version >= 2014111000) { // Moodle 2.8
            $post1id = forum_add_new_post($post1, null);
        } else {
            $post1id = forum_add_new_post($post1, null, $message);
        }
        $post1time = $DB->get_field('forum_posts', 'modified', array('id'=>$post1id));

        $cm2 = $DB->get_record('course_modules', array('id'=>$forum2instance->cmid));
        $cm2->showdescription = 1;
        $DB->update_record('course_modules', $cm2);

        $this->setUser($user1);
        /**
         * Default user id - user has forums
         */
        $forums = local_ombiel_webservices::get_user_forums();
        $forums = external_api::clean_returnvalue(local_ombiel_webservices::get_user_forums_returns(), $forums);

        $this->assertEquals(2, count($forums));
        $this->assertEquals('Forum 2', $forums[0]['name']);
        $cmcontext = context_module::instance($forum2instance->cmid);
        $this->assertEquals('<div class="text_to_html"><img src="'.$CFG->wwwroot.
                '/webservice/pluginfile.php/'.$cmcontext->id.
                '/mod_forum/intro/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" /></div>',
                $forums[0]['description']);

        $this->assertArrayNotHasKey('description',$forums[1]);
        $this->assertEquals($course2->id, $forums[0]['courseid']);
        $this->assertEquals(1, $forums[0]['unreadposts']);
        /**
         * Call with logged in user id - user has forums
         */
        $forums = local_ombiel_webservices::get_user_forums($user1->id);
        $forums = external_api::clean_returnvalue(local_ombiel_webservices::get_user_forums_returns(), $forums);

        $this->assertEquals(2, count($forums));
        /**
         * Call with another persons user id with authority- they have no forums
         */
        $usercontext = context_user::instance($user2->id, MUST_EXIST);

        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, $usercontext);
        role_assign($roleid, $USER->id, $usercontext);
        accesslib_clear_all_caches_for_unit_testing();
        $forums = local_ombiel_webservices::get_user_forums($user2->id);
        $forums = external_api::clean_returnvalue(local_ombiel_webservices::get_user_forums_returns(), $forums);

        $this->assertSame(array(), $forums);
        /**
         * Call with another persons user id without authority
         */
        $this->setExpectedException('moodle_exception');
        $courses = local_ombiel_webservices::get_user_forums($user3->id);

     }
     public function test_get_coursenews() {
        global $CFG, $DB, $USER;
        $CFG->forum_trackreadposts = true;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $user1 = self::getDataGenerator()->create_user();
        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        /**
         * default course to site
         */
        $results = local_ombiel_webservices::get_coursenews();
        $results = external_api::clean_returnvalue(local_ombiel_webservices::get_coursenews_returns(), $results);

        $forum = $DB->get_record('forum', array('course'=>SITEID, 'type'=>'news'));
        $cm = get_coursemodule_from_instance('forum', $forum->id);
        $this->assertEquals($cm->id, $results['coursemoduleid']);

        /**
         * Call with logged in user id - user has forums
         */
        $results = local_ombiel_webservices::get_coursenews($course1->id);
        $results = external_api::clean_returnvalue(local_ombiel_webservices::get_coursenews_returns(), $results);

        $forum = $DB->get_record('forum', array('course'=>$course1->id, 'type'=>'news'));
        $cm = get_coursemodule_from_instance('forum', $forum->id);
        $this->assertEquals($cm->id, $results['coursemoduleid']);

     }
     public function test_get_cm_choice() {
         global $CFG, $DB;

        require_once ($CFG->dirroot . '/mod/choice/lib.php');

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        /**
         * Set up label for negative test
         */
        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');
        $options = array(
            'course'=>$course1->id,
        );
        $labelinstance = $labelgenerator->create_instance($options);

        $lastweek = strtotime('last week');
        $yesterday = strtotime('yesterday');
        $tomorrow = strtotime('tomorrow');
        $nextweek = strtotime('next week');
        /**
         * Choice 1 - not answered, not yet open, show description
         */

        $options = array(
            'name'=>'Choice 1',
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'showdescription'=>true,
            'course'=>$course1->id,
            'allowupdate'=>true,
            'limitanswers'=>false,
            'timeopen'=>$tomorrow,
            'timeclose'=>$nextweek,
        );
        $choiceinstance1 = $this->choice_generator($options);
        $cm1 = $DB->get_record('course_modules', array('id'=>$choiceinstance1->cmid));
        $cmcontext1 = context_module::instance($cm1->id);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }

        $choice = local_ombiel_webservices::get_cm_choice($cm1->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_choice\event\course_module_viewed')) {
                    $eventstotest[] = $event;
                }
            }
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_choice',$eventstotest[0]->component);
            $this->assertEquals('viewed',$eventstotest[0]->action);
            $this->assertEquals($cmcontext1->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
        }

        $this->assertEquals('Choice 1', $choice['name']);
        $this->assertEquals('<div class="text_to_html"><img src="'.$CFG->wwwroot.
                '/webservice/pluginfile.php/'.$cmcontext1->id
                .'/mod_choice/intro/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" /></div>',
                $choice['description']);
        $this->assertEquals(false, $choice['allowanswer']);
        $this->assertEquals(false, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertArrayNotHasKey('count',$choice['options'][0]);
        $this->assertArrayNotHasKey('allow',$choice['options'][0]);
        $this->assertEquals('en', $choice['language']);

        /**
         * Choice 2 - not answered, open, show results after answer, don't show description
         */

        $options = array(
            'name'=>'Choice 2',
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'showdescription'=>false,
            'course'=>$course1->id,
            'allowupdate'=>true,
            'limitanswers'=>false,
            'timeopen'=>$yesterday,
            'timeclose'=>$tomorrow,
            'showresults'=>CHOICE_SHOWRESULTS_AFTER_ANSWER,
        );
        $choiceinstance2 = $this->choice_generator($options);

        // user2 answers to give some results to show
        $cm2 = $DB->get_record('course_modules', array('id'=>$choiceinstance2->cmid));
        end($choiceinstance2->option);
        $lastoptionid = key($choiceinstance2->option);
        choice_user_submit_response($lastoptionid, $choiceinstance2, $user2->id, $course1, $cm2);
        $cmcontext2 = context_module::instance($cm2->id);

        $choice = local_ombiel_webservices::get_cm_choice($cm2->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        $this->assertEquals('Choice 2', $choice['name']);
        $this->assertEquals('<div class="text_to_html"><img src="'.$CFG->wwwroot.
                '/webservice/pluginfile.php/'.$cmcontext2->id
                .'/mod_choice/intro/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" /></div>',
                $choice['description']);
        $this->assertEquals(true, $choice['allowanswer']);
        $this->assertEquals(false, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertArrayNotHasKey('count',$choice['options'][0]);

        /**
         * Choice 3 - not answered, open, always show results, limit answers
         */

        $options = array(
            'name'=>'Choice 3',
            'intro'=>'',
            'course'=>$course1->id,
            'allowupdate'=>true,
            'limitanswers'=>true,
            'timeopen'=>$yesterday,
            'timeclose'=>$tomorrow,
            'showresults'=>CHOICE_SHOWRESULTS_ALWAYS,
        );
        $choiceinstance3 = $this->choice_generator($options);

        // user2 answers to give some results to show
        $cm3 = $DB->get_record('course_modules', array('id'=>$choiceinstance3->cmid));
        end($choiceinstance3->option);
        $lastoptionid = key($choiceinstance3->option);
        choice_user_submit_response($lastoptionid, $choiceinstance3, $user2->id, $course1, $cm3);

        $cmcontext3 = context_module::instance($cm3->id);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }

        $choice = local_ombiel_webservices::get_cm_choice($cm3->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_choice\event\course_module_viewed')) {
                    $eventstotest[] = $event;
                }
            }
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_choice',$eventstotest[0]->component);
            $this->assertEquals('viewed',$eventstotest[0]->action);
            $this->assertEquals($cmcontext3->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
        }

        $this->assertEquals('Choice 3', $choice['name']);
        $this->assertEquals('',
                $choice['description']);
        $this->assertEquals(true, $choice['allowanswer']);
        $this->assertEquals(true, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertEquals(0, $choice['options'][0]['count']);
        $this->assertEquals(true, $choice['options'][0]['allowanswer']);
        $this->assertEquals(1, $choice['options'][3]['count']);
        $this->assertEquals(false, $choice['options'][3]['allowanswer']);

        /**
         * Choice 4 - not answered, open, never show results
         */

        $options = array(
            'name'=>'Choice 4',
            'intro'=>'',
            'course'=>$course1->id,
            'allowupdate'=>true,
            'limitanswers'=>false,
            'timeopen'=>$yesterday,
            'timeclose'=>$tomorrow,
            'showresults'=>CHOICE_SHOWRESULTS_NOT,
        );
        $choiceinstance4 = $this->choice_generator($options);
        // user2 answers to give some results to show
        $cm4 = $DB->get_record('course_modules', array('id'=>$choiceinstance4->cmid));
        end($choiceinstance4->option);
        $lastoptionid = key($choiceinstance4->option);
        choice_user_submit_response($lastoptionid, $choiceinstance4, $user2->id, $course1, $cm4);
        $cmcontext4 = context_module::instance($cm4->id);

        $choice = local_ombiel_webservices::get_cm_choice($cm4->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        $this->assertEquals('Choice 4', $choice['name']);
        $this->assertEquals('', $choice['description']);
        $this->assertEquals(true, $choice['allowanswer']);
        $this->assertEquals(false, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertArrayNotHasKey('count',$choice['options'][0]);

        /**
         * Choice 5 - answered (update allowed), open, show results after closed
         */

        $options = array(
            'name'=>'Choice 5',
            'intro'=>'',
            'course'=>$course1->id,
            'allowupdate'=>true,
            'limitanswers'=>false,
            'timeopen'=>$yesterday,
            'timeclose'=>$tomorrow,
            'showresults'=>CHOICE_SHOWRESULTS_AFTER_CLOSE,
        );
        $choiceinstance5 = $this->choice_generator($options);
        $cm5 = $DB->get_record('course_modules', array('id'=>$choiceinstance5->cmid));
        next($choiceinstance5->option);
        next($choiceinstance5->option);
        $optionid = key($choiceinstance5->option);
        choice_user_submit_response($optionid, $choiceinstance5, $user1->id, $course1, $cm5);

        next($choiceinstance5->option);
        $optionid = key($choiceinstance5->option);
        // user2 answers to give some results to show
        choice_user_submit_response($optionid, $choiceinstance5, $user2->id, $course1, $cm5);
        $cmcontext5 = context_module::instance($cm5->id);

        $choice = local_ombiel_webservices::get_cm_choice($cm5->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        $this->assertEquals('Choice 5', $choice['name']);
        $this->assertEquals('', $choice['description']);
        $this->assertEquals(true, $choice['allowanswer']);
        $this->assertEquals(false, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertArrayNotHasKey('count',$choice['options'][0]);

        /**
         * Choice 6 - answered (update not allowed), open, show results after answer
         */

        $options = array(
            'name'=>'Choice 6',
            'intro'=>'',
            'course'=>$course1->id,
            'allowupdate'=>false,
            'limitanswers'=>false,
            'timeopen'=>$yesterday,
            'timeclose'=>$tomorrow,
            'showresults'=>CHOICE_SHOWRESULTS_AFTER_ANSWER,
        );
        $choiceinstance6 = $this->choice_generator($options);
        $cm6 = $DB->get_record('course_modules', array('id'=>$choiceinstance6->cmid));
        next($choiceinstance6->option);
        next($choiceinstance6->option);
        $optionid = key($choiceinstance6->option);
        choice_user_submit_response($optionid, $choiceinstance6, $user1->id, $course1, $cm6);

        next($choiceinstance6->option);
        $optionid = key($choiceinstance6->option);
        // user2 answers to give some results to show
        choice_user_submit_response($optionid, $choiceinstance6, $user2->id, $course1, $cm6);
        $cmcontext6 = context_module::instance($cm6->id);

        $choice = local_ombiel_webservices::get_cm_choice($cm6->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        $this->assertEquals('Choice 6', $choice['name']);
        $this->assertEquals('', $choice['description']);
        $this->assertEquals(false, $choice['allowanswer']);
        $this->assertEquals(true, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertEquals(0, $choice['options'][0]['count']);
        $this->assertEquals(1, $choice['options'][2]['count']);
        $this->assertEquals(1, $choice['options'][3]['count']);

        /**
         * Choice 7 - answered, open, show results after closed
         */

        $options = array(
            'name'=>'Choice 7',
            'intro'=>'',
            'course'=>$course1->id,
            'allowupdate'=>true,
            'limitanswers'=>false,
            'timeopen'=>$yesterday,
            'timeclose'=>$tomorrow,
            'showresults'=>CHOICE_SHOWRESULTS_AFTER_CLOSE,
        );
        $choiceinstance7 = $this->choice_generator($options);
        $cm7 = $DB->get_record('course_modules', array('id'=>$choiceinstance7->cmid));
        next($choiceinstance7->option);
        next($choiceinstance7->option);
        $optionid = key($choiceinstance7->option);
        choice_user_submit_response($optionid, $choiceinstance7, $user1->id, $course1, $cm7);

        next($choiceinstance7->option);
        $optionid = key($choiceinstance7->option);
        // user2 answers to give some results to show
        choice_user_submit_response($optionid, $choiceinstance7, $user2->id, $course1, $cm7);
        $cmcontext7 = context_module::instance($cm7->id);

        $choice = local_ombiel_webservices::get_cm_choice($cm7->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        $this->assertEquals('Choice 7', $choice['name']);
        $this->assertEquals('', $choice['description']);
        $this->assertEquals(true, $choice['allowanswer']);
        $this->assertEquals(false, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertArrayNotHasKey('count',$choice['options'][0]);

        /**
         * Choice 8 - answered, closed, show results after closed
         */

        $options = array(
            'name'=>'Choice 8',
            'intro'=>'',
            'course'=>$course1->id,
            'allowupdate'=>true,
            'limitanswers'=>false,
            'timeopen'=>$lastweek,
            'timeclose'=>$yesterday,
            'showresults'=>CHOICE_SHOWRESULTS_AFTER_CLOSE,
        );
        $choiceinstance8 = $this->choice_generator($options);
        $cm8 = $DB->get_record('course_modules', array('id'=>$choiceinstance8->cmid));
        next($choiceinstance8->option);
        next($choiceinstance8->option);
        $optionid = key($choiceinstance8->option);
        choice_user_submit_response($optionid, $choiceinstance8, $user1->id, $course1, $cm8);

        next($choiceinstance8->option);
        $optionid = key($choiceinstance8->option);
        // user2 answers to give some results to show
        choice_user_submit_response($optionid, $choiceinstance8, $user2->id, $course1, $cm8);
        $cmcontext8 = context_module::instance($cm8->id);

        $choice = local_ombiel_webservices::get_cm_choice($cm8->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        $this->assertEquals('Choice 8', $choice['name']);
        $this->assertEquals('', $choice['description']);
        $this->assertEquals(false, $choice['allowanswer']);
        $this->assertEquals(true, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertEquals(0, $choice['options'][0]['count']);
        $this->assertEquals(1, $choice['options'][2]['count']);
        $this->assertEquals(1, $choice['options'][3]['count']);

        /**
         * Choice 9 - answered, closed, never show results
         */

        $options = array(
            'name'=>'Choice 9',
            'intro'=>'',
            'course'=>$course1->id,
            'allowupdate'=>true,
            'limitanswers'=>false,
            'timeopen'=>$lastweek,
            'timeclose'=>$yesterday,
            'showresults'=>CHOICE_SHOWRESULTS_NOT,
        );
        $choiceinstance9 = $this->choice_generator($options);
        $cm9 = $DB->get_record('course_modules', array('id'=>$choiceinstance9->cmid));
        next($choiceinstance9->option);
        next($choiceinstance9->option);
        $optionid = key($choiceinstance9->option);
        choice_user_submit_response($optionid, $choiceinstance9, $user1->id, $course1, $cm9);

        next($choiceinstance9->option);
        $optionid = key($choiceinstance9->option);
        // user2 answers to give some results to show
        choice_user_submit_response($optionid, $choiceinstance9, $user2->id, $course1, $cm9);
        $cmcontext9 = context_module::instance($cm9->id);

        $choice = local_ombiel_webservices::get_cm_choice($cm9->id);
        $choice = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_choice_returns(), $choice);

        $this->assertEquals('Choice 9', $choice['name']);
        $this->assertEquals('', $choice['description']);
        $this->assertEquals(false, $choice['allowanswer']);
        $this->assertEquals(false, $choice['showresults']);
        $this->assertEquals(4, count($choice['options']));
        $this->assertEquals('Soft Drink', $choice['options'][0]['option']);
        $this->assertEquals('Spirits', $choice['options'][3]['option']);
        $this->assertArrayNotHasKey('count',$choice['options'][0]);

        /**
         * cm is a label - user is enrolled
         */
        $this->setExpectedException('moodle_exception');
        $choice = local_ombiel_webservices::get_cm_choice($labelinstance->cmid);


     }
     public function test_get_cm_choice_not_enrolled() {
         global $CFG;
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $coursecontext = context_course::instance($course->id);
         /**
         * Set up choice
         */

        $options = array(
            'name'=>'Choice 1',
            'intro'=>'',
            'course'=>$course->id,
            'allowupdate'=>true,
            'limitanswers'=>false,
        );
        $choiceinstance1 = $this->choice_generator($options);

        $this->setExpectedException('moodle_exception');
        $choice = local_ombiel_webservices::get_cm_choice($choiceinstance1->cmid);


     }

      public function test_user_choice_response() {
        global $CFG, $DB, $USER;

        require_once ($CFG->dirroot . '/mod/choice/lib.php');

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
         /**
         * Set up choice
         */


        $options = array(
            'name'=>'Choice 1',
            'intro'=>'<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
            'showdescription'=>true,
            'course'=>$course1->id,
        );
        $choiceinstance1 = $this->choice_generator($options);

        next($choiceinstance1->option);
        $optionid = key($choiceinstance1->option);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }

        $results = local_ombiel_webservices::user_choice_response($optionid, $choiceinstance1->cmid);
        $results = external_api::clean_returnvalue(local_ombiel_webservices::user_choice_response_returns(), $results);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();

            $eventstotest = array();
            foreach ($events as $event) {

                if ($CFG->version >= 2016120500) { // Moodle 3.2
                    // event name changed in Moodle 3.2
                    if (is_a($event,'\mod_choice\event\answer_created')) {
                        $eventstotest[] = $event;
                    }
                } else {
                    if (is_a($event,'\mod_choice\event\answer_submitted')) {
                        $eventstotest[] = $event;
                    }
                }
            }
            $cmcontext = context_module::instance($choiceinstance1->cmid);
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_choice',$eventstotest[0]->component);
            if ($CFG->version >= 2016120500) { // Moodle 3.2
                $this->assertEquals('created',$eventstotest[0]->action);
            } else {
                $this->assertEquals('submitted',$eventstotest[0]->action);
            }
            $this->assertEquals($cmcontext->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
            $this->assertCount(1, $events);
        }

        $answers = $DB->get_records('choice_answers', array('choiceid'=>$choiceinstance1->id));

        $this->assertEquals(1, count($answers));
        $answer = current($answers);
        $this->assertEquals($user1->id, $answer->userid);
        $this->assertEquals($optionid, $answer->optionid);

        /**
         * cm is a choice - user is not enrolled
         */
        $this->setUser($user2);
        $this->setExpectedException('moodle_exception');
        $results = local_ombiel_webservices::user_choice_response(1, $choiceinstance1->cmid);

     }
     public function test_get_cm_book() {
         global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/book/locallib.php');
        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        /**
         * Set up label for negative test
         */
        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');
        $options = array(
            'course'=>$course1->id,
        );
        $labelinstance = $labelgenerator->create_instance($options);
         /**
         * Set up book, numbering: numbers
         */


        $bookoptions = array(
            'name'=>'Book 1',
            'course'=>$course1->id,
            'numbering'=>BOOK_NUM_NUMBERS,
            'customtitles'=>true,
        );
        $bookinstance1 = $this->book_generator($bookoptions);

        /**
         * Add some chapters
         */
        $chapteroptions = array(
            'title'=>'Chapter the First',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>false,
        );
        $chapter1 = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Chapter the First subchapter 1',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>true,
            'parent'=>$chapter1->id,
        );
        $chapter1_1 = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Hidden',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>true,
            'parent'=>$chapter1->id,
            'hidden'=>true,
        );
        $chapterhidden = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Chapter the First subchapter 2',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>true,
            'parent'=>$chapter1->id,
        );
        $chapter1_2 = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Chapter the Second',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>false,
        );
        $chapter2 = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Chapter the Second subchapter 1',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>true,
            'parent'=>$chapter2->id,
        );
        $chapter2_1 = $this->chapter_generator($chapteroptions);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }

        /**
         * cm is a book - user is enrolled
         */
        $book = local_ombiel_webservices::get_cm_book($bookinstance1->cmid);

        $book = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_book_returns(), $book);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_book\event\course_module_viewed')) {
                    $eventstotest[] = $event;
                }
            }
            $cmcontext = context_module::instance($bookinstance1->cmid);
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_book',$eventstotest[0]->component);
            $this->assertEquals('viewed',$eventstotest[0]->action);
            $this->assertEquals($cmcontext->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
        }

        $this->assertEquals('Book 1', $book['name']);
        $this->assertEquals($bookinstance1->id, $book['id']);
        $this->assertEquals('numbers', $book['numbering']);
        $this->assertEquals(5, count($book['chapters']));
        $this->assertEquals($chapter1_2->id, $book['chapters'][2]['chapterid']);
        $this->assertEquals("1.2 {$chapter1_2->title}", $book['chapters'][2]['title']);
        $this->assertEquals(true, $book['chapters'][2]['subchapter']);
        $this->assertEquals($chapter2->id, $book['chapters'][3]['chapterid']);
        $this->assertEquals("2 {$chapter2->title}", $book['chapters'][3]['title']);
        $this->assertEquals(false, $book['chapters'][3]['subchapter']);
        $this->assertEquals($chapter2_1->id, $book['chapters'][4]['chapterid']);
        $this->assertEquals("2.1 {$chapter2_1->title}", $book['chapters'][4]['title']);
        $this->assertEquals(true, $book['chapters'][4]['subchapter']);
        /**
         * Set up book, numbering: indent, not custom titles
         */
        $bookoptions = array(
            'name'=>'Book 2',
            'course'=>$course1->id,
            'numbering'=>BOOK_NUM_INDENTED,
        );
        $bookinstance2 = $this->book_generator($bookoptions);

        $chapteroptions = array(
            'title'=>'Chapter the First',
            'bookid'=>$bookinstance2->id,
            'subchapter'=>false,
        );
        $chapter1 = $this->chapter_generator($chapteroptions);

        $book = local_ombiel_webservices::get_cm_book($bookinstance2->cmid);
        $book = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_book_returns(), $book);

        $this->assertEquals('Book 2', $book['name']);
        $this->assertEquals($bookinstance2->id, $book['id']);
        $this->assertEquals('indented', $book['numbering']);
        $this->assertEquals(1, count($book['chapters']));
        $this->assertEquals($chapter1->title, $book['chapters'][0]['title']);
        /**
         * cm is a label - user is enrolled
         */
        $this->setExpectedException('moodle_exception');
        $book = local_ombiel_webservices::get_cm_book($labelinstance->cmid);

     }
     public function test_get_cm_book_not_enrolled() {
         global $CFG;
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $coursecontext = context_course::instance($course->id);



        $bookoptions = array(
            'name'=>'Book 1',
            'course'=>$course->id,
            'numbering'=>BOOK_NUM_NUMBERS,
            'customtitles'=>true,
        );
        $bookinstance = $this->book_generator($bookoptions);

        $this->setExpectedException('moodle_exception');
        $book = local_ombiel_webservices::get_cm_book($bookinstance->cmid);

     }
     public function test_get_book_chapter() {
         global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
         /**
         * Set up book, no custom titles
         */
        $bookoptions = array(
            'name'=>'Book 1',
            'course'=>$course1->id,
            'customtitles'=>false,
        );
        $bookinstance1 = $this->book_generator($bookoptions);

        $cmcontext = context_module::instance($bookinstance1->cmid);

        /**
         * Add some chapters
         */
        $chapteroptions = array(
            'title'=>'Chapter the First',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>false,
            'hidden'=>false,
            'contentformat'=>FORMAT_MOODLE,
            'content'=>'First chapter:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
        );
        $firstchapter = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Hidden',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>true,
            'parent'=>$firstchapter->id,
            'hidden'=>true,
        );
        $chapterhidden = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Chapter the First subchapter 1',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>true,
            'hidden'=>false,
            'parent'=>$firstchapter->id,
            'contentformat'=>FORMAT_MOODLE,
            'content'=>'Subchapter:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
        );
        $chapter2 = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Hidden',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>false,
            'hidden'=>true,
        );
        $chapterhidden2 = $this->chapter_generator($chapteroptions);
        $chapteroptions = array(
            'title'=>'Chapter the Second',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>false,
            'hidden'=>false,
            'contentformat'=>FORMAT_MOODLE,
            'content'=>'Last chapter:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
        );
        $lastchapter = $this->chapter_generator($chapteroptions);


        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }

        /**
         * First chapter
         */
        $chapterresult = local_ombiel_webservices::get_book_chapter($bookinstance1->id,$firstchapter->id);

        $chapterresult = external_api::clean_returnvalue(local_ombiel_webservices::get_book_chapter_returns(), $chapterresult);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_book\event\chapter_viewed')) {
                    $eventstotest[] = $event;
                }
            }
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_book',$eventstotest[0]->component);
            $this->assertEquals('viewed',$eventstotest[0]->action);
            $this->assertEquals($cmcontext->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
        }

        $this->assertEquals($firstchapter->title, $chapterresult['title']);
        $this->assertEquals('<div class="no-overflow"><div class="text_to_html">First chapter:<img src="'.$CFG->wwwroot.
                '/webservice/pluginfile.php/'.$cmcontext->id
                .'/mod_book/chapter/'.$firstchapter->id
                .'/_dummy.jpg" height="20" width="20" /><p>paragraph</p></div></div>',
                $chapterresult['content']);
        $this->assertEquals(0, $chapterresult['previd']);
        $this->assertEquals($chapter2->id, $chapterresult['nextid']);
        $this->assertEquals('en', $chapterresult['language']);
        /**
         * Subchapter
         */
        $chapterresult = local_ombiel_webservices::get_book_chapter($bookinstance1->id,$chapter2->id);

        $chapterresult = external_api::clean_returnvalue(local_ombiel_webservices::get_book_chapter_returns(), $chapterresult);

        $this->assertEquals($chapter2->title, $chapterresult['title']);
        $this->assertEquals('<div class="no-overflow"><div class="text_to_html">Subchapter:<img src="'.$CFG->wwwroot.
                '/webservice/pluginfile.php/'.$cmcontext->id
                .'/mod_book/chapter/'.$chapter2->id
                .'/_dummy.jpg" height="20" width="20" /><p>paragraph</p></div></div>',
                $chapterresult['content']);
        $this->assertEquals($firstchapter->id, $chapterresult['previd']);
        $this->assertEquals($lastchapter->id, $chapterresult['nextid']);

        /**
         * Last chapter
         */
        $chapterresult = local_ombiel_webservices::get_book_chapter($bookinstance1->id,$lastchapter->id);

        $chapterresult = external_api::clean_returnvalue(local_ombiel_webservices::get_book_chapter_returns(), $chapterresult);

        $this->assertEquals($lastchapter->title, $chapterresult['title']);
        $this->assertEquals('<div class="no-overflow"><div class="text_to_html">Last chapter:<img src="'.$CFG->wwwroot.
                '/webservice/pluginfile.php/'.$cmcontext->id
                .'/mod_book/chapter/'.$lastchapter->id
                .'/_dummy.jpg" height="20" width="20" /><p>paragraph</p></div></div>',
                $chapterresult['content']);
        $this->assertEquals($chapter2->id, $chapterresult['previd']);
        $this->assertEquals(0, $chapterresult['nextid']);

        /**
         * Set up book, with custom titles
         */
        $bookoptions = array(
            'name'=>'Book 1',
            'course'=>$course1->id,
            'customtitles'=>true,
        );
        $bookinstance1 = $this->book_generator($bookoptions);
        /**
         * Add a chapter
         */
        $chapteroptions = array(
            'title'=>'Chapter the First',
            'bookid'=>$bookinstance1->id,
            'subchapter'=>false,
            'hidden'=>false,
            'contentformat'=>FORMAT_MOODLE,
            'content'=>'First chapter:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" />',
        );
        $chapter = $this->chapter_generator($chapteroptions);

        $chapterresult = local_ombiel_webservices::get_book_chapter($bookinstance1->id, $chapter->id);

        $chapterresult = external_api::clean_returnvalue(local_ombiel_webservices::get_book_chapter_returns(), $chapterresult);

        $this->assertArrayNotHasKey('title', $chapterresult);

        /*
         * not enrolled - exception
         */

        $user2 = self::getDataGenerator()->create_user();

        $this->setUser($user2);
        $this->setExpectedException('moodle_exception');
        $chapterresult = local_ombiel_webservices::get_book_chapter($bookinstance1->id, $chapter->id);
        $chapterresult = external_api::clean_returnvalue(local_ombiel_webservices::get_book_chapter_returns(), $chapterresult);

     }
    public function test_get_cm_page() {
         global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();

        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        /**
         * Set up label for negative test
         */
        $labelgenerator = $this->getDataGenerator()->get_plugin_generator('mod_label');
        $options = array(
            'course'=>$course1->id,
        );
        $labelinstance = $labelgenerator->create_instance($options);
         /**
         * Set up page, don't print heading, print description
         */

        $pageoptions = array(
            'name'=>'Page 1',
            'course'=>$course1->id,
            'intro'=>'First Page intro:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
            'content'=>'First Page content:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
            'displayoptions'=>'a:2:{s:12:"printheading";s:1:"0";s:10:"printintro";s:1:"1";}',
            'revision'=>3,
        );
        $pageinstance1 = $this->page_generator($pageoptions);
        $context1 = context_module::instance($pageinstance1->cmid);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $sink = $this->redirectEvents();
        }

        $page = local_ombiel_webservices::get_cm_page($pageinstance1->cmid);

        $page = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_page_returns(), $page);

        if ($CFG->version >= 2014051200) { // Moodle 2.7
            $events = $sink->get_events();
            $eventstotest = array();
            foreach ($events as $event) {

                if (is_a($event,'\mod_page\event\course_module_viewed')) {
                    $eventstotest[] = $event;
                }
            }
            $this->assertCount(1, $eventstotest);
            $this->assertEquals('mod_page',$eventstotest[0]->component);
            $this->assertEquals('viewed',$eventstotest[0]->action);
            $this->assertEquals($context1->id,$eventstotest[0]->contextid);
            $this->assertEquals($user1->id,$eventstotest[0]->userid);
            $this->assertEquals($course1->id,$eventstotest[0]->courseid);
        }

        $this->assertArrayNotHasKey('name',$page);
        $this->assertEquals('<div class="text_to_html">First Page intro:<img src="' . $CFG->wwwroot . '/webservice/pluginfile.php/'.
            $context1->id.'/mod_page/intro/_dummy.jpg" height="20" width="20" alt="_dummy.jpg" /><p>paragraph</p></div>',
            $page['description']);
        $this->assertContains('<div class="text_to_html">First Page content:<img src="' . $CFG->wwwroot . '/webservice/pluginfile.php/'.
                $context1->id.'/mod_page/content/'.
                $pageinstance1->revision.'/_dummy.jpg" height="20" width="20" /><p>paragraph</p></div>',
                $page['content']);
        $this->assertEquals('en', $page['language']);
         /**
         * Set up page, print heading, don't print description
         */

        $pageoptions = array(
            'name'=>'Page 2',
            'course'=>$course1->id,
            'intro'=>'Second Page intro:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
            'content'=>'Second Page content:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
            'displayoptions'=>'a:2:{s:12:"printheading";s:1:"1";s:10:"printintro";s:1:"0";}',
            'revision'=>1,
        );
        $pageinstance2 = $this->page_generator($pageoptions);
        $context2 = context_module::instance($pageinstance2->cmid);

        $page = local_ombiel_webservices::get_cm_page($pageinstance2->cmid);

        $page = external_api::clean_returnvalue(local_ombiel_webservices::get_cm_page_returns(), $page);

        $this->assertEquals('Page 2', $page['name']);
        $this->assertArrayNotHasKey('description',$page);
        $this->assertEquals('<div class="no-overflow"><div class="text_to_html">Second Page content:<img src="' . $CFG->wwwroot . '/webservice/pluginfile.php/'.
                $context2->id.'/mod_page/content/'.
                $pageinstance2->revision.'/_dummy.jpg" height="20" width="20" /><p>paragraph</p></div></div>',
            $page['content']);
        /**
         * cm is a label - user is enrolled
         */
        $this->setExpectedException('moodle_exception');
        $page = local_ombiel_webservices::get_cm_page($labelinstance->cmid);

     }
     public function test_get_cm_page_not_enrolled() {
         global $CFG;
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $pageoptions = array(
            'name'=>'Page 2',
            'course'=>$course->id,
            'intro'=>'Second Page intro:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
            'content'=>'Second Page content:<img src="@@PLUGINFILE@@/_dummy.jpg" height="20" width="20" /><p>paragraph</p>',
            'displayoptions'=>'a:2:{s:12:"printheading";s:1:"1";s:10:"printintro";s:1:"0";}',
        );
        $pageinstance = $this->page_generator($pageoptions);

        $this->setExpectedException('moodle_exception');
        $page = local_ombiel_webservices::get_cm_page($pageinstance->cmid);

     }

     public function test_get_user_messages() {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $this->setUser($user1);

         /**
         * Set up sent messages
         */
        $sentmessage = new stdClass();
        $sentmessage->useridfrom = $user1->id;
        $sentmessage->useridto = $user2->id;
        $sentmessage->fullmessage = 'Sent message 1';
        $sentmessage->format = 1;
        $sentmessage->timecreated = 1388679900;

        $DB->insert_record('message', $sentmessage);

        $sentmessage->fullmessage = 'Sent message 2';
        $sentmessage->timeread = 1388679935;
        $DB->insert_record('message_read', $sentmessage);

         /**
         * Set up incoming messages
         */
        $sentmessage = new stdClass();
        $sentmessage->useridfrom = $user2->id;
        $sentmessage->useridto = $user1->id;
        $sentmessage->fullmessage = 'Incoming message 1';
        $sentmessage->format = 1;
        $sentmessage->timecreated = 1388679900;

        $DB->insert_record('message', $sentmessage);

        $sentmessage->fullmessage = 'Incoming message 2';
        $sentmessage->timeread = 1388679935;
        $DB->insert_record('message_read', $sentmessage);
         /**
         * Sent messages
         */
        $messages = local_ombiel_webservices::get_user_messages(false, true);
        $messages = external_api::clean_returnvalue(local_ombiel_webservices::get_user_messages_returns(), $messages);
        $this->assertEquals(2, count($messages));

        $this->assertEquals('Sent message 1', $messages[1]['message']);
        $this->assertEquals('Sent message 2', $messages[0]['message']);

         /**
         * Read messages
         */
        $messages = local_ombiel_webservices::get_user_messages(true);
        $messages = external_api::clean_returnvalue(local_ombiel_webservices::get_user_messages_returns(), $messages);
        $this->assertEquals(1, count($messages));

        $this->assertEquals('Incoming message 2', $messages[0]['message']);
         /**
         * Unread messages
         */
        $messages = local_ombiel_webservices::get_user_messages();
        $messages = external_api::clean_returnvalue(local_ombiel_webservices::get_user_messages_returns(), $messages);
        $this->assertEquals(1, count($messages));

        $this->assertEquals('Incoming message 1', $messages[0]['message']);

     }
     public function test_get_native_moodle_link() {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $user1 = self::getDataGenerator()->create_user();
        $this->setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        $context = context_course::instance($course1->id);

        $_GET['wstoken'] = md5('test');
         /**
         * No course
         */
        $link = local_ombiel_webservices::get_native_moodle_link();
        $link = external_api::clean_returnvalue(local_ombiel_webservices::get_native_moodle_link_returns(), $link);

        $this->assertEquals($CFG->wwwroot.'/local/ombiel_webservices/login.php?wstoken='.md5('test').'&userid='.$user1->id, $link['link']);


         /**
         * courseid
         */
        $link = local_ombiel_webservices::get_native_moodle_link($course1->id);
        $link = external_api::clean_returnvalue(local_ombiel_webservices::get_native_moodle_link_returns(), $link);

        $this->assertEquals($CFG->wwwroot.'/local/ombiel_webservices/login.php?wstoken='.md5('test').'&userid='.$user1->id.'&courseid='.$course1->id, $link['link']);
        /**
         * User is not enrolled on course
         */
        $this->setExpectedException('moodle_exception');
        $link = local_ombiel_webservices::get_native_moodle_link($course2->id);

     }
     public function test_get_message_settings_link() {
        global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $user1 = self::getDataGenerator()->create_user();
        $this->setUser($user1);

        $_GET['wstoken'] = md5('test');
         /**
         * No course
         */
        $link = local_ombiel_webservices::get_message_settings_link();
        $link = external_api::clean_returnvalue(local_ombiel_webservices::get_message_settings_link_returns(), $link);

        $this->assertEquals($CFG->wwwroot.'/local/ombiel_webservices/login.php?wstoken='.md5('test').'&userid='.$user1->id.'&messages=true', $link['link']);

     }
     public function test_get_message_settings() {

        $this->resetAfterTest(true);

        $user1 = self::getDataGenerator()->create_user();
        $this->setUser($user1);
        /**
         * No user settings - show defaults
         */


        $settingslist = local_ombiel_webservices::get_message_settings();
        $settingslist = external_api::clean_returnvalue(local_ombiel_webservices::get_message_settings_returns(), $settingslist);

        $assertEnrolSelfNote = array('component'=>"enrol_self",'componentlabel'=>"Self enrolment",'settings'=>array(
                array("name"=>"expiry_notification","label"=>"Self enrolment expiry notifications","online"=>true,"offline"=>false)
            ));
        $this->assertContains($assertEnrolSelfNote, $settingslist);

        foreach ($settingslist as $componentArray) {
            if ($componentArray['componentlabel'] == 'System') {
                $this->assertEquals('moodle', $componentArray['component']);
                $systemArray = $componentArray['settings'];
            }
            if ($componentArray['componentlabel'] == 'Assignment') {
                $this->assertEquals('mod_assign', $componentArray['component']);
                $assignArray = $componentArray['settings'];
            }
            if ($componentArray['componentlabel'] == 'Forum') {
                $this->assertEquals('mod_forum', $componentArray['component']);
                $forumArray = $componentArray['settings'];
            }
        }
        $assertIM = array("name"=>"posts","label"=>"Subscribed forum posts","online"=>true,"offline"=>false);
        $this->assertContains($assertIM, $forumArray);
        $assertIM = array("name"=>"assign_notification","label"=>"Assignment notifications","online"=>true,"offline"=>false);
        $this->assertContains($assertIM, $assignArray);
        $assertIM = array("name"=>"instantmessage","label"=>"Personal messages between users","online"=>false,"offline"=>false);
        $this->assertContains($assertIM, $systemArray);

        /*
         * Set enrol_self_expiry_notification to disallowed - shouldn't be returned
         */
        set_config('ombiel_alerts_provider_enrol_self_expiry_notification_permitted', 'disallowed', 'message');
        /**
         * add a couple of user settings
         */
        $preferences = array();
        $preferences['message_provider_moodle_instantmessage_loggedin'] = 'email,popup';
        $preferences['message_provider_moodle_instantmessage_loggedoff'] = 'email,popup,ombiel_alerts';
        $preferences['message_provider_mod_assign_assign_notification_loggedin'] = '';
        $preferences['message_provider_mod_assign_assign_notification_loggedoff'] = 'ombiel_alerts';
        $preferences['message_provider_mod_forum_posts_loggedin'] = '';
        $preferences['message_provider_mod_forum_posts_loggedoff'] = 'email,ombiel_alerts,popup';

        set_user_preferences($preferences, $user1->id);


        $settingslist = local_ombiel_webservices::get_message_settings();
        $settingslist = external_api::clean_returnvalue(local_ombiel_webservices::get_message_settings_returns(), $settingslist);

        $this->assertNotContains($assertEnrolSelfNote, $settingslist);

        foreach ($settingslist as $componentArray) {
            if ($componentArray['componentlabel'] == 'System') {
                $this->assertEquals('moodle', $componentArray['component']);
                $systemArray = $componentArray['settings'];
            }
            if ($componentArray['componentlabel'] == 'Assignment') {
                $this->assertEquals('mod_assign', $componentArray['component']);
                $assignArray = $componentArray['settings'];
            }
            if ($componentArray['componentlabel'] == 'Forum') {
                $this->assertEquals('mod_forum', $componentArray['component']);
                $forumArray = $componentArray['settings'];
            }
        }
        $assertIM = array("name"=>"posts","label"=>"Subscribed forum posts","online"=>false,"offline"=>true);
        $this->assertContains($assertIM, $forumArray);
        $assertIM = array("name"=>"assign_notification","label"=>"Assignment notifications","online"=>false,"offline"=>true);
        $this->assertContains($assertIM, $assignArray);
        $assertIM = array("name"=>"instantmessage","label"=>"Personal messages between users","online"=>false,"offline"=>true);
        $this->assertContains($assertIM, $systemArray);

     }
     public function test_update_message_setting() {
      global $CFG, $DB, $USER;

        $this->resetAfterTest(true);

        $user1 = self::getDataGenerator()->create_user();
        $this->setUser($user1);

        $loggedinfullname = 'message_provider_enrol_self_expiry_notification_loggedin';
        $loggedofffullname = 'message_provider_enrol_self_expiry_notification_loggedoff';
        set_config($loggedinfullname, 'email,popup', 'message');
        set_config($loggedofffullname, 'email,ombiel_alerts,popup', 'message');

        /**
         * Default settings - change from false, true to  true, false
         */

        $result = local_ombiel_webservices::update_message_setting("enrol_self","expiry_notification", true, false);
        $result = external_api::clean_returnvalue(local_ombiel_webservices::update_message_setting_returns(), $result);

        $loggedinpref = get_user_preferences($loggedinfullname, false, $USER->id);
        $this->assertEquals('email,ombiel_alerts,popup', $loggedinpref);
        $loggedoffpref = get_user_preferences($loggedofffullname, false, $USER->id);
        $this->assertEquals('email,popup', $loggedoffpref);

        /**
         * Change to false, true
         */

        $result = local_ombiel_webservices::update_message_setting("enrol_self","expiry_notification", false, true);
        $result = external_api::clean_returnvalue(local_ombiel_webservices::update_message_setting_returns(), $result);

        $loggedinpref = get_user_preferences($loggedinfullname, false, $USER->id);
        $this->assertEquals('email,popup', $loggedinpref);
        $loggedoffpref = get_user_preferences($loggedofffullname, false, $USER->id);
        $this->assertEquals('email,ombiel_alerts,popup', $loggedoffpref);

        /**
         * Invalid name
         */

        $this->setExpectedException('moodle_exception');
        $result = local_ombiel_webservices::update_message_setting("enrol_self","invalid_name", true, true);
        $result = external_api::clean_returnvalue(local_ombiel_webservices::update_message_setting_returns(), $result);

        $assertEnrolSelfNote = array('component'=>"Self enrolment", 'settings'=>array(array("name"=>"message_provider_enrol_self_expiry_notification","label"=>"Self enrolment expiry notifications","online"=>true,"offline"=>false)));
        $this->assertContains($assertEnrolSelfNote, $result);

     }

     function book_generator($bookrecord) {
        global $DB;

        $bookrecord['id'] = $DB->insert_record('book', $bookrecord);

        $cm = new stdClass();
        $cm->course             = $bookrecord['course'];
        $cm->module             = $DB->get_field('modules', 'id', array('name'=>'book'));
        $cm->instance           = $bookrecord['id'];
        $cm->section            = 0;
        $cm->idnumber           = 0;
        $cm->added              = time();
        $cmid = $DB->insert_record('course_modules', $cm);

        course_add_cm_to_section($bookrecord['course'], $cmid, 1);

        $bookrecord['cmid'] = $cmid;
        return (object) $bookrecord;
     }
     function chapter_generator($chapterrecord) {
        global $DB;

        if (empty($chapterrecord['content'])) {
            $chapterrecord['content'] = '';
        }

        $chapterrecord['id'] = $DB->insert_record('book_chapters', $chapterrecord);

        return (object) $chapterrecord;
     }
     function page_generator($pagerecord) {
        global $DB;

        $pagerecord['id'] = $DB->insert_record('page', $pagerecord);

        $cm = new stdClass();
        $cm->course             = $pagerecord['course'];
        $cm->module             = $DB->get_field('modules', 'id', array('name'=>'page'));
        $cm->instance           = $pagerecord['id'];
        $cm->section            = 0;
        $cm->idnumber           = 0;
        $cm->added              = time();
        $cmid = $DB->insert_record('course_modules', $cm);

        course_add_cm_to_section($pagerecord['course'], $cmid, 1);

        $pagerecord['cmid'] = $cmid;
        return (object) $pagerecord;
     }
     function choice_generator($choice_record) {
        global $DB;

        $choiceid = $DB->insert_record('choice', $choice_record);

        $cm = new stdClass();
        $cm->course             = $choice_record['course'];
        $cm->module             = $DB->get_field('modules', 'id', array('name'=>'choice'));
        $cm->instance           = $choiceid;
        $cm->section            = 0;
        $cm->idnumber           = 0;
        $cm->added              = time();
        $cmid = $DB->insert_record('course_modules', $cm);

        course_add_cm_to_section($choice_record['course'], $cmid, 1);

        $option = new stdClass();
        $option->choiceid = $choiceid;
        $option->text = 'Soft Drink';
        $option->maxanswers = 1;
        $option->timemodified = time();
        $DB->insert_record('choice_options', $option);

        $option->text = 'Beer';
        $DB->insert_record('choice_options', $option);
        $option->text = 'Wine';
        $DB->insert_record('choice_options', $option);
        $option->text = 'Spirits';
        $DB->insert_record('choice_options', $option);

        $choice = choice_get_choice($choiceid);
        $choice->cmid = $cmid;
        return $choice;
     }

 }
