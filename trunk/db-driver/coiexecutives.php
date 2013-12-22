<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("class.pop3.inc");
	require("emailApplicationTrigger.php");
	require("ConvertCharset.class.php");
	require("IMC_Parse.inc");

	$dfolder="sentmessages";
	$def_pop_suidl = "|^|";

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' AND capp_info.comp_id='coiexecutives' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("maildatabase.inc");
		require("database.inc");

		$que="select external_mail.imaddress,external_mail.import,external_mail.account,external_mail.passwd,external_mail.lcopy,external_mail.username,external_mail.sno,external_uidls_sent.uidls,external_mail.imsslchk,external_mail.sentfolder,external_uidls_sent.last_rdate,external_uidls_sent.luidl,external_mail.host_exchange,external_mail.stime,external_mail.lcount FROM external_mail LEFT JOIN users ON external_mail.username=users.username LEFT JOIN external_uidls_sent ON external_uidls_sent.extsno=external_mail.sno WHERE external_mail.lockm!='ERR' AND external_mail.reminder>0 AND external_mail.mtype='imap' AND external_mail.sentfolder!='' AND users.usertype!='' AND users.status!='DA'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_row($res))
		{
			$uidls_status="";
			$extsno=$row[6];
			$imaddress=$row[0];
			$im_port=$row[1];
			$account=$row[2];
			$passwd=$row[3];
			$username=$row[5];
			$db_uidls=explode($def_pop_suidl,$row[7]);
			$imsslchk=$row[8];
			$imap_sentfolder=$row[9];
			$last_rdate=$row[10];
			$last_uidl=$row[11];
			$hosted_exchange=$row[12];
			$acc_stime=$row[13];
			$lcount=$row[14];

			$pop3 = new pop3($imaddress,$im_port);
			if($imsslchk=="Yes")
				$pop3->TLS=1;

			$pop3->MAIL_BOX = $imap_sentfolder;
			$count = $pop3->imap_login($account,$passwd);
			if ($count <=0 || $count === false)
			{
				continue;
			}
			else if ($count == -4)
			{
				// Invalid Credentials -- Lock the account for one day. unlock.php process will unlock it. In the next attempt the credentials are wrong then account will be lock for another day.
				if($lcount<5)
					$uque="update external_mail set lockm='No',cdate=NOW(),lcount=".($lcount+1)." where sno=$extsno";
				else
					$uque="update external_mail set lockm='ERR',cdate = DATE_ADD(NOW(), INTERVAL 1 DAY) where sno=$extsno";
				mysql_query($uque,$db);
				continue;
			}
			else
			{
				if($hosted_exchange=="Y")
					$uidl_flags = $pop3->imap_custom_uidl();
				else
					$uidl_flags = $pop3->imap_uidl();
				$server_uidls = $uidl_flags["uid"];

				if($last_rdate=="" && $last_uidl=="")
				{
					// New account and first time we are processing, go ahead and download all emails.
					$req_uidls = $server_uidls;
				}
				else
				{
					$cdb_uidls=array_intersect($server_uidls,$db_uidls); // Common UIDLS
					$suidlpos=array_search($last_uidl,$server_uidls); // Server UIDL position with last processed UIDL
					$cuidlpos=array_search($last_uidl,$db_uidls); // Client UIDL position with last processed UIDL

					if((count($cdb_uidls)>0 && $suidlpos!==FALSE && $cuidlpos!==FALSE))
					{
						// UIDLS on server AND database are not currupted. Continue uidls checking.
						$uidls_status="OK";
					}
					else
					{
						// Either UIDLS currupted on mail server OR database OR deleting copy of email on mail server.
						$mbox=$pop3->imap_get_header(1);

						if(isNewMail($mbox,$last_rdate))
						{
							// Found first mail itself as not processed. Continue uidls checking. Mostly deleting copy of email on mail server.
							$uidls_status="OK";
						}
						else
						{
							// Mostly UIDLS are on server OR database are currupted. Needs to find until what mail we processed earlier in reverse order by checking the mail sent datetime which we processed last time.
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

						$uque="update external_uidls_sent set uidls='".addslashes($u_uidls)."' where extsno=$extsno";
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
							$mbox=$pop3->imap_get_header($i);

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

					$uque="update external_uidls_sent set uidls='".addslashes($u_uidls)."' where extsno=$extsno";
					mysql_query($uque,$db);
				}

				foreach($req_uidls as $i => $uid)
				{
					$msgid=$server_uidls[$i];
					if($msgid!="")
					{
						$mail_seen="U";
						$mail_answered="N";

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

						if($mbox !== FALSE)
						{
							$rrdate=insertMainData($mbox,0,0);
							if($rrdate!="")
							{
								$uque="update external_uidls_sent set luidl='".addslashes($server_uidls[$i])."',last_rdate=$rrdate where extsno=$extsno";
								mysql_query($uque,$db);
							}

							$uque="update external_uidls_sent set uidls=TRIM(LEADING '|^|' FROM CONCAT_WS('$def_pop_suidl',uidls,'".addslashes($server_uidls[$i])."')) where extsno=$extsno";
							mysql_query($uque,$db);
						}
						else
						{
							break;
						}
					}
				}
			}

			unset($uidl_flags);
			$pop3->imap_close();
		}
	}

	function isNewMail($content,$last_rdate)
	{
		$popper=new popper;
		$popper->load($content);

		$sentdate=$popper->decode_header($popper->headers->get("Date"));
		if($sentdate!="")
		{
			$udate = $popper->make_timestamp($sentdate);
			if($udate > $last_rdate)
				return true;
			else
				return false;
		}
		else
		{
			return false;
		}
	}

	// Parsing the original mail and store into the database
	function insertMainData($mbox,$last_id,$xmlattachid)
	{
		global $maildb,$db,$username,$extsno,$msgid,$dfolder,$CharSet_mail,$mail_answered,$mail_seen,$acc_stime;

		$popper=new popper;
		$popper->load($mbox);

		$XMailer = $popper->decode_header($popper->headers->get("X-Mailer"));
		if($XMailer=="AKKEN-IMAP" && $last_id==0)
		{
			$rrdate=$popper->make_timestamp($sentdate);
		}
		else
		{
			if($last_id==0)
			{
				// VERY IMPORTANT CONDITION :: We need to check the sent date with the email account setup date. Sent date should always be greater than account setup date. Other wise return the date with out processing the email.
				$sentdate = $popper->decode_header($popper->headers->get("Date"));
				$chkdate=$popper->make_timestamp($sentdate);
				if($chkdate<=$acc_stime)
					return $chkdate;

				$sent_status="DOW";
			}
			else
			{
				$msgid = $popper->decode_header($popper->headers->get("Message-ID"));
				$sent_status="";
			}

			$is_only_attach=false;
			$popper->get_body(0,$text_body,$html_body);
			$CharSet_mail="";
			$CharSet_mail=$popper->Body_Charset;
			if($text_body=="" && $html_body=="")
			{
				$text_body=$popper->decode($popper->body);
				$c_type = $popper->get_header("Content-Type");
				$type = strtoupper($c_type["CONTENT-TYPE"]);

				if($CharSet_mail=="")
					$CharSet_mail=$c_type["CHARSET"];

				$is_multipart = (substr($type, 0, strlen("MULTIPART/")) == "MULTIPART/");
				if (!$is_multipart && !empty($c_type["NAME"]) && empty($c_type["BOUNDARY"]))
				{
					$is_only_attach=true;
					$only_attach["type"]=$c_type["CONTENT-TYPE"];
					$only_attach["name"]=$c_type["NAME"];
					$only_attach["body"]=$text_body;
				}
				else
				{
					$mailtype=$c_type["CONTENT-TYPE"];
					$body=$text_body;
				}
			}
			else
			{
				if($html_body!="")
				{
					$body = $html_body;
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

			$date = $popper->decode_header($popper->headers->get("Date"));
			$subject = $popper->subject_decode($popper->headers->get("Subject"),$popper->decode_header($popper->headers->get("Subject")));
			$from = str_replace('"','',$popper->decode_header($popper->headers->get("From")));
			$to = str_replace('"','',$popper->decode_header($popper->headers->get("To")));
			$cc = str_replace('"','',$popper->decode_header($popper->headers->get("Cc")));

			if($last_id==0)
				$fid=$dfolder;
			else
				$fid="";

			if($mail_answered=="A")
				$reply="A";
			else
				$reply="N";

			$seen="S";
			$rrdate="";
			$attach="N";
			$recent="N";
			$forward="N";
			$flag="N";

			$sentdate = $popper->decode_header($popper->headers->get("Date"));
			$udate=$popper->make_timestamp($sentdate);
			$size="0.00";
			$conid="";
			$status="Active";

			$CharSet_mail=(trim($CharSet_mail)=='')?CONVERT_DEFAULT_MAIL_CHAR:strtolower($CharSet_mail);
			$sCharSet_mail=explode('"',$CharSet_mail);
			$CharSet_mail=$sCharSet_mail[0];

			// We need to check the result set after inserting into mail_headers is true or false, because we are doing check with complex key on (messageid,username,extid,udate) to avoid duplication of mail insertion incase when driver tries to download the same mail from external mail server. This won't be happen in email driver though at email driver the external server id is allways empty, in this case also it will do the same process. So if the result set is false we assume that the mail is already been downloaded from the external server.
			$rque="insert into mail_headers (mailid,username,folder,messageid,attach,seen,reply,forward,flag,fromadd,toadd,ccadd,bccadd,subject,date,udate,size,mailtype,inlineid,conid,status,xmltype,xmlbody,extid,sent,charset) values ('','$username','$fid','".addslashes($msgid)."','$attach','$seen','$reply','$forward','$flag','".addslashes($from)."','".addslashes($to)."','".addslashes($cc)."','','".addslashes($subject)."','".addslashes($date)."','".addslashes($udate)."','".addslashes($size)."','".addslashes($mailtype)."','$last_id','','".addslashes($status)."','', '','$extsno','$sent_status','".addslashes($CharSet_mail)."')";
			$rres=mysql_query($rque,$db);
			if($rres)
			{
				$mail_last_id=mysql_insert_id($db);

				if($last_id==0)
				{
					// We need to return $udate for main mail to maintain in the database as the mail processed last time.
					$rrdate=$udate;

					$UpEfldString=$fid."|^**^|1|^**^|0|^**^|false^fasle";
					update_efolder_operations($UpEfldString);
				}

				$last_id=$mail_last_id;

				$que="insert into mail_headers_body (id,body) values ($last_id,'".addslashes($body)."')";
				mysql_query($que,$maildb);

				if($is_only_attach)
					$popper->get_only_attachments($last_id,$only_attach);
				else
					$popper->get_attachments(0,$body,$last_id,$xmlattachid);
			}
		}
		return $rrdate;
	}
?>
