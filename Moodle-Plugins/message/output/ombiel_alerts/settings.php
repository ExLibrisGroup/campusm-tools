<?php

/**
 * Version details
 *
 * @copyright &copy; 2014 oMbiel
 * @author ExLibris
 * @package oMbiel_webservices
 * @version 1.0
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
            'ombielalertsserverendpoint', 
            get_string('ombielalertsserverendpoint', 'message_ombiel_alerts'), 
            get_string('configombielalertsserverendpoint', 'message_ombiel_alerts'), 
            '', 
            PARAM_URL
            )
    );
    $settings->add(new admin_setting_configtext(
            'ombielalertsserversserverusername', 
            get_string('ombielalertsserverusername', 'message_ombiel_alerts'), 
            get_string('configombielalertsserverusername', 'message_ombiel_alerts'), 
            '', 
            PARAM_TEXT
            )
    );
    $settings->add(new admin_setting_configpasswordunmask(
            'ombielalertsserverpassword', 
            get_string('ombielalertsserverpassword', 'message_ombiel_alerts'), 
            get_string('configombielalertsserverpassword', 'message_ombiel_alerts'), 
            '', 
            PARAM_RAW
            )
    );
    $settings->add(new admin_setting_configtext(
            'ombielalertsorgcode', 
            get_string('ombielalertsorgcode', 'message_ombiel_alerts'), 
            get_string('configombielalertsorgcode', 'message_ombiel_alerts'), 
            '', 
            PARAM_INT
            )
    );
    $settings->add(new admin_setting_configpasswordunmask(
            'ombielalertsorgpassword', 
            get_string('ombielalertsorgpassword', 'message_ombiel_alerts'), 
            get_string('configombielalertsorgpassword', 'message_ombiel_alerts'), 
            '', 
            PARAM_RAW
            )
    );
    $settings->add(new admin_setting_configtext(
            'ombielalertsaekmenuid', 
            get_string('ombielalertsaekmenuid', 'message_ombiel_alerts'), 
            get_string('configombielalertsaekmenuid', 'message_ombiel_alerts'), 
            '', 
            PARAM_TEXT
            )
    );
    $settings->add(new admin_setting_configtext(
            'ombielalertsaekserviceid', 
            get_string('ombielalertsaekserviceid', 'message_ombiel_alerts'), 
            get_string('configombielalertsaekserviceid', 'message_ombiel_alerts'), 
            '', 
            PARAM_INT
            )
    );
}
