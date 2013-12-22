<?php
	$sysdb=getActiveDatabase($int_sysdb);
	$maindb=$sysdb;

	require("sysDBsysdb.inc");

	// External IP Addresses
	$ext_ftp = getExtIPS("ftp",$sysdb);

	// Internal IP Addresses
	$int_ns = getLocIPS("ns",$sysdb);
	$int_ftp = getLocIPS("ftp",$sysdb);
	$int_app = getLocIPS("appserver",$sysdb);
	$int_mail = getLocIPS("mail",$sysdb);
?>
