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

		$ubque="select external_mail.sno,UNIX_TIMESTAMP()-UNIX_TIMESTAMP(external_mail.cdate) from external_mail LEFT JOIN users ON external_mail.username=users.username where ((UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(external_mail.cdate))>=300) and external_mail.lockm='Yes' and users.usertype!='' and users.status!='DA'";
		$ubres=mysql_query($ubque,$db);
		while($ubrow=mysql_fetch_row($ubres))
		{
			$extsno=$ubrow[0];
			$que="update external_mail set lockm='No',cdate=NOW() where sno=$extsno";
			mysql_query($que,$db);
		}
	}
?>