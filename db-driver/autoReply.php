<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("smtp.inc");
	require("html2text.inc");
	require("emailApplicationTrigger.php");
	require("saveemails.inc");
	require("PrepareInline.php");
	require("schedule.inc");

	$smtp=new smtp_class;

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("maildatabase.inc");
		require("database.inc");

		//Getting the logged in username
		$ubque="SELECT usr.username,rl.message,rl.RPLEmailIds,rl.sno FROM users usr, mail_auto_reply rl  WHERE usr.usertype!='' AND usr.status!='DA' AND usr.username=rl.username AND rl.automated_reply='Y'";
		$ubres=mysql_query($ubque,$db);
		while($ubrow=mysql_fetch_row($ubres))
		{
			$username=$ubrow[0];

			$uque="UPDATE mail_headers SET sent='REC' WHERE username='$username' AND sent='RPL' AND folder='spam'";
			mysql_query($uque,$db);

			$msg_body="";
			$RPLEmails=array();

			$Rec_msg_body=stripslashes($ubrow[1]);
			$SentMailIds=stripslashes($ubrow[2]);
			$UsrAutoRPLRecSno=$ubrow[3];
			if($SentMailIds!='')
				$RPLEmails=explode(",",$SentMailIds);

			//Getting the neccesary columns where the sent is RPL for autoreplying
			$que="SELECT mailid,extid,fromadd,subject FROM mail_headers WHERE username='$username' AND status='Active' AND sent='RPL'";
			$res=mysql_query($que,$db);
			$RPLRows=mysql_num_rows($res);	
			while($row=mysql_fetch_array($res))
			{
				$mailid=$row[0];
				$extsno=$row[1];

				$from="";
				$to=$row[2];
				$subject="Re: ".stripslashes($row[3]);

				if($extsno!="")
				{
					$eque="SELECT CONCAT(disname,' <',mailid,'>') FROM external_mail WHERE sno='$extsno'";
					$eres=mysql_query($eque,$db);
					$erow=mysql_fetch_array($eres);
					$from=stripslashes($erow[0]);
				}

				$toMailId=getEmailId($to);

				//send mail only one time for single emailid.	
				if(!in_array($toMailId, $RPLEmails))
				{
					$ato=array();
					array_push($ato,$to);

					$CharSet_mail="utf-8";
					$sentsubject=encodedMailsubject($CharSet_mail,$subject,'B');
					$mailtype="text/plain";
					$efrom=$from;

					require("setSMTP.php");

					$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: ","Subject: $sentsubject","MIME-Version: 1.0");
					$fromMailId= getEmailId($from);
					array_push($RPLEmails,$toMailId);

					//send mail only when from and to are different
					if($fromMailId != $toMailId)
					{
						$msg_body=prepareBodyA($Rec_msg_body,$mailheaders,$mailtype);
						$suc=$smtp->SendMessage($from,$ato,$mailheaders,$msg_body) ? "true" : "false";
						if($suc=="true")
						{
							$folder="sentmessages";
							$last_id1=mail_insert($folder,$from,$to,'','',$Rec_msg_body,$subject,'','','',$mailtype,"","Active","");
						}						
					}

					if(is_dir($WDOCUMENT_ROOT))
						exec("rm -fr $WDOCUMENT_ROOT");
				}

				//Updating the status from RPL to REC for sent in the db
				$uque="UPDATE mail_headers SET sent='REC' WHERE mailid='$mailid' AND sent='RPL'";
				mysql_query($uque,$db);
			}
			
			$RPLEmails=array_unique($RPLEmails);
			$newRPLEmailId=implode(",",$RPLEmails);
			
			//Updating only when there are RPL mails to autoreply, otherwise no need
			if($RPLRows>0)
			{
				$rque="UPDATE mail_auto_reply SET RPLEmailIds='".addslashes($newRPLEmailId)."' WHERE sno='".$UsrAutoRPLRecSno."'";
				mysql_query($rque,$db);
			}
		}
	}

	//to get the original emailId
	function getEmailId($mailAddr)
	{
		preg_match(EMAIL_REG_EXP,$mailAddr,$emailId);
		return $emailId[0];
	}
?>
