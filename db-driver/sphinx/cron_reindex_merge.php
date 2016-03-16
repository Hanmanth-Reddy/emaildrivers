<?php
$include_path=dirname(__FILE__);
ini_set('memory_limit',-1);
ini_set("max_execution_time", -1);
ini_set('display_errors', 1);
ini_set("include_path",$include_path);
$DOCUMENT_ROOT = '';
$companyuser='';
require("../global.inc");

$dque="SELECT capp_info.comp_id FROM company_info LEFT JOIN capp_info ON capp_info.sno = company_info.sno LEFT JOIN options ON options.sno=company_info.sno WHERE options.sphinxdb='Y' AND company_info.status = 'ER' ORDER BY capp_info.comp_id";

$dres=mysql_query($dque,$maindb);
if(mysql_num_rows($dres)>0)
{
	while($drow=mysql_fetch_array($dres))
	{
		// Fetch the Companies one by one
		$companyuser=strtolower($drow[0]);
		
		require("../database.inc");
		
		$cque="SELECT dp.comp_id ,dp.merge_status , GROUP_CONCAT(LOWER(dp.module)) AS modules, dp.last_update
		FROM akken_sphinx.deltaupdates as dp WHERE dp.comp_id='".$companyuser."' AND dp.merge_status = 'NO' GROUP BY dp.comp_id ORDER BY dp.last_update ASC";
		$cres=mysql_query($cque,$db);
		while($crow=mysql_fetch_array($cres))
		{
			$modules=strtolower($crow[2]);				
			$moduleslist = explode(",",$modules);
		
			if(count($moduleslist)!=0)
			{
				if (in_array("contacts", $moduleslist)) {
					
					`/usr/bin/indexer --merge {$companyuser}_cont_main {$companyuser}_cont_delta --rotate --config /etc/sphinx/sphinx.conf --merge-dst-range deleted 0 0`;
					
					mysql_query("REPLACE INTO sph_counter SELECT 'contacts_list',MAX(sno),'contacts',MAX(mdate) FROM staffoppr_contact",$db);
					
					mysql_query("UPDATE akken_sphinx.deltaupdates SET merge_status ='YES',merge_cron_update=NOW() WHERE comp_id ='".$companyuser."' AND module='Contacts' ",$db);
					
					`/usr/bin/indexer {$companyuser}_cont_delta --rotate --config /etc/sphinx/sphinx.conf`;
				}
				
				if (in_array("companies", $moduleslist)) {
					
					`/usr/bin/indexer --merge {$companyuser}_comp_main {$companyuser}_comp_delta --rotate --config /etc/sphinx/sphinx.conf --merge-dst-range deleted 0 0`;
					
					mysql_query("REPLACE INTO sph_counter SELECT 'companies_list',MAX(sno),'companies',MAX(mdate) FROM staffoppr_cinfo",$db);
					
					mysql_query("UPDATE akken_sphinx.deltaupdates SET merge_status ='YES',merge_cron_update=NOW() WHERE comp_id ='".$companyuser."' AND module='Companies' ",$db);
					
					`/usr/bin/indexer {$companyuser}_comp_delta --rotate --config /etc/sphinx/sphinx.conf`;
				}
				
				if (in_array("candidates", $moduleslist)) {
					
					`/usr/bin/indexer --merge {$companyuser}_cand_main {$companyuser}_cand_delta --rotate --config /etc/sphinx/sphinx.conf --merge-dst-range deleted 0 0`;
					
					mysql_query("REPLACE INTO sph_counter SELECT 'candidate_list',MAX(sno),'candidates',MAX(mtime) FROM candidate_list",$db);
					
					mysql_query("UPDATE akken_sphinx.deltaupdates SET merge_status ='YES',merge_cron_update=NOW() WHERE comp_id ='".$companyuser."' AND module='Candidates' ",$db);
					
					`/usr/bin/indexer {$companyuser}_cand_delta --rotate --config /etc/sphinx/sphinx.conf`;
				}
				
				if (in_array("joborders", $moduleslist)) {
					
					`/usr/bin/indexer --merge {$companyuser}_job_main {$companyuser}_job_delta --rotate --config /etc/sphinx/sphinx.conf --merge-dst-range deleted 0 0`;
					
					mysql_query("REPLACE INTO sph_counter SELECT 'joborders_list',MAX(posid),'joborders',MAX(mdate) FROM posdesc",$db);	
					
					mysql_query("UPDATE akken_sphinx.deltaupdates SET merge_status ='YES',merge_cron_update=NOW() WHERE comp_id ='".$companyuser."' AND module='JobOrders' ",$db);
					
					`/usr/bin/indexer {$companyuser}_job_delta --rotate --config /etc/sphinx/sphinx.conf`;
				}
				
			}
		}
	}
}
?>