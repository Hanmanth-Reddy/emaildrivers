<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$que="SELECT c.sno, c.email FROM campaigns_unsubscribe c LEFT JOIN campaigns p ON c.parid=p.sno WHERE c.status='N' AND p.comp_id='$companyuser'";
		$res=mysql_query($que,$maindb);
		while($row=mysql_fetch_row($res))
		{
			$uque="UPDATE candidate_list SET dontemail='Y' WHERE dontemail='N' AND (email='".addslashes($row[1])."' OR alternate_email='".addslashes($row[1])."' OR other_email='".addslashes($row[1])."')";
			mysql_query($uque,$db);

			$uque="UPDATE staffoppr_contact SET dontemail='Y' WHERE dontemail='N' AND (email='".addslashes($row[1])."' OR email_2='".addslashes($row[1])."' OR email_3='".addslashes($row[1])."')";
			mysql_query($uque,$db);

			$ique="INSERT INTO campaigns_unsubscribe (email,udate) VALUES ('".addslashes($row[1])."',NOW())";
			mysql_query($ique,$db);

			$uque="UPDATE campaigns_unsubscribe SET status='Y' WHERE sno='".$row[0]."'";
			mysql_query($uque,$maindb);
		}
	}
?>