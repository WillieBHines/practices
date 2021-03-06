<?php

$link_email_sent_flag = false;

switch ($ac) {


	case 'link':
	
		Wbhkit\set_vars(array('email', 'display_name'));
	
		// if a user exists for that email, get it
		$u->set_by_email($email);

		// if failed, that was a bad email
		if (!$u->logged_in()) {
			$error = $u->error;
			$logger->debug($error);
			break;
		}

		// send log in link to that user
		if ($u->email_link()) {
			$message = "Thanks! I've sent a link to your {$u->fields['email']}. If you don't see it <a href='index.php'>click here to refresh the page</a> and try again.";
			$link_email_sent_flag = true;
			$u->soft_logout();
			$logger->debug($message);
			
		} else {
			$error = "I was unable to email a link to {$u->fields['email']}! Sorry.";
			$logger->debug($error);
		}
		
		break;		
	
	
	// log out	
	case 'lo':
		$logger->info("{$u->fields['nice_name']} logging out.");
		$u->hard_logout();
		header("Location: ".URL);
		die();
		break;	

	case 'cemail':
		Wbhkit\set_vars(array('newemail'));

		if (!$u->logged_in()) {
			$error = 'You are not logged in! You have to be logged in to change your email.';
			$logger->debug($error);
			break;
		}
		if (!$u->validate_email($newemail)) {
			$error = 'You asked to change your email but the new email \'$newemail\' is not a valid email';
			$logger->debug($error);
			break;
		}

		if ($u->change_email_phase_one($newemail)) {
			$message = "Okay, a link has been sent to the new email address ({$newemail}). Check your spam folder if you don't see it.";
			$logger->debug($message);
		} else {
			// if error, email already being used
			$error = $u->error;
			$logger->debug($error);
		}
		break;


	// update display name
	case 'updatedn':
		Wbhkit\set_vars(array('display_name'));
	
		if (!$u->logged_in()) {
			$error = 'You are not logged in! You have to be logged in to update your display name.';
			$logger->debug($error);
			break;
		}
		$message = "Changing display name to '$display_name' from '{$u->fields['display_name']}' for user {$u->fields['id']}";
		$logger->info($message);
		$u->update_display_name($display_name);
		break;		


	// update text preferences
	case 'updateu':

		Wbhkit\set_vars(array('carrier_id', 'phone', 'send_text'));

		if (!$u->logged_in()) {
			$error = 'You are not logged in! You have to be logged in to update your text preferences.';
			break;
		}
		$u->update_text_preferences($phone, $send_text, $carrier_id); 
		if ($u->error) {
			$error = $u->error;
		} else {
			$message = "Text preferences updated!";
		}
		break;		


	case 'concemail':
		if (!$u->logged_in()) {
			$error = 'You are not logged in! You have to be logged in to change your email.';
			$logger->debug($error);
			break;
		}
		
		$oldemail = $u->fields['email'];
		$newemail = $u->fields['new_email'];
		if ($u->user_finish_change_email()) {
			$message = "Email changed from '{$oldemail}' to '{$newemail}'";
			$logger->info($message);
		} else {
			// if error, email being used
			$error = $u->error;
			$logger->debug($error);
		}
		break;

}	


