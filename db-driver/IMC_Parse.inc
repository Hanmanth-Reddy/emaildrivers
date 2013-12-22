<?php

if(!$pri_driver)
	require_once("savemails.inc");

class IMC_Parse
{
	var $count = -1;
	var $type = "REQUEST";
	var $status = "U";
	var $cid = 0;
	var $aid = 0;
	var $parid = 0;
	var $sequence = 0;
	var $mailid = 0;
	var $text = "";
	var $sdt = "S";
	var $utz = "+0000";
	var $sdtosf = "+0000";

	function __construct($val='')
	{
		$this->ModName = $val;
	}

	function parseCalendar($mailid)
	{
		global $maildb,$db,$maindb;

		$calbody="";
		$que="select cid,aid,content,type,status from mail_calendar where mailid='$mailid'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
		if(mysql_num_rows($res)>0)
		{
			$this->mailid=$mailid;
			$this->cid=$row[0];
			$this->aid=$row[1];
			$this->text=$row[2];
			$this->type=$row[3];
			$this->status=$row[4];
			$calbody=$this->fromFile($this->text);

			$dtstart = $this->getDateTime($calbody["VCALENDAR"]["VEVENT"]["DTSTART"][0]["value"]);

			if(date("I",$dtstart)==1)
				$this->sdt = "D";

			if($this->sdt=="S")
				$que="select REPLACE(timezone.stdtime,':','') FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone LEFT JOIN mail_headers ON orgsetup.userid=mail_headers.username LEFT JOIN mail_calendar ON mail_headers.mailid=mail_calendar.mailid WHERE mail_calendar.mailid=$mailid";
			else
				$que="select REPLACE(timezone.daytime,':','') FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone LEFT JOIN mail_headers ON orgsetup.userid=mail_headers.username LEFT JOIN mail_calendar ON mail_headers.mailid=mail_calendar.mailid WHERE mail_calendar.mailid=$mailid";
			$res=mysql_query($que,$db);
			$row=mysql_fetch_row($res);
			if($row[0]!="")
				$this->utz = $row[0];
		}
		return $calbody;
	}

	function parseInvCalendar($cid)
	{
		global $maildb,$db,$maindb;

		$calbody="";
		$que="select mailid,aid,content,type,status from mail_calendar where cid='$cid'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
		if(mysql_num_rows($res)>0)
		{
			$this->cid=$cid;
			$this->mailid=$row[0];
			$this->aid=$row[1];
			$this->text=$row[2];
			$this->type=$row[3];
			$this->status=$row[4];
			$calbody=$this->fromFile($this->text);

			$dtstart = $this->getDateTime($calbody["VCALENDAR"]["VEVENT"]["DTSTART"][0]["value"]);

			if(date("I",$dtstart)==1)
				$this->sdt = "D";

			if($this->sdt=="S")
				$que="select REPLACE(timezone.stdtime,':','') FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone LEFT JOIN mail_headers ON orgsetup.userid=mail_headers.username LEFT JOIN mail_calendar ON mail_headers.mailid=mail_calendar.mailid WHERE mail_calendar.cid=$cid";
			else
				$que="select REPLACE(timezone.daytime,':','') FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone LEFT JOIN mail_headers ON orgsetup.userid=mail_headers.username LEFT JOIN mail_calendar ON mail_headers.mailid=mail_calendar.mailid WHERE mail_calendar.cid=$cid";
			$res=mysql_query($que,$db);
			$row=mysql_fetch_row($res);
			if($row[0]!="")
				$this->utz = $row[0];
		}
		return $calbody;
	}

	function prepareBody($calinfo)
	{
		if($this->type=="REQUEST")
			return $this->parseRequest($calinfo,$this->type);
		else
			return $this->parseReply($calinfo,$this->type);
	}

	function prepareInvBody($calinfo)
	{
		if($this->sdt=="S")
		{
			$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
		}
		else
		{
			if($calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"]!="")
				$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"];
			else
				$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
		}

		if($sdtosf!="")
			$this->sdtosf = $sdtosf;

		$stzid = $calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["TZID"];
		$etzid = $calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["TZID"];
		$stampzid = $calinfo["VCALENDAR"]["VEVENT"]["DTSTAMP"][0]["param"]["TZID"];

		$rrule = $calinfo["VCALENDAR"]["VEVENT"]["RRULE"][0]["value"];
		if($rrule!="")
			$rrules = $this->parseRecurrence($rrule);

		require("iCalendar_INV_TMP.inc");
		
		if(strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["VALUE"])=="date" || strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["VALUE"])=="date")
		{
			$event = "allday";
			$allday_event = " <b>(Allday Event)</b>";
		}

		if($calinfo["VCALENDAR"]["VEVENT"]["DESCRIPTION"][0]["value"]!="")
			$description = $calinfo["VCALENDAR"]["VEVENT"]["DESCRIPTION"][0]["value"];
		else
			$description = $calinfo["VCALENDAR"]["VEVENT"]["COMMENT"][0]["value"];

		$description = nl2br(stripslashes(wordwrap($description,60,"<BR>",TRUE)));

		$summary = $calinfo["VCALENDAR"]["VEVENT"]["SUMMARY"][0]["value"];
		$class = $calinfo["VCALENDAR"]["VEVENT"]["CLASS"][0]["value"];
		$dtstart = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["value"],$stzid,$event);
		$dtend = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["value"],$etzid,$event);
		$location = $calinfo["VCALENDAR"]["VEVENT"]["LOCATION"][0]["value"];
		$transp = $calinfo["VCALENDAR"]["VEVENT"]["TRANSP"][0]["value"];
		$uid = $calinfo["VCALENDAR"]["VEVENT"]["UID"][0]["value"];
		$dtstamp = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTAMP"][0]["value"],$stampzid,"");
		$priority = $calinfo["VCALENDAR"]["VEVENT"]["PRIORITY"][0]["value"];
		$organizer=$calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["value"]."]";

		$attendees="";
		for($i=0;$i<count($calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"]);$i++)
		{
			if($attendees=="")
				$attendees=$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["value"]."]";
			else
				$attendees.=", ".$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["value"]."]";
		}

		$iCalendar_TMP_Text = str_replace("[[ORGANIZER]]",$organizer,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[SUBJECT]]",$summary,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[STARTDATETIME]]",$dtstart[1]." ".$dtstart[3],$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[ENDDATETIME]]",$dtend[1]." ".$dtend[3],$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[ALLDAYEVENT]]",$allday_event,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[LOCATION]]",$location,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[ATTENDEES]]",$attendees,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[DESCRIPTION]]",$description,$iCalendar_TMP_Text);

		return $iCalendar_TMP_Text;
	}

	function getRecurrenceText($val)
	{
		$msg = "Occurs";
		$arr = explode("|",$val);

		$weeks   = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");
		$months = array("January","February","March","April","May","June","July","August","September","October","November","December");

		switch($arr[0])
		{
			case "daily":
				switch($arr[1])
				{
					case "day":
						$msg .= $arr[2]==1 ? " Every day " : " Every ".$arr[2]." days";
					break;
					case "week":
						$msg .= " Every weekday ";
					break;
					case "regenerate":
						$msg .= $arr[2]==1 ? " 1 day" : $arr[2]." days";
					break;
				}
			break;
			case "weekly":
				switch($arr[1])
				{
					case "":
						$msg .= " Every ";
						$temp = explode(":",$arr[4]);
						if(count($temp) > 1)
							$msg .=  $arr[3]." weeks on ";
						for($i=0;$i<count($temp);$i++)
							$msg .= " ".$weeks[$temp[$i]];
					break;
					case "regenerate":
						$msg .= $arr[2]==1 ? " 1 week" : $arr[2]." weeks";
					break;
					case "day":
						$msg .= " Every ";
						$temp = explode(":",$arr[4]);
						if(count($temp) >= 1)
							$msg .=  $arr[3]." weeks on ";
						for($i=0;$i<count($temp);$i++)
							$msg .= " ".$weeks[$temp[$i]];
					break;
				}
			break;
			case "monthly":
				switch($arr[1])
				{
					case "day":
						$msg .= " day ".$arr[2]." of every ".$arr[4]." month(s)";
					break;
					case "month":
						$msg .= " the ".$arr[2]." ".$arr[3]." of every ".$arr[4]." month(s)";
					break;
					case "regenerate":
						$msg .= $arr[2]==1 ? " 1 month" : $arr[2]." months";
					break;
				}
			break;
			case "yearly":
				switch($arr[1])
				{
					case "day":
						$msg .= " every ".$months[$arr[4]-1]." ".$arr[2];
					break;
					case "month":
						$msg .= " the ".$arr[2]." ".$arr[3]." of ".$months[$arr[4]-1];
					break;
					case "regenerate":
						$msg .= $arr[2]==1 ? " 1 year" : $arr[2]." years";
					break;
				}
			break;
		}

		if(isset($rrules["COUNT"]))
		{
			$rrules["appt_enddate_option"]="endafter";
			$rrules["appt_no_occurances"]=$rrules["COUNT"];
			$rrules["appt_enddate"]="";
		}
		else if(isset($rrules["UNTIL"]))
		{
			$endate = $this->extractDateTime($rrules["UNTIL"]);
			$rrules["appt_enddate_option"]="endby";
			$rrules["appt_no_occurances"]="";
			$rrules["appt_enddate"]=$endate[0];
		}
		else
		{
			$rrules["appt_enddate_option"]="noenddate";
			$rrules["appt_no_occurances"]="";
			$rrules["appt_enddate"]="";
		}

		$msg .= " effective ".$arr[7];

		if($arr[5]=="endafter")
			$msg .= " for ".$arr[6]." times ";
		else if($arr[5]=="endby")
			$msg .= " until ".date('m/d/Y',$arr[11])." ";
		else
			$msg .= " until no end date ";

		if($arr[10] != "allday")
			$msg .= " from ".date('h:i A',strtotime($arr[8]))." to ".date('h:i A',strtotime($arr[9]));

		return $msg;
	}

	function getParentID($appsno)
	{
		global $maildb,$db;

		$pque="select parid from appointments where sno=$appsno";
		$pres=mysql_query($pque,$db);
		$prow=mysql_fetch_row($pres);
		if($prow[0]>0)
			$parid=$prow[0];
		else
			$parid=$appsno;

		return $parid;
	}

	function prepareRecurrence()
	{
		global $rep_type,$rec_type,$rec_subtype,$rec_day,$rec_week,$rec_month,$rec_endoption,$appt_enddate,$occurence;

		$byday = array("SU" => 0,"MO" => 1,"TU" => 2,"WE" => 3,"TH" => 4,"FR" => 5,"SA" => 6);
		$bystep = array("first" => 1,"second" => 2,"third" => 3,"fourth" => 4,"last" => -1);
		$byweekday = array("Sunday" => "SU", "Monday" => "MO", "Tuesday" => "TU", "Wednesday" => "WE", "Thursday" => "TH", "Friday" => "FR", "Saturday" => "SA");

		$rrule = "";

		if($rec_type=="daily" && $rec_subtype=="day")
		{
			// EXAMPLE : RRULE:FREQ=DAILY;INTERVAL=1;WKST=SU

			$rrule="RRULE:FREQ=DAILY;INTERVAL=$rec_day;WKST=SU";
		}
		else if($rec_type=="daily" && $rec_subtype=="week")
		{
			// EXAMPLE : RRULE:FREQ=DAILY;INTERVAL=1;BYDAY=TU,TH;WKST=SU

			$rrule="RRULE:FREQ=DAILY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR;WKST=SU";
		}
		else if($rec_type=="weekly")
		{
			// EXAMPLE : RRULE:FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,FR;WKST=SU

			$sval = explode(':',$rec_month);
			$fval = implode(",",array_keys(array_intersect($byday, $sval)));

			$rrule="RRULE:FREQ=WEEKLY;INTERVAL=$rec_week;BYDAY=$fval;WKST=SU";
		}
		else if($rec_type=="monthly" && $rec_subtype=="day")
		{
			// EXAMPLE : RRULE:FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=9;WKST=SU

			$rrule="RRULE:FREQ=MONTHLY;INTERVAL=$rec_month;BYMONTHDAY=$rec_day;WKST=SU";
		}
		else if($rec_type=="monthly" && $rec_subtype=="month")
		{
			// EXAMPLE : RRULE:FREQ=MONTHLY;COUNT=10;INTERVAL=1;BYDAY=TU;BYSETPOS=2;WKST=SU

			//$rrule="RRULE:FREQ=MONTHLY;INTERVAL=$rec_month;BYDAY=$byweekday[$rec_week];BYSETPOS=$bystep[$rec_day];WKST=SU";
			$rrule="RRULE:FREQ=MONTHLY;INTERVAL=$rec_month;BYDAY=$bystep[$rec_day]$byweekday[$rec_week];WKST=SU";
		}
		else if($rec_type=="yearly" && $rec_subtype=="day")
		{
			// EXAMPLE : RRULE:FREQ=YEARLY;UNTIL=20180609T000000Z;INTERVAL=1;BYMONTHDAY=9;BYMONTH=6;WKST=SU

			$rrule="RRULE:FREQ=YEARLY;INTERVAL=1;BYMONTHDAY=$rec_day;BYMONTH=$rec_month;WKST=SU";
		}
		else if($rec_type=="yearly" && $rec_subtype=="month")
		{
			// EXAMPLE : RRULE:FREQ=YEARLY;UNTIL=20180609T000000Z;INTERVAL=1;BYDAY=TU;BYMONTH=6;BYSETPOS=2

			//$rrule="RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=$byweekday[$rec_week];BYSETPOS=$bystep[$rec_day];BYMONTH=$rec_month";
			$rrule="RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=$bystep[$rec_day]$byweekday[$rec_week];BYMONTH=$rec_month";
		}

		if($rec_endoption=="endafter" && isset($occurence))
			$rrule.=";COUNT=$occurence";
		else if($rec_endoption=="endby" && isset($appt_enddate))
			$rrule.=";UNTIL=".$this->convertDateTime($appt_enddate,"");

		return $rrule;
	}


	function parseRecurrence($rrule)
	{
		$rrules = array();
		$rrules["recurrence"] = "recurrence";
		$byday = array("SU","MO","TU","WE","TH","FR","SA");
		$bystep = array("","first","second","third","fourth","last");
		$byweekday = array("SU" => "Sunday", "MO" => "Monday", "TU" => "Tuesday", "WE" => "Wednesday", "TH" => "Thursday", "FR" => "Friday", "SA" => "Saturday");

		$rrule = split (';', $rrule);
		foreach ($rrule as $recur) 
		{
			ereg ('(.*)=(.*)', $recur, $regs);
			$rrule_array[$regs[1]] = $regs[2];

			foreach ($rrule_array as $key => $val)
			{
				switch($key) 
				{
					case 'FREQ':
						$rrules["FREQ"] = strtolower($val);
					break;
					case 'COUNT':
						$rrules["COUNT"] = $val;
					break;
					case 'UNTIL':
						$rrules["UNTIL"] = $val;
					break;
					case 'INTERVAL':
						$rrules["INTERVAL"] = $val;
					break;
					case 'BYDAY':
						$rrules["BYDAY"] = $val;
						if(ereg_replace("[^0-9]","",$rrules["BYDAY"])!="")
						{
							// Use case is BYSETPOS AND BYDAY are included in BYDAY part.
							$temp=ereg_replace("[^A-Za-z]","",$rrules["BYDAY"]);
							$rrules["BYSETPOS"]=str_replace($temp,"",$rrules["BYDAY"]);
							$rrules["BYDAY"]=ereg_replace("[^A-Za-z]","",$rrules["BYDAY"]);

							if($rrules["BYSETPOS"]=="-1")
								$rrules["BYSETPOS"] = 5;
						}
					break;
					case 'BYMONTHDAY':
						$rrules["BYMONTHDAY"] = $val;
					break;
					case 'BYMONTH':
						$rrules["BYMONTH"] = $val;
					break;
					case 'BYSETPOS':
						if($rrules["BYSETPOS"]=="")
							$rrules["BYSETPOS"] = $val;
					break;
				}
			}
		}

		if($rrules["INTERVAL"]=="")
			$rrules["INTERVAL"] = 1;

		if(isset($rrules["COUNT"]))
		{
			$rrules["appt_enddate_option"]="endafter";
			$rrules["appt_no_occurances"]=$rrules["COUNT"];
			$rrules["appt_enddate"]="";
		}
		else if(isset($rrules["UNTIL"]))
		{
			$endate = $this->extractDateTime($rrules["UNTIL"]);
			$rrules["appt_enddate_option"]="endby";
			$rrules["appt_no_occurances"]="";
			$rrules["appt_enddate"]=$endate[0];
		}
		else
		{
			$rrules["appt_enddate_option"]="noenddate";
			$rrules["appt_no_occurances"]="";
			$rrules["appt_enddate"]="";
		}

		if($rrules["FREQ"]=="daily" && !isset($rrules["BYDAY"]))
		{
			// EXAMPLE : RRULE:FREQ=DAILY;INTERVAL=1;WKST=SU

			$rrules["appt_recurrence_type"]=$rrules["FREQ"];
			$rrules["appt_recurrence_subtype"]="day";
			$rrules["appt_recurrence_day"]=$rrules["INTERVAL"];
			$rrules["appt_recurrence_week"]="";
			$rrules["appt_recurrence_month"]="";
		}
		else if($rrules["FREQ"]=="daily" && isset($rrules["BYDAY"]))
		{
			// EXAMPLE : RRULE:FREQ=DAILY;INTERVAL=1;BYDAY=TU,TH;WKST=SU

			$rrules["appt_recurrence_type"]=$rrules["FREQ"];
			$rrules["appt_recurrence_subtype"]="week";
			$rrules["appt_recurrence_day"]="";
			$rrules["appt_recurrence_week"]="";
			$rrules["appt_recurrence_month"]="";
		}
		else if($rrules["FREQ"]=="weekly" && isset($rrules["BYDAY"]))
		{
			// EXAMPLE : RRULE:FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,TU,FR;WKST=SU

			$sval = explode(',',$rrules["BYDAY"]);
			$fval = array_intersect($byday, $sval);

			$rrules["appt_recurrence_type"]=$rrules["FREQ"];
			$rrules["appt_recurrence_subtype"]="";
			$rrules["appt_recurrence_day"]="";
			$rrules["appt_recurrence_week"]=$rrules["INTERVAL"];
			$rrules["appt_recurrence_month"]=implode(":",array_keys($fval));
		}
		else if($rrules["FREQ"]=="monthly" && !isset($rrules["BYDAY"]))
		{
			// EXAMPLE : RRULE:FREQ=MONTHLY;INTERVAL=1;BYMONTHDAY=9;WKST=SU

			$rrules["appt_recurrence_type"]=$rrules["FREQ"];
			$rrules["appt_recurrence_subtype"]="day";
			$rrules["appt_recurrence_day"]=$rrules["BYMONTHDAY"];
			$rrules["appt_recurrence_week"]="";
			$rrules["appt_recurrence_month"]=$rrules["INTERVAL"];
		}
		else if($rrules["FREQ"]=="monthly" && isset($rrules["BYDAY"]))
		{
			// EXAMPLE : RRULE:FREQ=MONTHLY;COUNT=10;INTERVAL=1;BYDAY=TU;BYSETPOS=2;WKST=SU

			$rrules["appt_recurrence_type"]=$rrules["FREQ"];
			$rrules["appt_recurrence_subtype"]="month";
			$rrules["appt_recurrence_day"]=$bystep[$rrules["BYSETPOS"]];
			$rrules["appt_recurrence_week"]=$byweekday[$rrules["BYDAY"]];
			$rrules["appt_recurrence_month"]=$rrules["INTERVAL"];
		}
		else if($rrules["FREQ"]=="yearly" && !isset($rrules["BYDAY"]))
		{
			// EXAMPLE : RRULE:FREQ=YEARLY;UNTIL=20180609T000000Z;INTERVAL=1;BYMONTHDAY=9;BYMONTH=6;WKST=SU

			$rrules["appt_recurrence_type"]=$rrules["FREQ"];
			$rrules["appt_recurrence_subtype"]="day";
			$rrules["appt_recurrence_day"]=$rrules["BYMONTHDAY"];
			$rrules["appt_recurrence_week"]="";
			$rrules["appt_recurrence_month"]=$rrules["BYMONTH"];
		}
		else if($rrules["FREQ"]=="yearly" && isset($rrules["BYDAY"]))
		{
			// EXAMPLE : RRULE:FREQ=YEARLY;UNTIL=20180609T000000Z;INTERVAL=1;BYDAY=TU;BYMONTH=6;BYSETPOS=2

			$rrules["appt_recurrence_type"]=$rrules["FREQ"];
			$rrules["appt_recurrence_subtype"]="month";
			$rrules["appt_recurrence_day"]=$bystep[$rrules["BYSETPOS"]];
			$rrules["appt_recurrence_week"]=$byweekday[$rrules["BYDAY"]];
			$rrules["appt_recurrence_month"]=$rrules["BYMONTH"];
		}

		return $rrules;
	}

	function getDateTime($data)
	{
		preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})T{0,1}([0-9]{0,2})([0-9]{0,2})/', $data, $regs);

		if($regs[4]=="" || $regs[5]=="")
			$unixtime = mktime(0, 0, 0, $regs[2], $regs[3], $regs[1]);
		else
			$unixtime = mktime($regs[4], $regs[5], 0, $regs[2], $regs[3], $regs[1]);

		return $unixtime;
	}

	function extractDateTime($data,$tzid="",$event)
	{
		global $maildb,$db,$username;

		if(!function_exists("getUserSTZOffset"))
		{
			if(file_exists(dirname(__FILE__)."/saveactivities.inc"))
				require_once("saveactivities.inc");
			else
				require_once("saveactivities.php");
		}

		if($event=="allday")
		{
			$diff = $this->calcTime("+0000", $this->utz);
		}
		else
		{
			if($tzid=="")
				$diff = 0;
			else
				$diff = $this->calcTime($this->sdtosf, "+0000");
		}

		preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})T{0,1}([0-9]{0,2})([0-9]{0,2})/', $data, $regs);
		if($event=="allday")
			$unixtime = mktime(0, 0, 0, $regs[2], $regs[3], $regs[1]) - $diff;
		else
			$unixtime = mktime($regs[4], $regs[5], 0, $regs[2], $regs[3], $regs[1]) + $diff;

		$utzos = getUserSTZOffset();
		$sent_utzos = getUserTZOffset($unixtime);
		$unixtime = $unixtime - ($utzos - $sent_utzos);

		$extdate = date('m/d/Y', $unixtime);
		$exttime = date('H:i', $unixtime);

		if($event=="allday")
			$aptime = date('h:iA', $unixtime + $this->utz);            // ???????????????*******?????????
		else
			$aptime = date('h:iA', $unixtime);

		return array($unixtime, $extdate, $exttime, $aptime);
	}

	function parseDateTime($data,$tzid="",$event)
	{
		if($tzid=="")
			$diff = $this->calcTime("+0000", $this->utz);
		else
			$diff = $this->calcTime($this->sdtosf, $this->utz);

		preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})T{0,1}([0-9]{0,2})([0-9]{0,2})/', $data, $regs);
		if($event=="allday")
			$unixtime = mktime(0, 0, 0, $regs[2], $regs[3], $regs[1]);
		else
			$unixtime = mktime($regs[4], $regs[5], 0, $regs[2], $regs[3], $regs[1]) + $diff;

		$extdate = date('m/d/Y', $unixtime);
		$exttime = date('H:i', $unixtime);
		$aptime = date('h:iA', $unixtime);

		return array($unixtime, $extdate, $exttime, $aptime);
	}

	function calcTime($have, $want)
	{
		$have_secs = $this->calcOffset($have);
		$want_secs = $this->calcOffset($want);
		$diff = $want_secs - $have_secs;
		return $diff;
	}

	function calcOffset($offset_str)
	{
		$sign = substr($offset_str, 0, 1);
		$hours = substr($offset_str, 1, 2);
		$mins = substr($offset_str, 3, 2);
		$secs = ((int)$hours * 3600) + ((int)$mins * 60);
		if ($sign == '-') 
			$secs = 0 - $secs;
		return $secs;
	}

	function convertDateTime($idatetime,$event="")
	{
		//We may have to SWITCH Back to getUserTZOffset in DAYLIGHT Savings.
		//$sent_utzos = getUserTZOffset($idatetime);

		$sent_utzos = getUserSTZOffset();

		$idatetime = $idatetime + $sent_utzos;

		if($event == "allday")
			return date('Ymd',$idatetime);
		else
			return date('Ymd',$idatetime)."T".date('His',$idatetime);
	}

	function replyDateTime($data,$utz="",$event)
	{
		if($data=="")
		{
			return date('Ymd')."T".date('His');
		}
		else
		{
			if($utz=="")
				$diff = 0;
			else
				$diff = $this->calcTime($this->sdtosf, "+0000");

			preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})T{0,1}([0-9]{0,2})([0-9]{0,2})/', $data, $regs);

			if($regs[4]=="" || $regs[5]=="")
				$unixtime = mktime(0, 0, 0, $regs[2], $regs[3], $regs[1]);
			else
				$unixtime = mktime($regs[4], $regs[5], 0, $regs[2], $regs[3], $regs[1]) + $diff;

			if($event == "allday")
				return date('Ymd',$unixtime);
			else
				return date('Ymd',$unixtime)."T".date('His',$unixtime);
		}
	}

	function replyTZDateTime($data,$tzid="",$event)
	{
		if($tzid=="")
			$diff = 0;
		else
			$diff = $this->calcTime($this->sdtosf, "+0000");

		preg_match ('/([0-9]{4})([0-9]{2})([0-9]{2})T{0,1}([0-9]{0,2})([0-9]{0,2})/', $data, $regs);

		if($regs[4]=="" || $regs[5]=="")
		{
			$unixtime = mktime(0, 0, 0, $regs[2], $regs[3], $regs[1]);
		}
		else
		{
			$unixtime = mktime($regs[4], $regs[5], 0, $regs[2], $regs[3], $regs[1]) + $diff;
			$unixtime = $unixtime + getUserTZOffset($unixtime);
		}

		if($event == "allday")
			return date('Ymd',$unixtime);
		else
			return date('Ymd',$unixtime)."T".date('His',$unixtime);
	}

	function parseRequest($calinfo,$rtype)
	{
		global $db;

		if($this->sdt=="S")
		{
			$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
		}
		else
		{
			if($calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"]!="")
				$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"];
			else
				$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
		}

		if($sdtosf!="")
			$this->sdtosf = $sdtosf;

		$uid = $calinfo["VCALENDAR"]["VEVENT"]["UID"][0]["value"];
		$sequence = $calinfo["VCALENDAR"]["VEVENT"]["SEQUENCE"][0]["value"];
		$stzid = $calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["TZID"];
		$etzid = $calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["TZID"];
		$stampzid = $calinfo["VCALENDAR"]["VEVENT"]["DTSTAMP"][0]["param"]["TZID"];

		$rrule = $calinfo["VCALENDAR"]["VEVENT"]["RRULE"][0]["value"];

		if($rrule!="")
			$rrules = $this->parseRecurrence($rrule);

		require("iCalendar_TMP.inc");

		if(strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["VALUE"])=="date" || strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["VALUE"])=="date")
		{
			$event = "allday";
			$allday_event = " <b>(Allday Event)</b>";
		}
		else
		{
			$event = "none";
		}

		if($calinfo["VCALENDAR"]["VEVENT"]["DESCRIPTION"][0]["value"]!="")
			$description = $calinfo["VCALENDAR"]["VEVENT"]["DESCRIPTION"][0]["value"];
		else
			$description = $calinfo["VCALENDAR"]["VEVENT"]["COMMENT"][0]["value"];

		$description = nl2br(stripslashes($description));

		$summary = $calinfo["VCALENDAR"]["VEVENT"]["SUMMARY"][0]["value"];
		$class = $calinfo["VCALENDAR"]["VEVENT"]["CLASS"][0]["value"];
		$dtstart = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["value"],$stzid,$event);
		$dtend = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["value"],$etzid,$event);
		$location = $calinfo["VCALENDAR"]["VEVENT"]["LOCATION"][0]["value"];
		$transp = $calinfo["VCALENDAR"]["VEVENT"]["TRANSP"][0]["value"];
		$dtstamp = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTAMP"][0]["value"],$stampzid,"");
		$priority = $calinfo["VCALENDAR"]["VEVENT"]["PRIORITY"][0]["value"];
		$organizer=$calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["value"]."]";

		$whenoccurs = $this->occurenceText($dtstart,$dtend,$event,$rrules);

		$attendees="";
		for($i=0;$i<count($calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"]);$i++)
		{
			if($attendees=="")
				$attendees=$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["value"]."]";
			else
				$attendees.=", ".$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["value"]."]";
		}

		$iCalendar_TMP_Text = str_replace("[[ORGANIZER]]",$organizer,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[SUBJECT]]",$summary,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[WHENOCCURS]]",$whenoccurs,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[ALLDAYEVENT]]",$allday_event,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[LOCATION]]",$location,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[ATTENDEES]]",$attendees,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[DESCRIPTION]]",$description,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[LINK_OPTIONS]]",$link_options,$iCalendar_TMP_Text);

		return $iCalendar_TMP_Text;
	}

	function parseReply($calinfo,$rtype)
	{
		require("iCalendar_TMP.inc");

		if($this->sdt=="S")
		{
			$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
		}
		else
		{
			if($calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"]!="")
				$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"];
			else
				$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
		}

		if($sdtosf!="")
			$this->sdtosf = $sdtosf;

		$stzid = $calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["TZID"];
		$etzid = $calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["TZID"];
		$stampzid = $calinfo["VCALENDAR"]["VEVENT"]["DTSTAMP"][0]["param"]["TZID"];

		$rrule = $calinfo["VCALENDAR"]["VEVENT"]["RRULE"][0]["value"];
		if($rrule!="")
		{
			$rrules = $this->parseRecurrence($rrule);
		}

		if(strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["VALUE"])=="date" || strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["VALUE"])=="date")
		{
			$event = "allday";
			$allday_event = " <b>(Allday Event)</b>";
		}
		else
		{
			$event = "none";
		}

		if($calinfo["VCALENDAR"]["VEVENT"]["DESCRIPTION"][0]["value"]!="")
			$description = $calinfo["VCALENDAR"]["VEVENT"]["DESCRIPTION"][0]["value"];
		else
			$description = $calinfo["VCALENDAR"]["VEVENT"]["COMMENT"][0]["value"];

		$description = nl2br(stripslashes($description));

		$summary = $calinfo["VCALENDAR"]["VEVENT"]["SUMMARY"][0]["value"];
		$class = $calinfo["VCALENDAR"]["VEVENT"]["CLASS"][0]["value"];
		$dtstart = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["value"],$stzid,$event);
		$dtend = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["value"],$etzid,$event);
		$location = $calinfo["VCALENDAR"]["VEVENT"]["LOCATION"][0]["value"];
		$transp = $calinfo["VCALENDAR"]["VEVENT"]["TRANSP"][0]["value"];
		$uid = $calinfo["VCALENDAR"]["VEVENT"]["UID"][0]["value"];
		$dtstamp = $this->parseDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTAMP"][0]["value"],$stampzid,"");
		$priority = $calinfo["VCALENDAR"]["VEVENT"]["PRIORITY"][0]["value"];
		if(trim($calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["value"]) != "")
		$organizer=$calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["value"]."]";
		else
		$organizer=$calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["param"]["CN"];
		
		$whenoccurs = $this->occurenceText($dtstart,$dtend,$event,$rrules);

		$attendees="";
		$attendees=$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][0]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][0]["value"]."]";
		$reply_action=$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][0]["param"]["PARTSTAT"];
		if($reply_action=="TENTATIVE")
			$reply_action="TENTATIVELY ACCEPTED";

		$iCalendar_TMP_Text = str_replace("[[ORGANIZER]]",$organizer,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[SUBJECT]]",$summary,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[WHENOCCURS]]",$whenoccurs,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[ALLDAYEVENT]]",$allday_event,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[LOCATION]]",$location,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[ATTENDEES]]",$attendees,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[REPLY_ACTION]]",$reply_action,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("[[DESCRIPTION]]",$description,$iCalendar_TMP_Text);
		$iCalendar_TMP_Text = str_replace("Description:","Comments:",$iCalendar_TMP_Text);

		return $iCalendar_TMP_Text;
	}

	function occurenceText($start,$end,$event,$rrules)
	{
		if(count($rrules)>0)
		{
			$recurrence = $rrules["appt_recurrence_type"]."|".$rrules["appt_recurrence_subtype"]."|".$rrules["appt_recurrence_day"]."|".$rrules["appt_recurrence_week"]."|".$rrules["appt_recurrence_month"]."|".$rrules["appt_enddate_option"]."|".(($rrules["appt_no_occurances"] == 0) ? "" : $rrules["appt_no_occurances"])."|".$start[1]."|".$start[2]."|".$end[2]."|".$event."|".$rrules["appt_enddate"];
			return $this->getRecurrenceText($recurrence);
		}
		else
		{
			if($event == "allday")
			{
				return date("l, F j, Y h:iA",$start[0])." to ".date("l, F j, Y h:iA",($start[0] + 86400)).".";
			}
			else
			{
				if($start[1] == $end[1])
					return date("l, F j, Y ",$start[0]).$start[3]." - ".$end[3].".";
				else
					return date("l, F j, Y ",$start[0]).$start[3]." ".$start[3]." to ".date("l, F j, Y ",$end[0])." ".$end[3].".";
			}
		}
	}

	function prepareRequest($appsno,$dinv_list,$sdatetime,$edatetime,$events,$title,$desc,$show,$remtime,$con_id,$colprivate,$appt_location,$appt_showtime,$rep_type,$rec_type,$rec_subtype,$rec_day,$rec_week,$rec_month,$rec_endoption,$appt_enddate,$occurence,$uid,$ninv_list,$oinv_list,$notify_guests,$invite_action,$emaillist,$exception,$orgusername)
	{
		global $utzos,$curtime_header,$smtp,$maildb,$db,$act_name,$companyuser,$username,$domainname,$disus,$maildomain,$akken_arec_port,$boun_mixed_part,$boun_alternative_part;

		$efrom=getSubDomainEmail();		// This is the function to get the Sub domain mail id. Defined in global_fun.inc file
		$event = $events;
		$invitation_current_status = "NEW";

		$tzque="select timezone.phpvar as TZID,REPLACE(timezone.stdtime,':','') as STDTIME,REPLACE(timezone.daytime,':','') as DAYTIME FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid=$username";
		$tzres=mysql_query($tzque,$db);
		$tzrow=mysql_fetch_array($tzres);

		if($uid=="")
			$uid = "AKKENICS@".time()."@$companyuser@$username@$appsno";
		else
			$invitation_current_status = "OLD";

		$que="update appointments set uid='".addslashes($uid)."' where sno=$appsno";
		mysql_query($que,$db);

		$parid = $this->getParentID($appsno);
		if($parid!=$appsno)
		{
			$eque="select exptime from appointments where sno=$appsno";
			$eres=mysql_query($eque,$db);
			$erow=mysql_fetch_row($eres);
			$exception = $erow[0];
		}

		$attendees="";

		foreach($oinv_list as $i => $temp_list)
		{
			if($attendees=="")
			{
				if($emaillist[$oinv_list[$i]]=="")
					$attendees.="ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$oinv_list[$i].":MAILTO:".$oinv_list[$i];
				else
					$attendees.="ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$emaillist[$oinv_list[$i]].":MAILTO:".$oinv_list[$i];
			}
			else
			{
				if($emaillist[$oinv_list[$i]]=="")
					$attendees.="\n"."ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$oinv_list[$i].":MAILTO:".$oinv_list[$i];
				else
					$attendees.="\n"."ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$emaillist[$oinv_list[$i]].":MAILTO:".$oinv_list[$i];
			}
		}

		foreach($ninv_list as $i => $temp_list)
		{
			if($attendees=="")
			{
				if($emaillist[$ninv_list[$i]]=="")
					$attendees.="ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$ninv_list[$i].":MAILTO:".$ninv_list[$i];
				else
					$attendees.="ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$emaillist[$ninv_list[$i]].":MAILTO:".$ninv_list[$i];
			}
			else
			{
				if($emaillist[$ninv_list[$i]]=="")
					$attendees.="\n"."ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$ninv_list[$i].":MAILTO:".$ninv_list[$i];
				else
					$attendees.="\n"."ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$emaillist[$ninv_list[$i]].":MAILTO:".$ninv_list[$i];
			}
		}

		if(count($dinv_list)>0)
		{
			$da_attendees="";
			foreach($dinv_list as $i => $temp_list)
			{
				if($da_attendees=="")
					$da_attendees.="ATTENDEE:MAILTO:".$dinv_list[$i];
				else
					$da_attendees.="\n"."ATTENDEE:MAILTO:".$dinv_list[$i];
			}
		}

		if($rep_type=="recurrence" && isset($rec_type))
			$rrule = $this->prepareRecurrence();

		if($exception=="" || $exception=="all")
		{
			$uque = "UPDATE appointments SET exception='', exptime='0' WHERE sno=$appsno";
			mysql_query($uque,$db);

			$this->deleteAppointmentHistory($parid);

			// NOTE: We have to include the below include file after RRULE, EXCEPTION STRINGS are defined.
			require("iCalendar_REQUEST.inc");
		}
		else
		{
			require("iCalendar_OCCURENCE.inc");

			$event_duration = $edatetime - $sdatetime;
			$recurrenceid = str_replace("Z","",$this->convertDateTime($exception,$event));
		}

		$sequence = "0";
		if($invite_action=="add" && ($exception=="" || $exception=="all"))
		{
			$inviteaction = "REQUEST";
			$sequence = "0";
		}
		else
		{
			$inviteaction = "REQUEST";

			$sque="select (sequence+1) from appointments where sno=$parid";
			$sres=mysql_query($sque,$db);
			$srow=mysql_fetch_row($sres);

			$sequence = $srow[0];

			$que="update appointments set sequence=$sequence where sno=$parid OR parid=$parid";
			mysql_query($que,$db);
		}

		if($sequence=="")
			$sequence = "0";

		require("setSMTP.php");

		if($event=="allday")
			$allday_event = ";VALUE=DATE";
		else
			$allday_event = "";

		if($appt_location!="")
		{
			$que="select name from manage where type='appt_location' AND sno=$appt_location";
			$res=mysql_query($que,$db);
			$row=mysql_fetch_row($res);
			$location = $row[0];
		}

		if($orgusername!=$username && $orgusername!="")
		{
			$que_users="SELECT h.username,TRIM(CONCAT(h.fname,' ',h.lname)) as name, h.email FROM hrcon_general h WHERE h.username='".$orgusername."' AND h.ustatus='active'";
			$res_users=mysql_query($que_users,$db);
			$row_users=mysql_fetch_row($res_users);

			$organizer_cn = $row_users[1];
			$organizer = $row_users[2];
		}
		else
		{
			$organizer_cn = $act_name;
			$organizer = $reqemail;
		}

		$summary = $title;
		$description = $desc;
		$priority = 5;
		$class = "PUBLIC";
		$transp = "OPAQUE";

		$dtstart = $this->convertDateTime($sdatetime,$event);

		if($event=="allday")
			$dtend = $this->convertDateTime($edatetime+"3600",$event);
		else
			$dtend = $this->convertDateTime($edatetime,$event);

		$dtstamp = $this->convertDateTime((time() - $utzos),"");

		if($orgusername==$username)
		{
			if($sequence>0)
				$message_body = $organizer_cn." [".$organizer."] has update calendar event -- ".$summary;
			else
				$message_body = $organizer_cn." [".$organizer."] has sent an Invitation -- ".$summary;
		}

		$iCalendar_REQUEST = str_replace("[[TZID]]",$tzrow['TZID'],$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[STDTIME]]",$tzrow['STDTIME'],$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DAYTIME]]",$tzrow['DAYTIME'],$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[ORGANIZER_CN]]",$organizer_cn,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[ORGANIZER]]",$organizer,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[SUMMARY]]",stripslashes($summary),$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[LOCATION]]",$location,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[TRANSP]]",$transp,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[CLASS]]",$class,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[PRIORITY]]",$priority,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DESCRIPTION]]",str_replace("\r\n", "\\n",$description),$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[UID]]",$uid,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DTSTAMP]]",$dtstamp,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DTSTART]]",$dtstart,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[RECURRENCE-ID]]",$recurrenceid,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[ALLDAYEVENT]]",$allday_event,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DTEND]]",$dtend,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[SEQUENCE]]",$sequence,$iCalendar_REQUEST);

		if(count($dinv_list)>0)
		{
			// We need to send cancel notification if an old invitee is deleted from the invitation. We have to prepare the text for CANCEL of INVITEACTION before preparing for REQUEST.

			$iCalendar_Cancel_REQUEST = str_replace("[[ALL_ATTENDEES]]",$da_attendees,$iCalendar_REQUEST);
			$iCalendar_Cancel_REQUEST = str_replace("[[INVITEACTION]]","CANCEL",$iCalendar_Cancel_REQUEST);
		}

		$iCalendar_REQUEST = str_replace("[[ALL_ATTENDEES]]",$attendees,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[INVITEACTION]]",$inviteaction,$iCalendar_REQUEST);

		$final_list = array_merge($oinv_list,$ninv_list);
		if($notify_guests==1)
			$final_tolist = array_merge($oinv_list,$ninv_list);
		else
			$final_tolist = $ninv_list;

		$from = "$organizer_cn <$organizer>";
		$to = implode(",",$final_tolist);

		$subject = stripslashes($summary);
		$ato=quotesplit($to,$split=",",$temp="^");

		if($invitation_current_status=="OLD")
			$invitation_mail_status = "updated";	
		else
			$invitation_mail_status = "sent";

		$invitation_mail_owner = $organizer;
		$invitation_mail_eventtype = "";
		$invitation_mail_subject = $subject;
		$invitation_mail_when = date('m/d/Y h:iA',($sdatetime + $utzos)) ." - ". date('m/d/Y h:iA',($edatetime + $utzos));
		$invitation_mail_location = $location;
		$invitation_mail_attendees = implode(", ",$final_tolist);
		$invitation_mail_desc = $description;	

		$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: ","Subject: $subject","MIME-Version: 1.0");
		array_push($mailheaders,"Content-Type: multipart/mixed; boundary=\"Message-Boundary$boun_mixed_part\"","Content-Transfer-Encoding: 7bit");
		$mail_body = $this->attachICS($iCalendar_REQUEST,"REQUEST",$message_body);
		$flag = $this->attachDocuments($appsno,$mail_body);
		$mail_body.="--Message-Boundary$boun_mixed_part--\n";
		$suc=$smtp->SendMessage($from,$ato,$mailheaders,$mail_body) ? "true" : "false";

		$folder="sentmessages";
		$stats="Active";
		$mailtype="text/html";
		$flag = 0;
		$fromTo = "";

		if($suc=="true")
		{
			if($invitation_mail_status = "sent")
				$invitation_mail_subject = "Invitation: ".$invitation_mail_subject;

			$invitation_mail_body=prepareSentInvitation($invitation_mail_status,$invitation_mail_owner,$invitation_mail_subject,$invitation_mail_when,$invitation_mail_location,$invitation_mail_attendees,$invitation_mail_desc,$invitation_mail_eventtype);

			$fromTo = $from."|^|".$to;
			$suc = "invitationMail";

			$cmnid=prosinsert($fromTo,$invitation_mail_subject,stripslashes($invitation_mail_body),$mailtype,$suc,$flag,'','',$username);
			$last_id=mail_insert($folder,$from,$to,'','',stripslashes($invitation_mail_body),$invitation_mail_subject,'','',$attach,$mailtype,'yes',$stats,$cmnid);

			if($cmnid!="")
            {
                $qrycmn="update mail_headers set conid=".$cmnid." where mailid=".$last_id;
            	mysql_query($qrycmn,$db);
            }            
        }

		$dinv_list = array_diff($dinv_list,$final_list);

		if(count($dinv_list)>0)
		{
			$to = implode(",",$dinv_list);
			$ato=quotesplit($to,$split=",",$temp="^");

			$message_body = $organizer_cn." [".$organizer."] has Cancelled Invitation -- ".$summary;

			$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: ","Subject: $subject","MIME-Version: 1.0");
			array_push($mailheaders,"Content-Type: multipart/mixed; boundary=\"Message-Boundary$boun_mixed_part\"","Content-Transfer-Encoding: 7bit");
			$mail_body = $this->attachICS($iCalendar_Cancel_REQUEST,"CANCEL");
			$mail_body.="--Message-Boundary$boun_mixed_part--\n";
			$suc=$smtp->SendMessage($from,$ato,$mailheaders,$mail_body) ? "true" : "false";

			if($suc=="true")
			{
				$invitation_mail_status = "removed";
			 	$invitation_mail_attendees = implode(", ",$dinv_list);

				$invitation_mail_body=prepareSentInvitation($invitation_mail_status,$invitation_mail_owner,$invitation_mail_subject,$invitation_mail_when,$invitation_mail_location,$invitation_mail_attendees,$invitation_mail_desc,$invitation_mail_eventtype);

				$fromTo = $from."|^|".$to;
				$suc = "invitationMail";

				$cmnid=prosinsert($fromTo,$invitation_mail_subject,stripslashes($invitation_mail_body),$mailtype,$suc,$flag,'','',$username);
				$last_id=mail_insert($folder,$from,$to,'','',stripslashes($invitation_mail_body),$subject,'','',$attach,$mailtype,$sent,$stats,$cmnid);

				if($cmnid!="")
                {
                    $qrycmn="update mail_headers set conid=".$cmnid." where mailid=".$last_id;
                	mysql_query($qrycmn,$db);
                }
            }
		}
	}

	function prepareOccurrenceRequest($parid,$oinv_list,$ninv_list,$emaillist,$organizer_cn,$organizer)
	{
		global $utzos,$curtime_header,$smtp,$maildb,$db,$act_name,$companyuser,$username,$domainname,$disus,$maildomain,$akken_arec_port,$boun_mixed_part,$boun_alternative_part;

		$efrom=getSubDomainEmail();		// This is the function to get the Sub domain mail id. Defined in global_fun.inc file

		$tzque="select timezone.phpvar as TZID,REPLACE(timezone.stdtime,':','') as STDTIME,REPLACE(timezone.daytime,':','') as DAYTIME FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid=$username";
		$tzres=mysql_query($tzque,$db);
		$tzrow=mysql_fetch_array($tzres);

		$attendees="";

		foreach($oinv_list as $i => $temp_list)
		{
			if($attendees=="")
			{
				if($emaillist[$oinv_list[$i]]=="")
					$attendees.="ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$oinv_list[$i].":MAILTO:".$oinv_list[$i];
				else
					$attendees.="ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$emaillist[$oinv_list[$i]].":MAILTO:".$oinv_list[$i];
			}
			else
			{
				if($emaillist[$oinv_list[$i]]=="")
					$attendees.="\n"."ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$oinv_list[$i].":MAILTO:".$oinv_list[$i];
				else
					$attendees.="\n"."ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$emaillist[$oinv_list[$i]].":MAILTO:".$oinv_list[$i];
			}
		}

		foreach($ninv_list as $i => $temp_list)
		{
			if($attendees=="")
			{
				if($emaillist[$ninv_list[$i]]=="")
					$attendees.="ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$ninv_list[$i].":MAILTO:".$ninv_list[$i];
				else
					$attendees.="ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$emaillist[$ninv_list[$i]].":MAILTO:".$ninv_list[$i];
			}
			else
			{
				if($emaillist[$ninv_list[$i]]=="")
					$attendees.="\n"."ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$ninv_list[$i].":MAILTO:".$ninv_list[$i];
				else
					$attendees.="\n"."ATTENDEE;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=".$emaillist[$ninv_list[$i]].":MAILTO:".$ninv_list[$i];
			}
		}

		$rddates = array();
		$inviteaction = "REQUEST";

		$final_list = array_merge($oinv_list,$ninv_list);
		$to = implode(",",$final_list);
		$ato=quotesplit($to,$split=",",$temp="^");

		$cque="select sdatetime, edatetime, event, title, descri, location, uid, exptime from appointments where parid=$parid AND exptime>0 ORDER BY exptime";
		$cres=mysql_query($cque,$db);
		while($crow=mysql_fetch_array($cres))
		{
			$sdatetime = $crow['sdatetime'];
			$edatetime = $crow['edatetime'];
			$events = $crow['event'];
			$title = $crow['title'];
			$desc = $crow['descri'];
			$appt_location = $crow['location'];
			$uid = $crow['uid'];
			$exception = $crow['exptime'];

			require("iCalendar_OCCURENCE.inc");
			require("setSMTP.php");

			$sque="select (sequence+1) from appointments where sno=$parid";
			$sres=mysql_query($sque,$db);
			$srow=mysql_fetch_row($sres);
			$sequence = $srow[0];

			$que="update appointments set sequence=$sequence where sno=$parid OR parid=$parid";
			mysql_query($que,$db);

			if($event=="allday")
				$allday_event = ";VALUE=DATE";
			else
				$allday_event = "";

			if($appt_location!="")
			{
				$que="select name from manage where type='appt_location' AND sno=$appt_location";
				$res=mysql_query($que,$db);
				$row=mysql_fetch_row($res);
				$location = $row[0];
			}

			$summary = $title;
			$description = $desc;
			$priority = 5;
			$class = "PUBLIC";
			$transp = "OPAQUE";

			$dtstart = $this->convertDateTime($sdatetime,$event);
			$dtend = $this->convertDateTime($edatetime,$event);
			$dtstamp = $this->convertDateTime((time() - $utzos),"");
			$recurrenceid = str_replace("Z","",$this->convertDateTime($exception,$event));

			$message_body = $organizer_cn." [".$organizer."] has sent Invitation -- ".$summary;

			$iCalendar_REQUEST = str_replace("[[TZID]]",$tzrow['TZID'],$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[STDTIME]]",$tzrow['STDTIME'],$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[DAYTIME]]",$tzrow['DAYTIME'],$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[INVITEACTION]]",$inviteaction,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[ALL_ATTENDEES]]",$attendees,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[ORGANIZER_CN]]",$organizer_cn,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[ORGANIZER]]",$organizer,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[SUMMARY]]",stripslashes($summary),$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[LOCATION]]",$location,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[TRANSP]]",$transp,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[CLASS]]",$class,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[PRIORITY]]",$priority,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[DESCRIPTION]]",str_replace("\r\n", "\\n",$description),$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[UID]]",$uid,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[DTSTAMP]]",$dtstamp,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[DTSTART]]",$dtstart,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[RECURRENCE-ID]]",$recurrenceid,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[ALLDAYEVENT]]",$allday_event,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[DTEND]]",$dtend,$iCalendar_REQUEST);
			$iCalendar_REQUEST = str_replace("[[SEQUENCE]]",$sequence,$iCalendar_REQUEST);

			$subject = stripslashes($summary);
			$from = "$organizer_cn <$organizer>";

			$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: ","Subject: $subject","MIME-Version: 1.0");
			array_push($mailheaders,"Content-Type: multipart/mixed; boundary=\"Message-Boundary$boun_mixed_part\"","Content-Transfer-Encoding: 7bit");
			$mail_body = $this->attachICS($iCalendar_REQUEST,"REQUEST",$message_body);
			$flag = $this->attachDocuments($appsno,$mail_body);
			$mail_body.="--Message-Boundary$boun_mixed_part--\n";
			$smtp->SendMessage($from,$ato,$mailheaders,$mail_body);
		}
	}

	function notifyCancelInvitation($appsno,$exception)
	{
		global $utzos,$curtime_header,$smtp,$maildb,$db,$act_name,$companyuser,$username,$domainname,$disus,$maildomain,$akken_arec_port,$boun_mixed_part,$boun_alternative_part;

		$efrom=getSubDomainEmail();		// This is the function to get the Sub domain mail id. Defined in global_fun.inc file

		$tzque="select timezone.phpvar as TZID,REPLACE(timezone.stdtime,':','') as STDTIME,REPLACE(timezone.daytime,':','') as DAYTIME FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid=$username";
		$tzres=mysql_query($tzque,$db);
		$tzrow=mysql_fetch_array($tzres);

		$sque="select sdatetime,edatetime,event,title,descri,location,uid,sequence,recurrence,recurrence_type,recurrence_subtype,recurrence_day,recurrence_week,recurrence_month,enddate_option,enddate,no_occurances from appointments where sno=$appsno";
		$sres=mysql_query($sque,$db);
		$srow=mysql_fetch_array($sres);

		$uid = $srow['uid'];
		$sequence = ($srow['sequence']+1);
		$event = $srow['event'];
		$title = $srow['title'];
		$desc = $srow['descri'];
		$appt_location = $srow['location'];
		$sdatetime = $srow['sdatetime'];
		$edatetime = $srow['edatetime'];
		$rep_type = $srow['recurrence'];
		$rec_type = $srow['recurrence_type'];
		$rec_subtype = $srow['recurrence_subtype'];
		$rec_day = $srow['recurrence_day'];
		$rec_week = $srow['recurrence_week'];
		$rec_month = $srow['recurrence_month'];
		$rec_endoption = $srow['enddate_option'];
		$appt_enddate = $srow['enddate'];
		$occurence = $srow['no_occurances'];

		$parid = $this->getParentID($appsno);
		if($parid!=$appsno)
		{
			$eque="select exptime from appointments where sno=$appsno";
			$eres=mysql_query($eque,$db);
			$erow=mysql_fetch_row($eres);
			$exception = $erow[0];
		}

		$attendees="";
		$final_list="";

		$ique="select email from invite_calendar where appno=$appsno";
		$ires=mysql_query($ique,$db);
		while($irow=mysql_fetch_row($ires))
		{
			if($attendees=="")
			{
				$final_list=$irow[0];
				$attendees.="ATTENDEE:MAILTO:".$irow[0];
			}
			else
			{
				$final_list.=",".$irow[0];
				$attendees.="\n"."ATTENDEE:MAILTO:".$irow[0];
			}
		}

		if($rep_type=="recurrence" && isset($rec_type))
			$rrule = $this->prepareRecurrence();

		// NOTE: We have to include the below include files after RRULE is defined.
		if($exception=="all" || $exception=="")
		{
			require("iCalendar_REQUEST.inc");
		}
		else
		{
			require("iCalendar_OCCURENCE.inc");

			$event_duration = $edatetime - $sdatetime;
			$recurrenceid = str_replace("Z","",$this->convertDateTime($exception,$event));

			$sdatetime = $exception;
			$edatetime = $exception + $event_duration;
		}

		$sque="select (sequence+1) from appointments where sno=$parid";
		$sres=mysql_query($sque,$db);
		$srow=mysql_fetch_row($sres);
		$sequence = $srow[0];

		if($sequence=="")
			$sequence = "0";

		$que="update appointments set sequence=$sequence where sno=$parid OR parid=$parid";
		mysql_query($que,$db);

		$inviteaction = "CANCEL";

		require("setSMTP.php");

		if($event=="allday")
			$allday_event = ";VALUE=DATE";
		else
			$allday_event = "";

		if($appt_location!="")
		{
			$que="select name from manage where type='appt_location' AND sno=$appt_location";
			$res=mysql_query($que,$db);
			$row=mysql_fetch_row($res);
			$location = $row[0];
		}

		$organizer_cn = $act_name;
		$organizer = $reqemail;
		$summary = $title;
		$description = $desc;
		$priority = 5;
		$class = "PUBLIC";
		$transp = "OPAQUE";

		$dtstart = $this->convertDateTime($sdatetime,$event);
		$dtend = $this->convertDateTime($edatetime,$event);
		$dtstamp = $this->convertDateTime((time() - $utzos),"");

		$message_body = $organizer_cn." [".$organizer."] has Cancelled Invitation -- ".$summary;

		$iCalendar_REQUEST = str_replace("[[TZID]]",$tzrow['TZID'],$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[STDTIME]]",$tzrow['STDTIME'],$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DAYTIME]]",$tzrow['DAYTIME'],$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[ALL_ATTENDEES]]",$attendees,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[ORGANIZER_CN]]",$organizer_cn,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[ORGANIZER]]",$organizer,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[SUMMARY]]",stripslashes($summary),$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[LOCATION]]",$location,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[TRANSP]]",$transp,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[CLASS]]",$class,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[PRIORITY]]",$priority,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DESCRIPTION]]",str_replace("\r\n", "\\n",$description),$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[UID]]",$uid,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DTSTAMP]]",$dtstamp,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DTSTART]]",$dtstart,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[RECURRENCE-ID]]",$recurrenceid,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[ALLDAYEVENT]]",$allday_event,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[DTEND]]",$dtend,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[INVITEACTION]]",$inviteaction,$iCalendar_REQUEST);
		$iCalendar_REQUEST = str_replace("[[SEQUENCE]]",$sequence,$iCalendar_REQUEST);

		$from = "$organizer_cn <$organizer>";
		$to = $final_list;
		$subject = stripslashes($summary);
		$ato=quotesplit($to,$split=",",$temp="^");

		$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: ","Subject: $subject","MIME-Version: 1.0");
		array_push($mailheaders,"Content-Type: multipart/mixed; boundary=\"Message-Boundary$boun_mixed_part\"","Content-Transfer-Encoding: 7bit");
		$mail_body = $this->attachICS($iCalendar_REQUEST,"CANCEL",$message_body);
		$mail_body.="--Message-Boundary$boun_mixed_part--\n";
		$suc=$smtp->SendMessage($from,$ato,$mailheaders,$mail_body) ? "true" : "false";

		$invitation_mail_status = "Cancelled";
		$invitation_mail_eventtype = "";
		$invitation_mail_owner = $organizer;
		$invitation_mail_subject = $subject;
		$invitation_mail_when = date('m/d/Y h:iA',($sdatetime + $utzos)) ." - ". date('m/d/Y h:iA',($edatetime + $utzos));
		$invitation_mail_location = $location;
		$invitation_mail_attendees = str_replace(",",", ",$final_list);
		$invitation_mail_desc = $description;		

		$folder="sentmessages";
		$stats="Active";
		$mailtype="text/html";
		$flag = 0;
		$fromTo = "";
		
		if($suc=="true")
		{
			$invitation_mail_body=prepareSentInvitation($invitation_mail_status,$invitation_mail_owner,$invitation_mail_subject,$invitation_mail_when,$invitation_mail_location,$invitation_mail_attendees,$invitation_mail_desc,$invitation_mail_eventtype);			
			
			$fromTo = $from."|^|".$to;
			$suc = "invitationMail";
			
			$cmnid=prosinsert($fromTo,$invitation_mail_subject,stripslashes($invitation_mail_body),$mailtype,$suc,$flag,'','',$username);		 	 
			$last_id=mail_insert($folder,$from,$to,'','',stripslashes($invitation_mail_body),$invitation_mail_subject,'','',$attach,$mailtype,'yes',$stats,$cmnid);
	       	
			if($cmnid!="")
            {
                $qrycmn="update mail_headers set conid=".$cmnid." where mailid=".$last_id;
            	mysql_query($qrycmn,$db);
            }
        }
	}

	function replyAction($calinfo,$action,$recipient,$efrom,$invitation_extra_datasend)
	{
		global $curtime_header,$smtp,$username,$maildb,$db,$act_name,$companyuser,$domainname,$disus,$maildomain,$akken_arec_port,$boun_mixed_part,$boun_alternative_part;
		global $rep_type,$rec_type,$rec_subtype,$rec_day,$rec_week,$rec_month,$rec_endoption,$appt_enddate,$occurence;

		$chkAidComLevel = ""; //For checking Appointment id in company level;

		$tzque="select timezone.phpvar as TZID,REPLACE(timezone.stdtime,':','') as STDTIME,REPLACE(timezone.daytime,':','') as DAYTIME FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid=$username";
		$tzres=mysql_query($tzque,$db);
		$tzrow=mysql_fetch_array($tzres);
		
		require_once("saveactivities.php");
		require_once("recurrence_options.php");

		if($action=="A" || $action=="T" || $action=="D")
		{
			require("setSMTP.php");

			$att_email = $reqemail;
			$att_name = $act_name;

			if($action=="A")
				$reply="ACCEPTED";
			else if ($action=="T")
				$reply="TENTATIVE";
			else if ($action=="D")
				$reply="DECLINED";

			if($this->sdt=="S")
			{
				$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
			}
			else
			{
				if($calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"]!="")
					$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"];
				else
					$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
			}

			if($sdtosf!="")
				$this->sdtosf = $sdtosf;

			$exzid = $calinfo["VCALENDAR"]["VEVENT"]["EXDATE"][0]["param"]["TZID"];
			$rdzid = $calinfo["VCALENDAR"]["VEVENT"]["RDATE"][0]["param"]["TZID"];
			$rczid = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["param"]["TZID"];

			$stzid = $calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["TZID"];
			$etzid = $calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["TZID"];
			$stampzid = $calinfo["VCALENDAR"]["VEVENT"]["DTSTAMP"][0]["param"]["TZID"];

			if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]!="")
			{
				$odates = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"];
				$temp = $this->extractDateTime($odates,$rczid,"");
				$rcdates = $temp[0];
			}

			if($calinfo["VCALENDAR"]["VEVENT"]["EXDATE"][0]["value"]!="")
			{
				$odates = explode(",",$calinfo["VCALENDAR"]["VEVENT"]["EXDATE"][0]["value"]);
				for($r=0;$r<count($odates);$r++)
				{
					$temp = $this->extractDateTime($odates[$r],$exzid,"");
					$exdates[$r] = $temp[0];
				}
			}

			if($calinfo["VCALENDAR"]["VEVENT"]["RDATE"][0]["value"]!="")
			{
				$odates = explode(",",$calinfo["VCALENDAR"]["VEVENT"]["RDATE"][0]["value"]);
				for($r=0;$r<count($odates);$r++)
				{
					$temp = $this->extractDateTime($odates[$r],$rdzid,"");
					$rddates[$r] = $temp[0];
				}
			}

			if(strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["VALUE"])=="date" || strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["VALUE"])=="date")
			{
				$event = "allday";
				$allday_event = ";VALUE=DATE";
			}
			else
			{
				$event = "none";
				$allday_event = "";
			}

			$sequence = $calinfo["VCALENDAR"]["VEVENT"]["SEQUENCE"][0]["value"];

			if($calinfo["VCALENDAR"]["VEVENT"]["DESCRIPTION"][0]["value"]!="")
				$description = $calinfo["VCALENDAR"]["VEVENT"]["DESCRIPTION"][0]["value"];
			else
				$description = $calinfo["VCALENDAR"]["VEVENT"]["COMMENT"][0]["value"];

			$description = stripslashes($description);

			$summary = $calinfo["VCALENDAR"]["VEVENT"]["SUMMARY"][0]["value"];
			$class = $calinfo["VCALENDAR"]["VEVENT"]["CLASS"][0]["value"];
			$dtstart = $this->extractDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["value"],$stzid,$event);
			$dtend = $this->extractDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["value"],$etzid,$event);
			$location = $calinfo["VCALENDAR"]["VEVENT"]["LOCATION"][0]["value"];
			$transp = $calinfo["VCALENDAR"]["VEVENT"]["TRANSP"][0]["value"];
			$uid = $calinfo["VCALENDAR"]["VEVENT"]["UID"][0]["value"];
			$dtstamp = $this->extractDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTAMP"][0]["value"],$stampzid);
			$priority = $calinfo["VCALENDAR"]["VEVENT"]["PRIORITY"][0]["value"];
			$organizer = $calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["param"]["CN"]." [".$calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["value"]."]";
			$org_email = trim($calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["value"]);

			$rrule = $calinfo["VCALENDAR"]["VEVENT"]["RRULE"][0]["value"];
			if($rrule!="")
			{
				$rrules = $this->parseRecurrence($rrule);

				$rep_type = $rrules["recurrence"];
				$rec_type = $rrules["appt_recurrence_type"];
				$rec_subtype = $rrules["appt_recurrence_subtype"];
				$rec_day = $rrules["appt_recurrence_day"];
				$rec_week = $rrules["appt_recurrence_week"];
				$rec_month = $rrules["appt_recurrence_month"];
				$rec_endoption = $rrules["appt_enddate_option"];
				$occurence = $rrules["appt_no_occurances"];
				$appt_enddate = $rrules["appt_enddate"];
			}
			else
			{
				$rep_type = "none";
			}

			if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
			{
				$pque="select sno from appointments where status='active' AND uid='$uid' AND parid=0";
				$pres=mysql_query($pque,$db);
				$prow=mysql_fetch_row($pres);

				if($prow[0]>0)
				{
					$uque = "UPDATE appointments SET exception='', exptime='0' WHERE sno=".$prow[0];
					mysql_query($uque,$db);

					$this->deleteAppointmentHistory($prow[0]);
				}

				$this->parid="0";
			}
			else
			{
				$pque="select sno from appointments where status='active' AND uid='$uid' AND parid=0";
				$pres=mysql_query($pque,$db);
				$prow=mysql_fetch_row($pres);
				if($prow[0]>0)
					$this->parid=$prow[0];
				else
					$this->parid="-1";
			}

			$hque="select username from external_mail where deflt='Yes' AND TRIM(mailid)='".addslashes($org_email)."' ORDER BY cdate DESC limit 1";
			$hres=mysql_query($hque,$db);
			$hrow=mysql_fetch_row($hres);
			$orguser=$hrow[0];

			$att_list = array();
			for($i=0;$i<count($calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"]);$i++)
			{
				$att_list[]=$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["value"];
				$emaillist[$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["value"]]=$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][$i]["param"]["CN"];
			}

			$title = $summary;
			$desc = $description;

			if($priority=="")
				$priority=5;

			$sdatetime = $dtstart[0];
			$edatetime = $dtend[0];

			if(strpos("*".$uid,"AKKENICS")<=0)
			{
				if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
					$uque="select sno, sequence, parid from appointments where status='active' AND uid='$uid' AND parid=0 ORDER BY sno DESC limit 1";
				else
					$uque="select sno, sequence, parid from appointments where status='active' AND uid='$uid' AND exptime='$rcdates' ORDER BY sno DESC limit 1";
				$ures=mysql_query($uque,$db);
				$urow=mysql_fetch_row($ures);

				if($urow[0]!="" && mysql_num_rows($ures)>0)
				{
					$this->aid=$urow[0];
					$this->sequence=$urow[1];
					$this->parid=$urow[2];

					$uque="update mail_calendar set aid='".$this->aid."' where type='REQUEST' AND cid='".$this->cid."'";
					mysql_query($uque,$db);
				}
			}

			if($this->aid>0)
			{
				if(strpos("*".$uid,"AKKENICS")<=0)
				{
					if($this->sequence <= $sequence)
					{
						if($this->parid==0)
							$que = "update appointments set sequence = $sequence where sno='".$this->aid."' OR parid='".$this->aid."'";
						else
							$que = "update appointments set sequence = $sequence where sno='".$this->parid."' OR parid='".$this->parid."'";
						mysql_query($que,$db);

						if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
							update_invitation($this->aid,$sdatetime,$edatetime,$event,addslashes($title),addslashes($desc),$show,$remtime,$con_id,$module_type_appoint,$invapproved,$invrejected,$invtentative,$colprivate,$modifiedby,$atype,$appt_priority,$appt_location,$appt_label,$appt_showtime,$rep_type,$rec_type,$rec_subtype,$rec_day,$rec_week,$rec_month,$rec_endoption,$appt_enddate,$occurence,$uid,$organizer,$rcdates,$exdates,$rddates);
						else
							update_invoccurrence($this->aid,$sdatetime,$edatetime,$event,addslashes($title),addslashes($desc),$show,$remtime,$con_id,$module_type_appoint,$invapproved,$invrejected,$invtentative,$colprivate,$modifiedby,$atype,$appt_priority,$appt_location,$appt_label,$appt_showtime,'none',$uid,$organizer,$rcdates,$this->parid);
					}

					$ique="select email from invite_calendar where appno='".$this->aid."'";
					$ires=mysql_query($ique,$db);
					while($irow=mysql_fetch_row($ires))
						$oatt_list[] = $irow[0];

					$natt_list = array_diff($att_list,$oatt_list);
					if(count($natt_list)>0)
						inviteInvAttendees($this->aid,$natt_list,$org_email,$emaillist);

				}

				if($action=="A")
					$this->acceptInv($username,$uid,$this->aid,$att_email,$action);
				else if($action=="T")
					$this->tentativeInv($username,$uid,$this->aid,$att_email,$action);
				else
					$this->declineInv($username,$uid,$this->aid,$att_email,$action);

				$uque="update mail_calendar set status='$action' where type='REQUEST' AND cid='".$this->cid."'";
				mysql_query($uque,$db);
			}
			else
			{
				if(strpos("*".$uid,"AKKENICS")>0)
				{
					if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
						$aque="select sno, parid from appointments where status='active' AND uid='$uid' AND parid=0 ORDER BY sno DESC limit 1";
					else
						$aque="select sno, parid from appointments where status='active' AND uid='$uid' AND exptime='$rcdates' ORDER BY sno DESC limit 1";
					$ares=mysql_query($aque,$db);
					if(mysql_num_rows($ares)>0)
					{
						$arow=mysql_fetch_row($ares);
						$this->aid = $arow[0];
						$this->parid = $arow[1];
					}
				}

				if($this->aid==0 || $this->aid=="")
				{
					$tsend = implode(",",array_keys($emaillist));
					if($tsend!="")
					{
						$pending="";

						$pque="SELECT users.username FROM users LEFT JOIN emp_list ON users.username=emp_list.username LEFT JOIN sysuser ON users.username=sysuser.username LEFT JOIN hrcon_general ON users.username=hrcon_general.username WHERE users.type IN ('sp','PE','consultant') AND users.status!='DA' AND sysuser.collaboration!='NO' AND hrcon_general.ustatus='active' AND users.username!='".$username."' AND hrcon_general.email IN ('".str_replace(",","','",$tsend)."')";
						$pres=mysql_query($pque,$db);
						while($prow=mysql_fetch_row($pres))
						{
							if($pending=="")
								$pending=$prow[0];
							else
								$pending.=",".$prow[0];
						}
					}

					if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
						$this->aid = insert_invitation($orguser,$sdatetime,$edatetime,$event,addslashes($title),addslashes($desc),$show,$remtime,$con_id,$module_type_appoint,$invapproved,$invrejected,$invtentative,$colprivate,$modifiedby,$atype,$appt_priority,$appt_location,$appt_label,$appt_showtime,$rep_type,$rec_type,$rec_subtype,$rec_day,$rec_week,$rec_month,$rec_endoption,$appt_enddate,$occurence,$uid,$organizer,$pending,$sequence);
					else
						$this->aid = insert_invoccurrence($orguser,$sdatetime,$edatetime,$event,addslashes($title),addslashes($desc),$show,$remtime,$con_id,$module_type_appoint,$invapproved,$invrejected,$invtentative,$colprivate,$modifiedby,$atype,$appt_priority,$appt_location,$appt_label,$appt_showtime,'none',$uid,$organizer,$pending,$this->parid,$rcdates,$sequence);

					if(count($att_list)>0)
						inviteInvAttendees($this->aid,$att_list,$org_email,$emaillist);
				}

				if($action=="A")
					$this->acceptInv($username,$uid,$this->aid,$att_email,$action);
				else if($action=="T")
					$this->tentativeInv($username,$uid,$this->aid,$att_email,$action);
				else
					$this->declineInv($username,$uid,$this->aid,$att_email,$action);

				$uque="update mail_calendar set aid='".$this->aid."', status='$action' where type='REQUEST' AND cid='".$this->cid."'";
				mysql_query($uque,$db);
			}

			if($invitation_extra_datasend['status']=="YES")
			{
				if($rep_type=="recurrence" && isset($rec_type))
					$rrule = $this->prepareRecurrence();
				else
					$rrule = "";

				if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
				{
					require("iCalendar_REPLY.inc");
				}
				else
				{
					require("iCalendar_REPLY_OCCURRENCE.inc");
					$recurrenceid = str_replace("Z","",$this->replyDateTime($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"],$rczid,$event));
				}

				if($reply=="TENTATIVE")
					$temp_reply="TENTATIVELY ACCEPTED";
				else
					$temp_reply=$reply;

				$message_body = "[".$org_email."] has ".$temp_reply." Invitation -- ".$summary;

				$iCalendar_REPLY = str_replace("[[TZID]]",$tzrow['TZID'],$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[STDTIME]]",$tzrow['STDTIME'],$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[DAYTIME]]",$tzrow['DAYTIME'],$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[ORGANIZER]]",$org_email,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[SUMMARY]]",$summary,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[DTSTART]]",$this->replyTZDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["value"],$stzid,$event),$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[DTEND]]",$this->replyTZDateTime($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["value"],$etzid,$event),$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[ALLDAYEVENT]]",$allday_event,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[LOCATION]]",$location,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[TRANSP]]",$transp,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[PRIORITY]]",$priority,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[UID]]",$uid,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[RECURRENCE-ID]]",$recurrenceid,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[DTSTAMP]]",$this->replyDateTime("",""),$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[CLASS]]",$class,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[REPLY_ACTION]]",$reply,$iCalendar_REPLY);

				$description = $invitation_extra_datasend['notes'];

				$iCalendar_REPLY = str_replace("[[DESCRIPTION]]",str_replace("\n", "\\n",$description),$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[ATTENDEE_EMAIL]]",$att_email,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[ATTENDEE_NAME]]",$att_name,$iCalendar_REPLY);
				$iCalendar_REPLY = str_replace("[[SEQUENCE]]",$sequence,$iCalendar_REPLY);

				$from = "$att_name <$att_email>";
				$to = $recipient;
				$subject = ucfirst($reply).": $summary";
				$ato=quotesplit($to,$split=",",$temp="^");

				$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: ","Subject: $subject","MIME-Version: 1.0");
				array_push($mailheaders,"Content-Type: multipart/mixed; boundary=\"Message-Boundary$boun_mixed_part\"","Content-Transfer-Encoding: 7bit");
				$mail_body = $this->attachICS($iCalendar_REPLY,"REPLY",$message_body);
				$mail_body.="--Message-Boundary$boun_mixed_part--\n";
				$suc=$smtp->SendMessage($from,$ato,$mailheaders,$mail_body) ? "true" : "false";

				if($reply=="TENTATIVE")
					$reply="TENTATIVELY ACCEPTED";

				$utzos = getUserSTZOffset();
				$sent_utzos = getUserTZOffset($sdatetime);

				$invitation_mail_status = "<font color=red>".$reply."</font>";
				$invitation_mail_eventtype = "";
				$invitation_mail_owner = $org_email;
				$invitation_mail_subject = $subject;
				$invitation_mail_when = date('m/d/Y h:iA',($sdatetime + $utzos)) ." - ". date('m/d/Y h:iA',($edatetime + $utzos));
				$invitation_mail_location = $location;
				$invitation_mail_attendees = $att_name."[".$att_email."]";
				$invitation_mail_desc = $description;

				$folder="sentmessages";
				$stats="Active";
				$mailtype="text/html";
				$flag = 0;
				$fromTo = "";

				if($suc=="true")
				{				
					$invitation_mail_body=prepareSentInvitation($invitation_mail_status,$invitation_mail_owner,$invitation_mail_subject,$invitation_mail_when,$invitation_mail_location,$invitation_mail_attendees,$invitation_mail_desc,$invitation_mail_eventtype);

					$fromTo = $from."|^|".$to;
					$suc = "invitationMail";

					$cmnid=prosinsert($fromTo,$invitation_mail_subject,stripslashes($invitation_mail_body),$mailtype,$suc,$flag,'','',$username);		 	 
					$last_id=mail_insert($folder,$from,$to,'','',stripslashes($invitation_mail_body),$invitation_mail_subject,'','',$attach,$mailtype,'yes',$stats,$cmnid);

					if($cmnid!="")
		            {
		                $qrycmn="update mail_headers set conid=".$cmnid." where mailid=".$last_id;
		            	mysql_query($qrycmn,$db);
		            }
		        }
			}
			return $this->aid;
		}
	}

	function replyAppointmentAction($appsno,$action,$recipient,$efrom,$invitation_extra_datasend)
	{
		global $curtime_header,$smtp,$username,$maildb,$db,$act_name,$companyuser,$domainname,$disus,$maildomain,$akken_arec_port,$boun_mixed_part,$boun_alternative_part;

		if(($action=="A" || $action=="T" || $action=="D") && ($invitation_extra_datasend['status']=="YES"))
		{
			$tzque="select timezone.phpvar as TZID,REPLACE(timezone.stdtime,':','') as STDTIME,REPLACE(timezone.daytime,':','') as DAYTIME FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid=$username";
			$tzres=mysql_query($tzque,$db);
			$tzrow=mysql_fetch_array($tzres);

			require_once("saveactivities.php");
			require_once("recurrence_options.php");
			require("setSMTP.php");

			$att_email = $reqemail;
			$att_name = $act_name;

			if($action=="A")
				$reply="ACCEPTED";
			else if ($action=="T")
				$reply="TENTATIVE";
			else if ($action=="D")
				$reply="DECLINED";

			$queapp="SELECT username, sdatetime, edatetime, event, title, descri, priority, location, label, recurrence, recurrence_type, recurrence_subtype, recurrence_day, recurrence_week, recurrence_month, enddate_option, enddate, no_occurances, uid, organizer, exception, sequence, pending, exptime FROM appointments WHERE sno=$appsno";
			$resapp = mysql_query($queapp,$db);
			$rsapp = mysql_fetch_array($resapp); 

			$sequence = $rsapp['sequence'];

			if($rsapp['location']!=0)
			{
				$que="select name from manage where type='appt_location' AND sno='".$rsapp['location']."'";
				$res=mysql_query($que,$db);
				$row=mysql_fetch_row($res);
				$location = $row[0];
			}			

			$uid = $rsapp['uid'];
			$event = $rsapp['event'];

			if($event == "allday")
				$allday_event = ";VALUE=DATE";
			else
				$allday_event = "";

			$org_email = $recipient;
			$summary = $rsapp['title'];
			$description = $rsapp['descri'];
			$exception = $rsapp['exptime'];
			$dtstart = $this->convertDateTime($rsapp['sdatetime'],$event);
			$dtend = $this->convertDateTime($rsapp['edatetime'],$event);
			$dtstamp = $this->convertDateTime((time() - $utzos),"");
			$rrule = $rsapp['recurrence'];

			$priority = 5;
			$class = "PUBLIC";
			$transp = "OPAQUE";

			if($rrule!="none")
			{
				$rep_type = $rsapp["recurrence"];
				$rec_type = $rsapp["appt_recurrence_type"];
				$rec_subtype = $rsapp["appt_recurrence_subtype"];
				$rec_day = $rsapp["appt_recurrence_day"];
				$rec_week = $rsapp["appt_recurrence_week"];
				$rec_month = $rsapp["appt_recurrence_month"];
				$rec_endoption = $rsapp["appt_enddate_option"];
				$occurence = $rsapp["appt_no_occurances"];
				$appt_enddate = $rsapp["appt_enddate"];
			}
			else
			{
				$rep_type = "none";
			}

			if($rep_type=="recurrence" && isset($rec_type))
				$rrule = $this->prepareRecurrence();
			else
				$rrule = "";

			if($exception=="0")
			{
				require("iCalendar_REPLY.inc");
			}
			else
			{
				require("iCalendar_REPLY_OCCURRENCE.inc");
				$recurrenceid = str_replace("Z","",$this->convertDateTime($exception,$event));
			}

			if($reply=="TENTATIVE")
				$temp_reply="TENTATIVELY ACCEPTED";
			else
				$temp_reply=$reply;
			$message_body = "[".$org_email."] has ".$temp_reply." Invitation -- ".$summary;

			$iCalendar_REPLY = str_replace("[[TZID]]",$tzrow['TZID'],$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[STDTIME]]",$tzrow['STDTIME'],$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[DAYTIME]]",$tzrow['DAYTIME'],$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[ORGANIZER]]",$org_email,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[SUMMARY]]",$summary,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[DTSTART]]",$dtstart,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[DTEND]]",$dtend,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[ALLDAYEVENT]]",$allday_event,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[LOCATION]]",$location,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[TRANSP]]",$transp,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[PRIORITY]]",$priority,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[UID]]",$uid,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[RECURRENCE-ID]]",$recurrenceid,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[DTSTAMP]]",$dtstamp,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[CLASS]]",$class,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[REPLY_ACTION]]",$reply,$iCalendar_REPLY);

			$description = $invitation_extra_datasend['notes'];

			$iCalendar_REPLY = str_replace("[[DESCRIPTION]]",str_replace("\n", "\\n",$description),$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[ATTENDEE_EMAIL]]",$att_email,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[ATTENDEE_NAME]]",$att_name,$iCalendar_REPLY);
			$iCalendar_REPLY = str_replace("[[SEQUENCE]]",$sequence,$iCalendar_REPLY);

			$from = "$att_name <$att_email>";
			$to = $recipient;
			$subject = ucfirst($reply).": $summary";
			$ato=quotesplit($to,$split=",",$temp="^");

			$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: ","Subject: $subject","MIME-Version: 1.0");
			array_push($mailheaders,"Content-Type: multipart/mixed; boundary=\"Message-Boundary$boun_mixed_part\"","Content-Transfer-Encoding: 7bit");
			$mail_body = $this->attachICS($iCalendar_REPLY,"REPLY",$message_body);
			$mail_body.="--Message-Boundary$boun_mixed_part--\n";
			$suc=$smtp->SendMessage($from,$ato,$mailheaders,$mail_body) ? "true" : "false";

			if($reply=="TENTATIVE")
				$reply="TENTATIVELY ACCEPTED";

			$utzos = getUserSTZOffset();
			$sent_utzos = getUserTZOffset($sdatetime);

			$invitation_mail_status = "<font color=red>".$reply."</font>";
			$invitation_mail_eventtype = "";
			$invitation_mail_owner = $org_email;
			$invitation_mail_subject = $subject;
			$invitation_mail_when = date('m/d/Y h:iA',($rsapp['sdatetime'] + $utzos)) ." - ". date('m/d/Y h:iA',($rsapp['edatetime'] + $utzos));
			$invitation_mail_location = $location;
			$invitation_mail_attendees = $att_name."[".$att_email."]";
			$invitation_mail_desc = $description;			

			$folder="sentmessages";
			$stats="Active";
			$mailtype="text/html";
			$flag = 0;
			$fromTo = "";

			if($suc=="true")
			{				
				$invitation_mail_body=prepareSentInvitation($invitation_mail_status,$invitation_mail_owner,$invitation_mail_subject,$invitation_mail_when,$invitation_mail_location,$invitation_mail_attendees,$invitation_mail_desc,$invitation_mail_eventtype);  // Function defined in /include/savemails.php

				$fromTo = $from."|^|".$to;
				$suc = "invitationMail";

				// Function defined in /include/savemails.php

				$cmnid=prosinsert($fromTo,$invitation_mail_subject,stripslashes($invitation_mail_body),$mailtype,$suc,$flag,'','',$username); 
				$last_id=mail_insert($folder,$from,$to,'','',stripslashes($invitation_mail_body),$invitation_mail_subject,'','',$attach,$mailtype,'yes',$stats,$cmnid);

				if($cmnid!="")
	            {
	                $qrycmn="update mail_headers set conid=".$cmnid." where mailid=".$last_id;
	            	mysql_query($qrycmn,$db);
	            }
	        }
		}
	}

	function acceptInv($iuser,$uid,$aid,$att_email,$action)
	{
		global $maildb,$db;

		if($aid!="")
		{
			$uque="update invite_calendar set status='$action' where appno='$aid' AND email='$att_email'";
			mysql_query($uque,$db);

			$que="update appointments set pending=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',pending,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',pending,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set rejected=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',rejected,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',rejected,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set tentative=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',tentative,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',tentative,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set approved=IF(approved='','$iuser',CONCAT(approved,',','$iuser')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',approved,'+'),',','+,+'))<=0";
			mysql_query($que,$db);
		}
	}

	function tentativeInv($iuser,$uid,$aid,$att_email,$action)
	{
		global $maildb,$db;

		if($aid!="")
		{
			$uque="update invite_calendar set status='$action' where appno='$aid' AND email='$att_email'";
			mysql_query($uque,$db);

			$que="update appointments set pending=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',pending,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',pending,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set rejected=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',rejected,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',rejected,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set approved=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',approved,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',approved,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set tentative=IF(tentative='','$iuser',CONCAT(tentative,',','$iuser')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',tentative,'+'),',','+,+'))<=0";
			mysql_query($que,$db);
		}
	}

	function declineInv($iuser,$uid,$aid,$att_email,$action)
	{
		global $maildb,$db;

		if($aid!="")
		{
			$uque="update invite_calendar set status='$action' where appno='$aid' AND email='$att_email'";
			mysql_query($uque,$db);

			$que="update appointments set pending=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',pending,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',pending,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set approved=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',approved,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',approved,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set tentative=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',tentative,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',tentative,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set rejected=IF(rejected='','$iuser',CONCAT(rejected,',','$iuser')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',rejected,'+'),',','+,+'))<=0";
			mysql_query($que,$db);
		}
	}

	function cancelInv($iuser,$uid,$aid,$parid,$rcdates)
	{
		global $maildb,$db;

		if($aid!="")
		{
			$que="update appointments set pending=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',pending,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',pending,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set rejected=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',rejected,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',rejected,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set tentative=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',tentative,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',tentative,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$que="update appointments set approved=TRIM(BOTH ',' FROM REPLACE(CONCAT(',',approved,','),',$iuser,',',')) where sno='$aid' AND uid='$uid' AND FIND_IN_SET(CONCAT('+','$iuser','+'),REPLACE(CONCAT('+',approved,'+'),',','+,+'))>0";
			mysql_query($que,$db);

			$sque="select username, pending, approved, tentative from appointments where sno=$aid";
			$sres=mysql_query($sque,$db);
			$srow=mysql_fetch_row($sres);
			if($srow[0]=="" && $srow[1]=="" && $srow[2]=="" && $srow[3]=="")
			{
				if($rcdates=="all")
				{
					$aque="select sno from appointments where sno=$aid OR parid=$aid";
					$ares=mysql_query($aque,$db);
					while($arow=mysql_fetch_row($ares))
					{
						$que="delete from recurrences where ano=".$arow[0];
						mysql_query($que,$db);

						$que="delete from invite_calendar where appno=".$arow[0];
						mysql_query($que,$db);

						$que="delete from tabappoint_attach where appt_appno=".$arow[0];
						mysql_query($que,$db);

						$que="delete from calendar_notes where appid=".$arow[0];
						mysql_query($que,$db);
					}

					$que="delete from appointments where sno=$aid OR parid=$aid";
					mysql_query($que,$db);
				}
				else
				{
					$que="delete from recurrences where ano=$parid AND otime='".$rcdates."'";
					mysql_query($que,$db);

					$que="update appointments set exception = IF(exception='','".$rcdates."',CONCAT(exception,',','".$rcdates."')) where sno=$parid";
					mysql_query($que,$db);

					$aque="select sno from appointments where parid=$parid AND exptime=$rcdates";
					$ares=mysql_query($aque,$db);
					$arow=mysql_fetch_row($ares);
					if($arow[0]!="")
					{
						$que="delete from invite_calendar where appno=".$arow[0];
						mysql_query($que,$db);

						$que="delete from tabappoint_attach where appt_appno=".$arow[0];
						mysql_query($que,$db);

						$que="delete from calendar_notes where appid=".$arow[0];
						mysql_query($que,$db);

						$que="delete from appointments where sno=".$arow[0];
						mysql_query($que,$db);
					}
				}
			}
		}
	}

	function fromFile($text, $decode_qp = true)
	{
		return $this->fromText($text, $decode_qp);
	}

	function fromText($text, $decode_qp = true) 
	{
		$this->convertLineEndings($text);
		$fold_regex = '(\n)([ |\t])';
		$text = preg_replace("/$fold_regex/i", "", $text);
		$lines = explode("\n", $text);
		return $this->_fromArray($lines, $decode_qp);
	}

	function convertLineEndings(&$text)
	{
		$text = str_replace("\r", "\n", $text);
		$text = str_replace("\n\n", "\n", $text);
	}

	function splitBySemi($text, $convertSingle = false)
	{
		// we use these double-backs (\\) because they get get converted to single-backs (\) by preg_split.  the quad-backs (\\\\) end up as as double-backs (\\), which is what preg_split requires to indicate a single backslash (\). what a mess.
		$regex = '(?<!\\\\)(\;)';
		$tmp = preg_split("/$regex/i", $text);
        
        // if there is only one array-element and $convertSingle is true, then return only the value of that one array element (instead of returning the array).
		if ($convertSingle && count($tmp) == 1) 
			return $tmp[0];
		else 
			return $tmp;
	}

	function splitByComma($text, $convertSingle = false)
	{
		// we use these double-backs (\\) because they get get converted to single-backs (\) by preg_split.  the quad-backs (\\\\) end up as as double-backs (\\), which is what preg_split requires to indicate a single backslash (\). ye gods, how ugly.
		$regex = '(?<!\\\\)(\,)';
		$tmp = preg_split("/$regex/i", $text);

		// if there is only one array-element and $convertSingle is true, then return only the value of that one array element (instead of returning the array).
		if ($convertSingle && count($tmp) == 1) 
			return $tmp[0];
		else
			return $tmp;
	}
    
	function unescape(&$text)
	{
		if (is_array($text)) 
		{
			foreach ($text as $key => $val) 
			{
				$this->unescape($val);
				$text[$key] = $val;
			}
		}
		else 
		{
			$text = str_replace('\;', ';', $text);
			$text = str_replace('\,', ',', $text);
			$text = str_replace('\n', "\n", $text);
		}
	}

	function _fromArray($source, $decode_qp = true)
	{
		$this->_parseBlock($source);
		$this->unescape($this->blocks);
		return $this->blocks;
	}

	function _parseBlock(&$source) 
	{
		for ($this->count++; $this->count < count($source); $this->count++) 
		{
			$line = $source[$this->count];

			// if the line is blank, skip it.
			if (trim($line) == '')
				continue;
            
			// find the first instance of ':' on the line.  The part to the left of the colon is the type and parameters; the part to the right of the colon is the value data.
			$pos = strpos($line, ':');
            
			// if there is no colon, skip the line.
			if ($pos === false)
				continue;            

			// get the left and right portions
			$left = trim(substr($line, 0, $pos));
			$right = trim(substr($line, $pos+1, strlen($line)));

			if (strtoupper($left) == "BEGIN") 
			{
				$block[$right] = $this->_parseBlock($source);
				$this->blocks = $block;
            }
			elseif (strtoupper($left) == "END") 
			{
				return $block;
			}
			else
			{
				// we're not on an ending line, so collect info from this line into the current card. split the left-portion of the line into a type-definition (the kind of information) and parameters for the type.
				$typedef = $this->_getTypeDef($left);
				$params = $this->_getParams($left);

				// if we are decoding quoted-printable, do so now. QUOTED-PRINTABLE is not allowed in version 3.0, but we don't check for versioning, so we do it regardless.  ;-)
				$this->_decode_qp($params, $right);

				// now get the value-data from the line, based on the typedef
				$func = '_parse' . strtoupper($typedef);
				if (method_exists(&$this, $func)) 
					$value = $this->$func($right);
				else
					$value = str_replace("MAILTO:","",str_replace("mailto:","",$right));

				if(count($params)>0)
					$block[$typedef][] = array('param' => $params,'value' => $value);
				else
					$block[$typedef][] = array('value' => $value);
			}
		}
	}

	function _getTypeDef($text)
	{
		// split the text by semicolons
		$split = $this->splitBySemi($text);

		// only return first element (the typedef)
		return $split[0];
	}
    
	function _getParams($text)
	{
		// split the text by semicolons into an array
		$split = $this->splitBySemi($text);

		// drop the first element of the array (the type-definition)
		array_shift($split);

		// set up an array to retain the parameters, if any
		$params = array();

		// loop through each parameter.  the params may be in the format... "TYPE=type1,type2,type3" ...or... "TYPE=type1;TYPE=type2;TYPE=type3"
		foreach ($split as $full) 
		{
			// split the full parameter at the equal sign so we can tell the parameter name from the parameter value 
			$tmp = explode("=", $full);

			// the key is the left portion of the parameter (before '='). if in 2.1 format, the key may in fact be the parameter value, not the parameter name.
			$key = strtoupper(trim($tmp[0]));

			// get the parameter name by checking to see if it's in vCard 2.1 or 3.0 format.
			$name = $this->_getParamName($key);

			// list of all parameter values
			$listall = trim($tmp[1]);

			// if there is a value-list for this parameter, they are separated by commas, so split them out too.
			$list = $this->splitByComma($listall);

			// now loop through each value in the parameter and retain it.  if the value is blank, that means it's a 2.1-style param, and the key itself is the value.
			foreach ($list as $val) 
			{
				if (trim($val) != '')
				{
					// 3.0 formatted parameter
					$params[$name] = trim($val);
				}
				else 
				{
					// 2.1 formatted parameter
					$params[$name] = $key;
				}
			}

			// if, after all this, there are no parameter values for the parameter name, retain no info about the parameter (saves ram and checking-time later).
			if (count($params[$name]) == 0)
			{
				unset($params[$name]);
			}
		}
        
		// return the parameters array.
		return $params;
	}

	function _getParamName($value)
	{
		static $types = array (
			'DOM', 'INTL', 'POSTAL', 'PARCEL','HOME', 'WORK',
			'PREF', 'VOICE', 'FAX', 'MSG', 'CELL', 'PAGER',
			'BBS', 'MODEM', 'CAR', 'ISDN', 'VIDEO',
			'AOL', 'APPLELINK', 'ATTMAIL', 'CIS', 'EWORLD',
			'INTERNET', 'IBMMAIL', 'MCIMAIL',
			'POWERSHARE', 'PRODIGY', 'TLX', 'X400',
			'GIF', 'CGM', 'WMF', 'BMP', 'MET', 'PMB', 'DIB',
			'PICT', 'TIFF', 'PDF', 'PS', 'JPEG', 'QTIME',
			'MPEG', 'MPEG2', 'AVI',
			'WAVE', 'AIFF', 'PCM',
			'X509', 'PGP'
		);

		// CONTENT-ID added by pmj
		static $values = array (
			'INLINE', 'URL', 'CID', 'CONTENT-ID'
		);

		// 8BIT added by pmj
		static $encodings = array (
			'7BIT', '8BIT', 'QUOTED-PRINTABLE', 'BASE64'
		);

		// changed by pmj to the following so that the name defaults to whatever the original value was.  Frank Hellwig's original code was "$name = 'UNKNOWN'".
		$name = $value;

		if (in_array($value, $types)) 
			$name = 'TYPE';
		elseif (in_array($value, $values)) 
			$name = 'VALUE';
		elseif (in_array($value, $encodings))
			$name = 'ENCODING';

		return $name;
	}

	function _decode_qp(&$params, &$text)
	{
		// loop through each parameter
		foreach ($params as $param_key => $param_val)
		{
			// check to see if it's an encoding param
			if (trim(strtoupper($param_key)) == 'ENCODING')
			{
				// loop through each encoding param value
				foreach ($param_val as $enc_key => $enc_val)
				{
					// if any of the values are QP, decode the text in-place and return
					if (trim(strtoupper($enc_val)) == 'QUOTED-PRINTABLE')
					{
						$text = quoted_printable_decode($text);
						return;
					}
				}
			}
		}
	}

	function attachICS($cal_body,$method,$message_body)
	{
		global $boun_mixed_part,$boun_alternative_part;

		$filename = "invite.ics";
		$contents = $cal_body;
		$filetype = "application/ics";

		$encoded_attach = chunk_split(base64_encode($contents));

		$mail_body="--Message-Boundary$boun_mixed_part\n";
		$mail_body.="Content-Type: multipart/alternative; boundary=\"Message-Boundary$boun_alternative_part\"\n\n";

		$mail_body.="--Message-Boundary$boun_alternative_part\n";
		$mail_body.="Content-Type: text/plain; charset=\"UTF-8\";\n";
		$mail_body.="Content-Transfer-Encoding: 7bit;\n\n";
		$mail_body.=$message_body."\n\n";

		$mail_body.="--Message-Boundary$boun_alternative_part\n";
		$mail_body.="Content-Type: text/html; charset=\"UTF-8\";\n";
		$mail_body.="Content-Transfer-Encoding: 7bit;\n\n";
		$mail_body.=$message_body."\n\n";

		$mail_body.="--Message-Boundary$boun_alternative_part\n";
		$mail_body.="Content-Type: text/calendar; method=$method; charset=\"UTF-8\";\n";
		$mail_body.="Content-Transfer-Encoding: 7bit;\n\n";
		$mail_body.=$cal_body."\n\n";
		$mail_body.="--Message-Boundary$boun_alternative_part--\n";

		$mail_body.="--Message-Boundary$boun_mixed_part\n";
		$mail_body.="Content-Type: ".$filetype."; name=\"$filename\"\n";
		$mail_body.="Content-Transfer-Encoding: base64\n";
		$mail_body.="Content-disposition: attachment; filename=\"$filename\"\n\n";
		$mail_body.="$encoded_attach\n";

		return $mail_body;
	}

	function attachDocuments($appno,&$mail_body)
	{
		global $maildb,$db,$boun_mixed_part; 

		$flag=0;

		$sqry = "SELECT appt_img_name,appt_img_content,appt_img_type,appt_img_size FROM tabappoint_attach WHERE appt_appno=$appno";
		$srs  = mysql_query($sqry,$db);
		$cnt  = mysql_num_rows($srs);
		if($cnt > 0)
		{
			while($srow = mysql_fetch_row($srs))
			{
				if($srow[0]!="")
				{
					$filename = stripslashes($srow[0]);
					$contents = $srow[1];
					$filetype = $srow[2];
					$filessize = $srow[3];

					$encoded_attach = chunk_split(base64_encode($contents));

					$mail_body.="--Message-Boundary$boun_mixed_part\n";
					$mail_body.="Content-Type: ".$filetype."; name=\"$filename\"\n";
					$mail_body.="Content-Transfer-Encoding: base64\n";
					$mail_body.="Content-disposition: attachment; filename=\"$filename\"\n\n";
					$mail_body.="$encoded_attach\n";

					$file_name[$flag]=$filename;
					$file_size[$flag]=$filesize;
					$file_type[$flag]=$filetype;
					$file_con[$flag]=$contents;

					$flag++;
				}
			}
		}
		return $flag;
	}

	function deleteAppointmentHistory($parid)
	{
		global $db;

		$aque="select sno from appointments where parid=$parid";
		$ares=mysql_query($aque,$db);
		while($arow=mysql_fetch_row($ares))
		if($arow[0]!="")
		{
			$que="delete from invite_calendar where appno=".$arow[0];
			mysql_query($que,$db);

			$que="delete from tabappoint_attach where appt_appno=".$arow[0];
			mysql_query($que,$db);

			$que="delete from calendar_notes where appid=".$arow[0];
			mysql_query($que,$db);

			$que="delete from appointments where sno=".$arow[0];
			mysql_query($que,$db);
		}
	}
}
?>