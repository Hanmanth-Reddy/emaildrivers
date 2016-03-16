<?php
if($companyuser!="")
{
	require("cdatabase.inc");
	$companyuser=strtolower($companyuser);

	$dbg_que="select group_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where capp_info.comp_id='$companyuser'";
	$dbg_res=mysql_query($dbg_que,$maindb);
	$dbg_row=mysql_fetch_row($dbg_res);

	require("sysDBsysdb.inc");
	$akken_db_group=$dbg_row[0];

	$int_master_db = getGroupDBIPS($akken_db_group,$sysdb,"Master");
	$db=getActiveDatabase($int_master_db);

	mysql_select_db("akken_sphinx",$db);
	require("cdatabase.inc");
}
?>