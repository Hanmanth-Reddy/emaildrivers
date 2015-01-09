<?php
	require("mailconfig.inc");

	preg_match(EMAIL_REG_EXP,$efrom,$eemail);
	$reqemail=$eemail[0];

	// We need to set all the properties of SMTP to DEFAULT. Because if the file is included recursively in loop few of the variables like $smtp->user and $smtp->password are not resetting back to empty and mails are failing because of these set for previous user. I guess, because of this eCampaigns are not attempt to send all the times. These values are set any way in this file based on the user emailid below.
	$smtp->ssl=0;
	$smtp->popssl=0;
	$smtp->og_encrypt="";
	$smtp->pop3_auth_host="";
	$smtp->pop3_auth_port="";
	$smtp->user="";
	$smtp->password="";
	$smtp->imapExtSno=0;

	$ogsslchk="";
	$imsslchk="";
	$og_encrypt="";

	$pophost="";
	$popport="";
	$popuser="";
	$poppwd="";
	$imapExtSno = "";

	$sdomain=explode("@",$reqemail);
	if($domainname==trim($sdomain[1]))
	{
		$from=$act_name." <".$reqemail.">";
		$hostname=$maildomain;
		$hostport=$akken_arec_port;
	}
	else
	{
		$sque="select ogaddress,ogport,imaddress,import,account,passwd,mailid,disname,logon,susername,spassword,ogsslchk,sno,mtype,og_encrypt,imsslchk from external_mail where username='".$username."' and mailid='".addslashes($reqemail)."'";
		$sres=mysql_query($sque,$db);
		$srow=mysql_fetch_row($sres);
		if(mysql_num_rows($sres)>0)
		{
			if(strpos($srow[7],","))
				$srow[7]="\"".$srow[7]."\"";

			$from=$srow[7]." <".$srow[6].">";
			$hostname=$srow[0];
			$hostport=$srow[1];
			$new_disus=$srow[6];
			$sdomain=explode("@",$srow[6]);
			$realm=$sdomain[1];
			$ogsslchk=$srow[11];
			$og_encrypt=$srow[14];
			$imsslchk=$srow[15];

			if($srow[13]=="imap")
				$imapExtSno = $srow[12];

			if($srow[8]!="")
			{
				require_once("sasl.inc");
				if($srow[8]=="LIM")
				{
					$popuser=$srow[4];
					$poppwd=$srow[5];
				}
				else if($srow[8]=="LOG")
				{
					$popuser=$srow[9];
					$poppwd=$srow[10];
				}
				else if($logon=="LBE")
				{
					if($imsslchk=="Yes")
						$smtp->popssl=1;

					$pophost=$srow[2];
					$popport=$srow[3];
					$popuser=$srow[4];
					$poppwd=$srow[5];
				}
			}
		}
		else
		{
			$from=$act_name." <".$reqemail.">";
			$hostname=$maildomain;
			$hostport=$akken_arec_port;
		}
	}

	if($from=="")
	{
		$from=$act_name." <".$disus.">";
		$hostname=$maildomain;
		$hostport=$akken_arec_port;
	}

	$smtp->host_name=$hostname;			/* Change this variable to the address of the SMTP server to relay, like "smtp.myisp.com" */
	$smtp->host_port=$hostport;			/* Change this variable to the address of the SMTP server to relay, like "smtp.myisp.com" */
	$smtp->localhost="smtp.akken.com";		/* Your computer address */

	if($ogsslchk=="Yes")
	{
		$smtp->ssl=1;
		$smtp->og_encrypt=$og_encrypt;
	}
	else
	{
		$smtp->ssl=0;
	}

	$smtp->pop3_auth_host=$pophost;		/* Set to the POP3 authentication host if your SMTP server requires prior POP3 authentication */
	$smtp->pop3_auth_port=$popport;		/* Set to the POP3 authentication port if your SMTP server requires prior POP3 authentication */
	$smtp->user=$popuser;				/* Set to the user name if the server requires authetication */
	$smtp->password=$poppwd;			/* Set to the authetication password */

	if($smtp->saveCopy)
		$smtp->imapExtSno=$imapExtSno;		/* Set external mail account sno if the type is IMAP to save sent items on IMAP Server*/
?>