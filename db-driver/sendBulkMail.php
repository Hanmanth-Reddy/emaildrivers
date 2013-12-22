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

/*
	Created Date : May 10, 2010.
	Created By   : Ramesh C.V.
	Purpose      : Need to provide the send mail functionality as backend process for CRM -> Groups enhancements.
	Task ID      : 5043.
*/	
	
	// Instance SMTP
	$smtp=new smtp_class;

	// Bulk mail count
	$def_max_bulk=100;

	// Bulk mail status
	$stat_bulk="'bulkMail'";

	if(is_dir($WDOCUMENT_ROOT))
	{
		$temp_dir=$WDOCUMENT_ROOT.md5(time());
		mkdir($temp_dir,"0777");
	}

	$dque="SELECT capp_info.comp_id FROM company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno WHERE company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("maildatabase.inc");
		require("database.inc");

		$que="SELECT a.toadd,a.ccadd,a.bccadd,a.fromadd,a.subject,'',a.mailid,a.attach,a.conid,a.status,a.xmltype,a.username,a.charset,a.mailtype FROM mail_headers a WHERE a.folder='outbox' AND a.status=$stat_bulk";
		$res=mysql_query($que,$db);
		if(mysql_num_rows($res) > 0)
		{	
			while($row=mysql_fetch_array($res))
			{
				$bulk_count=0;
				$flag=0;
				$attach="N";
				$attach_body="";
				$inlineattach_body="";

				$mailid=$row[6];

				$bque="SELECT b.body FROM mail_headers_body b b.id=$mailid";
				$bres=mysql_query($bque,$maildb);
				$brow=mysql_fetch_array($bres)
	
				$username=$row[11];
				$to=$row[0];
				$cc=$row[1];
				$bcc=$row[2];
				$from=$row[3];
				$subject=encodedMailsubject($CharSet_mail,$row[4],'B');
				$matter=$brow[0];
				$mail_attach=$row[7];
				$cmntid=$row[8];
				$statusmail=$row[9];
				$xmltype=$row[10];
				$msgs=$mailid;
				$CharSet_mail=AssignEmailCharset($row[12]);
				$mailtype=$row[13];
	
				$efrom=$from;
				require("setSMTP.php");
	
				// reqemail value is getting from setSMTP.php
				$bulk_count=bulkMailCount($username,$reqemail,$db);
				if($bulk_count!="" && $bulk_count>0)
					$max_bulk=$bulk_count;
				else
					$max_bulk=$def_max_bulk;
	
				// Locking mail to not use for next driver execution
				$que="update mail_headers set status='$statusmail-l' where mailid='$mailid'";
				mysql_query($que,$db);
	
				$i=0;
				if($mail_attach=="A")
				{
					$aque="select filename,filesize,filetype,filecontent from mail_attachs where mailid='$mailid' and filename!='' and inline!='true'";
					$ares=mysql_query($aque,$maildb);
					while($arow=mysql_fetch_array($ares))
					{
						$file_name[$i]=$arow['filename'];
						$file_type[$i]=$arow['filetype'];
						$tfile=$arow['filename'];
	
						$file=$temp_dir."/".$tfile;
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
	
				$ique="select subject,mailid from mail_headers where inlineid='$mailid'";
				$ires=mysql_query($ique,$db);
				$irow=mysql_fetch_array($ires);
				$inlinemailid=mysql_num_rows($ires);
					
				if((int)$inlinemailid>0)
					$inlineattach_body=prepareBody_inlinemails((int)$mailid);
	
				if(trim($to) == "")
					$to = $from;
	
				$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Cc: $cc","Subject: $subject","MIME-Version: 1.0");
				$msg_body=prepareBodyA($matter,$mailheaders,$mailtype);
				$msg_body.=$inlineattach_body.$attach_body;
	
				$allemails = $to.",".$cc.",".$bcc;
	
				$arrTotal=array_unique(explode(",",$allemails));
				$arrCount=count($arrTotal);
	
				if($arrCount > $max_bulk)
					$value=$max_bulk;
				else
					$value=$arrCount;
	
				$i=0;
				while($arrCount > $i)
				{
					$arrSub=array_slice($arrTotal,$i,$value);
					if(count($arrSub)>0)
					{
						$suc = $smtp->SendMessage($from,$arrSub,$mailheaders,$msg_body);
	
						sleep(2);
	
						$i=$i+$max_bulk;
						if($arrCount > $i)
						{
							if($arrCount-$i > $max_bulk)
								$value=$max_bulk;
							else
								$value=$arrCount-$value;
						}
						else
						{
							break;
						}
					}
				}
	
				$que="UPDATE mail_headers,cmngmt_pr SET mail_headers.status='Active',mail_headers.folder='sentmessages', mail_headers.seen='S', cmngmt_pr.title='Email',cmngmt_pr.sdate=NOW() WHERE mail_headers.conid = cmngmt_pr.sno AND mail_headers.mailid='$mailid'";
				mysql_query($que,$db);
				
				/*
				If we are not showing these emails in Outbox then we do not need to execute the below commented code.
				*/
				$UpEfldString="outbox|^**^|-1|^**^|0|^**^|false^fasle";
				update_efolder_operations($UpEfldString);
				
			}
		}
		if(is_dir($temp_dir))
			exec("rm -fr $temp_dir");
	}

	function bulkMailCount($username,$from,$db)
	{
		$que="SELECT bulknumrec FROM external_mail WHERE username='$username' AND mailid='$from'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
		$bulk_count=$row[0];

		return $bulk_count;
	}
?>