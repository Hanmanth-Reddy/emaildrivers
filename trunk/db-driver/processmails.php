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

		$trackPref=getTrackingPref();

		$ubque="select username from users where usertype!='' and status!='DA'";
		$ubres=mysql_query($ubque,$db);
		while($ubrow=mysql_fetch_row($ubres))
		{
			$username=$ubrow[0];
			$user_pref=getUserPref($username,$db);

			$lque="select mail_headers.mailid,mail_headers.fromadd,mail_headers.subject,mail_headers.toadd,mail_headers.ccadd,mail_headers.mailtype,mail_headers.udate,mail_headers.xmltype,mail_headers.date,mail_headers.xmlbody,if(mail_headers.charset!='',mail_headers.charset,'utf-8')  from mail_headers where mail_headers.sent='REC' AND  mail_headers.status='Active' AND mail_headers.inlineid='0' AND mail_headers.calendar!='Y' AND mail_headers.username='$username'";
			$lres=mysql_query($lque,$db);
			while($lrow=mysql_fetch_row($lres))
			{
				$cmid="";
				$jobinq="";
				$varConcat="";//Used to concat Candidate values.

				$mailid=$lrow[0];
				$cfrom=$lrow[1];
				$csubject=$lrow[2];
				$cto=$lrow[3];
				$cadd=$lrow[4];
				$mailtype=$lrow[5];
				$udate=$lrow[6];
				$varxmltype=$lrow[7];
				$senddate=$lrow[8];
				$varxmlbody=$lrow[9];
				$CharSet_mail=$lrow[10];

				if($user_pref["crm"]!="NO")
				{
					$InqResfolder="";
					$subjectId="";

					if(strpos("*".$csubject,"Request_Details_of_")>0 || strpos("*".$csubject,"Request_Resume_")>0)
					{
						$InqResfolder="CampaignResponses";
						$subjectId=substr($csubject,strpos($csubject,"@")+1,10);
					}
					else if(strpos("*".$csubject,"Request_ResDetails_of_")>0 || strpos("*".$csubject,"Request_Res_Resume_")>0)
					{
						$InqResfolder="ReqResponses";
						$subjectId=substr($csubject,strpos($csubject,"@")+1,10);
					}
					else if(strpos("*".$csubject,"Response_For_Requirement_")>0)
					{
						$InqResfolder="ReqPostResponses";
						$subjectId=substr($csubject,strpos($csubject,"@")+1,10);
					}
					else if(strpos("*".$csubject,"Request_More_Details_of_")>0)
					{
						$InqResfolder="ReqPost";
						$subjectId=substr($csubject,strpos($csubject,"@")+1,10);
					}
					else if($varxmltype=="")
					{
						if(strpos("*".$csubject,"(eCampaign@")>0)
						{
							$InqResfolder="CampaignResponses";
							$subjectId=substr($csubject,strpos($csubject,"@")+1,10);
						}
						else if(strpos("*".$csubject,"(Submission@")>0)
						{
							$InqResfolder="ReqResponses";
							$subjectId=substr($csubject,strpos($csubject,"@")+1,10);
						}
						else if(preg_match("/\(Posting.+@/isU",$csubject))
						{
							$InqResfolder="ReqPost";
							$subjectId=substr($csubject,strpos($csubject,"@")+1,10);
						}
					}

					if($InqResfolder!="")
					{
						if($InqResfolder=="CampaignResponses")
							$ique="select id from campaign_list where par_id='0' and campaign_list.status='OPEN' and id='$subjectId' and (FIND_IN_SET($username,accessto)>0 or accessto='all')";
						else if($InqResfolder=="ReqResponses")
							$ique="select p.posid,r.resumeid from reqresponse r,posdesc p where r.seqnumber=$subjectId and r.par_id=0 and p.status in ('approve','Accepted') and p.posid=r.posid  and (FIND_IN_SET($username,p.accessto)>0 or p.accessto='all')";
						else
							$ique="select p.posid from job_post_det j,posdesc p where j.seqnumber=$subjectId and j.par_id=0 and p.status in ('approve','Accepted') and p.posid=j.posid  and (FIND_IN_SET($username,p.accessto)>0 or p.accessto='all')";
						$ires=mysql_query($ique,$db);
						$irow=mysql_fetch_row($ires);

						if($InqResfolder!="CampaignResponses")
						{
							$jobinq="req".$irow[0];
							$varConcat=",";
						}
						if($InqResfolder=="ReqResponses")
						{
							if($irow[1]!='')
								$jobinq.=$varConcat.$irow[1];
						}
						if($irow[0]!="")
						{
							$que="insert into process_mail_headers(mailid , username , folder , messageid , attach , seen , reply , forward , flag , fromadd , toadd , ccadd , bccadd , subject , date , udate , size , mailtype , inlineid , conid , status , xmltype , xmlbody , extid , sent , charset ) select mailid,username,'$InqResfolder',messageid,attach,seen,reply,forward,flag,fromadd,toadd,ccadd,bccadd,subject,date,udate,size,mailtype,inlineid,conid,status,xmltype,xmlbody,extid,'',charset from mail_headers where mailid='$mailid'";
							mysql_query($que,$db);

							$que="insert into process_mail_headers_body select id,body from mail_headers_body where id='$mailid'";
							mysql_query($que,$maildb);

							$que="insert into process_mail_attachs select attachid,mailid,filecontent,filename,filesize,filetype,inline from mail_attachs where mailid='$mailid'";
							mysql_query($que,$maildb);
							
							if($InqResfolder=="ReqResponses")
							{
								$que="update posdesc set sub_inq_count=sub_inq_count+1 where posid='".$irow[0]."'";
								mysql_query($que,$db);
							}

							getMailProcessData($mailid,$db,$username);
						}
					}
				}

				// Getting email address in From Field
				preg_match(EMAIL_REG_EXP,$cfrom,$eemail);
				$frmemail=trim($eemail[0],"'");

				$fullelist = $cfrom.",".$cto.",".$cadd;

				if($trackPref['etrack']=="Y")
					$trackEmail=trackEmail($dlist,$fullelist);
				else
					$trackEmail=false;

				if(($user_pref["crm"]!="NO" || $user_pref["hrm"]!="NO" || $user_pref["accounting"]!="NO") && $trackEmail && trim($frmemail)!="")
				{
					$conid="";

					require("activities_que.inc");

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

						//added "utf8_encode "for insering From,Subject,TO and CC with utf-8 encoded format Sundar on 25-feb-2009
						$cfrom=utf8_encode($cfrom);
						$csubject=utf8_encode($csubject);
						$cto=utf8_encode($cto);
						$cadd=utf8_encode($cadd);

					 	$que="insert into contact_email( sno, username, contactsno, subject, fromadd, toadd, ccadd, date, inlineid, type, sdate, xmltype, xmlbody,charset) values ('', '$username', '$conid', '".addslashes($csubject)."', '".addslashes($cfrom)."','".addslashes($cto)."','".addslashes($cadd)."','".addslashes($senddate)."', '0', '$mailtype', '$findate', '".addslashes($varxmltype)."', '',SUBSTRING_INDEX('".addslashes($CharSet_mail)."','\\r','1'))";
						mysql_query($que,$db);
						$eid=mysql_insert_id($db);

						$bque="select body from mail_headers_body where id=$mailid";
						$bres=mysql_query($bque,$maildb);
						$brow=mysql_fetch_row($bres);
						$cmsg=$brow[0];

						$que="insert into contact_email_body values ('$eid','".addslashes($cmsg)."')";
						mysql_query($que,$maildb);

						$que="insert into cmngmt_pr  (sno, con_id, username, tysno, title, sdate, subject,  lmuser) values ('', '$conid', '$username', '$eid', 'REmail','$findate','".addslashes($csubject)."','$username')";
						mysql_query($que,$db);
						$cmid=mysql_insert_id($db);

						if($varxmlbody!=0 && $varxmlbody!='' && $varxmltype!='')
						{
							$que="insert into contact_em_attach select '','$eid',filename,filetype,filecontent,inline from mail_attachs where mailid='$mailid' and attachid!='$varxmlbody'";
							mysql_query($que,$maildb);

							$que="insert into contact_em_attach select '','$eid',filename,filetype,filecontent,inline from mail_attachs where  attachid='$varxmlbody'";
							mysql_query($que,$maildb);

							$vareffins=mysql_insert_id($maildb);
							$vareff=mysql_affected_rows();

							if($vareff>0)
							{
								$que="update contact_email set xmlbody='$vareffins' where sno='$eid'";
								mysql_query($que,$db);
							}
						}
						else
						{
							$que="insert into contact_em_attach select '','$eid',filename,filetype,filecontent,inline from mail_attachs where mailid='$mailid'";
							mysql_query($que,$maildb);
						}

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

		$que="select mailid from mail_headers where folder='' AND username='$username' AND status='Active' AND inlineid='$mailid'";
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
		return true;
	}

	function getMailProcessData($mailid,$db,$username)
	{
		global $maildb;

		$que="select mailid from mail_headers where folder='' AND username='$username' AND status='Active' AND inlineid='$mailid'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_array($res))
		{
			$que="insert into process_mail_headers(mailid , username , folder , messageid , attach , seen , reply , forward , flag , fromadd , toadd , ccadd , bccadd , subject , date , udate , size , mailtype , inlineid , conid , status , xmltype , xmlbody , extid , sent , charset ) select mailid,username,'', messageid, attach, seen,reply, forward, flag,fromadd,toadd,ccadd,bccadd,subject,date,udate,size,mailtype,inlineid,conid,status,xmltype,xmlbody,extid,'',charset from mail_headers where mailid='$row[0]'";
			mysql_query($que,$db);

			$que="insert into process_mail_headers_body select id,body from mail_headers_body where id='$row[0]'";
			mysql_query($que,$maildb);

			$que="insert into process_mail_attachs select attachid,mailid,filecontent,filename,filesize,filetype,inline from mail_attachs where mailid='$row[0]'";
			mysql_query($que,$maildb);

			getMailProcessData($row[0],$db,$username);
		}
		return true;
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

	//Preparing email checking string
	function getActivitiesEmailCondition($ChkEmail,$tbl,$col1,$col2,$col3)
	{
		$ChkEmail=trim(addslashes($ChkEmail));
		if($ChkEmail=="")
		{
			return " 1=2 ";
		}
		else
		{
			$tbl = ($tbl=='') ? "" : $tbl.".";
			$CommonEmailCond = " (".$tbl.$col1." = '".addslashes(trim($ChkEmail))."' OR ".$tbl.$col2." = '".addslashes(trim($ChkEmail))."' OR ".$tbl.$col3." = '".addslashes(trim($ChkEmail))."') ";
			return $CommonEmailCond;
		}
	}
?>