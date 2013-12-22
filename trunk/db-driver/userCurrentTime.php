<?php
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");

	$dque="SELECT capp_info.comp_id FROM company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno WHERE company_info.status='ER' AND capp_info.comp_id IN ('akken','akkentech','betaasd') ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$que="UPDATE users_session SET active='N' WHERE FROM_UNIXTIME(sessiontime) < DATE_SUB((NOW()), INTERVAL 1 minute) AND active='Y'";
		mysql_query($que,$db);
	}
?>
