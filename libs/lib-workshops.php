<?php
namespace Workshops;
	
// workshops
function get_workshop_info($id) {
	$statuses = \Lookups\get_statuses();
	$locations = \Lookups\get_locations();
	
	$stmt = \DB\pdo_query("select w.* from workshops w where w.id = :id", array(':id' => $id));
	while ($row = $stmt->fetch()) {
		$row = fill_out_workshop_row($row);
		return $row;

	}
	return false;
}

function fill_out_workshop_row($row) {
	$locations = \Lookups\get_locations();
	
	foreach (array('address', 'city', 'state', 'zip', 'place', 'lwhere') as $loc_field) {
		$row[$loc_field] = $locations[$row['location_id']][$loc_field];
	}
		
	if ($row['when_public'] == 0 ) {
		$row['when_public'] = '';
	}
	$row = format_workshop_startend($row);	
	
	if (strtotime($row['start']) < strtotime('now')) { 
		$row['type'] = 'past'; 
	} else {
		$row['type'] = null;
	}
	
	$row['costdisplay'] = $row['cost'] ? $row['cost'] : 'Pay what you can / donation';
	
	$row['sessions'] = \XtraSessions\get_xtra_sessions($row['id']);	
	
	// when is the next starting sessions
	// if all are in past, set this to most recent one
	$row['nextstart'] = $row['start'];
	$row['nextend'] = $row['end'];
	if (!is_future($row['nextstart'])) {
		foreach ($row['sessions'] as $s) {
			if (is_future($s['start'])) {
				$row['nextstart'] = $s['start'];
				$row['nextend'] = $s['end'];
				break; // found the next start
			}
		}
	}
	
	$row = set_enrollment_stats($row);
	$row = set_workshop_type($row);
	$row = check_last_minuteness($row);
	
	return $row;
	
}


// used in fill_out_workshop_row and also get_sessions_to_come
// expects 'id' and 'capacity' to be set
function set_enrollment_stats($row) {
	$statuses = \Lookups\get_statuses();
	
	$enrollments = \Enrollments\get_enrollments($row['id']);
	foreach ($statuses as $sid => $sname) {
		$row[$sname] = $enrollments[$sid];
	}	
	$row['attended'] = how_many_attended($row);
	$row['open'] = ($row['enrolled'] >= $row['capacity'] ? 0 : $row['capacity'] - $row['enrolled']);
	return $row;
}

function set_workshop_type($row) {
	
	if (strtotime($row['start']) < strtotime('now')) { 
		$row['type'] = 'past'; 
	} elseif ($row['enrolled'] >= $row['capacity'] || $row['waiting'] > 0 || $row['invited'] > 0) { 
		$row['type'] = 'soldout'; 
	} else {
		$row['type'] = 'open';
	}
	return $row;
}


function check_last_minuteness($wk) {
	
	/* 
		there's two flags:
			1) workshops have "sold_out_late" meaning the workshop was sold out within LATE_HOURS of the start. We update this to 1 or 0 everytime the web site selects the workshop info from the db.
			2) registrations have a "while_sold_out" flag. if it is set to 1, then you were enrolled in this workshop while it was sold_out_late (i.e. sold out within $late_hours of its start). we also check this every time we select the workshop info. but this never gets set back to zero. 
			If a "while sold out" person drops, that's not cool. They held a spot during a sold out time close to the start of the workshop.
	*/ 
			
	$hours_left = (strtotime($wk['start']) - strtotime('now')) / 3600;
	if ($hours_left > 0 && $hours_left < LATE_HOURS) {
		// have we never checked if it's sold out
		if ($wk['sold_out_late'] == -1) {
			if ($wk['type'] == 'soldout') {
				
				$stmt = \DB\pdo_query("update workshops set sold_out_late = 1 where id = :wid", array(':wid' => $wk['id']));				

				$stmt = \DB\pdo_query("update registrations set while_soldout = 1 where workshop_id = :wid and status_id = '".ENROLLED."'", array(':wid' => $wk['id']));
				
				$wk['sold_out_late'] = 1;
			} else {

				$stmt = \DB\pdo_query("update workshops set sold_out_late = 0 where id = :wid", array(':wid' => $wk['id']));

				$wk['sold_out_late'] = 0;
			}
		}
	}
	return $wk;
}


function get_workshop_info_tabled($wk) {
	
	global $view;
	$stds = \Enrollments\get_students($wk['id']);

	$snames = array();
	foreach ($stds as $s) {
		if ($s['display_name']) {
			$snames[] = $s['display_name'];
		}
	}
	$known = count($snames);
	
	$names_list = '';
	if ($known) {
		natcasesort($snames);
		$names_list = implode("<br>", $snames);
		if ($known < $wk['enrolled']) {
			$names_list .= "<br>plus ".($wk['enrolled']-$known)." more.";
		}
		$names_list = "<tr><td scope=\"row\">Currently Registered:</td><td>{$names_list}</td></tr>";
	}
	$view->data['names_list'] = $names_list;
	return $view->renderSnippet('workshop_info');
}

function get_workshops_dropdown($start = null, $end = null) {
	
	$locations = \Lookups\get_locations();
	$stmt = \DB\pdo_query("select w.* from workshops w order by start desc");
	$workshops = array();
	while ($row = $stmt->fetch()) {
		$row = format_workshop_startend($row);
		$workshops[$row['id']] = $row['title'];
	}
	return $workshops;
}

// pass in the workshop row as it comes from the database table
// add some columns with date / time stuff figured out
function format_workshop_startend($row) {
	$row['showstart'] = friendly_date($row['start']).' '.friendly_time($row['start']);
	$row['showend'] = friendly_time($row['end']);
	if ($row['cancelled']) {
		$row['title'] = "CANCELLED: {$row['title']}";
	}
	$row['when'] = "{$row['showstart']}-{$row['showend']}";
		
	return $row;
}

function get_workshops_list($admin = 0, $page = 1) {
	
	global $view;
	
	// get IDs of workshops
	$sql = 'select w.* from workshops w ';
	if ($admin) {
		$sql .= " order by start desc"; // get all
	} else {
		$mysqlnow = date("Y-m-d H:i:s");
		$sql .= "where when_public < '$mysqlnow' and date(start) >= date('$mysqlnow') order by start asc"; // get public ones to come
	}
		
	// prep paginator
	$paginator  = new \Paginator( $sql );
	$rows = $paginator->getData($page);
	$links = $paginator->createLinks();

	// calculate enrollments, ranks, etc
	if ($rows->total > 0) {
		$workshops = array();
		foreach ($rows->data as $row ) {
			$workshops[] = fill_out_workshop_row($row);
		}
	} else {
		return "<p>No upcoming workshops!</p>\n";	// this skips $body variable contents	
	}
		
	// prep view
	$view->data['links'] = $links;
	$view->data['admin'] = $admin;
	$view->data['rows'] = $workshops;
	return $view->renderSnippet('workshop_list');
}


// data only, for admin_calendar
function get_sessions_to_come() {
	
	// get IDs of workshops
	$mysqlnow = date("Y-m-d H:i:s", strtotime("-3 hours"));
	
	$stmt = \DB\pdo_query("
(select id, title, start, end, capacity from workshops where date(start) >= date('$mysqlnow'))
union
(select x.workshop_id, w.title, x.start, x.end, w.capacity from xtra_sessions x, workshops w where w.id = x.workshop_id and date(x.start) >= date('$mysqlnow'))
order by start asc"); 
		
	$sessions = array();
	while ($row = $stmt->fetch()) {
		$sessions[] = set_enrollment_stats($row);
	}
	return $sessions;
}


function how_many_attended($wk) {
	$stmt = \DB\pdo_query("select count(*) as total_attended from registrations where workshop_id = :wid and attended = 1", array(':wid' => $wk['id']));
	while ($row = $stmt->fetch()) {
		return $row['total_attended'];
	}
	return 0;
}

function get_workshops_list_bydate($start = null, $end = null) {
	if (!$start) { $start = "Jan 1 1000"; }
	if (!$end) { $end = "Dec 31 9000"; }
	
	$stmt = \DB\pdo_query("select w.* from workshops w WHERE w.start >= :start and w.end <= :end order by start desc", array(':start' => date('Y-m-d H:i:s', strtotime($start)), ':end' => date('Y-m-d H:i:s', strtotime($end))));
	
	$workshops = array();
	while ($row = $stmt->fetch()) {
		$workshops[$row['id']] = fill_out_workshop_row($row);
	}
	return $workshops;
}	

function get_empty_workshop() {
	return array(
		'id' => null,
		'title' => null,
		'location_id' => null,
		'online_url' => null,
		'start' => null,
		'end' => null,
		'cost' => null,
		'capacity' => null,
		'notes' => null,
		'revenue' => null,
		'expenses' => null,
		'when_public' => null,
		'sold_out_late' => null,
		'cancelled' => null
	);
}

function add_workshop_form($wk) {
	global $sc;
	return "<form id='add_wk' action='$sc' method='post' novalidate>".
	\Wbhkit\form_validation_javascript('add_wk').
	"<fieldset name='session_add'><legend>Add Session</legend>".
	\Wbhkit\hidden('ac', 'ad').
	workshop_fields($wk).
	\Wbhkit\submit('Add').
	"</fieldset></form>";
	
}

function workshop_fields($wk) {
	return \Wbhkit\texty('title', $wk['title'], null, null, null, 'Required', ' required ').
	\Lookups\locations_drop($wk['location_id']).
	\Wbhkit\texty('online_url', $wk['online_url'], 'Online URL').	
	\Wbhkit\texty('start', $wk['start'], null, null, null, 'Required', ' required ').
	\Wbhkit\texty('end', $wk['end'], null, null, null, 'Required', ' required ').
	\Wbhkit\texty('cost', $wk['cost']).
	\Wbhkit\texty('capacity', $wk['capacity']).
	\Wbhkit\textarea('notes', $wk['notes']).
	\Wbhkit\texty('revenue', $wk['revenue']).
	\Wbhkit\texty('expenses', $wk['expenses']).
	\Wbhkit\checkbox('cancelled', 1, null, $wk['cancelled']).	
	\Wbhkit\texty('when_public', $wk['when_public'], 'When Public');
	
}

// $ac can be 'up' or 'ad'
function add_update_workshop($wk, $ac = 'up') {
	
	global $last_insert_id;
	
	// fussy MySQL 5.7
	if (!$wk['cancelled']) { $wk['cancelled'] = 0; }
	if (!$wk['revenue']) { $wk['revenue'] = 0; }
	if (!$wk['expenses']) { $wk['expenses'] = 0; }
	if (!$wk['cost']) { $wk['cost'] = 0; }
	if (!$wk['capacity']) { $wk['capacity'] = 0; }
	if (!$wk['when_public']) { $wk['when_public'] = NULL; }
	if (!$wk['start']) { $wk['start'] = NULL; }
	if (!$wk['end']) { $wk['end'] = NULL; }
	
	
		
	$params = array(':title' => $wk['title'],
		':start' => date('Y-m-d H:i:s', strtotime($wk['start'])),
		':end' => date('Y-m-d H:i:s', strtotime($wk['end'])),
		':cost' => $wk['cost'],
		':capacity' => $wk['capacity'],
		':lid' => $wk['location_id'],
		':online_url' => $wk['online_url'],
		':notes' => $wk['notes'],
		':revenue' => $wk['revenue'],
		':expenses' => $wk['expenses'],
		':public' => date('Y-m-d H:i:s', strtotime($wk['when_public'])),
		':cancelled' => $wk['cancelled']);
		
		if ($ac == 'up') {
			$params[':wid'] = $wk['id'];
			$stmt = \DB\pdo_query("update workshops set title = :title, start = :start, end = :end, cost = :cost, capacity = :capacity, location_id = :lid, online_url = :online_url, notes = :notes, revenue = :revenue, expenses = :expenses, when_public = :public, cancelled = :cancelled where id = :wid", $params);
			return $wk['id'];
		} elseif ($ac = 'ad') {
			$stmt = \DB\pdo_query("insert into workshops (title, start, end, cost, capacity, location_id, online_url, notes, revenue, expenses, when_public, cancelled)
			VALUES (:title, :start, :end, :cost, :capacity, :lid, :online_url, :notes, :revenue, :expenses, :public, :cancelled)",
			$params);
			return $last_insert_id; // set as a global by my dbo routines
		}
	
}

function delete_workshop($workshop_id) {
	$stmt = \DB\pdo_query("delete from registrations where workshop_id = :wid", array(':wid' => $workshop_id));
	$stmt = \DB\pdo_query("delete from xtra_sessions where workshop_id = :wid", array(':wid' => $workshop_id));
	$stmt = \DB\pdo_query("delete from workshops where id = :wid", array(':wid' => $workshop_id));

}

/*
* time date functions
*/

function friendly_time($time_string) {
	$ts = strtotime($time_string);
	$minutes = date('i', $ts);
	if ($minutes == 0) {
		return date('ga', $ts);
	} else {
		return date('g:ia', $ts);
	}
}

function friendly_date($time_string) {
	$now_doy = date('z'); // day of year
	$wk_doy = date('z', strtotime($time_string)); // workshop day of year

	if ($wk_doy - $now_doy < 7) {
		return date('l', strtotime($time_string)); // Monday, Tuesday, Wednesday
	} elseif (date('Y', strtotime($time_string)) != date('Y')) {  
		return date('D M j, Y', strtotime($time_string));
	} else {
		return date('D M j', strtotime($time_string));
	}
}	

function friendly_when($time_string) {
	return friendly_date($time_string).' '.friendly_time($time_string);
}

function is_future($time_string) {
	if (strtotime($time_string) > strtotime('now')) {
		return 1;
	} else {
		return 0;
	}
}

