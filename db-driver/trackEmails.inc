<?php
	function trackEmail($dlist,$emails)
	{
		if(count($dlist)>0)
		{
			$elist=getEmailDomains($emails);
			$diff=array_diff($elist,$dlist);

			if(count($diff)>0)
				return true;
			else
				return false;
		}

		return true;
	}

	function getEmailDomains($emails)
	{
		$elist=array();

		$semail=explode(",",$emails);
		for($i=0;$i<count($semail);$i++)
		{
			preg_match(EMAIL_REG_EXP,$semail[$i],$edomain);
			if($edomain[0]!="")
			{
				$sdomain=explode("@",$edomain[0]);
				$elist[]=trim(strtolower($sdomain[1]));
			}
		}

		return array_unique($elist);
	}

	function getTrackingPref()
	{
		global $db;

		$que="SELECT etrack,contacts,candidates,clients,consultants,employees,customers FROM company_info";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_array($res);

		return $row;
	}
?>