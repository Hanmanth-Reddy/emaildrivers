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
	$fromOutbox="YES";
	$update_mail_options="No";

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("maildatabase.inc");
		require("database.inc");

		$ubque="SELECT username FROM users WHERE usertype!='' AND status!='DA'";
		$ubres=mysql_query($ubque,$db);
		while($ubrow=mysql_fetch_row($ubres))
		{
			$username=$ubrow[0];
			$update_mail_options="No";

			$que="SELECT a.toadd,a.ccadd,a.bccadd,a.fromadd,a.subject,'',a.mailid,a.attach,a.conid,a.status,a.mailtype,a.xmltype,a.xmlbody,a.seen,a.charset FROM mail_headers a LEFT JOIN mail_editor c ON a.username=c.username LEFT JOIN mail_scheduled d ON a.mailid=d.mailid WHERE a.folder='outbox' AND a.username='$username' AND a.status='scheduled'";
			$res=mysql_query($que,$db);
			while($row=mysql_fetch_array($res))
			{
				$file_name=array();
				$file_size=array();
				$file_type=array();
				$tempfile=array();
				$arrTotal=array();

				$delqstr="";
				$attach="N";

				$mailid=$row[6];

				$bque="SELECT body FROM mail_headers_body WHERE id=$mailid";
				$bres=mysql_query($bque,$maildb);
				$brow=mysql_fetch_row($bres);

				$to=$row[0];
				$cc=$row[1];
				$bcc=$row[2];
				$from=$row[3];
				$subject=$row[4];
				$matter=$brow[0];
				$mail_attach=$row[7];
				$cmntid=$row[8];
				$statusmail=$row[9];
				$mailtype=$row[10];
				$xmltype=$row[11];
				$xmlbody=$row[12];
				$msgs=$mailid;
				$read=$row[13];

				$CharSet_mail=AssignEmailCharset($row[14]);
				$sentsubject=encodedMailsubject($CharSet_mail,$subject,'B');
				
				if($statusmail=="scheduled")
				{
					$schstat=scheduleStatus($mailid);
					if($schstat=="1")
					{
						// Not scheduled at this time, continue next mail.
						continue;
					}
					else if($schstat=="2")
					{
						// Recurrence is completed, delete mail and continue next mail.
						$que="update mail_headers set status='deleted' where mailid='$mailid'";
						mysql_query($que,$db);

						$Updatedrows=mysql_affected_rows();
						if($Updatedrows>0)
						{
							 $untot=($read=='U')?'1':'0';	
							 $UpEfldString="outbox|^**^|-1|^**^|-".$untot."|^**^|false^fasle";
							 update_efolder_operations($UpEfldString);
						}
						
						$que="delete from mail_scheduled where mailid='$mailid'";
						mysql_query($que,$db);

						continue;
					}
					else
					{
						// Scheduled at this time, send it and update senton to now.
						$que="update mail_scheduled set senton=UNIX_TIMESTAMP() where mailid='$mailid'";
						mysql_query($que,$db);
					}
				}
				else
				{
					$update_mail_options="Yes";
				}

				$efrom=$from;
				require("setSMTP.php");

				$attach_body="";
				$inlineattach_body="";
				$attach_folder=mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"));
				$attach_folder.=$mailid;//Added in case two mails in outbox are processed with in a sec

				$i=0;
				$flag=0;
				if($mail_attach=="A")
				{
					$aque="select filename,filesize,filetype,filecontent from mail_attachs where mailid='$mailid' and filename!='' and inline!='true'";
					$ares=mysql_query($aque,$maildb);
					while($arow=mysql_fetch_array($ares))
					{
						$file_name[$i]=$arow['filename'];
						$file_type[$i]=$arow['filetype'];
						$tfile=$arow['filename'];
						$isDirEx=$WDOCUMENT_ROOT."/".$attach_folder;

						if(!is_dir($isDirEx))
							mkdir($isDirEx,0777);

						$file=$isDirEx."/".$tfile;
						$fp = fopen($file,"w");
						fwrite($fp, $arow['filecontent']);
						fclose($fp);
						$file_size[$i]=filesize($file);
						$tempfile[$i]=$tfile."|-".stripslashes($arow['filename']);
						$i++;
						$flag++;
					}

					$attachments=implode(",",$file_name);
					$hfattachments=implode("|^",$file_name);
					$hfAttach=$hfattachments;
					$attachType=implode(",",$file_type);
					$asize=implode("|^",$file_size);
					$sesstr=implode("|^",$tempfile);
					$flag=prepareAttachsNEW($file_name,$file_size,$file_type,$file_con,$attach_body);
					$attach = $flag=="0" ? "NA" : "A";
				}

				$x=0;
				$atl=$to.",".$cc.",".$bcc;
				$arrTotal1=array_values(array_unique(quotesplit($atl,$split=",",$temp="^")));
				for($l=0;$l<count($arrTotal1);$l++)
				{
					if($arrTotal1[$l]!="")
					{
						$arrTotal[$x]=$arrTotal1[$l];
						$x++;
					}
				}

				$ique="select subject,mailid from mail_headers where inlineid='$mailid'";
				$ires=mysql_query($ique,$db);
				$irow=mysql_fetch_array($ires);
				$inlinemailid=mysql_num_rows($ires);
				
                if((int)$inlinemailid>0)
                    $inlineattach_body=prepareBody_inlinemails((int)$mailid);

				//checking whether the mail is related to ecampaign or Jobpostings

				$esid="";
				if($xmlbody!="")
				{
					if(preg_match("/\(eCampaign/isU",$subject))
					{
						$xmlHeader="HotList";
						$xque="select campaign_list.id from campaign_list left join cmngmt_pr on campaign_list.sno=cmngmt_pr.tysno left join mail_headers on cmngmt_pr.sno=mail_headers.conid where mail_headers.mailid='$mailid'";
						$xres=mysql_query($xque,$db);
						$xrow=mysql_fetch_row($xres);
						$esid=$xrow[0];
					}
					else if(preg_match("/\(Posting/isU",$subject))
					{
						$xmlHeader="HotReq";
						$xque="select job_post_det.seqnumber from job_post_det left join cmngmt_pr on job_post_det.sno=cmngmt_pr.tysno left join mail_headers on cmngmt_pr.sno=mail_headers.conid where mail_headers.mailid='$mailid'";
						$xres=mysql_query($xque,$db);
						$xrow=mysql_fetch_row($xres);
						$esid=$xrow[0];
					}
				}
				
				if($esid!="")
					$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: $cc","Subject: $sentsubject","$xmlHeader: true","MIME-Version: 1.0","Xmlid: $esid");
				else
					$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: $cc","Subject: $sentsubject","MIME-Version: 1.0");
				$msg_body=prepareBodyA($matter,$mailheaders,$mailtype);
				$msg_body.=$inlineattach_body.$attach_body;
				$suc=$smtp->SendMessage($from,$arrTotal,$mailheaders,$msg_body) ? "true" : "false";
				if($suc=="true")
				{
					$folder="sentmessages";
					$stats="Active";

					$last_id1=mail_insert($folder,$from,$to,$cc,$bcc,$matter,$subject,$xmltype,$xmlbody,$attach,$mailtype,$sent,$stats,"");
					for($i=0;$i<$flag;$i++)
						mail_attach($last_id1,$file_con[$i],$file_name[$i],$file_size[$i],$file_type[$i],"");

					$cque="select conid from mail_headers where mailid='$mailid'";
					$cres=mysql_query($cque,$db);
					$crow=mysql_fetch_row($cres);
					if($crow[0]!="")
					{
						$tque="select title from cmngmt_pr where sno='$crow[0]'";
						$tres=mysql_query($tque,$db);
						$trow=mysql_fetch_row($tres);
						if($trow[0]=="CFailed")
							$title="Campaign";
						else if($trow[0]=="SFailed")
							$title="Submissions";
						else if($trow[0]=="PFailed")
							$title="Postings";
						else if($trow[0]=="MFailed")
							$title="Email";

						$que="update cmngmt_pr set title='$title' where con_id='$crow[0]'";
						mysql_query($que,$db);
					}

					if(is_dir($WDOCUMENT_ROOT))
						exec("rm -fr $WDOCUMENT_ROOT"."/"."$attach_folder");						
				}

				if(is_dir($WDOCUMENT_ROOT))
					exec("rm -fr $WDOCUMENT_ROOT");
			}
		}
	}
?>
