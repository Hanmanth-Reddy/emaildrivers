<?php
	function submailid($mailid)
	{
		global $maildb,$db,$delqstr;

		$que="select mailid from mail_headers where inlineid='$mailid'";
		$res=mysql_query($que,$db);
		if((int)mysql_num_rows($res)>0)
		{
			while($row=mysql_fetch_array($res))
			{
				$delqstr.=",'$row[0]'";
				submailid($row[0]);
			}
		}
	}

	function deletemail($mailid)
	{
		global $maildb,$db,$delqstr;

		submailid($mailid);

		$que="delete from mail_headers where 1=2 or mailid IN ('$mailid'".$delqstr.")";
		mysql_query($que,$db);

		$que="delete from mail_attachs where 1=2 or mailid IN ('$mailid'".$delqstr.")";
		mysql_query($que,$maildb);

		$que="delete from mail_headers_body where 1=2 or id IN ('$mailid'".$delqstr.")";
		mysql_query($que,$maildb);

		$delqstr="";
	}

	$monthdays=array();
	$Weekmonthdays=array();
	// function to get all days of the month according to week
	function getMonthWeek($chkdate)
	{	global $Weekmonthdays;
			echo $mno=date("n",$chkdate);
			if(!(isset($Weekmonthdays[$mno])))
			$Weekmonthdays[$mno]=array();
			$daysloop=1;
			for($wloop=0;$wloop<6;$wloop++){
				$Weekmonthdays[$mno][$wloop]=array();
				$swk=0;
				for($sw=$swk;$sw<=6;$sw++){
					array_push($Weekmonthdays[$mno][$wloop],$daysloop);
					$swk++;$daysloop++;
				}
			}
	}

	function scheduleStatus($mailid)
	{
		global $maildb,$db,$Weekmonthdays;
		
		$sque="SELECT recurrence,recurr_days,recurr_options,date_format( FROM_UNIXTIME( startdate ) , '%Y-%m-%d %k-%i') startdate,date_format(FROM_UNIXTIME(enddate),'%Y-%m-%d') enddate,date_format(FROM_UNIXTIME(senton),'%Y-%m-%d') senton,startdate start_date,enddate end_date FROM mail_scheduled where mailid='$mailid'";
		$sres=mysql_query($sque,$db);
		$srow=mysql_fetch_array($sres);
		if(mysql_num_rows($sres)>0)
		{
			$recurrence_sch=$srow['recurrence'];
			$recurr_days_sch=$srow['recurr_days'];
			$recurr_options_sch=$srow['recurr_options'];
			$recurr_options_sch_array=explode(":",$srow['recurr_options']);
			$startdatetime_sch = explode(" ",$srow['startdate'])	;
			$startdate_sch1 = $startdatetime_sch[0];
			$starttime_sch1 = $startdatetime_sch[1];
			$recurr_start_date=$srow['start_date'];
			$recurr_end_date=$srow['end_date'];
			$enddate_sch1=$srow['enddate'];
			$senton_sch=$srow['senton'];
			$cur_date_sch=explode("-",date('w-j'));					// current numeric representation of the day of the week and date of the month
			
			$startdate_sch = strtotime($startdate_sch1);
			$cur_date = strtotime(date("Y-m-d"));					// current date
			
			$enddate_sch = strtotime($enddate_sch1);
			$starttime_sch2 = explode("-",$starttime_sch1);
			$starttime_sch = "$starttime_sch2[0]$starttime_sch2[1]";
			$curr_time1 = explode("-",date("H-i"));					// Current time
			
			$curr_time = "$curr_time1[0]$curr_time1[1]";
			 
			$cur_date_time = strtotime("now");						// Current date with time
			
			/***************************
			******* 1 : continue *******
			******* 2 : delete   *******
			******* 3 : update   *******
			****************************/
			
			if((($cur_date>$enddate_sch) || ($cur_date==$enddate_sch && $curr_time>$starttime_sch)) && $senton_sch!="" && $recurrence_sch == "0")
			{
				$return_status="2";
			}
			else if($recurrence_sch=="0")	// Repetitive Type is None
			{
			
				if($cur_date_time<$recurr_start_date)
				{
					$return_status="1";
				}
				else if($cur_date_time>=$recurr_start_date && $senton_sch=="")
				{
					$return_status="3";
				}
				else if($cur_date_time>=$recurr_start_date && $senton_sch!="")
				{
					$return_status="2";
				}
			}
			else if($recurrence_sch=="2")	//Weekly
			{	
				if($cur_date_time<$recurr_start_date)
				{
					$return_status="1";
				}
				else if(strpos($recurr_days_sch,$cur_date_sch[0])>-1 && $curr_time>=$starttime_sch && $senton_sch!=date("Y-m-d")) 
				{
					$return_status="3";
				}
				else if($cur_date>=$recurr_end_date && $curr_time>$starttime_sch && $senton_sch==date("Y-m-d") && $recurr_end_date != "0")
				{
					$return_status="2";
				}
				else
				{
					$return_status="1";
				}
				
			}
			else if($recurrence_sch=="3")	// Monthly
			{				
				$stdate=$cur_date_time;
				getMonthWeek($stdate);
				$mno=date("n",$stdate);
				
				if(strpos($recurr_days_sch,$cur_date_sch[0])>-1 && $curr_time>=$starttime_sch && $senton_sch!=date("Y-m-d")) 
				{
					foreach($Weekmonthdays[$mno] as $mtWk=>$weekdays)
					{
						if(in_array($cur_date_sch[1],$weekdays) && in_array(($mtWk+1),$recurr_options_sch_array))
						{
							$return_status="3";
						}
					}
				}
				else if($cur_date_time<$recurr_start_date)
				{
					$return_status="1";
				}
				else if($cur_date>=$recurr_end_date && $curr_time>$starttime_sch && $senton_sch==date("Y-m-d") && $recurr_end_date != "0")			{
					$return_status="2";
				}
				else
				{
					$return_status="1";
				}
			}
			else if($recurrence_sch=="1")	// Alternate week
			{
				$stdate=$cur_date_time;
				$dayOfRecord=date("w",$recurr_start_date);
				$addRecordday=(date("d",$recurr_start_date)-$dayOfRecord);
				$RecordSunday=mktime(0,0,0,date("m",$recurr_start_date),$addRecordday,date("Y",$recurr_start_date));
				$dayOfLoopday=date("w",$stdate);
				$addLoopday=(date("j",$stdate)-$dayOfLoopday);
				$LoopSunday=mktime(0,0,0,date("m",$stdate),$addLoopday,date("Y",$stdate));
				$diffOfdays=ceil(($LoopSunday/86400)-($RecordSunday/86400));
				$ResulMod=($diffOfdays%14);
				
				if($cur_date_time<$recurr_start_date)
				{
					$return_status="1";
				}
				else if($ResulMod==0 && strpos($recurr_days_sch,$cur_date_sch[0])>-1 && $starttime_sch  <= $curr_time && $senton_sch!=date("Y-m-d"))
					$return_status="3";
				else if($cur_date>=$recurr_end_date && $curr_time>$starttime_sch && $senton_sch==date("Y-m-d") && $recurr_end_date != "0")
				{
					$return_status="2";
				}
				else
				{
					$return_status="1";
				}
				
			}
			else if($recurrence_sch=="4")	//Yearly
			{
				if(date("jn",$recurr_start_date)==date("jn") && $starttime_sch  <= $curr_time && $senton_sch!=date("Y-m-d"))
				{
					$return_status="3";
				}
				else if($cur_date>=$recurr_end_date && $curr_time>$starttime_sch && $senton_sch==date("Y-m-d") && $recurr_end_date != "0")
				{
					$return_status="2";
				}
				else
				{
					$return_status="1";
				}			
			}
			else
			{
				$return_status="1";
			}
			if($recurr_end_date<$recurr_start_date && $recurr_end_date!="0")
			{
				$return_status="2";
			}
		}
		return $return_status;
	}
?>
