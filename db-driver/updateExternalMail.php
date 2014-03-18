<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER'".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$ubque="SELECT external_mail.sno, external_mail.stime, external_uidls.last_rdate FROM external_mail LEFT JOIN external_uidls ON external_mail.sno=external_uidls.extsno LEFT JOIN users ON external_mail.username=users.username WHERE external_mail.stime < (external_uidls.last_rdate-3600) AND users.usertype!='' AND users.status!='DA'";
		$ubres=mysql_query($ubque,$db);
		while($ubrow=mysql_fetch_row($ubres))
		{
			$stime = 0;
			$extsno=$ubrow[0];

			if($ubrow[1] < $ubrow[2])
			{
				$stime = $ubrow[2] - 3600;
				if($stime < $ubrow[2] && $stime > 0 && $stime > $ubrow[1])
				{
					$que="UPDATE external_mail SET stime=$stime WHERE sno=$extsno";
					mysql_query($que,$db);
				}
			}
		}
	}
?>