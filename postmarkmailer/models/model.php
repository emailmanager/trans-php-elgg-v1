<?php

/**
 * Elevate user to admin.
 *
 * @param bool $unsu -- Return to original permissions? 
 * @return old 
 */
function emailmanagermailer_su($unsu=false) {
	global $is_admin;
	static $is_admin_orig = null; 
	
	if (is_null($is_admin_orig)) {
		$is_admin_orig = $is_admin;
	}
	
	if ($unsu) {
		return $is_admin = $is_admin_orig;
	} else {
		return $is_admin = true;
	}
}


function emailmanager_cron() {
	
	global $CONFIG;
	emailmanagermailer_su();
	$is_running = get_plugin_setting('running', 'emailmanagermailer');	
	
	if (!$is_running)
		//no instance is running
		set_plugin_setting('running', true, 'emailmanagermailer');	
	else {
		// an instance is already running
		return false;	
	}
	
	$period = get_plugin_setting('mailer_cron', 'emailmanagermailer');		  
	$api_key = get_plugin_setting('api_key','emailmanagermailer');	
	$max = get_plugin_setting('send_limit', 'emailmanagermailer');
	$attempt_max = 3;
	if (get_plugin_setting('reply_email','emailmanager_mailer'))
	 	$reply_email = get_plugin_setting('reply_email','emailmanagermailer');
	else 
		$reply_email = $from_email;
	  
	if (get_plugin_setting('reply_name','emailmanager_mailer'))
	 	$reply_name = get_plugin_setting('reply_name','emailmanagermailer');
	else 
		$reply_name = $from_name;

		
		
	// avoid OOM errors on very high number of messages by only
	// processing 25 at a time.
	$limit = 25;
	$count = 0;
	$offset = 0;
	
	set_context('emailmanager_cron');
   	//admin_init();
   	
	$has_mail_to_send = true;
	
	while($has_mail_to_send){
		//get mails to send
		$query = "SELECT id, from_address, from_name, to_address, to_name, subject, message_plain, message_html, send_try, is_sent";
		$query .= " FROM elgg_emailmanagermailer_queue";
		$query .= " WHERE is_sent = 0 and is_fail = 0";
		$query .= " ORDER BY id DESC";
		$query .= " LIMIT ". $limit . " OFFSET " . $offset;
		
		$row_data = get_data($query);
		
		if ($row_data){
			//send the emails
			
			$sent_mail_id = array();
			foreach($row_data as $mail){
				
				if(!$reply_email) 
					$reply_email = $mail->from_address;
				
				if(!$reply_name) 
					$reply_name = $mail->from_name;
					
				$options = array (
				  	'api_key' => $api_key,
				  	'from_name' => $mail->from_name,
				  	'from_address' => $mail->from_address,
				  	'_reply_to_name' => $reply_name,
				  	'_reply_to_address' => $reply_email,
				  	'_to_name' => $mail->to_name,
				  	'_to_address' => $mail->to_address,
				  	'_subject' => $mail->subject,
				  );	
			  	
			  	$emailmanager_mail = new Emailmanager($options);
				$emailmanager_mail->message_plain($mail->message_plain);
				$emailmanager_mail->message_html($mail->message_html);		
			  	$return = $emailmanager_mail->send();
			  	
		  		$mail->send_try += 1;
			  	
		  		if($return){
			  		$mail->is_sent = 1;
			  		$sent_mail_id[] = $mail->id;
			  	} elseif ($mail->send_try >= $attempt_max) {
		  			$mail->is_fail = 1; 			
		  			$result_query = "UPDATE elgg_emailmanagermailer_queue";
		  			$result_query .= " SET is_sent = " . $mail->is_sent . ", send_try = " . $mail->send_try . ", is_fail = " . $mail->is_fail . ";" ;
			  		$query_result = update_data($result_query);
			  	
				  	if(!$query_result){
				  		$error_message = "The mail with id " . $mail->id . "was not correctly updated in db : is_sent = ". $mail->is_sent .", is_fail = " . $mail->is_sent . ".";
				  		elgg_log($error_message, 'ERROR');	
				  	}
			  	}
	
			}

			// All retrieve mails were processed ==> now we delete them from table
			
	  	    $result_query = "DELETE e.* FROM elgg_emailmanagermailer_queue e";
	  		$result_query .= " WHERE id in ( " .  implode(',', $sent_mail_id) .");";
	  		$query_result = delete_data($result_query);
			if(!$query_result){
		  		$error_message = "The mail with id " . $mail->id . "was not correctly updated in db : is_sent = ". $mail->is_sent .", is_fail = " . $mail->is_sent . ".";
		  		elgg_log($error_message, 'ERROR');	
		  	}
		} else {
			$has_mail_to_send = false;
		}
	}
	emailmanagermailer_su(true);
	
	set_plugin_setting('running', false, 'emailmanagermailer');	
	
	return $result;
}
	
?>