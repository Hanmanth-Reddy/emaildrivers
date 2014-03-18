<?php
	// Mount point for Attachments
	$WDOCUMENT_ROOT="/tmp/".md5(time());
	if(!is_dir($WDOCUMENT_ROOT))
		mkdir($WDOCUMENT_ROOT,0777);

	$maildomain=$akken_arec_mail.".".$egdomain;
	$domainname=$companyuser.".".$egdomain;

	$mc_que="select emailuser from EmailAcc where username='$username' and type='R'";
	$mc_res=mysql_query($mc_que,$db);
	$mc_row=mysql_fetch_row($mc_res);
	$disus=$mc_row[0]."@".$domainname;

	$mc_que="select name from emp_list where username='$username'";
	$mc_res=mysql_query($mc_que,$db);
	$mc_row=mysql_fetch_row($mc_res);
	$act_name=$mc_row[0];
?>