<?php
$include_path=dirname(__FILE__);
ini_set('memory_limit',-1);
ini_set("max_execution_time", -1);
ini_set('display_errors', 1);
ini_set("include_path",'/usr/bin/db-driver/');
$DOCUMENT_ROOT = '';
$companyuser='';
require("global.inc");

while (1) {
	$dque="SELECT capp_info.comp_id FROM company_info LEFT JOIN capp_info ON capp_info.sno = company_info.sno LEFT JOIN options ON options.sno=company_info.sno WHERE options.sphinxdb='Y' AND company_info.status = 'ER' ORDER BY capp_info.comp_id";
	
	$dres=mysql_query($dque,$maindb);
	if(mysql_num_rows($dres)>0)
	{
		while($drow=mysql_fetch_array($dres))
		{
			// Fetch the Companies one by one
			$companyuser=strtolower($drow[0]);
			
			require("sphinxdatabase.inc");
			
			$cque="SELECT dp.comp_id ,dp.delta_status , GROUP_CONCAT(LOWER(dp.module)) AS modules, dp.last_update
			FROM deltaupdates as dp WHERE dp.comp_id='".$companyuser."' AND dp.delta_status = 'NO' GROUP BY dp.comp_id ORDER BY dp.last_update ASC";
			$cres=mysql_query($cque,$db);
			if(mysql_num_rows($cres)>0)
			{
				while($crow=mysql_fetch_array($cres))
				{
					$modules=strtolower($crow[2]);				
					$moduleslist = explode(",",$modules);
				
					if(count($moduleslist)!=0)
					{
						if (in_array("contacts", $moduleslist)) {
							
							`/usr/bin/indexer {$companyuser}_cont_delta --rotate --config /etc/sphinx/sphinx.conf`;
							
							mysql_query("UPDATE deltaupdates SET delta_status ='YES',delta_cron_update=NOW() WHERE comp_id ='".$companyuser."' AND module='Contacts' ",$db);
						}
						
						if (in_array("companies", $moduleslist)) {
							
							`/usr/bin/indexer {$companyuser}_comp_delta --rotate --config /etc/sphinx/sphinx.conf`;
							
							mysql_query("UPDATE deltaupdates SET delta_status ='YES',delta_cron_update=NOW() WHERE comp_id ='".$companyuser."' AND module='Companies' ",$db);
						}
						
						if (in_array("candidates", $moduleslist)) {
							
							`/usr/bin/indexer {$companyuser}_cand_delta --rotate --config /etc/sphinx/sphinx.conf`;
							
							mysql_query("UPDATE deltaupdates SET delta_status ='YES',delta_cron_update=NOW() WHERE comp_id ='".$companyuser."' AND module='Candidates' ",$db);
						}
						
						if (in_array("joborders", $moduleslist)) {
							
							`/usr/bin/indexer {$companyuser}_job_delta --rotate --config /etc/sphinx/sphinx.conf`;
							
							mysql_query("UPDATE deltaupdates SET delta_status ='YES',delta_cron_update=NOW() WHERE comp_id ='".$companyuser."' AND module='JobOrders' ",$db);
						}
						
					}
				}
			}else
			{
				mysql_close($db);
				sleep(1*60);
				clearstatcache();
			}
		}
	}
}
?>