<?php
$heading = 'improv practices';
$sc = "workshop.php";
include 'lib-master.php';


switch ($ac) {

	case 'enroll':
		if ($wk['cancelled']) {
			$error = 'This workshop has been cancelled.';
			$logger->debug("{$u['nice_name']} cannot enroll since {$wk['title']} is cancelled.");
			
			break;
		}	
		if (Users\logged_in()) {
			$message = Enrollments\handle_enroll($wk, $u);
			if (!$u['send_text']) {
				$message .= " Want notifications by text? <a  class='btn btn-primary' href='$sc?v=text'>Set your text preferences</a>.";	
			}
		} else {
			$error = "You must be logged in to enroll.";
			$logger->debug("attempted enroll with no one logged in.");
			
		}
		break;
		
	// request a drop (still must be confirmed)
	case 'drop':
		if (!Users\logged_in()) {
			$error = 'You are not logged in! You have to be logged in to drop a workshop.';
			$logger->debug("attempted drop with no one logged in.");
			
			break;
		}
		if ($wk['cancelled']) {
			$error = 'This workshop has been cancelled.';
			$logger->debug("{$u['nice_name']} tried to drop from {$wk['title']} but it's cancelled.");
			
			break;
		}
	
		if ($u) {
			if (Users\verify_key($key, $u['ukey'], $error)) {
								
				$message = "Do you really want to drop '{$wk['title']}'? Then click <a class='btn btn-warning' href=\"$sc?ac=condrop&uid={$u['id']}&wid={$wid}\">confirm drop</a>";
				
				$e = Enrollments\get_an_enrollment($wk, $u);
				if ($e['while_soldout']) { 
					$message .= '<br><br>'.Emails\get_dropping_late_warning();
				}
			}
		}
		break;
		
	// confirm drop
	case 'condrop':
		if (!Users\logged_in()) {
			$error = 'You are not logged in! You have to be logged in to drop a workshop.';
			break;
		}
		if ($wk['cancelled']) {
			$error = 'This workshop has been cancelled.';
			break;
		}
	
		$message = Enrollments\change_status($wk, $u, DROPPED, 1);
		$wk =  Workshops\get_workshop_info($wk['id']);
		Enrollments\check_waiting($wk);
		$message = "Dropped user ({$u['email']}) from practice '{$wk['title']}.'";
		break;	


		// accept an invite to a workshop
		case 'accept':
			if (!Users\logged_in()) {
				$error = 'You are not logged in! You have to be logged in to accept an invite.';
				$logger->debug($error);
			
				break;
			}
			if ($wk['cancelled']) {
				$error = 'Cannot accept invite. This workshop has been cancelled.';
				$logger->debug("Rejected invite for {$u['nice_name']} since {$wk['title']} is cancelled.");
			
				break;
			}
			$e = Enrollments\get_an_enrollment($wk, $u);
			if ($e['status_id'] == INVITED) {
				Enrollments\change_status($wk, $u, ENROLLED, 1);
				Enrollments\check_waiting($wk);
				$message = "You are now enrolled in '{$wk['title']}'! Info emailed to <b>{$u['email']}</b>.";
			
			} else {
				$error = "You tried to accept an invitation to '{$wk['title']}', but I don't see that there is an open spot.";
				$logger->debug("Rejected invite for {$u['nice_name']} since {$wk['title']} is full.");
			}
			break;

		case 'decline':
			if (!Users\logged_in()) {
				$error = 'You are not logged in! You have to be logged in to decline an invite.';
				$logger->debug($error);
				break;
			}
			if ($wk['cancelled']) {
				$error = 'This workshop has been cancelled.';
				$logger->debug("Rejected decline for {$u['nice_name']} since {$wk['title']} is cancelled.");
				break;
			}
	
			$e = Enrollments\get_an_enrollment($wk, $u);
			if ($e['status_id'] == INVITED) {
				Enrollments\change_status($wk, $u, DROPPED, 1);
				Enrollments\check_waiting($wk);
				$message = "You have dropped out of the waiting list for '{$wk['title']}'.";			
			} else {
				$error = "You tried to decline an invitation to '{$wk['title']}', but I don't see that there was an open spot.";
				$logger->debug("Rejected decline for {$u['nice_name']} since {$wk['title']} is full.");
			
			}
			break;

}


// maybe check the $wk or $wk['id'] here?

if (isset($wk) && isset($wk['id']) && $wk['id']) {
	$wk = Workshops\fill_out_workshop_row($wk);
	$view->data['e'] = Enrollments\get_an_enrollment($wk, $u);
	$view->data['workshop_tabled'] = Workshops\get_workshop_info_tabled($wk);
	$view->data['admin'] = 0;
	$view->renderPage('winfo');
} else {
	$view->data['error_message'] = "<h1>Whoops!</h1><p>You are asking to look at info about a workshop, but I (the computer) cannot tell which workshop you mean. Sorry!</p>\n";
	$view->renderPage('error');
}
