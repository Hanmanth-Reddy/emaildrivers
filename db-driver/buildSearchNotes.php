<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where company_info.status='ER' ".$version_clause." ORDER BY company_info.sno";
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$setQry="SET sql_log_bin=0";
		mysql_query($setQry,$db);

		$setQry="SET SESSION group_concat_max_len=1073740800";
		mysql_query($setQry,$db);

		$nque="SELECT type, contactid, GROUP_CONCAT(notes SEPARATOR ' '), GROUP_CONCAT(sno) FROM notes WHERE processed='N' GROUP BY type, contactid";
		$nres=mysql_query($nque,$db);
		while($nrow=mysql_fetch_row($nres))
		{
			$csno=0;

			if($nrow[0]=="oppr")
			{
				$cque="SELECT csno FROM staffoppr_contact WHERE sno='".$nrow[1]."'";
				$cres=mysql_query($cque,$db);
				$crow=mysql_fetch_row($cres);
				$csno=$crow[0];
			}

			$nrow[2]=addslashes($nrow[2]);

			$sque="SELECT COUNT(1) FROM search_notes WHERE uid='".$nrow[1]."' AND type='".$nrow[0]."'";
			$sres=mysql_query($sque,$db);
			$srow=mysql_fetch_row($sres);

			if($srow[0]>0)
				$uque="UPDATE search_notes SET notes = CONCAT(notes,' ','".$nrow[2])."') WHERE uid='".$nrow[1]."' AND type='".$nrow[0]."'";
			else
				$uque="INSERT INTO search_notes (uid,type,notes) VALUES ('".$nrow[1]."','".$nrow[0]."','".$nrow[2]."')";
			mysql_query($uque,$db);

			if($csno>0)
			{
				$sque="SELECT COUNT(1) FROM search_notes WHERE uid='".$csno."' AND type='com'";
				$sres=mysql_query($sque,$db);
				$srow=mysql_fetch_row($sres);

				if($srow[0]>0)
					$uque="UPDATE search_notes SET notes = CONCAT(notes,' ','".$nrow[2]."') WHERE uid='".$csno."' AND type='com'";
				else
					$uque="INSERT INTO search_notes (uid,type,notes) VALUES ('".$csno."','com','".$nrow[2]."')";
				mysql_query($uque,$db);
			}

			if($nrow[3]!="")
			{
				$uque="UPDATE notes SET processed='Y' WHERE sno IN (".$nrow[3].") AND processed='N'";
				mysql_query($uque,$db);
			}
		}
	}
?>