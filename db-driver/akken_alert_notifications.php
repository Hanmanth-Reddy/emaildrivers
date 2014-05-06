<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");

	$meteor = fsockopen("192.168.1.62", 4671, $errno, $errstr, 10);

	if($meteor)
	{
		$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where company_info.status='ER' ".$version_clause;
		$dres=mysql_query($dque,$maindb);
		while($drow=mysql_fetch_row($dres))
		{
			$companyuser=strtolower($drow[0]);
			require("database.inc");

			$cque="SELECT c.username 
			FROM akken_notifications.notifications p 
			LEFT JOIN akken_notifications.notifications_list c ON p.sno=c.psno 
			WHERE p.alert='Y' AND c.username>0 AND c.alert_status='N' GROUP BY c.username";
			$cres=mysql_query($cque,$db);
			while($crow=mysql_fetch_row($cres))
			{
				$event = "";
				$username = $crow[0];
				$channel_name = "chan-".$companyuser."-".$username;

				$nque="SELECT p.title, p.descri, c.sno 
				FROM akken_notifications.notifications p 
				LEFT JOIN akken_notifications.notifications_list c ON p.sno=c.psno 
				WHERE p.alert='Y' AND c.alert_status='N' AND c.username='$username' ORDER BY p.cdate";
				$nres=mysql_query($nque,$db);
				while($nrow=mysql_fetch_row($nres))
				{
					if($event=="")
						$event=addslashes($nrow[0])."|akkenPSplit|"."================================================"."|akkenPSplit|".str_replace("\n","|akkenPSplit|",$nrow[1]);
					else
						$event.="|akkenESplit|".addslashes($nrow[0])."|akkenPSplit|"."================================================"."|akkenPSplit|".str_replace("\n","|akkenPSplit|",$nrow[1]);

					$uque="UPDATE akken_notifications.notifications_list SET alert_status='Y', alert_ndate=NOW() WHERE sno=".$nrow[2];
					mysql_query($uque,$db);
				}

				if($event!="")
				{
					$cmdop = "ADDMESSAGE ".$channel_name." ".$event."\n";
					fwrite($meteor, $cmdop);
				}
			}
		}

		fwrite($meteor, "QUIT\n");
		fclose($meteor);
	}
?>