<?php

/**
 * Version details
 *
 * @copyright &copy; 2014 ExLibris
 * @author ExLibris
 * @package ExLibris_webservices
 * @version 1.0
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
            'exlibrisalertsserverendpoint', 
            get_string('exlibrisalertsserverendpoint', 'message_exlibris_alerts'), 
            get_string('configexlibrisalertsserverendpoint', 'message_exlibris_alerts'), 
            '', 
            PARAM_URL
            )
    );
    $settings->add(new admin_setting_configtext(
            'exlibrisalertsserversserverusername', 
            get_string('exlibrisalertsserverusername', 'message_exlibris_alerts'), 
            get_string('configexlibrisalertsserverusername', 'message_exlibris_alerts'), 
            '', 
            PARAM_TEXT
            )
    );
    $settings->add(new admin_setting_configpasswordunmask(
            'exlibrisalertsserverpassword', 
            get_string('exlibrisalertsserverpassword', 'message_exlibris_alerts'), 
            get_string('configexlibrisalertsserverpassword', 'message_exlibris_alerts'), 
            '', 
            PARAM_RAW
            )
    );
    $settings->add(new admin_setting_configtext(
            'exlibrisalertsorgcode', 
            get_string('exlibrisalertsorgcode', 'message_exlibris_alerts'), 
            get_string('configexlibrisalertsorgcode', 'message_exlibris_alerts'), 
            '', 
            PARAM_INT
            )
    );
    $settings->add(new admin_setting_configpasswordunmask(
            'exlibrisalertsorgpassword', 
            get_string('exlibrisalertsorgpassword', 'message_exlibris_alerts'), 
            get_string('configexlibrisalertsorgpassword', 'message_exlibris_alerts'), 
            '', 
            PARAM_RAW
            )
    );
    $settings->add(new admin_setting_configtext(
            'exlibrisalertsaction', 
            get_string('exlibrisalertsaction', 'message_exlibris_alerts'), 
            get_string('configexlibrisalertsaction', 'message_exlibris_alerts'), 
            '', 
            PARAM_TEXT
            )
    );
}
