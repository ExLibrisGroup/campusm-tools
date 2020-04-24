<?php
/**
 * Version details
 *
 * @copyright &copy; 2014 ExLibris
 * @author ExLibris
 * @package ExLibris_webservices
 * @version 1.0
 */

require_once($CFG->dirroot.'/message/output/lib.php');

/**
 * The alerts message processor
 *
 */
class message_output_exlibris_alerts extends message_output {

    /**
     * Processes the message and sends a notification via the ExLibris alerts web service
     *
     * @param stdClass $eventdata the event data submitted by the message sender plus $eventdata->savedmessageid
     * @return true if ok, false if error
     */
    function send_message($eventdata){
        global $DB, $CFG, $SITE;

        if (!empty($CFG->noemailever)) {
            // hidden setting for development sites, set in config.php if needed
            debugging('$CFG->noemailever active, no message sent.', DEBUG_MINIMAL);
            return true;
        }

        // skip any messaging suspended and deleted users
        if ($eventdata->userto->auth === 'nologin' or $eventdata->userto->suspended or $eventdata->userto->deleted) {
            return true;
        }
        
        // Build message
        $note = $SITE->shortname.': '.$eventdata->subject;
        
        if (!empty($eventdata->smallmessage)) {
            $message = $eventdata->smallmessage;
        } else {
            $message = $eventdata->fullmessage;
        }
        
        if (!empty($CFG->exlibrisalertsaction)) {
            if (!empty($eventdata->contexturlname)) {
                $message .= "<br><br><a href='{$CFG->exlibrisalertsaction}'>"
                . "{$eventdata->contexturlname}</a>\n";
            }
        }

        // set up stream context and SOAP options for proxies, SSL etc.
        $credentials = sprintf('Authorization: Basic %s', 
            base64_encode("{$CFG->exlibrisalertsserversserverusername}:{$CFG->exlibrisalertsserverpassword}") ); 
        
        $parsedEndpoint = parse_url($CFG->exlibrisalertsserverendpoint);
        
        $streamContextOptions = array(
          'http'=>array(
              'user_agent' => 'PHPSoapClient',
              'request_fulluri' => true,
              'header'=>$credentials,
              ),
          'ssl'=>array(
              'SNI_enabled'=>true,
              'peer_name'=>$parsedEndpoint['host']
            ),
        );
        
        if (!empty($CFG->proxyhost)) {
            $streamContextOptions['http']['proxy'] = "{$CFG->proxyhost}:{$CFG->proxyport}";
        }    
        
        $context = stream_context_create($streamContextOptions);
        
        $soapOptions = array(
            'login' => $CFG->exlibrisalertsserversserverusername,
            'password' => $CFG->exlibrisalertsserverpassword,
            'location' => $CFG->exlibrisalertsserverendpoint,
            'stream_context' => $context,
        );
        
        if (!empty($CFG->proxyhost)) {
            $soapOptions['proxy_host'] = $CFG->proxyhost;
            $soapOptions['proxy_port'] = $CFG->proxyport;
        }
                    
         
        try {         
            // Get WSDL
            $soapclient = new SoapClient($CFG->exlibrisalertsserverendpoint.'?wsdl', $soapOptions);

            // Create request
            $request = array(
                'orgCode' => $CFG->exlibrisalertsorgcode,
                'password' => $CFG->exlibrisalertsorgpassword,
                'notifications'=> array(
                    'notification'=> array(
                        'notificationTargets' => array(
                            'notificationTarget'=> array(
                                'emailAddress'=>$eventdata->userto->email,                    
                            )
                        ),
                        'note' => $note,
                        'message' => $message,
                        'forceSms' => 'N',
                        'forceEmail' => 'N',
                        'forceCampusmNotification' => 'Y',
                     )                    
                 ),
            );

            // Make request          
                $result = $soapclient->sendAlerts($request);
            
        } catch(SoapFault $e) {  
            debugging($e->getMessage());

             // Trigger event for failing to send email but change error to show we mean the ExLibris Alerts system.
            $event = \core\event\email_failed::create(array(
                'context' => context_system::instance(),
                'userid' => $eventdata->userfrom->id,
                'relateduserid' => $eventdata->userto->id,
                'other' => array(
                    'subject' => 'ExLibris Alerts ',
                    'message' => $note,
                    'errorinfo' => 'Link to ExLibris Alerts system failed with error: '.$e->getMessage()
                )
            ));
            $event->trigger();
            return true;
        }
        return ($result->desc == 'Successful');
        
    }
    
    /**
     * Creates necessary fields in the messaging config form.
     *
     * @param array $preferences An array of user preferences
     */
    function config_form($preferences){
        return null;
    }

    /**
     * Parses the submitted form data and saves it into preferences array.
     *
     * @param stdClass $form preferences form class
     * @param array $preferences preferences array
     */
    function process_form($form, &$preferences){
        return null;
    }

    /**
     * Loads the config data from database to put on the form during initial form display
     *
     * @param array $preferences preferences array
     * @param int $userid the user id
     */
    function load_data(&$preferences, $userid){
        return null;
    }

    /**
     * Tests whether the alerts web service is configured
     * @return boolean true if the alerts web service is configured
     */
    function is_system_configured() {
        global $CFG;
        return (!empty($CFG->exlibrisalertsserverendpoint) && 
                !empty($CFG->exlibrisalertsserversserverusername) && 
                !empty($CFG->exlibrisalertsserverpassword) && 
                !empty($CFG->exlibrisalertsorgcode) && 
                !empty($CFG->exlibrisalertsorgpassword)
                );
    }

}

