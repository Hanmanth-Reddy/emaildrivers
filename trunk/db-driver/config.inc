<?php
	require("sysdb.inc");

	// Company ID for redirecting to Company Website when logout.
	$temp_companyuser=$companyuser;

	// Mail details
	$service_title="Akken, Inc";
	$admin_title="Akken, Inc - Admin";
	$admin_mail="akkensysadmin@$egdomain";
	$reg_lead_mail="akkenonline@$egdomain";
	$team_title="Akken, Inc";
	$support_mail="akkenonline@$egdomain";

	$PLUGIN_PATH="/usr/lib/nagios/plugins";
	$DB_HEALTH="$PLUGIN_PATH/check_db_health";

	// Public domain key value needs to inserted for each customer domain and subdomain
	$public_domainkey="k=rsa; t=n; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCjNYq8q5FYNpHrRs0+hz+pPdPuVraJPseGZu8JDqFmBnyR1d0y6Scj5dC7d00tkd5Fb+hA4o65WLE0AVjfU77oPlH1/fhrawjPnMf44Q37gFBwIul7VFz786YDVbhCHa+0GcXhzk5X/7Qtnh9AH0CBzj64/05OE4nsCF9yogYF+wIDAQAB;";

	// Redirect to maintanance page when a service on both of the servers (primary/secondary) fails.
	$url_redirect="http://www.$egdomain/showmes.php";

	// Session expired link
	$default_expire_link="http://login.$egdomain/?error=expire";

	// Registration redirection
	$default_reg_link="http://login.$egdomain/?error=success";

	// Logout Link
	$default_admin_logout_link="http://admin.$egdomain/"; // needs to define this variable for our sys admin module

	// Mount point for Companies Web Site for Front Office
	$pri_web_mount="/var/www/companies";

	// Default website template path
	$default_api_path="$DOCUMENT_ROOT/APIS/";

	// Mount point for Users Uploaded Files
	if(is_dir("/var/www/fs"))
	{
		$WDOCUMENT_ROOT="/var/www/fs/".session_id();
		if(!is_dir($WDOCUMENT_ROOT))
			mkdir($WDOCUMENT_ROOT,0777);
	}

	// Maximum Upload Size of Attachments
	$max_upload=20*1024*1024; // 20MB

	// Default akken Logo
	$default_comp_logo="$DOCUMENT_ROOT/logos/akken.gif";

	// Powered by logo name, path and link
	if($DOCUMENT_ROOT=="")
		$poweredby_logo="$include_path/poweredByAkken.gif";
	else
		$poweredby_logo="$DOCUMENT_ROOT/logos/poweredByAkken.gif";
	$poweredby_name="akken.gif";
	$poweredby_link="http://www.$egdomain/industries-akkenstaffing.php";
	
	//*********  /user/bin/ running path for excute the commands ********************************
	define("TNEF_EXC_PATH","/usr/local/bin/");
	
	//*********  Regular expreesion for E-Mail ********************************
	define("EMAIL_REG_EXP","/(([a-z0-9_]|\\!|\\#|\\$|\\%|\\*|\\+|\\/|\\=|\\?|\\^|\\_|\\`|\\{|\\||\\}|\\~|\\&|\\-|\\.|\\')+@([^[:space:]]*)([[:alnum:]-]))/i",true);
	
	// Set PushWoosh defines
	define('PW_AUTH', '0xF45s4arIuam9Fqr1WCiKWcWpWz0x0aA68ZHBOJueebMc1qTHn15xTHUcQd1EzDcKdYJHp1Pn4kWJo44lMf');
    define('PW_APPLICATION', '296D7-4C7A8');
	define('AKKEN_MOBI_URL', 'akken.mobi');
?>
