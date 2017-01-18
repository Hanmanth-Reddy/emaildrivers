<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("saveactivities.inc");

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
	/////////////////////////////////////////////////////
	/////////////////////////////////////////////////////

	$starttime = time();
	$meteor = fsockopen("192.168.1.12", 4671, $errno, $errstr, 10);

	if($meteor)
	{
		$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
		$dres=mysql_query($dque,$maindb);
		while($drow=mysql_fetch_row($dres))
		{
			$companyuser=strtolower($drow[0]);
			require("database.inc");

			$uque="select users.username, timezone.phpvar,su.collaboration
					from users 
					LEFT JOIN orgsetup ON users.username=orgsetup.userid 
					LEFT JOIN timezone ON orgsetup.timezone=timezone.sno
					LEFT JOIN sysuser su ON users.username=su.username 
					where users.usertype!='' AND users.status!='DA'";
			$ures=mysql_query($uque,$db);
			while($urow=mysql_fetch_row($ures))
			{
				$collaborationVal = $urow[2];
				if($collaborationVal!='NO' && strpos($collaborationVal,"+10+")!==false){
					if($urow[1]!="")
					{
						$event = "";
						$username = $urow[0];
						$channel_name = "chan-".$companyuser."-".$username;

						if(in_array($urow[1],$us_tz))
							$usertime = $starttime + $us_tz_value;
						else
							$usertime = $starttime + $non_us_tz_value;

						$utzos = getUserSTZOffset();
						$qsdatetime = $usertime - $slot_time;
						$qedatetime = $usertime;

						$nque="SELECT appointments.title, DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.sdatetime + '$utzos'),(recurrences.otime + '$utzos'))),'%W, %m/%d/%Y %h:%i%p') stime, DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.edatetime + '$utzos'),(recurrences.etime + '$utzos'))),'%W, %m/%d/%Y %h:%i%p') etime FROM appointments LEFT JOIN recurrences ON recurrences.ano = appointments.sno WHERE appointments.dis='Yes' AND appointments.status='active' AND (appointments.username='".$username."' OR FIND_IN_SET('".$username."',appointments.approved) > 0 OR FIND_IN_SET('".$username."',appointments.tentative) > 0 OR FIND_IN_SET('".$username."',appointments.pending) > 0) AND ((appointments.recurrence='none' AND ((appointments.sdatetime - appointments.rtime)>=$qsdatetime AND (appointments.sdatetime - appointments.rtime)<=$qedatetime)) OR (appointments.recurrence='recurrence' AND ((recurrences.otime - appointments.rtime)>=$qsdatetime AND (recurrences.otime - appointments.rtime)<=$qedatetime))) ORDER BY stime,appointments.title ASC";
						$nres=mysql_query($nque,$db);
						while($nrow=mysql_fetch_row($nres))
						{
							if($event=="")
								$event="Subject : ".addslashes($nrow[0])."|akkenPSplit|"."Start Time : ".$nrow[1]."|akkenPSplit|"."End Time : ".$nrow[2];
							else
								$event.="|akkenESplit|"."Subject : ".addslashes($nrow[0])."|akkenPSplit|"."Start Time : ".$nrow[1]."|akkenPSplit|"."End Time : ".$nrow[2];
						}

						if($event!="")
						{
							$cmdop = "ADDMESSAGE ".$channel_name." ".$event."\n";
							fwrite($meteor, $cmdop);
						}
					}
				}
			}
		}

		fwrite($meteor, "QUIT\n");
		fclose($meteor);
	}
?>
