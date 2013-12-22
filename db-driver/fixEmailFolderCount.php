<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("emailApplicationTrigger.php");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$cque="SELECT username FROM users WHERE usertype!='' AND status!='DA'";
		$cres=mysql_query($cque,$db);
		while($crow=mysql_fetch_row($cres))
		{
			$username=$crow[0];
			update_efolder();
		}
	}
?>
