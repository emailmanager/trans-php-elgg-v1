<?php

  /**
   * emailmanager plugin
   * @license General Public License (GPL)
   * @author Michael Albert [monstermick@gmail.com]
   * @copyright Michael Albert 2011
   **/
  
  
  /** THe emailmanager plugin is using:
    * @category	Libraries
	 * @author      Based on work by János Rusiczki & Markus Hedlund’s.
	 * @modified    Heavily Modified by Zack Kitzmiller
	 * @link        http://www.github.com/zackkitzmiller/emailmanager-codeigniter
   **/   

  
    /**
     * initialize the plugin
     */    
	
	function emailmanagermailer_init() {
		global $CONFIG;
	    //register_page_handler('emailmanagermailer','emailmanagermailer_page_handler');
	    require_once $CONFIG->pluginspath . 'emailmanagermailer/models/emailmanager.php';
	    require_once $CONFIG->pluginspath . 'emailmanagermailer/models/model.php';
	    	     
    	run_function_once('emailmanagermailer_run_once');
    	
	    //register_notification_handler('email', 'emailmanagermailer_notify_handler');
	    
	    //no need of submenu
	    //no need of extended view
	    
		// call cron to send invites
		$period = get_plugin_setting('mailer_cron', 'emailmanagermailer');
		if ($period != 'disabled') {
			register_plugin_hook('cron', $period, 'emailmanager_cron');
		}
	
	}
	 
	register_elgg_event_handler('init', 'system', 'emailmanagermailer_init');
	    
    /**
     * Send a notification via email using phpmailer
     *
     * @param ElggEntity $from The from user/site/object
     * @param ElggUser $to To which user?
     * @param string $subject The subject of the message.
     * @param string $message The message body
     * @param array $params Optional parameters (not used)
     * @return bool
     */
    function emailmanagermailer_notify_handler(ElggEntity $from, ElggUser $to, $subject, $message, array $params = NULL) 
    {
      global $CONFIG;
	  $site = get_entity($CONFIG->site_guid);
	  $html = true;
	  
      if (!$from)
        throw new NotificationException(sprintf(elgg_echo('NotificationException:MissingParameter'), 'from'));
       
      if (!$to)
        throw new NotificationException(sprintf(elgg_echo('NotificationException:MissingParameter'), 'to'));
    
      if ($to->email=="")
        throw new NotificationException(sprintf(elgg_echo('NotificationException:NoEmailAddress'), $to->guid));      
  
	 
	  if ($params['html']) 
	  	$html = $params['html'];

      $to_name = '';
	  if (!($to->firstname && $to->lastname) ) {
	  	$to_name = $to->firstname . " " . $to->lastname;
	  }else{
	  	$to_name = $to->name;
	  } 
	  
	  emailmanager_send(null, null, $to->email, $to_name, $subject, $message, null, $html);	  	
	 }
	 
	 /**
	   * Send an email using emailmanagermailer
	   *
	   * @param string $from       From address 
	   * @param string $from_name  From name
	   * @param string $to         To address
	   * @param string $to_name    To name
	   * @param string $subject    The subject of the message.
	   * @param string $body       The message body
	   * @param array  $bcc        Array of address strings
	   * @param bool   $html       Set true for html email, also consider setting 
	   *                           altbody in $params array
	   * @param array  $files      Array of file descriptor arrays, each file array 
	   *                           consists of full path and name 
	   * @param array  $params     Additional parameters
	   * @return bool
	   */   
	
	  function emailmanager_send($from, $from_name, $to, $to_name, $subject, $body, array $bcc = NULL, $html = false, array $files = NULL, array $params = NULL){
	 	  
	  	global $CONFIG;
	  	$from_email = get_plugin_setting('from_email','emailmanagermailer');
                    $api_key = get_plugin_setting('api_key','emailmanagermailer');	
	
	      if (!$from_name)
	      	$from_name = get_plugin_setting('from_name','emailmanagermailer');

	      if (!$to && !$bcc)
        	throw new NotificationException(sprintf(elgg_echo('NotificationException:MissingParameter'), 'to'));  
	      
            if (!$subject)
        	throw new NotificationException(sprintf(elgg_echo('NotificationException:MissingParameter'), 'subject')); 
        	 
		  // if no different reply address is defined, we take the from address 
		  if (get_plugin_setting('reply_email','emailmanager_mailer'))
		 	 $reply_email = get_plugin_setting('reply_email','emailmanagermailer');
		  else 
		 	$reply_email = $from_email;
		  
		  if (get_plugin_setting('reply_name','emailmanager_mailer'))
		 	 $reply_name = get_plugin_setting('reply_name','emailmanagermailer');
		  else 
		 	$reply_name = $from_name;

		  if ($html){
			  $html_message = $body;
                          
                          if ($params['plaintext'])
                              $plain_message = $params['plaintext'];
			  else
                              $plain_message = strip_tags($body);
		  } else {
			 $plain_message =  $body;
			 $html_message = "";
		  }
		 $plain_message = trim($plain_message);
		 
		  $period = get_plugin_setting('mailer_cron', 'emailmanagermailer');		  
		  //if there is no cron, direct sending

		  if ($period == 'disabled') {
		  	$options = array (
			  	'api_key' => $api_key,
			  	'from_name' => $from_name,
			  	'from_address' => $from_email,
			  	'_reply_to_name' => $reply_email,
			  	'_reply_to_address' => $reply_name,
			  	'_to_name' => $to_name,
			  	'_to_address' => $to,
			  	'_subject' => $subject,
			  );	
		  	
		  	$emailmanager_mail = new Emailmanager($options);
		  	$emailmanager_mail->message_plain($plain_message);
			$emailmanager_mail->message_html($html_message);	
				
		  	$emailmanager_mail->send();
		  } else {
		  	$test = addslashes($html_message);
		  	$query  = "INSERT INTO elgg_emailmanagermailer_queue(from_address, from_name, to_address, to_name, subject, message_plain, message_html)";
		  	$query .= "VALUES (\"" . $from_email . "\",\"" . addslashes($from_name)  . "\",\"" . $to . "\",\"" . addslashes($to_name) . "\",\"" . addslashes($subject) . "\",\"" . addslashes($plain_message) . "\",\"" .  addslashes($html_message) . "\");";
		  	$return = insert_data($query);
		  	
		  	if (!$return){
		  		elgg_log('Emailmanager - error inserting the mail in the queue', 'ERROR');
		  	}
		  }
	 }
	 
	 /**
	 * Register the plugin settings.
	 * 
	 * @param -
	 * @return -
	 */
	function emailmanagermailer_run_once(){
		
		global $CONFIG;
		
		$path = $CONFIG->pluginspath . 'emailmanagermailer/emailmanagermailer_init.SQL';
		run_sql_script($path) ;	
		
	}
          
?>
