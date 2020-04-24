<?php
/**
 * Version details
 *
 * @copyright &copy; 2014 ExLibris
 * @author ExLibris
 * @package ExLibris_webservices
 * @version 1.0
 */

/**
 * Install the ExLibris alerts message processor
 */
function xmldb_message_exlibris_alerts_install(){
    global $DB;

    $result = true;

    $provider = new stdClass();
    $provider->name  = 'exlibris_alerts';
    $DB->insert_record('message_processors', $provider);
    
    $defaults = (array) $DB->get_records_select('config_plugins', "plugin = 'message' AND name LIKE 'message_provider_%'");
    
    foreach($defaults as $defaultsetting) {
        $defaultsetting->value .= ',exlibris_alerts';
        $DB->update_record('config_plugins', $defaultsetting);
    }
    
    $actuals = (array) $DB->get_records_select('user_preferences', "name LIKE 'message_provider_%'");
    
    foreach($actuals as $actualsetting) {
        $actualsetting->value .= ',exlibris_alerts';
        $DB->update_record('user_preferences', $actualsetting);
    }
    
    return $result;
}
