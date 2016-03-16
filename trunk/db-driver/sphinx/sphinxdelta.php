<?php
$include_path=dirname(__FILE__);
ini_set('memory_limit',-1);
ini_set("max_execution_time", -1);
ini_set('display_errors', 1);
ini_set("include_path",$include_path);
require("../global.inc");
$dque="SELECT capp_info.comp_id FROM company_info LEFT JOIN capp_info ON capp_info.sno = company_info.sno LEFT JOIN options ON options.sno=company_info.sno WHERE options.sphinxdb='Y' AND company_info.status = 'ER' AND capp_info.comp_id='nagarajum' ORDER BY capp_info.comp_id";
$dres=mysql_query($dque,$maindb);
if(mysql_num_rows($dres)>0)
{
	while($drow=mysql_fetch_array($dres))
	{
		// Fetch the Companies one by one
		$companyuser=strtolower($drow[0]);
		require("../database.inc");
			while (1) {
				if (filemtime('/sphinx-data/'.$companyuser.'/'.$companyuser.'_cand_main.sph') < time()-(24*3600)) {
					`/usr/bin/indexer {$companyuser}_cand_delta --rotate --config /etc/sphinx/sphinx.conf`;
						sleep(10);
					`/usr/bin/indexer --merge {$companyuser}_cand_main {$companyuser}_cand_delta --rotate`;
						mysql_query("REPLACE INTO sph_counter SELECT 'candidate_list',MAX(sno),'candidates',MAX(mtime) FROM candidate_list",$db);
					`/usr/bin/indexer {$companyuser}_cand_delta --rotate --config /etc/sphinx/sphinx.conf`;

				} elseif (filemtime('/sphinx-data/'.$companyuser.'/'.$companyuser.'_cont_main.sph') < time()-(24*3600)) {
					`/usr/bin/indexer {$companyuser}_cont_delta --rotate --config /etc/sphinx/sphinx.conf`;
						sleep(10);
					`/usr/bin/indexer --merge {$companyuser}_cont_main {$companyuser}_cont_delta --rotate`;
						mysql_query("REPLACE INTO sph_counter SELECT 'contacts_list',MAX(sno),'contacts',MAX(mdate) FROM staffoppr_contact",$db);
					`/usr/bin/indexer {$companyuser}_cont_delta --rotate --config /etc/sphinx/sphinx.conf`;
					
				} elseif (filemtime('/sphinx-data/'.$companyuser.'/'.$companyuser.'_comp_main.sph') < time()-(24*3600)) {
					`/usr/bin/indexer {$companyuser}_comp_delta --rotate --config /etc/sphinx/sphinx.conf`;
						sleep(10);
					`/usr/bin/indexer --merge {$companyuser}_comp_main {$companyuser}_comp_delta --rotate`;
						mysql_query("REPLACE INTO sph_counter SELECT 'companies_list',MAX(sno),'companies',MAX(mdate) FROM staffoppr_cinfo",$db);
					`/usr/bin/indexer {$companyuser}_comp_delta --rotate --config /etc/sphinx/sphinx.conf`;
					
				} elseif (filemtime('/sphinx-data/'.$companyuser.'/'.$companyuser.'_job_main.sph') < time()-(24*3600)) {
					`/usr/bin/indexer {$companyuser}_job_delta --rotate --config /etc/sphinx/sphinx.conf`;
						sleep(10);
					`/usr/bin/indexer --merge {$companyuser}_job_main {$companyuser}_job_delta --rotate`;
						mysql_query("REPLACE INTO sph_counter SELECT 'joborders_list',MAX(posid),'joborders',MAX(mdate) FROM posdesc",$db);
					`/usr/bin/indexer {$companyuser}_job_delta --rotate --config /etc/sphinx/sphinx.conf`;
					
				} else {
					`/usr/bin/indexer {$companyuser}_cand_delta --rotate --config /etc/sphinx/sphinx.conf`;
					`/usr/bin/indexer {$companyuser}_cont_delta --rotate --config /etc/sphinx/sphinx.conf`;
					`/usr/bin/indexer {$companyuser}_comp_delta --rotate --config /etc/sphinx/sphinx.conf`;
					`/usr/bin/indexer {$companyuser}_job_delta --rotate --config /etc/sphinx/sphinx.conf`;
				}
				sleep(5*60);
				clearstatcache();
			}
	}
}
?>