<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("smtp.inc");
	require("html2text.inc");
	require("saveemails.inc");

	$smtp=new smtp_class;
	$smtp->host_name="smtp.akken.com";
	$smtp->host_port="465";
	$smtp->localhost="smtp.akken.com";

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$nque="SELECT p.id, p.type, p.title, p.descri, c.username, c.sno, c.email 
		FROM akken_notifications.notifications p 
		LEFT JOIN akken_notifications.notifications_list c ON p.sno=c.psno 
		WHERE p.email='Y' AND p.comp_id='".$companyuser."' AND c.email!='' AND c.email_status='N' ORDER BY p.cdate";
		$nres=mysql_query($nque,$db);
		while($nrow=mysql_fetch_row($nres))
		{
			$from = "Akken Notifications <donot-reply@akken.com>";
			$mailtype = "text/html";

			$nid = $nrow[0];
			$ntype = $nrow[1];
			$csno = $nrow[5];

			$to = $nrow[6];
			$subject = $nrow[2];

			$matter = "<div style='font-family: arial; font-size: 10pt;'>".str_replace("\n","<br>",$nrow[3])."<br><br></div>";
			$ato = explode(",",$to);

			if($to!="")
			{
				$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Subject: $subject","MIME-Version: 1.0");
				$msg_body=prepareBodyA($matter,$mailheaders,$mailtype);
				$smtp->SendMessage($from,$ato,$mailheaders,$msg_body);
			}

			$uque="UPDATE akken_notifications.notifications_list SET email_status='Y', email_ndate=NOW() WHERE sno=$csno";
			mysql_query($uque,$db);
		}
	}
?>