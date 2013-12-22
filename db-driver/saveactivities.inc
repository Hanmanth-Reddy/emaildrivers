<?php
	//For getting the STANDARD time zone offset
	function getUserSTZOffset()
	{
	    global $maildb,$db,$username;

		$utz = "+0000";

		$que="select REPLACE(timezone.stdtime,':','') FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid='$username'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
		if($row[0]!="")
			$utz = $row[0];

		$sign = substr($utz, 0, 1);
		$hours = substr($utz, 1, 2);
		$mins = substr($utz, 3, 2);
		$secs = ((int)$hours * 3600) + ((int)$mins * 60);

		if ($sign == '-') 
			$secs = 0 - $secs;

		return $secs;
	}

	//For getting the time zone offset
	function getUserTZOffset($timestamp)
	{
	    global $maildb,$db,$username;

		$utz = "+0000";

		if(date("I",$timestamp)==1)
			$que="select REPLACE(timezone.daytime,':','') FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid='$username'";
		else
			$que="select REPLACE(timezone.stdtime,':','') FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid='$username'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
		if($row[0]!="")
			$utz = $row[0];

		$sign = substr($utz, 0, 1);
		$hours = substr($utz, 1, 2);
		$mins = substr($utz, 3, 2);
		$secs = ((int)$hours * 3600) + ((int)$mins * 60);

		if ($sign == '-') 
			$secs = 0 - $secs;

		return $secs;
	}
?>