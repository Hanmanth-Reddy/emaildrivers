<?php
	// rec_type = daily and rec_subtype = day
	function recurrenceOption1($sdatetime,$rec_endoption,$appt_enddate,$occurence,$rec_day,$rec_week,$rec_month)
	{
		global $maildb,$db;

		$toccurences = 0;
		if($rec_endoption == "noenddate")
			$toccurences = 100;
		else if($rec_endoption == "endby")
			$toccurences = round(($appt_enddate - $sdatetime)/(86400 * $rec_day)) + 1;
		else if($rec_endoption == "endafter")
			$toccurences = $occurence;

		$i=0;
		$count=1;
		$recurrences = array();

		$utzos = getUserSTZOffset();
		list($yr,$mon,$day,$hr,$min,$sec) = explode("-",date('Y-m-d-H-i-s',($sdatetime + $utzos)));

		do
		{
			$startday = date("z", $sdatetime + $utzos);
			$thisday = date("z", $sdatetime + (86400 * $i) + $utzos);
			$diffday = $thisday - $startday;

			if($diffday%$rec_day == 0)
			{
				$recurrences[$count] = mktime($hr,$min,$sec,$mon,($day+$i),$yr) - $utzos;
				$count++;
			}
			$i++;
		}
		while($count<=$toccurences);

		return $recurrences;
	}

	// rec_type = daily and rec_subtype = week
	function recurrenceOption2($sdatetime,$rec_endoption,$appt_enddate,$occurence,$rec_day,$rec_week,$rec_month)
	{
		global $maildb,$db;

		$increment = 1;
		if($appt_enddate != 0)
		{
			$wenddate = date("w",$appt_enddate);
			if($wenddate == 0 || $wenddate == 6)
				$increment = 0;
		}	

		$toccurences = 0;
		if($rec_endoption == "noenddate")
			$toccurences = 100;
		else if($rec_endoption == "endby")
			$toccurences = round(($appt_enddate - $sdatetime)/86400) + $increment;
		else if($rec_endoption == "endafter")
			$toccurences = $occurence;

		$i=0;
		$count=1;
		$recurrences = array();
		
		$utzos = getUserSTZOffset();
		list($yr,$mon,$day,$hr,$min,$sec) = explode("-",date('Y-m-d-H-i-s',($sdatetime + $utzos)));

		do
		{
			$newday = ($sdatetime + (86400 * $i));
			$weekday = date("w",($utzos + $newday));
			if($weekday != 0 && $weekday != 6)
			{
				$recurrences[$count] = $newday;
				$recurrences[$count] = mktime($hr,$min,$sec,$mon,($day+$i),$yr) - $utzos;
				$count++;
			}
			if($rec_endoption == "endby" && (date('Y-m-d',$newday) == date('Y-m-d',$appt_enddate)))
				$count = $toccurences+1;
			$i++;
		}
		while($count<=$toccurences);
		
		return $recurrences;
	}

	// rec_type = weekly
	function recurrenceOption3($sdatetime,$rec_endoption,$appt_enddate,$occurence,$rec_day,$rec_week,$rec_month)
	{
		global $maildb,$db;

		$weekdays=explode(":",$rec_month);

		$toccurences = 0;
		if($rec_endoption == "noenddate")
			$toccurences = 100;
		else if($rec_endoption == "endby")
			$toccurences = (round(($appt_enddate - $sdatetime)/(86400 * 7 * $rec_week)) * count($weekdays)) + 1;
		else if($rec_endoption == "endafter")
			$toccurences = $occurence;

		$i=0;
		$count=1;
		$recurrences = array();
		
		$utzos = getUserSTZOffset();
		list($yr,$mon,$day,$hr,$min,$sec) = explode("-",date('Y-m-d-H-i-s',($sdatetime + $utzos)));

		if($rec_endoption == "endby")
		{
			$enddate = date('Y-m-d',$appt_enddate);
			do
			{
				$startweek = date("W",$sdatetime);
				$thisweek = date("W",$sdatetime + (86400 * $i));
				$weekday = date("w", $sdatetime + $utzos + (86400 * $i));
				$diffweek = $thisweek - $startweek;
	
				if(($diffweek%$rec_week == 0) && (in_array($weekday,$weekdays)))
				{
					$recurrences[$count] = mktime($hr,$min,$sec,$mon,($day+$i),$yr) - $utzos;
					$count++;
				}
				$curdate = date('Y-m-d',($sdatetime + $utzos + (86400 * $i)));
				$i++;
			}
			while($curdate<=$enddate);
		}
		else
		{
			do
			{
				$startweek = date("W",$sdatetime);
				$thisweek = date("W",$sdatetime + (86400 * $i));
				$weekday = date("w", $sdatetime + $utzos + (86400 * $i));
				$diffweek = $thisweek - $startweek;
	
				if(($diffweek%$rec_week == 0) && (in_array($weekday,$weekdays)))
				{
					$recurrences[$count] = mktime($hr,$min,$sec,$mon,($day+$i),$yr) - $utzos;
					$count++;
				}
				$i++;
			}
			while($count<=$toccurences);
		}
		return $recurrences;
	}

	// rec_type = monthly and rec_subtype = day
	function recurrenceOption4($sdatetime,$rec_endoption,$appt_enddate,$occurence,$rec_day,$rec_week,$rec_month)
	{
		global $maildb,$db;

		$toccurences = 0;
		if($rec_endoption == "noenddate")
			$toccurences = 100;
		else if($rec_endoption == "endby")
			$toccurences = round(($appt_enddate - $sdatetime)/(86400 * 30 * $rec_month)) + 1;
		else if($rec_endoption == "endafter")
			$toccurences = $occurence;

		$i=0;
		$count=1;
		$mdays=0;
		$recurrences = array();

		$utzos = getUserSTZOffset();

		list($yr,$mon,$day,$hr,$min,$sec) = explode("-",date('Y-m-d-H-i-s',($sdatetime + $utzos)));
		$cnt = 0;
		
		if($rec_endoption == "endby")
		{
			do
			{	
				$enddate = date('Y-m-d',$appt_enddate);
				if($mdays==0)
				{
					$mdays = date("t",$sdatetime);
					$cdatetime = $sdatetime + $utzos;
				}
				else
				{
					$mdays = date("t",$cdatetime);
					
					$cnt = $cnt + $rec_month;
					$newmon = $mon + $cnt;
					
					$mon1 = ($newmon > 12) ? $newmon % 12 : $newmon;
					
					if($rec_day > 28)
					{
						if($mon1==2)
						{
							if($yr%4 == 0)
								$day = 29;
							else
								$day = 28;	
						}
						else if($rec_day==31 && ($mon1 == 4 || $mon1 == 6 || $mon1 == 9 || $mon1 == 11))
						{
							$day = 30;
						}
						else
						{
							$day = $rec_day;
						}
					}
					else
					{
						$day = $rec_day;
					}
					$cdatetime = mktime($hr,$min,$sec,$newmon,$day,$yr);
				}

				$startmonth = calc_months($sdatetime);
				$thismonth = calc_months($cdatetime);
				$diffmonth = abs($thismonth - $startmonth);

				$curdate = date('Y-m-d',$cdatetime);

				if($diffmonth%$rec_month == 0)
				{
					if($curdate<=$enddate)
					{
						$recurrences[$count] = $cdatetime - $utzos;
						$count++;
					}
				}
				$i++;
			}
			while($curdate<=$enddate);
		}
		else
		{	
			do
			{
				if($mdays==0)
				{
					$mdays = date("t",$sdatetime);
					$cdatetime = $sdatetime + $utzos;
				}
				else
				{
					$mdays = date("t",$cdatetime);

					$cnt = $cnt + $rec_month;
					$newmon = $mon + $cnt;

					$mon1 = ($newmon > 12) ? $newmon % 12 : $newmon;

					if($rec_day > 28)
					{
						if($mon1==2)
						{
							if($yr%4 == 0)
								$day = 29;
							else
								$day = 28;
						}
						else if($rec_day==31 && ($mon1 == 4 || $mon1 == 6 || $mon1 == 9 || $mon1 == 11))
						{
							$day = 30;
						}
						else
						{
							$day = $rec_day;
						}
					}
					else
					{
						$day = $rec_day;
					}
					$cdatetime = mktime($hr,$min,$sec,$newmon,$day,$yr);
				}

				$startmonth = calc_months($sdatetime);
				$thismonth = calc_months($cdatetime);
				$diffmonth = abs($thismonth - $startmonth);

				if($diffmonth%$rec_month == 0)
				{
					$recurrences[$count] = $cdatetime - $utzos;
					$count++;
				}
				$i++;
			}
			while($count<=$toccurences);
		}
		return $recurrences;
	}

	// rec_type = monthly and rec_subtype = month
	function recurrenceOption5($sdatetime,$rec_endoption,$appt_enddate,$occurence,$rec_day,$rec_week,$rec_month)
	{
		global $maildb,$db,$cdatetime;

		$toccurences = 0;
		if($rec_endoption == "noenddate")
			$toccurences = 100;
		else if($rec_endoption == "endby")
			$toccurences = round(($appt_enddate - $sdatetime)/(86400 * 30 * $rec_month));
		else if($rec_endoption == "endafter")
			$toccurences = $occurence;

		$i=0;
		$count=1;
		$mdays=0;
		$recurrences = array();

		// Very important to add this starttime difference for recurrence events for 5 and 7
		$starttime = $sdatetime - mktime(0, 0, 0, date("m",$sdatetime), date("d",$sdatetime), date("Y",$sdatetime));

		$utzos = getUserSTZOffset();
		list($yr,$mon,$day,$hr,$min) = explode("-",date('Y-m-d-H-i',$sdatetime + $utzos));
		$cnt = 0;

		do
		{
			if($mdays==0)
			{
				$mdays = date("t",$sdatetime);
				$cdatetime = $sdatetime;
			}
			else
			{
				$mdays = date("t",$cdatetime);
				$cnt = $cnt + $rec_month;
				$newmon = $mon + $cnt;
				$cdatetime = mktime($hr,$min,0,$newmon,1,$yr);
			}

			$startmonth = calc_months($sdatetime);
			$thismonth = calc_months($cdatetime);
			$diffmonth = abs($thismonth - $startmonth);

			if($diffmonth%$rec_month == 0)
			{
				if($count == 1)
				{
					$recurrences[$count] = $sdatetime;
				}
				else
				{
					if($rec_week == "day")
						$recurrences[$count] = getDayValue() + $starttime;
					else if($rec_week == "weekday")
						$recurrences[$count] = getWeekdayValue() + $starttime;
					else if($rec_week == "weekend day")
						$recurrences[$count] = getWeekendValue() + $starttime;
					else
						$recurrences[$count] = getAlldayValue() + $starttime;
				}
				$count++;
			}
			$i++;
		}
		while($count<=$toccurences);

		return $recurrences;
	}

	// rec_type = yearly and rec_subtype = day
	function recurrenceOption6($sdatetime,$rec_endoption,$appt_enddate,$occurence,$rec_day,$rec_week,$rec_month)
	{
		global $maildb,$db;

		$toccurences = 0;
		if($rec_endoption == "noenddate")
			$toccurences = 100;
		else if($rec_endoption == "endby")
			$toccurences = round(($appt_enddate - $sdatetime)/(86400 * 365)) + 1;
		else if($rec_endoption == "endafter")
			$toccurences = $occurence;

		$i=0;
		$count=1;
		$recurrences = array();

		$utzos = getUserSTZOffset();

		$cdatetime = $sdatetime + $utzos;

		do
		{
			$recurrences[$count] = mktime(date("H",$cdatetime), date("i",$cdatetime), date("s",$cdatetime), $rec_month, $rec_day, (date("Y",$cdatetime) + ($i))) - $utzos;
			$count++;
			$i++;
		}
		while($count<=$toccurences);

		return $recurrences;
	}

	// rec_type = yearly and rec_subtype = month
	function recurrenceOption7($sdatetime,$rec_endoption,$appt_enddate,$occurence,$rec_day,$rec_week,$rec_month)
	{
		global $maildb,$db,$cdatetime;

		$toccurences = 0;
		if($rec_endoption == "noenddate")
			$toccurences = 100;
		else if($rec_endoption == "endby")
			$toccurences = round(($appt_enddate - $sdatetime)/(86400 * 365)) + 1;
		else if($rec_endoption == "endafter")
			$toccurences = $occurence;

		$i=0;
		$count=1;
		$recurrences = array();

		$utzos = getUserSTZOffset();
		list($yr,$mon,$day,$hr,$min) = explode("-",date('Y-m-d-H-i',($sdatetime+$utzos)));

		// Very important to add this starttime difference for recurrence events for 5 and 7
		$starttime = $sdatetime - mktime(0, 0, 0, date("m",$sdatetime), date("d",$sdatetime), date("Y",$sdatetime));

		do
		{
			$cdatetime = mktime($hr, $min, 0, $mon, 1, ($yr + ($i)));

			if($count == 1)
			{
				$recurrences[$count] = $sdatetime;
			}
			else 
			{
				if($rec_week == "day")
					$recurrences[$count] = getDayValue() + $starttime;
				else if($rec_week == "weekday")
					$recurrences[$count] = getWeekdayValue() + $starttime;
				else if($rec_week == "weekend day")
					$recurrences[$count] = getWeekendValue() + $starttime;
				else
					$recurrences[$count] = getAlldayValue() + $starttime;
			}
			$count++;
			$i++;
		}
		while($count<=$toccurences);

		return $recurrences;
	}

	function calc_months($datetime)
	{
		$year = date("Y", $datetime);
		$months= $year * 12;
		$month = date("n", $datetime);

		return $months + $month;
	}

	function getdaynum($day)
	{
		switch($day)
		{
			case "second" : 
				return "1";
				break;
			case "third" : 
				return "2";
				break;
			case "fourth" : 

				return "3";
				break;
		}
	}

	function getalldays($day,$week,$month,$year)
	{
		if($day == "last")
			return strtotime($day." ".$week, mktime(0, 0, 0, $month+1, 1, $year));
		else
			return strtotime($day." ".$week, mktime(0, 0, 0, $month, 0, $year));
	}

	function getfirstweekday($day,$month,$year)
	{
		$lday = strtotime($day." Day", mktime(0, 0, 0, $month, 0, $year));
		return getfirst_weekday($lday);
	}

	function getfirst_weekday($lday)
	{
		$weekday = date("w", $lday);
		if($weekday>0 && $weekday<6)
			return $lday;

		return getfirst_weekday($lday + 86400);
	}

	function getlastweekday($day,$month,$year)
	{
		$lday = strtotime($day, mktime(0, 0, 0, $month+1, 1, $year));
		return getlast_weekday($lday);
	}

	function getlast_weekday($lday)
	{
		$weekday = date("w", $lday);
		if($weekday>0 && $weekday<6)
			return $lday;

		return getlast_weekday($lday - 86400);
	}

	function getweekdays($day,$month,$year)
	{
		$lday = strtotime("first Day", mktime(0, 0, 0, $month, 0, $year));
		$weekday = date("w", $lday);

		//if($weekday == 6 || $weekday == 5) Not sure, this case commented out for future reference in case we come across into this case.
		if($weekday == 0)
			$lday = $lday + 86400;
		else if($weekday == 6)
			$lday = $lday + 172800;

		$d = getdaynum($day);
		$lday = $lday + (86400 * $d);

		return getfirst_weekday($lday);
	}

	function getfirstweekend($day,$month,$year)
	{
		$lday = strtotime($day." Day", mktime(0, 0, 0, $month, 0, $year));
		return getfirst_weekend($lday);
	}

	function getfirst_weekend($lday)
	{
		$details = getdate($lday);
		$weekday = date("w", $lday);
		if($weekday == 0 || $weekday == 6)
			return $lday;

		return getfirst_weekend($lday + 86400);
	}

	function getlastweekend($day,$month,$year)
	{
		$lday = strtotime($day, mktime(0, 0, 0, $month+1, 1, $year));
		return getlast_weekend($lday);
	}

	function getlast_weekend($lday)
	{
		$details = getdate($lday);
		$weekday = date("w", $lday);
		if($weekday == 0 || $weekday == 6)
			return $lday;

		return getlast_weekend($lday - 86400);
	}

	function getallweekends($day,$month,$year)
	{
		$firstweekend = getfirstweekend("first",$month,$year);

		$weekday = date("w", $firstweekend);
		if($weekday == 6)
		{
			if($day == "second")
				return $firstweekend + (86400 * 1);
			else if($day == "third")
				return $firstweekend + (86400 * 7);
			else if($day == "fourth")
				return $firstweekend + (86400 * 8);
		}
		else if($weekday == 0)
		{
			if($day == "second")
				return $firstweekend + (86400 * 6);
			else if($day == "third")
				return $firstweekend + (86400 * 7);
			else if($day == "fourth")
				return $firstweekend + (86400 * 13);
		}
	}

	function getAlldayValue()
	{
		global $cdatetime,$rec_day,$rec_week,$rec_month;

		$cmonth = date("m",$cdatetime);
		$cyear = date("Y",$cdatetime);

		return getalldays($rec_day,$rec_week,$cmonth,$cyear);
	}

	function getDayValue()
	{
		global $cdatetime,$rec_day,$rec_week,$rec_month;

		$cmonth = date("m",$cdatetime);
		$cyear = date("Y",$cdatetime);

		return getalldays($rec_day,"Day",$cmonth,$cyear);
	}

	function getWeekdayValue()
	{
		global $cdatetime,$rec_day,$rec_week,$rec_month;

		$cmonth = date("m",$cdatetime);
		$cyear = date("Y",$cdatetime);

		if($rec_day == "first")
			return getfirstweekday($rec_day,$cmonth,$cyear);
		else if($rec_day == "last")
			return getlastweekday('last Day',$cmonth,$cyear);
		else
			return getweekdays($rec_day,$cmonth,$cyear);
	}

	function getWeekendValue()
	{
		global $cdatetime,$rec_day,$rec_week,$rec_month;

		$cmonth = date("m",$cdatetime);
		$cyear = date("Y",$cdatetime);

		if($rec_day == "first")
			return getfirstweekend($rec_day,$cmonth,$cyear);
		else if($rec_day == "last")
			return getlastweekend('last Day',$cmonth,$cyear);
		else
			return getallweekends($rec_day,$cmonth,$cyear);
	}
?>