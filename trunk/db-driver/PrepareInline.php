<?php
	function prepareInlineCID(&$inlinematter,&$inlinecid_body,$mesid,$parid)
	{
		global $maildb,$db,$boun_related_part;

		$mailid=$mesid;

		$que="select body from mail_headers_body where id='$mesid'";
		$res=mysql_query($que,$maildb);
		$row=mysql_fetch_row($res);
		$realmes=$row[0];
		if (preg_match_all("/educeit-cid:(.[^\">]*)/", $realmes, $matches))
		{
			foreach($matches[1] as $cid)
			{
				$filename=$cid;

                $que="select filecontent from mail_attachs where mailid=$mesid and filename='$filename'";
                $find_url="/BSOS/getcid.php?mesid=$mesid&amp;lookfor=mail_attachs&amp;filename=$filename";

				$res=mysql_query($que,$maildb);
				$row=mysql_fetch_row($res);

				$cidcontent=chunk_split(base64_encode($row[0]));
				$temp_cid = md5(uniqid("$filename"));

				if(strpos($inlinematter,$find_url))
					$inlinematter = str_replace($find_url,"cid:$temp_cid",$inlinematter);
				else
					$inlinematter = str_replace("educeit-cid:$cid","cid:$temp_cid",$inlinematter);

				$strLIT = strtolower($filename);
				$strFileType = "jpg";
				if(strpos($strLIT,"jpg") || strpos($strLIT,"jpeg"))
					$strFileType="jpg";
				else if(strpos($strLIT,"png"))
					$strFileType="png";
				else if(strpos($strLIT,"gif"))
					$strFileType="gif";

				$inlinecid_body .= "--Message-Boundary$parid-$boun_related_part\n";
				$inlinecid_body .= "Content-Type: image/$strFileType; name=\"$filename\"\n";
				$inlinecid_body .= "Content-Transfer-Encoding: base64\n";
				$inlinecid_body .= "Content-ID: <$temp_cid>\n\n";
				$inlinecid_body .= $cidcontent."\n\n";
			}
		}
	}

	function prepareBody_inlinemails($mesid,$parid)
	{
		global $maildb,$db,$msgs,$loop_boundary,$companyuser,$inlinemail_body,$sesstr,$docs,$boun_mixed_part,$boun_related_part,$boun_alternative_part;

		$que="select a.mailid mailid,a.fromadd fromadd,a.toadd toadd,a.ccadd ccadd,a.subject subject,a.date date ,a.udate udate,a.mailtype type,'',if(a.charset!='',a.charset,'utf-8') as charset from mail_headers a where a.inlineid='$mesid'";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_array($res))
		{
			$mailid=$row['mailid'];

			$bque="select b.body message from mail_headers_body b where b.id='$mesid'";
			$bres=mysql_query($bque,$maildb);
			$brow=mysql_fetch_array($bres);

			$from=$row['fromadd'];
			$to=$row['toadd'];
			$cc=$row['ccadd'];
			$subject=$row['subject'];
			$date=$row['date'];
			$inlinematter=$brow['message'];
			$mailtype=$row['type'];
			$inlineCharset=$row['charset'];

			if($subject=="")
				$subject="No Subject";

			$inlinematter=str_replace("<!--mail-->","",$inlinematter);
			$mailtype=$mailtype=="" ? "text/html" : $mailtype;
			$related_part_cid=strpos($inlinematter,"educeit-cid:") ? "cid" : "";

			if($mesid==$msgs)
			{
				$inlinemail_body.= "--Message-Boundary$boun_mixed_part\n";
				$inlinemail_body.= "Content-Type: message/rfc822\n";
				$inlinemail_body.="Content-Transfer-encoding: 8bit\n\n";
			}
			else
			{
				$inlinemail_body.="--Message-Boundary$parid-$boun_mixed_part\n";
				$inlinemail_body.= "Content-Type: message/rfc822\n";
				$inlinemail_body.="Content-Transfer-encoding: 8bit\n\n";
			}
			$parid=$mesid;
			$sentsubject=encodedMailsubject('utf-8',$subject,'B');
			$inlinemail_body.="From: $from\n";
			$inlinemail_body.="To: $to\n";
			$inlinemail_body.="Cc: $cc\n";
			$inlinemail_body.="Subject: $sentsubject\n";
			$inlinemail_body.="Date: $date\n";
			$inlinemail_body.="MIME-Version: 1.0\n";

			$aque="select count(*) from mail_attachs where mailid='".$mailid."' and inline!='true'";
			$ares=mysql_query($aque,$maildb);
			$arow=mysql_fetch_row($ares);
			$attachs=$arow[0];

			$ique="select count(*) from mail_attachs where mailid='".$mailid."' and inline='true'";
			$ires=mysql_query($ique,$maildb);
			$irow=mysql_fetch_row($ires);

			$imque="select count(*) from mail_headers where inlineid='$mailid'";
			$imres=mysql_query($imque,$db);
			$imrow=mysql_fetch_row($imres);

			$iattachs=$irow[0]+$imrow[0];

			if($related_part_cid!="")
				prepareInlineCID($inlinematter,$inlinecid_body,$mailid,$parid);

			if($attachs>0 || $iattachs>0)
			{
				$inlinemail_body.="Content-Type: multipart/mixed; boundary=\"Message-Boundary$parid-$boun_mixed_part\"\nContent-Transfer-Encoding: 8bit\n\n";
				$inlinemail_body.="--Message-Boundary$parid-$boun_mixed_part\n";
			}
			else if($related_part_cid!="")
			{
				$inlinemail_body.="Content-Type: multipart/related; boundary=\"Message-Boundary$parid-$boun_related_part\"\n\n";
			}
			else if($mailtype=="text/html")
			{
				$inlinemail_body.="Content-Type: multipart/alternative; boundary=\"Message-Boundary$parid-$boun_alternative_part\"\n\n";
			}
			else
			{
				$inlinemail_body.="Content-Type: text/plain; Charset=\"".$inlineCharset."\";\n";
				$inlinemail_body.="Content-Transfer-encoding: 7bit\n\n";
			}

			if(($attachs>0 || $iattachs>0) && $related_part_cid!="")
				$inlinemail_body.="Content-Type: multipart/related; boundary=\"Message-Boundary$parid-$boun_related_part\"\n\n";

			if($related_part_cid!="")
				$inlinemail_body.="--Message-Boundary$parid-$boun_related_part\n";

			if($mailtype=="text/html" && ($related_part_cid!="" || $attachs>0 || $iattachs>0))
				$inlinemail_body.="Content-type: multipart/alternative; boundary=\"Message-Boundary$parid-$boun_alternative_part\"\n\n";

			if($mailtype=="text/html")
				$inlinemail_body.="--Message-Boundary$parid-$boun_alternative_part\n";

			if($mailtype=="text/html" || $related_part_cid!="" || $attachs>0 || $iattachs>0)
			{
				$inlinemail_body.="Content-Type: text/plain; Charset=\"".$inlineCharset."\";\n";
				$inlinemail_body.="Content-Transfer-encoding: 7bit\n\n";

				if($mailtype=="text/plain")
					$inlinemail_body.=strip_tags($inlinematter)."\n\n";
				else
					$inlinemail_body.=html2text($inlinematter)."\n\n";
			}
			else
			{
				$inlinemail_body.=strip_tags($inlinematter)."\n\n";
			}

			if($mailtype=="text/html")
			{
				$inlinemail_body.="--Message-Boundary$parid-$boun_alternative_part\n";
				$inlinemail_body.="Content-type: text/html; Charset=\"".$inlineCharset."\";\n";
				$inlinemail_body.="Content-Transfer-encoding: 7bit\n\n";

				$inlinemail_body.=wordwrap($inlinematter,750)."\n\n";
				$inlinemail_body.="--Message-Boundary$parid-$boun_alternative_part--\n";
			}

			if($related_part_cid!="")
			{
				$inlinemail_body.=$inlinecid_body;
				$inlinemail_body.="--Message-Boundary$parid-$boun_related_part--\n";
			}

			if($attachs>0)
				$inlinemail_body.=inline_mail_attachments($mailid,$parid);

			if($parid!="")
				$loop_boundary="\n--Message-Boundary$parid-$boun_mixed_part--\n".$loop_boundary;
			else
				$loop_boundary="";

			prepareBody_inlinemails($mailid,$mesid);
		}

		if($docs!="" || $sesstr!="")
			return $inlinemail_body.$loop_boundary;
		else
			return $inlinemail_body.$loop_boundary."--Message-Boundary$boun_mixed_part--\n";
	}

	function inline_mail_attachments($mailid,$parid)
	{
		global $maildb,$db,$boun_mixed_part;

		$mail_attachbody="";

		$que="select filetype,filename,filecontent from mail_attachs where mailid='$mailid' and inline!='true'";
		$res=mysql_query($que,$maildb);
		while($row=mysql_fetch_row($res))
		{
			$filetype=$row[0];
			$filename=$row[1];

			$encoded_attach = chunk_split(base64_encode($row[2]));
			$mail_attachbody .= "--Message-Boundary$parid-$boun_mixed_part\n";
			$mail_attachbody .= "Content-Type: ".$filetype."; name=\"$filename\"\n";
			$mail_attachbody .= "Content-Transfer-Encoding: base64\n";
			$mail_attachbody .= "Content-disposition: attachment; filename=\"$filename\"\n\n";
			$mail_attachbody .= "$encoded_attach\n";
		}
		return $mail_attachbody;
	}
?>
