<?php

class Enrollment extends WBHObject {
	
	public array $fields;	
	public User $u;
	public array $wk;
	
	
	function __construct() {		
		parent::__construct(); // load logger, lookups

		$this->fields = array(
				'id' => false,
				'user_id' => false,
				'workshop_id' => false,
				'status_id' => false,
				'paid' => false,
				'registered' => false, 	
				'last_modified' => false,
				'while_soldout' => false);	
			
		$this->u = new User();
		$this->wk = array();			

	}

	function set_by_id(int $enrollment_id) {
	
		$stmt = \DB\pdo_query("select r.* from registrations r where r.id = :eid", array(':eid' => $enrollment_id));

		while ($row = $stmt->fetch()) {
			return $this->finish_fields($row);
		}
		$this->error = "No enrollment found for enrollment id '{$enrollment_id}'";
		return false;

	}

	function set_by_uid_wid(int $uid, int $wid) {
		$stmt = \DB\pdo_query("select r.* from registrations r where r.workshop_id = :wid and r.user_id = :uid", array(':wid' => $wid, ':uid' => $uid));

		while ($row = $stmt->fetch()) {
			return $this->finish_fields($row);
		}
		$this->error = "No enrollment found for uid/wid '$uid' / '$wid'";
		return false;
		
	}

	function set_by_u_wk(User $u, array $wk) {
		$this->u = $u;
		$this->wk =$wk;
		return $this->set_by_uid_wid($u->fields['id'], $wk['id']);
	}


	function finish_fields($row) {
		$this->replace_fields($row);
		$this->fields['status_name'] = $this->lookups->statuses[$row['status_id']];
		$this->figure_rank();
		return $this->fields['id'];
	}
	
	function figure_rank() {
								
		// check rank
		if ($this->fields['status_id'] == WAITING) {
			$stmt2 = \DB\pdo_query("select r.* from registrations r where r.workshop_id = :wid and r.status_id = :sid order by last_modified", array(':wid' => $this->fields['workshop_id'], ':sid' => WAITING));
			$i = 1;
			while ($row2 = $stmt2->fetch()) {
				if ($row2['id'] == $this->fields['id']) {
					break;
				}
				$i++;
			}
			$this->fields['rank'] = $i;
		} else {
			$this->fields['rank'] = null;
		}
		return $this->fields['rank'];
	}

	function reset_user_and_workshop() {
		if (isset($this->fields['user_id']) && $this->fields['user_id']) {
			$this->u->set_by_id($this->fields['user_id']);
		}
		if (isset($this->fields['workshop_id']) && $this->fields['workshop_id']) {
			$this->wk = \Workshops\get_workshop_info($this->fields['workshop_id']);
		}
	}
	
	// assumes we have set u and wk
	function change_status(int $status_id = ENROLLED, bool $confirm = true) {
		
		if (!$this->u->fields['id']) { 
			$this->error = "No user set!";
			return false;
		}
		
		if (!$this->wk['id']) {
			$this->error = "No workshop set!";
			return false;
		}

		$statuses = $this->lookups->statuses;
		
		//update or insert?
		if ($this->fields['id']) { // update
			// are we changing a status or nah
			if ($this->fields['status_id'] != $status_id) {
		
				$stmt = \DB\pdo_query("update registrations set status_id = :status_id,  last_modified = '".date("Y-m-d H:i:s")."' where workshop_id = :wid and user_id = :uid", array(':status_id' => $status_id, ':wid' => $this->fields['workshop_id'], ':uid' => $this->fields['user_id']));
		
				$this->fields['status_id'] = $status_id;
			} else { // no need to update
				return $this->message = "User ({$this->u->fields['email']}) was already status '{$statuses[$status_id]}' for {$this->wk['title']}.";
			}
		} else { // insert
			$stmt = \DB\pdo_query("INSERT INTO registrations (workshop_id, user_id, status_id, registered, last_modified) VALUES (:wid, :uid, :status_id, '".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."')", array(':wid' => $this->wk['id'], ':uid' => $this->u->fields['id'], ':status_id' => $status_id));
			$this->set_by_id($_GLOBALS['last_insert_id']);
		}
		
		$this->update_change_log($status_id);	
		if ($confirm) { 
			\Emails\confirm_email($this->wk, $this->u, $status_id); 
		}
		return $this->message = "Updated user ({$this->u->fields['email']}) to status '{$statuses[$status_id]}' for {$this->wk['title']}.";

	}

	function update_change_log($status_id) {
	
		$statuses = $this->lookups->statuses;
	
		$stmt = \DB\pdo_query("insert into status_change_log (workshop_id, user_id, status_id, happened) VALUES (:wid, :uid, :status_id, '".date('Y-m-d H:i:s', time())."')", 
		array(':wid' => $this->fields['workshop_id'],
		':uid' => $this->fields['user_id'],
		':status_id' => $status_id));
	
		$this->logger->info("{$this->u->fields['fullest_name']} is now '{$statuses[$status_id]}' for '{$this->wk['title']}'");
	
		return true;
	}


	// this checks for open spots, and makes sure invites have gone out to anyone on waiting list
	// i call this in places just to make sure i haven't neglected the waiting list
	function check_waiting(array $wk) {
		$wk = \Workshops\fill_out_workshop_row($wk); // make sure it's up to date
		$msg = '';
		if ($wk['upcoming'] == 0) {
			return 'Workshop is in the past';
		}
		while (($wk['enrolled']+$wk['invited']) < $wk['capacity'] && $wk['waiting'] > 0) {
		
			$stmt = \DB\pdo_query("select * from registrations where workshop_id = :wid and status_id = '".WAITING."' order by last_modified limit 1", array(':wid' => $wk['id']));
		
			while ($row = $stmt->fetch()) {
				$this->set_by_uid_wid($row['user_id'], $wk['id']);
				$this->reset_user_and_workshop();
				$msg .= $this->change_status(INVITED, true);
				
				//adjust our totals so we don't get caught infinite loop!
				$wk['invited']++;
				$wk['waiting']--;
			}
		}
		if ($msg) { 
			return $this->message = $msg;
		}
		return $this->message = "No invites sent.";
	}

	function update_paid(int $wid = null, int $uid = null, int $paid = 1, int $eid = null) {
		
		// set enrollment fields, user, workshop	
		if ($eid) {
			$this->set_by_id($eid);
		} else {
			$this->set_by_uid_wid($wid, $uid);
		}
		$this->reset_user_and_workshop();
	
		// get paid status before
		$paid_before = $this->fields['paid'];
	
		if ($paid != $paid_before) {
			$stmt = \DB\pdo_query("update registrations set paid = :paid where id = :rid", array(':paid' => $paid, ':rid' => $this->fields['id']));
			$this->fields['paid'] = $paid;
			
			// send payment confirmation
			if ($paid == 1) {
		
				$body = "<p>This is automated email to confirm that I've received your payment for class.</p>";
				$body .= "<p>Class: {$this->wk['title']} {$this->wk['showstart']} (California time, PST)</p>\n";
				$body .= "<p>Student: {$this->u->fields['nice_name']}</p>";
				$body .= "<p>Amount: \${$this->wk['cost']} (USD)</p>\n";
				$body .= "<p>Thanks!<br>-Will</p>\n";
				
				\Emails\centralized_email($this->u->fields['email'], "Payment received for {$this->wk['title']} {$this->wk['showstart']} (PDT)", $body); 
			
				return $this->message = "Set user '{$this->u->fields['nice_name']}' to 'paid' for workshop '{$this->wk['title']}'";
			} else {
				return $this->message = "Set user '{$this->u->fields['nice_name']}' to 'unpaid' for workshop '{$this->wk['title']}'";
			}
		
		}
		return false; // no update needed
	
	}


	function drop_session() {	
		if (!isset($this->fields['id']) || !$this->fields['id']) {
			return false;
		}
		$this->update_change_log(DROPPED); // really should be a new status like "REMOVED" 
		$stmt = \DB\pdo_query('delete from registrations where id = :eid', array(':eid' => $this->fields['id']));	
		$this->check_waiting($this->wk);
		return true;
	}	

}
	