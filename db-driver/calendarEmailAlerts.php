<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("saveactivities.inc");
	require("smtp.inc");
	require("html2text.inc");
	require("saveemails.inc");

	/////////////////////////////////////////////////////
	// DO NOT CHANGE THESE VALUES                      //
	/////////////////////////////////////////////////////

	$smtp=new smtp_class;
	$smtp->host_name="smtp.akken.com";
	$smtp->host_port="25";
	$smtp->localhost="smtp.akken.com";

	$from = "Akken Notifications <donot-reply@akken.com>";
	$mailtype = "text/html";

	$starttime = time();
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

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$uque="SELECT users.username, timezone.phpvar, orgsetup.notify_email, orgsetup.notify_val FROM users LEFT JOIN orgsetup ON users.username=orgsetup.userid LEFT JOIN timezone ON orgsetup.timezone=timezone.sno WHERE users.usertype!='' AND users.status!='DA' AND orgsetup.notify_email!=''";
		$ures=mysql_query($uque,$db);
		while($urow=mysql_fetch_row($ures))
		{
			$username = $urow[0];
			$notify_to = $urow[2];
			$notify_val = $urow[3];

			if(in_array($urow[1],$us_tz))
				$usertime = $starttime + $us_tz_value;
			else
				$usertime = $starttime + $non_us_tz_value;

			$utzos = getUserSTZOffset();
			$qsdatetime = $usertime - $slot_time;
			$qedatetime = $usertime;

			$nque="SELECT appointments.title, DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.sdatetime + '$utzos'),(recurrences.otime + '$utzos'))),'%W, %m/%d/%Y %h:%i%p') stime, DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.edatetime + '$utzos'),(recurrences.etime + '$utzos'))),'%W, %m/%d/%Y %h:%i%p') etime, appointments.descri FROM appointments LEFT JOIN recurrences ON recurrences.ano = appointments.sno WHERE appointments.status='active' AND (appointments.username='".$username."' OR FIND_IN_SET('".$username."',appointments.approved) > 0 OR FIND_IN_SET('".$username."',appointments.tentative) > 0 OR FIND_IN_SET('".$username."',appointments.pending) > 0) AND ((appointments.recurrence='none' AND ((appointments.sdatetime - $notify_val)>=$qsdatetime AND (appointments.sdatetime - $notify_val)<=$qedatetime)) OR (appointments.recurrence='recurrence' AND ((recurrences.otime - $notify_val)>=$qsdatetime AND (recurrences.otime - $notify_val)<=$qedatetime))) ORDER BY stime,appointments.title ASC";
			$nres=mysql_query($nque,$db);
			while($nrow=mysql_fetch_row($nres))
			{
				$to = $notify_to;
				$nval = $notify_val/60;
				$ato = explode(",",$to);

				$subject = "Event Reminder: ".$nrow[0];
				$notify_body="Your event <b>".$nrow[0]."</b>, will start in <b>".$nval."</b> mins"."<BR><BR><b>Start Time: </b>".$nrow[1]."<br>"."<b>End Time: </b>".$nrow[2];

				if(trim($nrow[3])!="")
					$notify_body.="<BR><b>Description:</b><BR><BR>".$nrow[3];

				$matter = "<div style='font-family: arial; font-size: 10pt;'>".str_replace("\n","<br>",$notify_body)."<br><br></div>";

				if($to!="")
				{
					$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Subject: $subject","MIME-Version: 1.0");
					$msg_body=prepareBodyA($matter,$mailheaders,$mailtype);
					$smtp->SendMessage($from,$ato,$mailheaders,$msg_body);
				}
			}
		}
	}
?>