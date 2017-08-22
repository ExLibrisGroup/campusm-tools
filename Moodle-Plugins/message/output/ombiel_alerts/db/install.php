<?php
/**
 * Version details
 *
 * @copyright &copy; 2014 ExLibris
 * @author ExLibris
 * @package oMbiel_webservices
 * @version 1.0
 */

/**
 * Install the oMbiel alerts message processor
 */
function xmldb_message_ombiel_alerts_install(){
    global $DB;

    $result = true;

    $provider = new stdClass();
    $provider->name  = 'ombiel_alerts';
    $DB->insert_record('message_processors', $provider);
    
    $defaults = (array) $DB->get_records_select('config_plugins', "plugin = 'message' AND name LIKE 'message_provider_%'");
    
    foreach($defaults as $defaultsetting) {
        $defaultsetting->value .= ',ombiel_alerts';
        $DB->update_record('config_plugins', $defaultsetting);
    }
    
    $actuals = (array) $DB->get_records_select('user_preferences', "name LIKE 'message_provider_%'");
    
    foreach($actuals as $actualsetting) {
        $actualsetting->value .= ',ombiel_alerts';
        $DB->update_record('user_preferences', $actualsetting);
    }
    
    return $result;
}
