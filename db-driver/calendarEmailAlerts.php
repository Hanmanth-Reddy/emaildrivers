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
	$smtp->host_port="465";
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

		$uque="SELECT users.username, timezone.phpvar FROM users LEFT JOIN orgsetup ON users.username=orgsetup.userid LEFT JOIN timezone ON orgsetup.timezone=timezone.sno WHERE users.usertype!='' AND users.status!='DA' AND notify_email!=''";
		$ures=mysql_query($uque,$db);
		while($urow=mysql_fetch_row($ures))
		{
			$username = $urow[0];

			if(in_array($urow[1],$us_tz))
				$usertime = $starttime + $us_tz_value;
			else
				$usertime = $starttime + $non_us_tz_value;

			$utzos = getUserSTZOffset();
			$qsdatetime = $usertime - $slot_time;
			$qedatetime = $usertime;

			$nque="SELECT orgsetup.notify_email, orgsetup.notify_val, appointments.title, DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.sdatetime + '$utzos'),(recurrences.otime + '$utzos'))),'%W, %m/%d/%Y %h:%i%p') stime, DATE_FORMAT(FROM_UNIXTIME(IF(appointments.recurrence='none',(appointments.edatetime + '$utzos'),(recurrences.etime + '$utzos'))),'%W, %m/%d/%Y %h:%i%p') etime, appointments.descri FROM appointments LEFT JOIN orgsetup ON appointments.username=orgsetup.userid LEFT JOIN recurrences ON recurrences.ano = appointments.sno WHERE orgsetup.notify_email!='' AND appointments.status='active' AND (appointments.username='".$username."' OR FIND_IN_SET('".$username."',appointments.approved) > 0 OR FIND_IN_SET('".$username."',appointments.tentative) > 0 OR FIND_IN_SET('".$username."',appointments.pending) > 0) AND ((appointments.recurrence='none' AND ((appointments.sdatetime - orgsetup.notify_val)>=$qsdatetime AND (appointments.sdatetime - orgsetup.notify_val)<=$qedatetime)) OR (appointments.recurrence='recurrence' AND ((recurrences.otime - orgsetup.notify_val)>=$qsdatetime AND (recurrences.otime - orgsetup.notify_val)<=$qedatetime))) ORDER BY stime,appointments.title ASC";
			$nres=mysql_query($nque,$db);
			while($nrow=mysql_fetch_row($nres))
			{
				$to = $nrow[0];
				$nval = $nrow[1]/60;
				$ato = explode(",",$to);

				$subject = "Event Reminder: ".$nrow[2];
				$notify_body="Your event <b>".$nrow[2]."</b>, will start in <b>".$nval."</b> mins"."<BR><BR><b>Start Time: </b>".$nrow[3]."<br>"."<b>End Time: </b>".$nrow[4];

				if(trim($nrow[5])!="")
					$notify_body.="<BR><b>Description:</b><BR><BR>".$nrow[5];

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