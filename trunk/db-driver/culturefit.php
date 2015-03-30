<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("class.pop3.inc");
	require("IMC_Parse.inc");
	require("emailApplicationTrigger.php");
	require("ConvertCharset.class.php");
	require("pushEmailFunctions.php"); // Push Email Notification Functions

	$def_pop_suidl = "|^|";

	$dque="SELECT capp_info.comp_id FROM company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno WHERE company_info.status='ER' AND capp_info.comp_id = 'culturefit' ".$version_clause." ORDER BY capp_info.comp_id";
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$autoReplyChkArr = array();
		$companyuser=strtolower($drow[0]);
		$PushWooshCompanyUser=strtolower($drow[0]);

		require("maildatabase.inc");
		require("database.inc");

		$ique = "SELECT imapsync FROM options WHERE comp_id='$companyuser'";
		$ires = mysql_query($ique,$maindb);
		$irow = mysql_fetch_row($ires);
		$irow[0] = ($irow[0]=="") ? "N" : $irow[0];
		$DEFAULT_IMAPSYNC = $irow[0];

		$que="select external_mail.imaddress,external_mail.import,external_mail.account,external_mail.passwd,external_mail.lcopy,external_mail.username,external_mail.sno,external_uidls.uidls,external_mail.imsslchk,external_mail.mtype,external_uidls.last_rdate,external_uidls.luidl,if(external_mail.folder is NULL,'inbox',external_mail.folder),external_mail.host_exchange,external_mail.stime,external_mail.lcount,external_uidls.sno, external_uidls.afolder,external_uidls.sfolder from external_mail LEFT JOIN external_uidls ON external_uidls.extsno=external_mail.sno LEFT JOIN users ON external_mail.username=users.username where external_uidls.afolder!='sentmessages' AND ((UNIX_TIMESTAMP()-(UNIX_TIMESTAMP(external_mail.cdate)+external_mail.reminder))>0) and external_mail.lockm='No' and external_mail.reminder!='0' and users.usertype!='' and users.status!='DA'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_row($res))
		{
			$extsno=$row[6];

			$gque="select lockm from external_mail where sno=$extsno";
			$gres=mysql_query($gque,$db);
			$grow=mysql_fetch_row($gres);
			if($grow[0]=="No")
			{
				$uidls_status="";

				$lque="update external_mail set lockm='Yes',cdate=NOW() where sno=$extsno";
				mysql_query($lque,$db);

				$imaddress=$row[0];
				$im_port=$row[1];
				$account=$row[2];
				$passwd=$row[3];
				$lcopy=$row[4];
				$username=$row[5];
				$db_uidls=explode($def_pop_suidl,$row[7]);
				$imsslchk=$row[8];
				$ext_type=$row[9];
				$last_rdate=$row[10];
				$last_uidl=$row[11];
				$hosted_exchange=$row[13];
				$acc_stime=$row[14];
				$lcount=$row[15];
				$sextno=$row[16];

				$PushWooshUserId = $username;
	
				if($row[17]=="inbox")
				{			
					if($row[12]=="")
						$dfolder="inbox";
					else
						$dfolder=$row[12];
				}
				else
				{
					$dfolder=$row[17];
				}

				$spam_header=getSpamHeader($username,$db);
				$mail_rules=getMailRules($username,$db);

				$pop3 = new pop3($imaddress,$im_port);
				if($imsslchk=="Yes")
					$pop3->TLS=1;

				if($row[17]!="inbox")
					$pop3->MAIL_BOX = $row[18];

				if($ext_type=="imap")
					$count = $pop3->imap_login($account,$passwd);
				else
					$count = $pop3->pop_login($account,$passwd);

				if($count=="-4")
				{
					// Invalid Credentials -- Lock the account for one day. unlock.php process will unlock it. In the next attempt the credentials are wrong then account will be lock for another day.

					if($lcount<5)
						$uque="update external_mail set lockm='No',cdate=NOW(),lcount=".($lcount+1)." where sno=$extsno";
					else
						$uque="update external_mail set lockm='ERR',cdate = DATE_ADD(NOW(), INTERVAL 1 DAY) where sno=$extsno";

					mysql_query($uque,$db);
					continue;
				}
				else if($count <=0 || $count === false)
				{
					if($count<=0)
						$uque="update external_mail set lockm='No',cdate=NOW() where sno=$extsno";
					else
						$uque="update external_mail set lockm='No',cdate=SUBTIME(NOW(),'0:10:0') where sno=$extsno";
					mysql_query($uque,$db);
					continue;
				}
				else
				{
					if($ext_type=="imap")
					{
						if($hosted_exchange=="Y")
							$uidl_flags = $pop3->imap_custom_uidl();
						else
							$uidl_flags = $pop3->imap_uidl();
						$server_uidls = $uidl_flags["uid"];
					}
					else
					{
						$server_uidls = $pop3->uidl();
					}

					if($last_rdate=="" && $luidl=="")
					{
						// New account and first time we are processing, go ahead and download all emails.
						$req_uidls = $server_uidls;
					}
					else
					{
						if($ext_type=="imap" && $DEFAULT_IMAPSYNC=="Y")
							require("imapsync.php");

						$cdb_uidls=array_intersect($server_uidls,$db_uidls); // Common UIDLS
						$suidlpos=array_search($last_uidl,$server_uidls); // Server UIDL position with last processed UIDL
						$cuidlpos=array_search($last_uidl,$db_uidls); // Client UIDL position with last processed UIDL

						// Added condition to pull new mails from poop account. 					
						if((count($cdb_uidls)>0 && $suidlpos!==FALSE && $cuidlpos!==FALSE) || $lcopy=="")
						{
							// UIDLS on server AND database are not currupted. Continue uidls checking.
							$uidls_status="OK";
						}
						else
						{
							// Either UIDLS currupted on mail server OR database OR deleting copy of email on mail server.
							if($ext_type=="imap")
								$mbox=$pop3->imap_get_header(1);
							else
								$mbox=$pop3->top(1);

							if(isNewMail($mbox,$last_rdate))
							{
								// Found first mail itself as not processed. Continue uidls checking. Mostly deleting copy of email on mail server.
								$uidls_status="OK";
							}
							else
							{
								// Mostly UIDLS are on server OR database are currupted. Needs to find until what mail we processed earlier in reverse order by checking the mail received datetime which we processed last time.
								$uidls_status="NOTOK";
							}
						}
					}

					if($uidls_status=="OK")
					{
						$req_uidls = array_diff($server_uidls,$cdb_uidls);
						if(count($req_uidls)>0)
						{
							$u_uidls=implode($def_pop_suidl,$cdb_uidls);

							$uque="update external_uidls set uidls='".addslashes($u_uidls)."' where sno=$sextno";
							mysql_query($uque,$db);
						}
					}
					else if($uidls_status=="NOTOK")
					{
						$tuidls=array();
						if($suidlpos!==FALSE)
						{
							for($k=0;$k<=$suidlpos;$k++)
								$tuidls[]=$server_uidls[$k];
							$req_uidls=array_diff($server_uidls, $tuidls);
						}
						else
						{
							$req_uidls=array_reverse($server_uidls,TRUE);
							foreach($req_uidls as $i => $uid)
							{
								if($ext_type=="imap")
									$mbox=$pop3->imap_get_header($i);
								else
									$mbox=$pop3->top($i);

								if(isNewMail($mbox,$last_rdate) && isNewMail($mbox,$acc_stime))
									$tuidls[$i]=$server_uidls[$i];
								else
									break;
							}
							$req_uidls=array_reverse($tuidls,TRUE);
						}
						reset($req_uidls);

						$cdb_uidls=array_diff($server_uidls,$req_uidls);
						$u_uidls=implode($def_pop_suidl,$cdb_uidls);

						$uque="update external_uidls set uidls='".addslashes($u_uidls)."' where sno=$sextno";
						mysql_query($uque,$db);
					}
					
					$totalPushEmails = 0;
					foreach($req_uidls as $i => $uid)
					{
						$msgid=$server_uidls[$i];
						if($msgid!="")
						{
							$mail_seen="U";
							$mail_answered="N";

							if($ext_type=="imap")
							{
								$mbox=$pop3->imap_get_message($i);

								if(!$uidl_flags["seen"][$i])
									$pop3->imap_unseen($i);

								if($uidl_flags["seen"][$i])
									$mail_seen="S";
								else
									$mail_seen="U";

								if($uidl_flags["answered"][$i])
									$mail_answered="A";
								else
									$mail_answered="N";
							}
							else
							{
								$mbox=$pop3->get_text($i);
							}

							if($mbox !== FALSE && trim($mbox)!="")
							{
								$rrdate=insertMainData($mbox,0,0,$sextno);
								
								if($rrdate!="") $totalPushEmails = $totalPushEmails+1;

								$uque="update external_mail set cdate=NOW() where sno=$extsno";
								mysql_query($uque,$db);

								if($rrdate=="" || $rrdate<=$last_rdate)
									$uque="update external_uidls set uidls=TRIM(LEADING '|^|' FROM CONCAT_WS('$def_pop_suidl',uidls,'".addslashes($server_uidls[$i])."')) where sno=$sextno";
								else
									$uque="update external_uidls set luidl='".addslashes($server_uidls[$i])."', last_rdate=$rrdate, uidls=TRIM(LEADING '|^|' FROM CONCAT_WS('$def_pop_suidl',uidls,'".addslashes($server_uidls[$i])."')) where sno=$sextno";
								mysql_query($uque,$db);

								if($lcopy=="")
								{
									if($ext_type=="imap")
										$pop3->imap_delete_message($i);
									else
										$pop3->delete($i);
								}
							}
							else
							{
								break;
							}
						}
					}

					if($totalPushEmails!=0)
					{
						$checkMobilePushAccess = checkMobilePush($username, $db);
						if($checkMobilePushAccess==1)
							pushMailMessage($totalPushEmails, $PushWooshCompanyUser, $PushWooshUserId);
					}
				}

				unset($uidl_flags);

				if($ext_type=="imap")
					$pop3->imap_close();
				else
					$pop3->quit();

				$uque="update external_mail set lockm='No',cdate=NOW(),lcount=0 where sno=$extsno";
				mysql_query($uque,$db);
			}
		}

		if($DEFAULT_IMAPSYNC=="Y")
			update_efolder_all("all");
	}

	function isNewMail($content,$last_rdate)
	{
		$popper=new popper;
		$popper->load($content);

		$received=explode(";",$popper->decode_header($popper->headers->get("Received")));
		$udate = $popper->make_timestamp($received[count($received)-1]);

		if($udate > $last_rdate)
			return true;
		else
			return false;
	}

	// Parsing the original mail and store into the database
	function insertMainData($mbox,$last_id,$xmlattachid,$sextno)
	{
		global $maildb,$db,$username,$extsno,$msgid,$mail_rules,$spam_header,$dfolder,$CharSet_mail,$mail_answered,$mail_seen,$acc_stime;

		$popper=new popper;
		$popper->load($mbox);

		if($last_id==0)
		{
			// VERY IMPORTANT CONDITION :: We need to check the received date with the email account setup date. Received date should always be greater than account setup date. Other wise return the date with out processing the email.
			$received=explode(";",$popper->decode_header($popper->headers->get("Received")));
			$chkdate=$popper->make_timestamp($received[count($received)-1]);
			if($chkdate<=$acc_stime)
				return $chkdate;

			$orgbody=$mbox;
		}
		else
		{
			$msgid = $popper->decode_header($popper->headers->get("Message-ID"));
			$orgbody="";
		}

		$is_only_calendar=false;
		$is_only_attach=false;
		$popper->get_body(0,$text_body,$html_body,$calendar_body);

		$CharSet_mail="";
		$CharSet_mail=$popper->Body_Charset;

		if($text_body=="" && $html_body=="" && $calendar_body=="")
		{
			$text_body=$popper->decode($popper->body);
			$c_type = $popper->get_header("Content-Type");
			$type = strtoupper($c_type["CONTENT-TYPE"]);

			if($CharSet_mail=="")
				$CharSet_mail=$c_type["CHARSET"];

			$is_multipart = (substr($type, 0, strlen("MULTIPART/")) == "MULTIPART/");
			if (!$is_multipart && !empty($c_type["NAME"]) && empty($c_type["BOUNDARY"]))
			{
				$mailtype=strtolower($c_type["CONTENT-TYPE"]);
				$cmethod=$c_type["METHOD"];
				if(strtolower($mailtype)=="text/calendar" && $cmethod!="")
				{
					$is_only_calendar=true;
					$only_calendar["type"]=$c_type["CONTENT-TYPE"];
					$only_calendar["method"]=$c_type["METHOD"];
					$only_calendar["body"]=$text_body;
				}

				$is_only_attach=true;
				$only_attach["type"]=$c_type["CONTENT-TYPE"];
				$only_attach["name"]=$c_type["NAME"];
				$only_attach["body"]=$text_body;
			}
			else
			{
				$mailtype=strtolower($c_type["CONTENT-TYPE"]);
				$cmethod=$c_type["METHOD"];

				if(strtolower($mailtype)=="text/calendar" && $cmethod!="")
				{
					$is_only_calendar=true;
					$only_calendar["type"]=$c_type["CONTENT-TYPE"];
					$only_calendar["method"]=$c_type["METHOD"];
					$only_calendar["body"]=$text_body;
				}
				else
				{
					if(!$is_multipart && empty($c_type["BOUNDARY"]))
						$body=$text_body;
				}
			}
		}
		else
		{
			if($html_body!="")
			{
				$body = $html_body;
				$mailtype = "text/html";
			}
			else if($calendar_body!="")
			{
				$body = "";
				$mailtype = "text/html";
			}
			else
			{
				$body = $text_body;
				$mailtype = "text/plain";
			}
		}

		if($mailtype=="")
			$mailtype = "text/plain";

		if($mailtype=="multipart/report")
		{
			$mailtype = "text/plain";
			$body=$text_body;
		}

		$date = $popper->decode_header($popper->headers->get("Date"));
		// Merged converting underscore(_) into space if the mail is quoted printable -- kumar raju k.
		$subject = $popper->subject_decode($popper->headers->get("Subject"),$popper->decode_header($popper->headers->get("Subject")));
		$from = str_replace('"','',$popper->decode_header($popper->headers->get("From")));
		$to = str_replace('"','',$popper->decode_header($popper->headers->get("To")));
		$cc = str_replace('"','',$popper->decode_header($popper->headers->get("Cc")));

		if(trim($from)=="")
		{
			// We do not process emails that has from address as empty in the parent email. We need to maintain this email recieved date in the database as processed, so return it.

			$received=explode(";",$popper->decode_header($popper->headers->get("Received")));
			$udate=$popper->make_timestamp($received[count($received)-1]);

			return $udate;
		}

		//X-Spam-Status: (Yes-No)
		$XSpam = explode(",",$popper->decode_header($popper->headers->get("X-Spam-Status")));
		$SpamStatus = trim($XSpam[0]);

		if($last_id==0)
		{
			$tcfrom="**".strtolower($from);
			$ffolder="";

			if($SpamStatus=='Yes')
				$ffolder="spam";

			if(is_array($spam_header) && $ffolder=="")
			{
				$spamHeader=$popper->decode_header($popper->headers->get($spam_header["header"]));
				if($spam_header["status"]=="ID" && $spamHeader!="") // Is Defined
				{
					$ffolder="spam";
				}
				else if($spam_header["status"]=="ET") // Contains
				{
					$spamValue=explode(",",$spamHeader);
					if(trim($spamValue[0])==trim($spam_header["value"]))
						$ffolder="spam";
				}
			}

			if($ffolder=="")
			{
				preg_match(EMAIL_REG_EXP,$from,$eemail);
				$frmemail=$eemail[0];
				for($i=0;$i<count($mail_rules);$i++)
				{
					if($mail_rules[$i]["folder"]=="spam")
					{
						if($mail_rules[$i]["from"]!="" && strpos("**".$mail_rules[$i]["from"],$frmemail)>0)
							$ffolder="spam";
						break;
					}
				}
			}

			if($ffolder=="")
			{
				$strToCC = $to.",".$cc;
				for($i=0;$i<count($mail_rules);$i++)
				{
					$bFrom = true;
					$bTo   = true;
					$bSub  = true;
					$bMsg  = true;

					if($mail_rules[$i]["from"] != "C-")
						$bFrom = CheckCondition($mail_rules[$i]["from"], $from);

					if($mail_rules[$i]["to"] != "C-")
						$bTo   = CheckCondition($mail_rules[$i]["to"], $strToCC);

					if($mail_rules[$i]["subject"] != "C-")
					{
						$bSub  = CheckCondition($mail_rules[$i]["subject"], $subject);
					}
					if($mail_rules[$i]["message"] != "C-")
					{
						$bMsg  = CheckCondition($mail_rules[$i]["message"], $body);
					}
					if($bFrom && $bTo && $bSub && $bMsg)
					{
						$ffolder = $mail_rules[$i]["folder"];
						break;
					}
				}
			}

			if($ffolder!="")
				$fid=$ffolder;
			else
				$fid=$dfolder;
		}
		else
		{
			$fid="";
		}

		if($mail_answered=="A")
			$reply="A";
		else
			$reply="N";

		if($mail_seen=="S")
			$seen="S";
		else
			$seen="U";

		$rrdate="";
		$attach="N";
		$recent="N";
		$forward="N";
		$flag="N";
		$received=explode(";",$popper->decode_header($popper->headers->get("Received")));
		$udate=$popper->make_timestamp($received[count($received)-1]);
		$size="0.00";
		$conid="";
		$status="Active";

		// HR-XML integrated mails
		$hotlistpos=$popper->decode_header($popper->headers->get("HotList"));
		$hotlistres=$popper->decode_header($popper->headers->get("HotListResponse"));
		$hotreqpos=$popper->decode_header($popper->headers->get("HotReq"));
		$hotreqposrep=$popper->decode_header($popper->headers->get("HotReqReply"));
		$xmlattachid=$popper->decode_header($popper->headers->get("Xmlid"));

		if($hotlistpos!="" || $hotlistres!="" || $hotreqpos!="" || $hotreqposrep!="")
		{
			if($hotlistpos!="")
				$xmltype="HotList";
			else if($hotlistres!="")
				$xmltype="HotListResponse";
			else if($hotreqpos!="")
				$xmltype="HotReq";
			else if($hotreqposrep!="")
				$xmltype="HotReqReply";
		}

		$CharSet_mail=(trim($CharSet_mail)=='')?CONVERT_DEFAULT_MAIL_CHAR:strtolower($CharSet_mail);
		$sCharSet_mail=explode('"',$CharSet_mail);
		$CharSet_mail=$sCharSet_mail[0];

		if($last_id==0 && $fid!="spam" && $fid!="failed" && $fid!="unsubscribe")
			$sentval=checkAutoReply($username,$frmemail);
		else 
			$sentval="";
		
		if($last_id!=0 && trim($subject)=="")
			$subject="No Subject";

		// We need to check the result set after inserting into mail_headers is true or false, because we are doing check with complex key on (messageid,username,extid,udate) to avoid duplication of mail insertion incase when driver tries to download the same mail from external mail server. This won't be happen in email driver though at email driver the external server id is allways empty, in this case also it will do the same process. So if the result set is false we assume that the mail is already been downloaded from the external server.
	
		$rque="insert into mail_headers (mailid,username,folder,messageid,attach,seen,reply,forward,flag,fromadd,toadd,ccadd,bccadd,subject,date,udate,size,mailtype,inlineid,conid,status,xmltype,xmlbody,extid,sent,charset,sfolder) values ('','$username','$fid','".addslashes($msgid)."','$attach','$seen','$reply','$forward','$flag','".addslashes($from)."','".addslashes($to)."','".addslashes($cc)."','','".addslashes($subject)."','".addslashes($date)."','".addslashes($udate)."','".addslashes($size)."','".addslashes($mailtype)."','$last_id','','".addslashes($status)."','', '','$extsno','".$sentval."','".addslashes($CharSet_mail)."','$sextno')";
		$rres=mysql_query($rque,$db);
		if($rres)
		{
			$mail_last_id=mysql_insert_id($db);

			if($last_id==0)
			{
				// We need to return $udate for main mail to maintain in the database as the mail processed last time.
				$rrdate=$udate;

				if($seen=="S")
					$UpEfldString=$fid."|^**^|1|^**^|0|^**^|false^fasle";
				else
					$UpEfldString=$fid."|^**^|1|^**^|1|^**^|false^fasle";
				update_efolder_operations($UpEfldString);
			}

			$last_id=$mail_last_id;

			$que="insert into mail_headers_body (id,body) values ($last_id,'".addslashes($body)."')";
			mysql_query($que,$maildb);

			if($is_only_attach)
				$popper->get_only_attachments($last_id,$only_attach);
			else if($is_only_calendar)
				$popper->get_only_calendar($last_id,$only_calendar);
			else
				$popper->get_attachments(0,$body,$last_id,$xmlattachid);
		}

		return $rrdate;
	}

	// for mail rules
	function CheckCondition($strCond, $strData)
	{
		$strCond=strtolower($strCond);
		$strData=strtolower($strData);

		$bFrom1=0;
		$cType=strtoupper(substr($strCond,0,strpos($strCond,"-")));
		$cKeyWord=substr($strCond,strpos($strCond,"-")+1);

		if(is_array($strData))
			$strData=implode(",",$strData);

		$cKeyWord1=explode(',',$cKeyWord);

		for($ck=0;$ck<count($cKeyWord1);$ck++)
		{
			if($cType == "C")
			{
				$bFrom1 = strpos("*".$strData,trim($cKeyWord1[$ck]));
			}
			else if($cType == "NC")
			{
				$bFrom1 = !strpos("*".$strData,trim($cKeyWord1[$ck]));
			}
			else if($cType == "BW")
			{
				$bFrom1 = strpos("*".trim(strip_tags($strData)),trim($cKeyWord1[$ck])) === 1;
			}
			else
			{
				$strData1 = trim(strip_tags($strData));
				$bFrom1 = (substr($strData1,-1*strlen($cKeyWord1[$ck])) == trim($cKeyWord1[$ck]));
			}

			if($bFrom1)
				return true;
		}
		return false;
	}

	//Getting mail rules
	function getMailRules($username,$db)
	{
		$i=0;

		$que="select emailfrom,emailto,subject,message,destination from mail_options where username='$username' order by priority,sno";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_row($res))
		{
			$mail_rules[$i]["from"]=$row[0];
			$mail_rules[$i]["to"]=$row[1];
			$mail_rules[$i]["subject"]=$row[2];
			$mail_rules[$i]["message"]=$row[3];
			$mail_rules[$i]["folder"]=$row[4];
			$i++;
		}
		return $mail_rules;
	}

	function getSpamHeader($username,$db)
	{
		$que="select spam_header,soptions,svalue from mail_editor where username='$username'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
		if($row[0]!="")
		{
			$spam_header["header"]=$row[0];
			$spam_header["status"]=$row[1];
			$spam_header["value"]=$row[2];
		}
		return $spam_header;
	}

	//to check for the automated reply
	function checkAutoReply($username,$fromEmailId)
	{
		global $autoReplyChkArr,$maildb,$db;
		$RtnSentval="REC";

		if(in_array($username,$autoReplyChkArr))
		{
			$RtnSentval=$autoReplyChkArr[$username];
		}
		else
		{
			//to check for automated reply is Yes for the user, and set the sent column as RPL
			$fromEmailId=addslashes($fromEmailId);
			$qryautoreply ="SELECT count(1) FROM mail_auto_reply WHERE username='$username' AND automated_reply='Y' AND !FIND_IN_SET('$fromEmailId',RPLEmailIds)";
			$autores=mysql_query($qryautoreply,$db);
			$autorow=mysql_fetch_row($autores);

			if($autorow[0] >0)
			{
				$RtnSentval="RPL";
				$autoReplyChkArr[$username]="RPL";
			}
			else
			{
				$RtnSentval="REC";
			}
		}
		return $RtnSentval;
	}
?>
