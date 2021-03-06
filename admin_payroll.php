<?php
$heading = "payroll";
include 'lib-master.php';

$u->reject_user_below(3); // group 3 or higher

$ph = new PayrollsHelper();

$vars = array('searchstart', 'searchend', 'lastweekstart', 'lastweekend', 'nextweekstart', 'nextweekend', 'pid', 'singleadd', 'task', 'table_id', 'teacher_id', 'amount', 'when_paid', 'when_happened');
Wbhkit\set_vars($vars);

switch ($ac) {
	
	case 'del':
		$p = new Payroll();
		$p->fields['id'] = $pid;
		$p->delete_row();
		break;
	case 'singleadd':
		$ph->add_claim($task, $table_id, $teacher_id, $amount, $when_paid, $when_happened);
		break;
	case 'add':


		$payroll_data = array();
		foreach ($_REQUEST as $k => $v) {
			if (substr($k, 0, 3) == 'pd_') {
				$ps = explode('_', $k);
				$payroll_data["{$ps[1]}-{$ps[2]}"]['task'] = $ps[1];
				$payroll_data["{$ps[1]}-{$ps[2]}"]['tableid'] = $ps[2];
				$payroll_data["{$ps[1]}-{$ps[2]}"][$ps[3]] = $v;
			}
		}
		//print_r($payroll_data);
		foreach ($payroll_data as $id => $item) {
			$ph->add_claim($item['task'], $item['tableid'], $item['teacherid'], $item['amount'], $item['whenpaid'], $item['whenhappened']);
		}
		break;
		
						
}


// search defaults to current week
if (!$searchstart && !$searchend) {
	$searchstart = (date("l") == 'Sunday' ? 'today' : 'last Sunday');
	$searchend = (date("l") == 'Saturday' ? 'today' : 'next Saturday');
}

if ($searchstart) { $searchstart = date('Y-m-d 00:00:00', strtotime($searchstart)); }
if ($searchend) { $searchend = date('Y-m-d 23:59:59', strtotime($searchend)); }

$lastweekstart = change_date_string($searchstart, '-7 days');
$lastweekend = change_date_string($searchend, '-7 days');
$nextweekstart = change_date_string($searchstart, '+7 days');
$nextweekend = change_date_string($searchend, '+7 days');


$view->add_globals($vars);	

$view->data['payrolls'] = $ph->get_payrolls($searchstart, $searchend);
$view->data['claims'] = $ph->get_claims($searchstart, $searchend);
$view->data['searchstart'] = $searchstart;
$view->data['searchend'] = $searchend;


$view->renderPage('admin/payroll');


function change_date_string($timestring, $change) {
	$lastweek = date_create($timestring);
	date_modify($lastweek, $change);
	return date_format($lastweek, 'Y-m-d');
}

function get_table_id(array $ps) {
	
	$table = '';
	$id = '';
	
	if ($ps[3]) {
		$table = 'shows';
		$id = $ps[3];
	} elseif ($ps[2]) {
		$table = 'xtra_sessions';
		$id = $ps[2];
	} else {
		$table = 'workshops';
		$id = $ps[1];
	}
	return array($table, $id);
	
}

