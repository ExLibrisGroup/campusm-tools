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
 * Details services
 *
 * @copyright 2016 ExLibris
 * @author ExLibris
 * @package oMbiel_webservices
 * @version 1.0
 */

$functions = array (
    'ombiel_get_user_dashboard' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_user_dashboard',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns a information for the user dashboard',
        'type'        => 'read',
    ),
    'ombiel_get_user_courses' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_user_courses',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get the users courses',
        'type'        => 'read',
    ),
    'ombiel_get_course_sections' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_course_sections',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns a list of the sections within a course',
        'type'        => 'read',
    ),
    'ombiel_get_section_content' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_section_content',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns the content within a section',
        'type'        => 'read',
    ),
    'ombiel_get_cm_assignment' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_cm_assignment',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns a assigment based on its course module id',
        'type'        => 'read',
    ),
    'ombiel_get_user_grades' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_user_grades',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns all finalised grades for the user with the given id',
        'type'        => 'read',
    ),
    'ombiel_get_course_grades' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_course_grades',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns all grades the given user in the given course',
        'type'        => 'read',
    ),

    'ombiel_get_user_forums' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_user_forums',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get all forums a user can post to',
        'type'        => 'read',
    ),
    'ombiel_get_cm_forum' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_cm_forum',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns forum with course module id',
        'type'        => 'read',
    ),
    'ombiel_get_forum_discussions' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_forum_discussions',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns all discussions within the forum with the given id',
        'type'        => 'read',
    ),
    'ombiel_get_discussion_posts' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_discussion_posts',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns all posts within the discussion with the given id',
        'type'        => 'read',
    ),
    'ombiel_add_forum_discussion' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'add_forum_discussion',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Adds a discussion post to the forum with the given id',
        'type'        => 'write',
    ),
    'ombiel_add_discussion_post' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'add_discussion_post',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Adds a post to a forum discussion',
        'type'        => 'write',
    ),
    'ombiel_get_coursenews' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_coursenews',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get the course news',
        'type'        => 'read',
    ),
    'ombiel_get_cm_book' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_cm_book',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get the book record linked to the given course module id',
        'type'        => 'read',
    ),
    'ombiel_get_book_chapter' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_book_chapter',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get the book chapter record from the id',
        'type'        => 'read',
    ),
    'ombiel_get_cm_choice' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_cm_choice',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get the choice record linked to the given course module id',
        'type'        => 'read',
    ),
    'ombiel_user_choice_response' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'user_choice_response',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'submit a response to a choice',
        'type'        => 'write',
    ),
    'ombiel_get_cm_page' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_cm_page',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get the page for the given course module id',
        'type'        => 'read',
    ),
    'ombiel_get_course_quizzes' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_course_quizzes',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'Returns all quizzes within the given course',
        'type'        => 'read',
    ),
    'ombiel_get_user_messages' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_user_messages',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get user messages',
        'type'        => 'read',
    ),
    'ombiel_get_native_moodle_link' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_native_moodle_link',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get native moodle link',
        'type'        => 'read',
    ),
    'ombiel_get_message_settings_link' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_message_settings_link',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get message settings',
        'type'        => 'read',
    ),
    'ombiel_get_message_settings' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_message_settings',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get message settings',
        'type'        => 'read',
    ),
    'ombiel_update_message_setting' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'update_message_setting',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'update message a setting',
        'type'        => 'write',
    ),
    'ombiel_get_block_content' => array(
        'classname'   => 'local_ombiel_webservices',
        'methodname'  => 'get_block_content',
        'classpath'   => 'local/ombiel_webservices/externallib.php',
        'description' => 'get block content and title',
        'type'        => 'read',
    ),

);
$services = array(
    'campusm' => array(
        'functions' => array (
            'core_webservice_get_site_info',
            'ombiel_get_course_sections',
            'ombiel_get_user_dashboard',
            'ombiel_get_user_courses',
            'ombiel_get_course_sections', // From Moodle 3.3 this is deprecated use core_course_get_contents
            'core_course_get_contents',
            'ombiel_get_section_content',
            'ombiel_get_cm_assignment',
            'mod_assign_get_submission_status',
            'core_comment_get_comments',
            'ombiel_get_user_grades',
            'ombiel_get_course_grades',
            'ombiel_get_user_forums',
            'ombiel_get_cm_forum',
            'ombiel_get_forum_discussions',
            'ombiel_get_discussion_posts',
            'ombiel_add_forum_discussion',
            'ombiel_add_discussion_post',
            'ombiel_get_coursenews',
            'ombiel_get_cm_book',
            'ombiel_get_book_chapter',
            'ombiel_get_cm_choice',
            'ombiel_user_choice_response',
            'ombiel_get_cm_page',
            'ombiel_get_user_messages',
            'ombiel_get_native_moodle_link',
            'ombiel_get_message_settings',
            'ombiel_update_message_setting',
            'ombiel_get_message_settings_link',
            'ombiel_get_block_content'),
        'enabled'=>1,
        'restrictedusers' => 0,
        'shortname' => 'campusm',
        'downloadfiles' => 1,
    ),
);
