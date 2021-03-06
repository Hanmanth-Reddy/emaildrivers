<?php
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	
	$main_query = ""; //variable for main query.
	
	$roles_query = ""; //variable for roles query.
	$roles_cand_query = ""; //variable for roles query.
	$roles_emp_query = ""; //variable for roles query.
	
	$owner_query = ""; //variable for owner query.
	$owner_cand_query = ""; //variable for owner query.
	$owner_emp_query = ""; //variable for owner query.
	
	$people_query = ""; //variable for people query.
	$people_cand_query = ""; //variable for people query.
	$people_emp_query = ""; //variable for people query.
	
	$email_flag = 'N'; //default value for email alert in notification table.
	$sms_flag = 'N'; //default value for sms alert in notification table.
	$popup_flag = 'N'; // //default value for popup alert in notification table.
	$rrow =""; // Variable used for Notification setting query result. 
	$date_expr =""; // Date Expression for notification frequency.

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	if(mysql_num_rows($dres)>0)
	{
		while($drow=mysql_fetch_row($dres))
		{
			// Fetch the Companies one by one
			$companyuser=strtolower($drow[0]);
			require("database.inc");
			
			$notify_to_list = "";
			$people_list = "";
			$notify_time = array();
			$notify_to_val = array();
			$credentials_assoc_with = array();
			
			$main_query = ""; //variable for main query.
			
			$roles_query = ""; //variable for roles query.
			$roles_cand_query = ""; //variable for roles query.
			$roles_emp_query = ""; //variable for roles query.
			
			$owner_query = ""; //variable for owner query.
			$owner_cand_query = ""; //variable for owner query.
			$owner_emp_query = ""; //variable for owner query.
			
			$people_query = ""; //variable for people query.
			$people_cand_query = ""; //variable for people query.
			$people_emp_query = ""; //variable for people query.
			
			$date_expr = ""; // Date Expression for notification frequency.

			//QUERY TO FETCH NOTIFICATION SETTINGS DATA	
			$notification_settings="select notify_time,status,notify_mode,notify_to,notify_people,credentials_assoc_with from notifications_settings  where status='1' and mod_id='credential' and notify_status = 'ACTIVE'";
			$notification_result=mysql_query($notification_settings,$db);
			if(mysql_num_rows($notification_result)==1)
			{
				$rrow = mysql_fetch_assoc($notification_result);
				$notify_time = explode(",",$rrow['notify_time']);
				$notify_to_list  = $rrow['notify_to'];
				$notify_to_val = explode(",",$notify_to_list);
				$people_list = $rrow['notify_people'];
				$credentials_assoc_with = explode(",",$rrow['credentials_assoc_with']);

				/* Conditions to check selected time frequency. According to that will create a phrase for query. if multiple then will create a phrase with OR condition.*/

				if(in_array("1",$notify_time))
					$date_expr = 'DATE_SUB(valid_to, INTERVAL 1 DAY) = CURDATE()'; 

				if(in_array("2",$notify_time))
				{
					if($date_expr!='')
						$date_expr .= ' OR DATE_SUB(valid_to, INTERVAL 7 DAY) = CURDATE()'; 
					else
						$date_expr = ' DATE_SUB(valid_to, INTERVAL 7 DAY) = CURDATE()'; 
				}

				if(in_array("3",$notify_time))
				{
					if($date_expr!='')
						$date_expr .= ' OR DATE_SUB(valid_to, INTERVAL 1 MONTH) = CURDATE()';
					else
						$date_expr = ' DATE_SUB(valid_to, INTERVAL 1 MONTH) = CURDATE()';
				}

				if(in_array("4",$notify_time))
				{
					if($date_expr!='')
						$date_expr .= ' OR DATE_SUB(valid_to, INTERVAL 2 MONTH) = CURDATE()';
					else
						$date_expr = ' DATE_SUB(valid_to, INTERVAL 2 MONTH) = CURDATE()';
				}

				if(in_array("5",$notify_time))
				{
					if($date_expr!='')
						$date_expr .= ' OR DATE_SUB(valid_to, INTERVAL 3 MONTH) = CURDATE()'; 
					else
						$date_expr = ' DATE_SUB(valid_to, INTERVAL 3 MONTH) = CURDATE()'; 
				}

				/* Condition to check flags for Email , SMS , Popup */

				if(strpos($rrow['notify_mode'],'e')!==false)
					$email_flag = 'Y';
				if(strpos($rrow['notify_mode'],'s')!==false)
					$sms_flag = 'Y';
				if(strpos($rrow['notify_mode'],'p')!==false)
					$popup_flag = 'Y';

				if($date_expr != "")
				{
					// IF ELSE TO GENERATE MAIN QUERY TO FETCH USERNAME OF EMPLOYEE(S) DEPENDING UPON GROUP SELECTED	
					// 1= ROLES
					// 2= OWNER
					// 3= PEOPLE

					if(in_array('1',$notify_to_val))
					{
						if(in_array("1",$credentials_assoc_with))
						{
							$roles_cand_query = "SELECT  emp.username,date_format(cc.valid_to,'%m/%d/%Y'),mct.credential_type,mcn.credential_name,cl.fname,cl.lname,emp.name,cc.cre_number,date_format(cc.valid_from,'%m/%d/%Y') as valid_from,cc.id,IF(hg.mobile IS NULL,'',hg.mobile) mobile,IF(hg.email='',IF(hg.alternate_email='',IF(hg.other_email='',' ',hg.other_email),hg.alternate_email),hg.email) email,IF(hg.esms='N','',hg.mser_domain) smsdomain, 'Candidate' AS credential_candemp
							FROM hrcon_general hg,entity_roles el, emp_list emp, candidate_list cl, candidate_credentials cc
							LEFT JOIN manage_credentials_name mcn ON cc.cre_name_id = mcn.id
							LEFT JOIN manage_credentials_type mct ON cc.cre_type_id = mct.id
							WHERE el.empId = emp.username AND cc.cand_sno = cl.sno AND cc.cand_sno = el.entityId AND cl.status = 'ACTIVE' AND cc.status = 'ACTIVE' AND hg.username = emp.username AND (".$date_expr.")";
						}
						
						if(in_array("2",$credentials_assoc_with))
						{
							$roles_emp_query = "SELECT hg.username,DATE_FORMAT(emp2.valid_to,'%m/%d/%Y'),emp2.credential_type,emp2.credential_name,emp2.fname,emp2.lname,CONCAT(hg.fname,' ',hg.lname),emp2.cre_number,DATE_FORMAT(emp2.valid_from,'%m/%d/%Y') AS valid_from,emp2.id,IF(hg.mobile IS NULL,'',hg.mobile)    mobile,IF(hg.email='',IF(hg.alternate_email='',IF(hg.other_email='',' ',hg.other_email),hg.alternate_email),hg.email)    email,IF(hg.esms='N','',hg.mser_domain)    smsdomain,'Employee' AS credential_candemp
							FROM hrcon_general hg,
								(SELECT hc.valid_to, mct.credential_type, mcn.credential_name, hg_temp.fname, hg_temp.lname, el.name, hc.cre_number, hc.valid_from, hc.id, ac.person roleid FROM hrcon_credentials hc
								LEFT JOIN manage_credentials_name mcn
								  ON hc.cre_name_id = mcn.id
								LEFT JOIN manage_credentials_type mct
								  ON hc.cre_type_id = mct.id, emp_list el 
								LEFT JOIN empcon_jobs ej
								  ON (el.username = ej.username AND ej.jtype != '' AND ej.jotype != '0' AND ej.assg_status IN('approved')) 
								LEFT JOIN assign_commission ac 
								  ON (ac.assignid = ej.sno AND ac.assigntype='E') 
								LEFT JOIN hrcon_compen hcomp
								  ON (el.username = hcomp.username AND hcomp.ustatus = 'active'), hrcon_general hg_temp
								WHERE el.sno = hc.app_sno 								 
								  AND el.lstatus NOT IN ('DA','INACTIVE')
								  AND el.empterminated != 'Y'
								  AND hc.status = 'ACTIVE'
								  AND hc.ustatus = 'active' 
								  AND ac.person IS NOT NULL 
								  AND el.username = hg_temp.username
								  AND (".$date_expr.")
								GROUP BY ac.person, hc.id 
								ORDER BY ac.person) emp2
							WHERE emp2.roleid = hg.username ";
						}
										
						if($roles_cand_query != ""){
							$roles_query = $roles_cand_query;
						}
	  
						if($roles_emp_query != ""){
							$roles_query = $roles_emp_query;
						}
						
						if($roles_cand_query != "" && $roles_emp_query != ""){
							$roles_query = $roles_cand_query." UNION ".$roles_emp_query;
						}
					}
					if(in_array('2',$notify_to_val)) 
					{
						if(in_array("1",$credentials_assoc_with))
						{
							$owner_cand_query .= "SELECT  emp.username,date_format(cc.valid_to,'%m/%d/%Y'),mct.credential_type,mcn.credential_name,cl.fname,cl.lname,emp.name,cc.cre_number,date_format(cc.valid_from,'%m/%d/%Y') as valid_from,cc.id,IF(hg.mobile IS NULL,'',hg.mobile) mobile,IF(hg.email='',IF(hg.alternate_email='',IF(hg.other_email='',' ',hg.other_email),hg.alternate_email),hg.email) email,IF(hg.esms='N','',hg.mser_domain) smsdomain, 'Candidate' AS credential_candemp
							FROM hrcon_general hg, candidate_list cl, emp_list emp, candidate_credentials cc
							LEFT JOIN manage_credentials_name mcn ON cc.cre_name_id = mcn.id
							LEFT JOIN manage_credentials_type mct ON cc.cre_type_id = mct.id
							WHERE  cl.sno = cc.cand_sno AND cl.owner = emp.username AND cl.status = 'ACTIVE' AND cc.status = 'ACTIVE' AND hg.username = emp.username AND (".$date_expr.")";
						}
						
						if(in_array("2",$credentials_assoc_with))
						{
							$owner_emp_query = "SELECT hg.username,DATE_FORMAT(emp2.valid_to,'%m/%d/%Y'),emp2.credential_type,emp2.credential_name,emp2.fname,emp2.lname,CONCAT(hg.fname,' ',hg.lname),emp2.cre_number,DATE_FORMAT(emp2.valid_from,'%m/%d/%Y') AS valid_from,emp2.id,IF(hg.mobile IS NULL,'',hg.mobile)    mobile,IF(hg.email='',IF(hg.alternate_email='',IF(hg.other_email='',' ',hg.other_email),hg.alternate_email),hg.email)    email,IF(hg.esms='N','',hg.mser_domain)    smsdomain,'Employee' AS credential_candemp
							FROM hrcon_general hg,
								(SELECT hc.valid_to,mct.credential_type,mcn.credential_name,hg_temp.fname,hg_temp.lname,el.name,hc.cre_number,hc.valid_from,hc.id,el.approveuser OWNER
								FROM hrcon_credentials hc
								   LEFT JOIN manage_credentials_name mcn
								     ON hc.cre_name_id = mcn.id
								   LEFT JOIN manage_credentials_type mct
								     ON hc.cre_type_id = mct.id,
								   emp_list el
								   LEFT JOIN hrcon_compen hcomp
								     ON (el.username = hcomp.username
									 AND hcomp.ustatus = 'active'),
								   hrcon_general hg_temp
								WHERE el.sno = hc.app_sno								 
								     AND el.lstatus NOT IN ('DA','INACTIVE')
								     AND el.empterminated != 'Y'
								     AND hc.status = 'ACTIVE'
								     AND hc.ustatus = 'active'     
								     AND el.username = hg_temp.username
								     AND (".$date_expr.")
								GROUP BY hc.id) emp2
							WHERE emp2.owner = hg.username";
						}
								      
						if($owner_cand_query != ""){
							$owner_query = $owner_cand_query;
						}
	  
						if($owner_emp_query != ""){
							$owner_query = $owner_emp_query;
						}
						
						if($owner_cand_query != "" && $owner_emp_query != ""){
							$owner_query = $owner_cand_query." UNION ".$owner_emp_query;
						}
					}
					if(in_array('3',$notify_to_val))
					{
						if(in_array("1",$credentials_assoc_with))
						{
							$people_cand_query = "SELECT  emp.username,date_format(cc.valid_to,'%m/%d/%Y'),mct.credential_type,mcn.credential_name,cl.fname,cl.lname,emp.name,cc.cre_number,date_format(cc.valid_from,'%m/%d/%Y') as valid_from,cc.id,IF(hg.mobile IS NULL,'',hg.mobile) mobile,IF(hg.email='',IF(hg.alternate_email='',IF(hg.other_email='',' ',hg.other_email),hg.alternate_email),hg.email) email,IF(hg.esms='N','',hg.mser_domain) smsdomain, 'Candidate' AS credential_candemp
							FROM hrcon_general hg, emp_list emp,candidate_list cl,candidate_credentials cc 
							LEFT JOIN manage_credentials_name mcn ON cc.cre_name_id = mcn.id
							LEFT JOIN manage_credentials_type mct ON cc.cre_type_id = mct.id
							where  emp.username IN (".$people_list.") AND cc.cand_sno = cl.sno AND cl.status = 'ACTIVE' AND cc.status = 'ACTIVE' AND hg.username = emp.username AND (".$date_expr.")";
						}
						
						if(in_array("2",$credentials_assoc_with))
						{
							$people_emp_query = "SELECT hg.username,DATE_FORMAT(emp2.valid_to,'%m/%d/%Y'),emp2.credential_type,emp2.credential_name,emp2.fname,emp2.lname,CONCAT(hg.fname,' ',hg.lname),emp2.cre_number,DATE_FORMAT(emp2.valid_from,'%m/%d/%Y') AS valid_from,emp2.id,IF(hg.mobile IS NULL,'',hg.mobile)    mobile,IF(hg.email='',IF(hg.alternate_email='',IF(hg.other_email='',' ',hg.other_email),hg.alternate_email),hg.email)    email,IF(hg.esms='N','',hg.mser_domain)    smsdomain,'Employee' AS credential_candemp
							FROM hrcon_general hg,
								(SELECT hc.valid_to,mct.credential_type,mcn.credential_name,hg_temp.fname,hg_temp.lname,el.name,hc.cre_number,hc.valid_from,hc.id     
								 FROM hrcon_credentials hc
								   LEFT JOIN manage_credentials_name mcn
								     ON hc.cre_name_id = mcn.id
								   LEFT JOIN manage_credentials_type mct
								     ON hc.cre_type_id = mct.id,
								   emp_list el
								   LEFT JOIN hrcon_compen hcomp
								     ON (el.username = hcomp.username
									 AND hcomp.ustatus = 'active'),
								   hrcon_general hg_temp
								WHERE el.sno = hc.app_sno								     
								     AND el.lstatus NOT IN ('DA','INACTIVE')
								     AND el.empterminated != 'Y'
								     AND hc.status = 'ACTIVE'
								     AND hc.ustatus = 'active'     
								     AND el.username = hg_temp.username
								     AND (".$date_expr.")
								GROUP BY hc.id) emp2
							WHERE hg.username IN (".$people_list.")";
						}
								      
						if($people_cand_query != ""){
							$people_query = $people_cand_query;
						}
	  
						if($people_emp_query != ""){
							$people_query = $people_emp_query;
						}
						
						if($people_cand_query != "" && $people_emp_query != ""){
							$people_query = $people_cand_query." UNION ".$people_emp_query;
						}
					}

					if($people_query !="")
						$main_query = $people_query;

					if($roles_query!="")
						$main_query = $roles_query;

					if($owner_query!="")
						$main_query = $owner_query;

					if($people_query!="" and $owner_query!="")
						$main_query = $people_query." UNION ".$owner_query;

					if($owner_query!="" and $roles_query!="")
						$main_query = $owner_query." UNION ".$roles_query;

					if($people_query!="" and $roles_query!="")
						$main_query = $people_query." UNION ".$roles_query;

					if($people_query!="" and $roles_query!="" and $owner_query!="")
						$main_query = $people_query." UNION ".$roles_query ." UNION ".$owner_query;

					// VARIABLES USED TO AVOID MULTIPLE ENTRIES IN NOTIFICATIONS TABLE					
					$temp_notification_cred_ids = array(); //Credential IDs with notification id stored temporarily.

					//Last inserted id generated from notifications table stored temporarily.
					$temp_notification_psno = "";

					if($main_query !="")
					{	
						$result_email = mysql_query($main_query,$db);
						//LOOP TO INSERT INTO NOTIFICATION TABLE
						while($email_array = mysql_fetch_array($result_email))
						{									
							// AVOID MULTIPLE ENTRIES IN NOTIFICATIONS TABLE
							if(!isset($temp_notification_cred_ids[$email_array[9]."_".$email_array['credential_candemp']]))
							{							
								$subject = $email_array['credential_candemp']." Credential Expiry Alert! - ".$email_array['fname']." ".$email_array['lname']." - ".$email_array['credential_type'];

								// Message content for Email
								$matter = "Hello,<br><br>Below ".$email_array['credential_candemp']."(s) Credentials are nearing expiry.<br><br>".$email_array['credential_candemp']." Name : ".$email_array[4]." ".$email_array[5]."<br>Credential Type : ".$email_array[2]."<br>Credential Name : ".$email_array[3]."<br>Credential Number : ".$email_array[7]."<br>Valid From : ".$email_array[8]."<br>Valid To : ".$email_array[1]."<br>";

								// replaces <br> tags with newline character as same data used for mail and popup.
								$matter  = str_replace('<br>','\n',$matter);

								// Message content for SMS - Max 160 chars in SMS
								$sms_matter = "Credential of ".$email_array['credential_candemp'].": ".$email_array[4]." having Cred No.: ".$email_array[7]." is going to expire on ".$email_array[1];								

								//Call to procedure for inserting to notifications table of akken_notifications.
								$akken_insert_notification = "call akken_notifications.insertNotification ('".$companyuser."','credential','".$email_array[9]."','".mysql_real_escape_string($subject)."','".mysql_real_escape_string($matter)."','Active','".mysql_real_escape_string($sms_matter)."',@psno)";

								$res = mysql_query($akken_insert_notification,$db);
								$res = mysql_query("select @psno as psno",$db);
								$fetch_psno_result = mysql_fetch_array($res);
								$psno = $fetch_psno_result[0];

								//Update the flags in the table 
								$alert_type_set_query = "UPDATE akken_notifications.notifications SET alert = '".$popup_flag."', email = '".$email_flag."', sms = '".$sms_flag."' WHERE sno = ".$psno."";
								mysql_query($alert_type_set_query,$db);

								$temp_notification_cred_ids[$email_array[9]."_".$email_array['credential_candemp']] = $psno; // set this to the current credential id
							}
							// END OF BLOCK TO AVOID MULTIPLE ENTRIES IN NOTIFICATIONS TABLE

							// Get Notification ID based on Credemtial ID set earlier
							$temp_notification_psno = $temp_notification_cred_ids[$email_array[9]."_".$email_array['credential_candemp']];
														
							if(!empty($temp_notification_psno)){
								//Call to procedure for inserting to notifications_list table of akken_notifications.
								$akken_insert_notificationList = "call akken_notifications.insertNotificationList(".$temp_notification_psno.",'".$email_array[0]."','".$email_array['email']."','".$email_array['mobile']."','".$email_array['smsdomain']."');";
								mysql_query($akken_insert_notificationList,$db);
							}

							$email_array = "";
						}
					}
				}
			}
		}
	}
?>