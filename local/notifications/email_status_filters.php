<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package
 * @author     eAbyas Info Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG, $USER, $PAGE, $OUTPUT,$DB;

if ($_REQUEST['columns'][1]['search'] != "" ){
   $organization_search=$_REQUEST['columns'][1]['search']['value'] ;
}
if ( $_REQUEST['columns'][2]['search'] != "" ){
      $status_search=$_REQUEST['columns'][2]['search']['value'] ;
}


$countquery="SELECT count(le.id) FROM {local_emaillogs} AS le INNER JOIN {local_notification_info} AS ni ON le.notification_infoid=ni.id where 1=1 ";
$select_query = "SELECT le.id,le.notification_infoid,le.from_userid,le.to_userid,le.status,le.admissionid,
	le.timecreated,le.sent_date,ni.costcenterid,ni.notificationid, 
	concat(u.firstname,' ', u.lastname) AS to_username,
	(SELECT concat(u.firstname,' ', u.lastname) FROM {user} as u WHERE u.id=le.from_userid) as from_username, lnt.name AS notification_type, lc.fullname AS organization
	FROM {local_emaillogs} AS le
	INNER JOIN {local_notification_info} AS ni ON le.notification_infoid=ni.id 
	JOIN {user} as u ON u.id = le.to_userid
	JOIN {local_notification_type} as lnt ON lnt.id=ni.notificationid
	JOIN {local_costcenter} as lc ON lc.id = ni.costcenterid
	WHERE 1=1";

$systemcontext = context_system::instance();
$params = array();
if(!(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext))){
	$cond_query .= " AND ni.costcenterid = :userorgid ";
	$params['userorgid'] = $USER->open_costcenterid;
}

if (isset($organization_search) && $organization_search != ""){

	$cond_query .= " AND ni.costcenterid=:orgid";
	$params['orgid'] = $organization_search;

}
if(isset($status_search) && $status_search != ""){
	if($status_search){
		$cond_query .= " AND le.status=:status ";
	}else{
		$cond_query .= " AND (le.status=:status OR le.status IS NULL) ";
	}
	$params['status'] = $status_search;
}

 $resultcount=$DB->count_records_sql($countquery.$cond_query, $params);
 $cond_query .=" order by id desc";
 $select_query.=$cond_query;
 $result = $DB->get_records_sql($select_query, $params, $_REQUEST['start'], $_REQUEST['length']);
	
$data = array();
foreach ($result as $key => $value){

	if ($value->admissionid > 0) {
        $admissionrecords = $DB->get_record_sql("SELECT u.firstname, u.lastname FROM {local_users} u 
                    JOIN {local_emaillogs} e ON e.admissionid = u.id 
                    WHERE e.id = {$value->id}");
        $value->to_username = ($admissionrecords->firstname .' '.$admissionrecords->lastname);

	} else {
		$value->to_username;
	}

	if($value->status==1){
		$status="Sent";
	}else{
		$status="Not Sent";
	}

	
	$created_date= \local_costcenter\lib::get_userdate("d/m/Y H:i", $value->timecreated);
	if($value->sent_date!="" && $value->sent_date!=0 ){
		$send_date= \local_costcenter\lib::get_userdate("d/m/Y H:i", $value->sent_date);
	}else{
		$send_date="N/A";
	}


	$row = array($value->organization,$value->from_username,$value->to_username,$value->notification_type,$created_date,$send_date,$status,'<a href="'.$CFG->wwwroot.'/local/notifications/email_status_details.php?id='.$value->id.'" target="_blank">View</a>');
	$data[] = $row;

}

$iTotal = $resultcount;
$outputs = array(
        "draw" => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
        "sEcho" => intval($requestData['sEcho']),
        "iTotalRecords" => $iTotal,
        "iTotalDisplayRecords" => $iTotal,
        "aaData" => $data
    );
echo json_encode($outputs);
          

