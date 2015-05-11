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

		$tblList = array('acc_reg','api_con_resumes','bank_trans','campaign_list','cmngmt_pr','con_resumes','contact_event','emp_list','empcon_tab','expense_processed','folder','folderlist2','folderlist_per','hrcon_compen','hrcon_general','hrcon_jobs','job_post_det','mail_headers','messageboard','orgsetup','par_timesheet','reg_accdesc','reg_category','reg_payee','reqresponse','resource_manage','search_data','search_notes','staffacc_list','sysuser','tabappoint','tasklist','timesheet','timesheet_processed','users','webfolder2');
		for($i=0;$i<count($tblList);$i++)
		{
			$tblName = $tblList[$i];
			if($tblName!="")
			{
				$fque="FLUSH TABLE ".$companyuser.".".$tblName;
				mysql_query($fque,$db);
				print $fque."\n";
			}
		}
	}
?>