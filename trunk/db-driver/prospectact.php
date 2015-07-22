<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER'".$version_clause;
 	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$compuser=strtolower($drow[0]);
		require("maildatabase.inc");
		require("database.inc");

		$ubque="select users.username from users LEFT JOIN sysuser ON users.username=sysuser.username where users.type not in ('con','cllacc') and users.status!='DA' and sysuser.crm!='NO'";
		$ubres=mysql_query($ubque,$db);
		while($ubrow=mysql_fetch_row($ubres))
		{
			$username=$ubrow[0];

			$csno="";
			$cque="select cmngmt_process.csno from cmngmt_process LEFT JOIN staffoppr_contact ON staffoppr_contact.sno=cmngmt_process.csno where staffoppr_contact.approveuser='$username' and cmngmt_process.ctype='oppr'";
			$cres=mysql_query($cque,$db);
			while($crow=mysql_fetch_row($cres))
			{
				if($csno=="")
					$csno=$crow[0];
				else
					$csno.=",".$crow[0];
			}

			if($csno=="")
				$que="select staffoppr_contact.sno,staffoppr_contact.email from staffoppr_contact where staffoppr_contact.approveuser='$username' and staffoppr_contact.email!='' and !ISNULL(staffoppr_contact.email)";
			else
				$que="select sno,email from staffoppr_contact where approveuser='$username' and email!='' and !ISNULL(email) and sno not in ($csno)";
			$res=mysql_query($que,$db);
			while($row=mysql_fetch_row($res))
			{
				$conid=$row[0];
				$opprconid="oppr$conid";

				$que="insert into cmngmt_process values ('','oppr','$conid')";
				mysql_query($que,$db);

				$mque="SELECT mailid,folder,subject,mailtype,udate,fromadd,toadd,ccadd,bccadd,date,inlineid,conid,charset FROM mail_headers WHERE username=$username AND ((folder='sentmessages' AND (FIND_IN_SET('$row[1]',REPLACE(REPLACE(toadd,'<',','),'>',','))>0 OR FIND_IN_SET('$row[1]',REPLACE(REPLACE(ccadd,'<',','),'>',','))>0 OR FIND_IN_SET('$row[1]',REPLACE(REPLACE(bccadd,'<',','),'>',','))>0)) OR (conid='' AND folder NOT IN ('sentmessages','outbox','drafts','spam') AND folder!='' AND FIND_IN_SET('$row[1]',REPLACE(REPLACE(fromadd,'<',','),'>',','))>0)) AND sent!='REC'";
				$mres=mysql_query($mque,$db);
				while($mrow=mysql_fetch_row($mres))
				{
					$mailid=$mrow[0];
					$folder=$mrow[1];
					$csubject=$mrow[2];
					$mailtype=$mrow[3];
					$findate=date('Y/m/d H:i:s',$mrow[4]);
					$from=$mrow[5];
					$to=$mrow[6];
					$cc=$mrow[8] ? $mrow[7].",".$mrow[8] : $mrow[7];
					$date=$mrow[9];
					$inlineid=$mrow[10];
					$rconid=$mrow[11];
					$CharSet_mail=$mrow[12];
					
					$bque="select body from mail_headers_body where id='$mailid'";
					$bres=mysql_query($bque,$maildb);
					$brow=mysql_fetch_row($bres);
					$cmsg=$brow[0];

					$newrecord="YES";
					if($folder=="sentmessages")
					{
						$rmail="Email";
						if($rconid!="")
						{
							$rque="select count(*) from cmngmt_pr where FIND_IN_SET ('$opprconid',con_id)=0 and sno=$rconid";
							$rres=mysql_query($rque,$db);
							$rnum=mysql_fetch_row($rres);
							if($rnum[0]>0)
							{
								$newrecord="NO";
								$que="update cmngmt_pr set con_id=concat(con_id,',$opprconid') where sno=$rconid";
								mysql_query($que,$db);
							}
						}
					}
					else
					{
						$rmail="REmail";
					}

					if($newrecord=="YES")
					{
						$que="insert into contact_email (sno,username,contactsno,subject,fromadd,toadd,ccadd,date,inlineid,type,sdate,charset) values ('', '$username', '$opprconid', '".addslashes($csubject)."','".addslashes($from)."','".addslashes($to)."','".addslashes($cc)."','$date','0','".$mailtype."', '".$findate."',SUBSTRING_INDEX('".addslashes($CharSet_mail)."','\\r','1'))";
						mysql_query($que,$db);
						$eid=mysql_insert_id($db);

						$que="insert into contact_email_body values ('$eid','".addslashes($cmsg)."')";
						mysql_query($que,$maildb);

						$que="insert into cmngmt_pr (sno, con_id, username, tysno, title, sdate, subject,  lmuser) values ('', '$opprconid', '$username', '$eid', '$rmail','$findate','".addslashes($csubject)."','$username')";
						mysql_query($que,$db);
						$cmid=mysql_insert_id($db);

						$que="insert into contact_em_attach select '','$eid',filename,filetype,filecontent,inline from mail_attachs where mailid='$mailid'";
						mysql_query($que,$maildb);

						if($inlineid!='' && $inlineid!='0')
							getMailContactActivities($mailid,$db,$eid);

						$que="update mail_headers set conid=$cmid where mailid=$mailid";
						mysql_query($que,$db);
					}
				}
			}
		}
	}

	function getMailContactActivities($mailid,$db,$ceid)
	{
		global $maildb;

		$que="select mailid from mail_headers where inlineid='$mailid'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_array($res))
		{
			$que="insert into contact_email( sno , username , contactsno , subject , fromadd , toadd , ccadd , date , inlineid , type , sdate , xmltype , xmlbody , charset) select '',username,'',subject,fromadd,toadd,ccadd,date,'$ceid',mailtype,NOW(),charset from mail_headers where mailid='$row[0]'";
			mysql_query($que,$db);
			$neid=mysql_insert_id($db);

			$que="insert into contact_email_body select '$neid',body from mail_headers_body where id='$row[0]'";
			mysql_query($que,$maildb);

			$que="insert into contact_em_attach select '','$neid',filename,filetype,filecontent,inline from mail_attachs where mailid='$row[0]'";
			mysql_query($que,$maildb);

			getMailContactActivities($row[0],$db,$neid);
		}
		return true;
	}
?>
