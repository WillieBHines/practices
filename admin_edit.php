<?php
$sc = "admin_edit.php";
$heading = "practices: admin edit";
include 'lib-master.php';


$wk_vars = array('wid', 'title', 'notes', 'start', 'end', 'lid', 'online_url', 'cost', 'capacity', 'notes', 'revenue', 'expenses', 'when_public', 'email', 'con', 'cancelled', 'xtraid', 'class_show', 'guest_id', 'reminder_sent', 'sold_out_late', 'teacher_id');
Wbhkit\set_vars($wk_vars);


$guest = array(); // the user we're going to change
if ($guest_id > 0) {
	$guest = Users\get_user_by_id($guest_id, 0); // second parameter means "don't save this in the cookie"
}



switch ($ac) {


	case 'sar':
		Emails\remind_enrolled($wk);
		$message = "Reminders sent to enrolled.";
		break;

	case 'up':
	case 'ad':
	
		if ($ac == 'ad' && !$title) {
			$error = 'Must include a title for new workshop.';
			$view->renderPage('admin_error');
			exit();
		}

		// build a workshop array from data we have
		$id = $wid ? $wid : null; // so the next bit can find the id
		$location_id = $lid;
		$wk_fields = \Workshops\get_empty_workshop();
		foreach ($wk_fields as $field => $fieldvalue) {
			$wk[$field] = $$field;
		}
	
		$wid = Workshops\add_update_workshop($wk, $ac);
		
		// fill out $wk array
		$wk = Workshops\fill_out_workshop_row($wk);
		
		if ($ac == 'up') {
			$message = "Updated practice ({$wid}) - {$wk['title']}";
			$logger->info($message);
		} elseif ($ac == 'ad') {
			$message = "Added practice ({$title})";
			$logger->info($message);
		}
		break;
		
	case 'cw':
		$message = Enrollments\check_waiting($wk);
		break;

	case 'conrem':
		Enrollments\drop_session($wk, $guest);
		$message = "Removed user ({$guest['email']}) from practice '{$wk['title']}'";
		$logger->info($message);
		break;

	case 'enroll':
		Wbhkit\set_vars(array('email', 'con'));
		$guest = Users\get_user_by_email($email);
		$message = Enrollments\handle_enroll($wk, $guest, $con); 
		break;

	// initially called in admin_student.php
	// but it comes here to finish the job
	case 'delstudentconfirm':
		Users\delete_student($guest['id']);
		break;

	
	case 'cdel':
		$error = "Are you sure you want to delete '{$wk['title']}'? <a class='btn btn-danger' href='admin.php?ac=del&wid={$wk['id']}'>delete</a>";
		break;

		
	case 'adxtra':	
		XtraSessions\add_xtra_session($wid, $start, $end, $class_show);
		$wk = Workshops\fill_out_workshop_row($wk);
		break;
		
	case 'delxtra':
		XtraSessions\delete_xtra_session($xtraid);
		$wk = Workshops\fill_out_workshop_row($wk);
		break;
		
		
	case 'at':
		if (isset($_REQUEST['users']) && is_array($_REQUEST['users']) && $wid) {
			$users = $_REQUEST['users'];
			foreach ($statuses as $sid => $sts) {
				$stds = Enrollments\get_students($wid, $sid);
				foreach ($stds as $as) {
					if (in_array($as['id'], $users)) {
						Enrollments\update_paid($wid, $as['id'], 1);
					} else {
						Enrollments\update_paid($wid, $as['id'], 0);
					}
				}
			}		
		}
							
}

if (!$wid) {
	$view->data['error_message'] = "<h1>Whoops!</h1><p>You are asking to look at info about a workshop, but I (the computer) cannot tell which workshop you mean. Sorry!</p>\n";
	$view->renderPage('admin_error');
	exit();
}


$stats = array();
$lists = array();
foreach ($statuses as $stid => $status_name) {
	$stats[$stid] = count(Enrollments\get_students($wid, $stid));
	$lists[$stid] = Enrollments\get_students($wid, $stid);
}
$data['log'] = Enrollments\get_status_change_log($wk);
$status_log = $view->renderSnippet('admin_status', $data);

$view->add_globals(array('stats', 'statuses', 'lists', 'status_log'));	
$view->renderPage('admin_edit');




