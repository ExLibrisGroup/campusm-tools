<?php
/**
 * Version details
 *
 * @copyright &copy; 2014 ExLibris
 * @author ExLibris
 * @package oMbiel_webservices
 * @version 1.0
 */

require_once($CFG->dirroot.'/message/output/lib.php');

/**
 * The alerts message processor
 *
 */
class message_output_ombiel_alerts extends message_output {

    /**
     * Processes the message and sends a notification via the oMbiel alerts web service
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
        
        if (!empty($CFG->ombielalertsaekmenuid)) {
            if (!empty($eventdata->contexturl)) {
                $parsedurl = parse_url($eventdata->contexturl);

                if (strpos($parsedurl['path'], '/mod/forum/discuss.php') !== false) {
                    parse_str($parsedurl['query'], $parsedquery);       
                    $forumid = $DB->get_field('forum_discussions', 'forum', array('id'=>$parsedquery['d']));
                    $cm = get_coursemodule_from_instance('forum', $forumid);  
                    $aekparams = "&_action=show_discussion&module={$cm->id}&discussion={$parsedquery['d']}&course_id={$cm->course}";
                    $fragment = '#'.$parsedurl['fragment'];
                } elseif (strpos($parsedurl['path'], '/mod/assign/view.php') !== false) {
                    parse_str($parsedurl['query'], $parsedquery);
                    $cm = $DB->get_record('course_modules', array('id'=>$parsedquery['id']));
                    $aekparams = "&_action=show_assign&module={$parsedquery['id']}&course_id={$cm->course}";
                    $fragment = ''; 
                    // Assignment subjects are a bit long 
                    $note = substr($note, 0, 45).'...';
                } else {
                    $aekparams = false;
                    $fragment = '';
                }

                if ($aekparams) {   
                    $message .= "<br><br><a href='campusm://loadaek?sid={$CFG->ombielalertsaekserviceid}&"
                    . "toolbar={$CFG->ombielalertsaekmenuid}{$aekparams}{$fragment}'>"
                    . "{$eventdata->contexturlname}</a>\n";
                }
            }
        }

        // set up stream context and SOAP options for proxies, SSL etc.
        $credentials = sprintf('Authorization: Basic %s', 
            base64_encode("{$CFG->ombielalertsserversserverusername}:{$CFG->ombielalertsserverpassword}") ); 
        
        $parsedEndpoint = parse_url($CFG->ombielalertsserverendpoint);
        
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
            'login' => $CFG->ombielalertsserversserverusername,
            'password' => $CFG->ombielalertsserverpassword,
            'location' => $CFG->ombielalertsserverendpoint,
            'stream_context' => $context,
        );
        
        if (!empty($CFG->proxyhost)) {
            $soapOptions['proxy_host'] = $CFG->proxyhost;
            $soapOptions['proxy_port'] = $CFG->proxyport;
        }
                    
         
        try {         
            // Get WSDL
            $soapclient = new SoapClient($CFG->ombielalertsserverendpoint.'?wsdl', $soapOptions);

            // Create request
            $request = array(
                'orgCode' => $CFG->ombielalertsorgcode,
                'password' => $CFG->ombielalertsorgpassword,
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

             // Trigger event for failing to send email but change error to show we mean the oMbiel Alerts system.
            $event = \core\event\email_failed::create(array(
                'context' => context_system::instance(),
                'userid' => $eventdata->userfrom->id,
                'relateduserid' => $eventdata->userto->id,
                'other' => array(
                    'subject' => 'oMbiel Alerts ',
                    'message' => $note,
                    'errorinfo' => 'Link to oMbiel Alerts system failed with error: '.$e->getMessage()
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
        return (!empty($CFG->ombielalertsserverendpoint) && 
                !empty($CFG->ombielalertsserversserverusername) && 
                !empty($CFG->ombielalertsserverpassword) && 
                !empty($CFG->ombielalertsorgcode) && 
                !empty($CFG->ombielalertsorgpassword)
                );
    }

}

