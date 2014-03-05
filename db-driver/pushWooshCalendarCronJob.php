<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set('memory_limit',-1);
	ini_set("max_execution_time", -1);
	//ini_set("include_path",$include_path);
	require("global.inc");
	require("json_EncodeDecode.php");
	require("PushWooshFunctions.php");
	ini_set("display_errors",1);
	/////////////////////////////////////////////////////
	// DO NOT CHANGE THESE VALUES                      //
	/////////////////////////////////////////////////////
	$slot_time = 900;
	$us_tz = array("PST8PDT","MST7MDT","CST6CDT","EST5EDT");
	if(date("I",time())==1)
	{
		$us_tz_value = "18000";
		$non_us_tz_value = "14400";
	}
	else
	{
		$us_tz_value = "18000";
		$non_us_tz_value = "18000";
	}
	$starttime = time();
	/////////////////////////////////////////////////////
	/////////////////////////////////////////////////////
	// Get Active Companies
	$dque="SELECT capp_info.comp_id,company_info.company_name,company_info.port,company_info.version,company_info.group_id, MB_LoginStatus.comp_sno, MB_LoginStatus.ServerName
			FROM company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno 
			INNER JOIN MB_LoginStatus ON capp_info.sno=MB_LoginStatus.comp_sno
			WHERE company_info.status='ER' ".$version_clause." GROUP BY capp_info.sno";
	$dres=mysql_query($dque,$maindb);
	if(mysql_num_rows($dres)>0){
		$inc=0;
		$incW = 0;
		// Checking the count of Companies
		while($drow=mysql_fetch_row($dres)){
			// Fetch the Companies one by one
			$companyuser=strtolower($drow[0]);			
			$comName=$drow[1];
			$version_port=$drow[2];
			$version=$drow[3];
			$group_id=$drow[4];
			$comp_sno=$drow[5];
			$ServerName=$drow[6];
			require("database.inc");

			// Get Active Mobi login Sessions users for Pushing Maessage
			$ubque="SELECT u.username as username, u.userid AS userid, t.phpvar as usertz, a.c_value as Platform_Type_id
					FROM  mobi_customProperties AS a,
					  QB_LoginStatus AS b,    
					  users AS u,   
					  orgsetup AS o,
					  timezone AS t 
					WHERE b.wsname='AkkenForMobi' 
					AND b.LoginStatus=1 
					AND a.c_active=1 
					AND a.c_name = 'Platform_Type' 
					AND u.usertype!='' 
					AND u.status!='DA' 
					AND a.LoginSession=b.LoginSession
					AND b.username = u.username 
					AND u.username=o.userid 
					AND o.timezone=t.sno
					GROUP BY u.username
					ORDER BY username ";
			$ubres=mysql_query($ubque,$db);
			if(mysql_num_rows($ubres)>0)
			{
				while($ubrow=mysql_fetch_array($ubres))
				{
					$username=$ubrow['username'];
					$userid=strtolower($ubrow['userid']);
					$Platform_Type_id = trim($ubrow['Platform_Type_id']);
					
					if($ubrow['usertz']!=""){
					
					if(in_array($ubrow['usertz'],$us_tz))
						$usertime = $starttime + $us_tz_value;
					else
						$usertime = $starttime + $non_us_tz_value;

					$utzos = getUserSTZOffset();
					$qsdatetime = $usertime - $slot_time;
					$qedatetime = $usertime;
					
						// Get Active Appointments
							$nque="SELECT 
							appointments.sno as a_sno, recurrences.sno as r_sno,
							appointments.title as title, 
							DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.sdatetime + '$utzos'),(recurrences.otime + '$utzos'))),'%W, %m/%d/%Y %h:%i%p') stime, 
							DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.edatetime + '$utzos'),(recurrences.etime + '$utzos'))),'%W, %m/%d/%Y %h:%i%p') etime, 
							appointments.sdatetime,
							DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.sdatetime - rtime + '$utzos'),(recurrences.otime  - rtime + '$utzos'))),'%H-%i-%m-%d-%Y') remtime
							FROM appointments LEFT JOIN recurrences ON recurrences.ano = appointments.sno
							WHERE appointments.dis='Yes' AND appointments.status='active' AND 
							(appointments.username='".$username."' OR FIND_IN_SET('".$username."',appointments.approved) > 0 OR FIND_IN_SET('".$username."',appointments.tentative) > 0 OR FIND_IN_SET('".$username."',appointments.pending) > 0) AND 
							((appointments.recurrence='none' AND ((appointments.sdatetime - appointments.rtime)>=$qsdatetime AND (appointments.sdatetime - appointments.rtime)<=$qedatetime)) OR (appointments.recurrence='recurrence' AND ((recurrences.otime - appointments.rtime)>=$qsdatetime AND (recurrences.otime - appointments.rtime)<=$qedatetime))) ORDER BY stime,appointments.title ASC";
							$nres=mysql_query($nque,$db);
							if(mysql_num_rows($nres)>0){
								while($nrow=mysql_fetch_array($nres))
								{
								    $action = '/Collaboration-Appointment-view.php?appno='.$nrow['a_sno'].'&tmpstmp='.$nrow['sdatetime'];
									
									$remtime = explode('-', $nrow['remtime']);
									$nowtime1 = gmstrftime("%Y-%m-%d %H:%M", mktime($remtime[0], $remtime[1], 0, $remtime[2], $remtime[3], $remtime[4]));
									if($ServerName!='')
									{
										$host = $ServerName;
										
									}else
									{
										$host = "appserver1";
									}
									
									$link_event = 'https://'.$host.'.'.AKKEN_MOBI_URL.'/Collaboration-Appointment-view.php?appno='.$nrow['a_sno'].'&tmpstmp='.$nrow['sdatetime'];
									 
									$subject =  addslashes($nrow['title'])."\r\n".date("h:i a",strtotime($nrow['stime']))." (".date("D m-d-Y",strtotime($nrow['stime'])).")";
									$subject_withoutSub = date("h:i a",strtotime($nrow['stime']))." (".date("D m-d-Y",strtotime($nrow['stime'])).")";
									
									try
									{
										$output_send = pwCall( 'createMessage', array(
													'application' => PW_APPLICATION,
													'auth' => PW_AUTH,
													'notifications' => array(
														array(
															'send_date' => 'now',
															'content' => $subject,
															'data' => array( 'isNotficationGrouped' => 'false' ),
															'minimize_link' => 0,
															'link' => $link_event,
															'platforms' => array(1,2,3,5),
															'conditions' => array( array('User','EQ',$companyuser.'@'.$username))
														)
													)
												)
										);
										//1 - iOS; 2 - BB; 3 - Android; 5 - Windows Phone; 7 - OS X; 8 - Windows 8; 9 - Amazon
										if($output_send!='')
										{
											$Messageid = $output_send['response']['Messages'][0];
											$insert_log = "INSERT INTO pushwoosh_logs (user_id,log_status,log_msg,company_id,log_pw_id,log_type,ano) VALUES ('".$username."','".$output_send['status_code']."','".$output_send['status_message']."','".$companyuser."','".$Messageid."','C','".$nrow['a_sno']."') ";
											$insert_logRes = mysql_query($insert_log,$db);
										}
										
									}
									catch(Exception $e)
									{
										$insert_log = "INSERT INTO pushwoosh_logs (user_id,log_status,log_msg,company_id,log_type,ano) VALUES ('".$username."','".$e->getMessage()."','pwCall Failed','".$companyuser."','C','".$nrow['a_sno']."') ";
										$insert_logRes = mysql_query($insert_log,$db);
									}
									
									
								}
							}
					}
					 
				} // while loop ends here
			} // if loop ends here
		
		} // while loop ends
	} // if loop
?>
