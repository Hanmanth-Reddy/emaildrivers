<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("emailApplicationTrigger.php");
	require("parsingMailRules.php");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);

		require("maildatabase.inc");
		require("database.inc");

		$doque="select GROUP_CONCAT(domain) from domains";
		$dores=mysql_query($doque,$db);
		$dorow=mysql_fetch_row($dores);
		$dlist=explode(",",strtolower($dorow[0]));

		$ubque="select username from users where usertype!='' and status!='DA'";
		$ubres=mysql_query($ubque,$db);
		while($ubrow=mysql_fetch_row($ubres))
		{
			$username=$ubrow[0];
			$user_pref=getUserPref($username,$db);

			$que="select mail_headers.mailid,mail_headers.fromadd,mail_headers.subject,mail_headers.toadd,mail_headers.ccadd,mail_headers.mailtype,mail_headers.udate,mail_headers.xmltype,mail_headers.date,mail_headers.xmlbody,if(mail_headers.charset!='',mail_headers.charset,'utf-8') from mail_headers LEFT JOIN external_mail ON mail_headers.extid=external_mail.sno where mail_headers.folder='sentmessages' AND mail_headers.sent='DOW' AND mail_headers.status='Active' AND mail_headers.inlineid='0' AND mail_headers.username='$username' AND mail_headers.calendar!='Y'";
			$res=mysql_query($que,$db);
			while($row=mysql_fetch_row($res))
			{
				$cmid="";
				$jobinq="";
				$varConcat="";

				$mailid=$row[0];
				$cfrom=$row[1];
				$csubject=$row[2];
				$cto=$row[3];
				$cadd=$row[4];
				$mailtype=$row[5];
				$udate=$row[6];
				$varxmltype=$row[7];
				$senddate=$row[8];
				$varxmlbody=$row[9];
				$CharSet_mail=$row[10];

				$fullelist = $cfrom.",".$cto.",".$cadd;

				$emaillist = $cto.",".$cadd;
				$frmemail=parseEmailAddresses($emaillist);

				$trackPref=getTrackingPref();

				if($trackPref['etrack']=="Y")
					$trackEmail=trackEmail($dlist,$fullelist);
				else
					$trackEmail=false;

				if(($user_pref["crm"]!="NO" || $user_pref["hrm"]!="NO" || $user_pref["accounting"]!="NO") && $trackEmail && $trackPref['etrack']=="Y" && trim($frmemail)!="")
				{
					$conid="";

					require("activities_sent_que.inc");

					if($user_pref["crm"]!="NO")
					{
						if($trackPref['contacts']=="Y")
						{
							$cres = mysql_query($crm_contacts_que,$db);
							while($crow = mysql_fetch_row($cres))
								$conid .= "oppr".$crow[0].",";
						}

						if($trackPref['clients']=="Y")
						{
							$cres = mysql_query($crm_active_clients_que,$db);
							while($crow = mysql_fetch_row($cres))
								$conid .= "acc".$crow[0].",";
						}

						if($trackPref['candidates']=="Y")
						{
							$cres = mysql_query($crm_candidates_que,$db);
							while($crow = mysql_fetch_row($cres))
								$conid .= "cand".$crow[0].",";
						}
					}

					if($user_pref["hrm"]!="NO")
					{
						if($trackPref['consultants']=="Y")
						{
							$cres = mysql_query($hrm_consultants_que,$db);
							while($crow = mysql_fetch_row($cres))
								$conid .= "con".$crow[0].",";
						}

						if($trackPref['employees']=="Y")
						{
							$cres = mysql_query($hrm_employees_que,$db);
							while($crow = mysql_fetch_row($cres))
								$conid .= "emp".$crow[0].",";
						}
					}

					if($user_pref["accounting"]!="NO")
					{
						if($trackPref['customers']=="Y")
						{
							$cres = mysql_query($acc_companies_que,$db);
							while($crow = mysql_fetch_row($cres))
								$conid .= "acc".$crow[0].",";
						}
					}

					$conid=unique_conid(trim($conid,","));

					if($jobinq!="")
					{
						if($conid=="")
							$conid=$jobinq;
						else 
							$conid.=",".$jobinq;
					}

					if($conid!="")
					{
						$findate=date('Y/m/d H:i:s',$udate);

						$cfrom=utf8_encode($cfrom);
						$csubject=utf8_encode($csubject);
						$cto=utf8_encode($cto);
						$cadd=utf8_encode($cadd);

					 	$que="insert into contact_email( sno, username, contactsno, subject, fromadd, toadd, ccadd, date, inlineid, type, sdate, xmltype, xmlbody,charset) values ('', '$username', '$conid', '".addslashes($csubject)."', '".addslashes($cfrom)."','".addslashes($cto)."','".addslashes($cadd)."','".addslashes($senddate)."', '0', '$mailtype', '$findate', '".addslashes($varxmltype)."', '','".addslashes($CharSet_mail)."')";
						mysql_query($que,$db);
						$eid=mysql_insert_id($db);

						$bque="select body from mail_headers_body where id=$mailid";
						$bres=mysql_query($bque,$maildb);
						$brow=mysql_fetch_row($bres);
						$cmsg=$brow[0];

						$que="insert into contact_email_body values ('$eid','".addslashes($cmsg)."')";
						mysql_query($que,$maildb);

						$que="insert into contact_em_attach select '','$eid',filename,filetype,filecontent,inline from mail_attachs where mailid='$mailid'";
						mysql_query($que,$maildb);

						$que="insert into cmngmt_pr (sno, con_id, username, tysno, title, sdate, subject,  lmuser) values ('', '$conid', '$username', '$eid', 'Email','$findate','".addslashes($csubject)."','$username')";
						mysql_query($que,$db);
						$cmid=mysql_insert_id($db);

						getMailContactData($mailid,$db,$eid,$username);
					}
				}

				$que="update mail_headers set sent=NULL, conid='$cmid' where mailid='$mailid'";
				mysql_query($que,$db);
			}
		}
	}

	function getMailContactData($mailid,$db,$ceid,$username)
	{
		global $maildb;

		$que="select mailid from mail_headers where username='$username' AND status='Active' AND inlineid='$mailid'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_array($res))
		{
			$que="insert into contact_email( sno , username , contactsno , subject , fromadd , toadd , ccadd , date , inlineid , type , sdate , xmltype , xmlbody , charset) select '',username,'',subject,fromadd,toadd,ccadd,date, '$ceid',mailtype,NOW(),'','',charset from mail_headers where mailid='$row[0]'";
			mysql_query($que,$db);
			$neid=mysql_insert_id($db);

			$que="insert into contact_email_body select '$neid',body from mail_headers_body where id='$row[0]'";
			mysql_query($que,$maildb);

			$que="insert into contact_em_attach select '','$neid',filename,filetype,filecontent,inline from mail_attachs where mailid='$row[0]'";
			mysql_query($que,$maildb);

			getMailContactData($row[0],$db,$neid,$username);
		}
	}

	function getUserPref($username,$db)
	{
		$que="select crm,hrm,accounting,admin from sysuser where username='$username'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);

		$user_pref["crm"]=$row[0];
		$user_pref["hrm"]=$row[1];
		$user_pref["accounting"]=$row[2];
		$user_pref["admin"]=$row[3];

		return $user_pref;
	}

	function unique_conid($conid)
	{
		return $sconid = implode(",",array_unique(explode(",",$conid)));
	}

	function parseEmailAddresses($inemails)
	{
		$emails = "";
		$emaillist=explode(",",$inemails);
		for($i=0;$i<count($emaillist);$i++)
		{
			preg_match(EMAIL_REG_EXP,$emaillist[$i],$eemail);
			if($eemail[0]!="")
			{
				if($emails=="")
					$emails=addslashes(trim($eemail[0],"'"));
				else
					$emails.="','".addslashes(trim($eemail[0],"'"));
			}
		}

		if($emails=="")
			return "";
		else
			return "'".$emails."'";
	}

	function getActivitiesEmailCondition($ChkEmail,$tbl,$col1,$col2,$col3)
	{	
		$CommonEmailCond="($ChkEmail) IN (".$tbl.".".$col1.",".$tbl.".".$col2.",".$tbl.".".$col3.") ";
		return $CommonEmailCond;
	}
?>