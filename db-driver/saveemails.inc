<?php
	define ("SEND_DEFAULT_MAIL_CHAR","utf-8");// while sending time default charset is utf-8
    function prepareAttachsNEW(&$file_name,&$file_size,&$file_type,&$file_con,&$attach_body)
    {
    	global $WDOCUMENT_ROOT,$filename,$filetype,$filename1,$filetype1,$filename2,$filetype2,$docs,$maildb,$db,$hfAttach,$sesstr,$attach_folder,$attachType,$boun_mixed_part;

    	$flag=0;
    	$attachtype1=explode(",",$attachType);
    	$nos=explode('|^', stripslashes($sesstr));
    	if(trim($hfAttach)!="")
    	for($i=0;$i<count($nos);$i++)
        {
    			$varfname=explode('|-', stripslashes($nos[$i]));
    			$attfilename=$WDOCUMENT_ROOT."/".$attach_folder."/".$varfname[0];
    			$file = fopen($attfilename, "r");
    			$filetype=$attachType[$i];
    			$contents = fread($file, filesize($attfilename));
    			fclose($file);
    			$encoded_attach = chunk_split(base64_encode($contents));

    			$attach_body.="--Message-Boundary$boun_mixed_part\n";
    			$attach_body .= "Content-Type: ".$filetype."; name=\"$varfname[1]\"\n";
    			$attach_body .= "Content-Transfer-Encoding: base64\n";
    			$attach_body .= "Content-disposition: attachment; filename=\"$varfname[1]\"\n\n";
    			$attach_body .= "$encoded_attach\n";

    			$file_name[$flag]=$varfname[1];
    			$file_size[$flag]=filesize($attfilename);
    			$file_type[$flag]="text/plain";
    			//$file_con[$flag]=addslashes($contents);
    			$file_con[$flag]=$contents;
    			$flag++;
    	}

    	if($docs!="")
    		$sdoc=explode("|",$docs);

    	for($i=0;$i<count($sdoc);$i++)
        {
            $que="select filecontent,filename,filetype,size from webfolder where sno=".$sdoc[$i];
           	$res=mysql_query($que,$db);
        	$row=mysql_fetch_row($res);
        	$contents =$row[0] ;

        	$filetype=$row[2];
        	$filename=$row[1];
        	$encoded_attach = chunk_split(base64_encode($contents));
    		$attach_body.="--Message-Boundary$boun_mixed_part\n";
    		$attach_body .= "Content-Type: ".$filetype."; name=\"$filename\"\n";
    		$attach_body .= "Content-Transfer-Encoding: base64\n";
    		$attach_body .= "Content-disposition: attachment; filename=\"$filename\"\n\n";
    		$attach_body .= "$encoded_attach\n";
        	$file_name[$flag]=$filename;
            $file_size[$flag]=$row[3];
            $file_type[$flag]=$filetype;
            //$file_con[$flag]=addslashes($contents);
    		$file_con[$flag]=$contents;
            $flag++;
        }
        if($flag>0)
            $attach_body.="--Message-Boundary$boun_mixed_part--\n";
        return $flag;
    }

	function prepareBody($matter,&$mailheaders,$mailtype)
	{
		global $maildb,$db,$companyuser,$msgs,$boun_mixed_part,$boun_related_part,$boun_alternative_part,$mail_attach,$CharSet_mail;
		
		$CharSet_mail=AssignEmailCharset($CharSet_mail);
		$mailtype=$mailtype=="" ? "text/html" : $mailtype;
		$related_part_logo=(strpos($matter,"logo.php?id=$companyuser") ? "logo" : (strpos($matter,"/BSOS/logo.php?uploadImageStat=true&amp;imgid=") ? "upload" : ""));
		$related_part_cid=strpos($matter,"getcid.php") ? "cid" : "";
		$matter=str_replace("<!--mail-->","",$matter);
		$matter=str_replace("<!--oldmail-->","",$matter);
        $uploadStat="";

		//preparePoweredBy($matter,$pwd_body);

		if($related_part_logo!="" || $related_part_cid!="")
		{
			if($related_part_cid!="")
				prepareCID($matter,$cid_body,$msgs);

			if($related_part_logo!="")
			{
                $lque="select image_data,image_type,image_size from company_logo";
                $lres=mysql_query($lque,$db);
                $lrow=mysql_fetch_row($lres);
                $logocontent=chunk_split(base64_encode($lrow[0]));
                
 				$cid = md5(uniqid("$companyuser"));
                $matter = str_replace("/BSOS/logo.php?id=$companyuser","cid:$cid",$matter);
                
				$strLIT = strtolower($lrow[1]);
				$strFileType = ".jpg";
				if(strpos($strLIT,"jpg") || strpos($strLIT,"jpeg"))
					$strFileType=".jpg";
				else if(strpos($strLIT,"gif"))
					$strFileType=".gif";

                $logo_body.="--Message-Boundary$boun_related_part\n";
				$logo_body.= "Content-Type: $lrow[1]; name=\"".$companyuser.$strFileType."\"\n";
				$logo_body.= "Content-Transfer-Encoding: base64\n";
				$logo_body.= "Content-ID: <$cid>\n\n";
				$logo_body.= $logocontent."\n\n";
			}
			
			if(strpos("*".$matter,"/BSOS/logo.php?uploadImageStat=true&amp;imgid=")>0) //This loop executes when user uploads any header image.
			{
			   
				$posStr =  strpos($matter,"/BSOS/logo.php?uploadImageStat=true&amp;imgid=");
				$newStr = substr($matter,$posStr+46);
				$posNew =  strpos($newStr,"\"");
				$imgSno = (int)substr($newStr,0,$posNew);
	
				$upque="select img_content,img_type,img_size,name,img_name from header_footer where sno='".$imgSno."'";
				$upres=mysql_query($upque,$db);
				if(mysql_num_rows($upres)>0)
				{
					$uprow=mysql_fetch_row($upres);
					$upLogoContent=chunk_split(base64_encode($uprow[0]));
					$HImgName="";
					
					$Hdcid = md5(uniqid("$imgSno"));
					$matter = str_replace("/BSOS/logo.php?uploadImageStat=true&amp;imgid=$imgSno","cid:$Hdcid",$matter);
	
					$upstrLIT = strtolower($uprow[1]);
					if(trim($uprow[4]!=''))
					{
						$HImgName=$uprow[4];
					}
					else 
					{
						$HImgName="Image".$imgSno;
						$upFileType = ".jpg";
						if(strpos($upstrLIT,"jpg") || strpos($upstrLIT,"jpeg"))
							$upFileType=".jpg";
						else if(strpos($upstrLIT,"gif"))
							$upFileType=".gif";
							
						$HImgName.=$upFileType;	
					}				
					$logo_body.="--Message-Boundary$boun_related_part\n";
					$logo_body.= "Content-Type: $uprow[1]; name=\"".$HImgName."\"\n";
					$logo_body.= "Content-Transfer-Encoding: base64\n";
					$logo_body.= "Content-ID: <$Hdcid>\n\n";
					$logo_body.= $upLogoContent."\n\n";
				}
			}
			
			
		}
		else
		{
            $related_part_cid="cid";
            $cid_body=$logo_body="";
        }
		
		if($mail_attach=="A" || ((int)$inlinemailid>0))
		{
			array_push($mailheaders,"Content-Type: multipart/mixed; boundary=\"Message-Boundary$boun_mixed_part\"","Content-Transfer-Encoding: 8bit");
			$ret_body.="--Message-Boundary$boun_mixed_part\n";
		}
		else if($related_part_logo!="" || $related_part_cid!="")
		{
			array_push($mailheaders,"Content-Type: multipart/related; boundary=\"Message-Boundary$boun_related_part\"");
		}
		else if($mailtype=="text/html")
		{
			array_push($mailheaders,"Content-Type: multipart/alternative; boundary=\"Message-Boundary$boun_alternative_part\"");
		}
		else
		{
			array_push($mailheaders,"Content-Type: text/plain; Charset=\"$CharSet_mail\";");
			array_push($mailheaders,"Content-Transfer-encoding: 7bit");
		}

		if(($mail_attach=="A" || ((int)$inlinemailid>0)) && ($related_part_logo!="" || $related_part_cid!=""))
			$ret_body.="Content-Type: multipart/related; boundary=\"Message-Boundary$boun_related_part\"\n\n";

		if($related_part_logo!="" || $related_part_cid!="")
			$ret_body.="--Message-Boundary$boun_related_part\n";

		if($mailtype=="text/html" && ($related_part_logo!="" || $related_part_cid!="" || ((int)$inlinemailid>0) || $mail_attach=="A"))
			$ret_body.="Content-type: multipart/alternative; boundary=\"Message-Boundary$boun_alternative_part\"\n\n";

		if($mailtype=="text/html")
			$ret_body.="--Message-Boundary$boun_alternative_part\n";

		if($mailtype=="text/html" || $related_part_logo!="" || $related_part_cid!="" || $mail_attach=="A")
		{
			$ret_body.="Content-Type: text/plain; Charset=\"".$CharSet_mail."\";\n";
			$ret_body.="Content-Transfer-encoding: 7bit\n\n";

			if($mailtype=="text/plain")
				$ret_body.=strip_tags($matter)."\n\n";
			else
				$ret_body.=html2text($matter)."\n\n";
		}
		else
		{
			$ret_body=strip_tags($matter)."\n\n";
		}

		if($mailtype=="text/html")
		{
			$ret_body.="--Message-Boundary$boun_alternative_part\n";
			$ret_body.="Content-type: text/html; Charset=\"".$CharSet_mail."\";\n";
			$ret_body.="Content-Transfer-encoding: 7bit\n\n";

			$ret_body.=wordwrap($matter,750)."\n\n";
			$ret_body.="--Message-Boundary$boun_alternative_part--\n";
		}

		if($related_part_logo!="" || $related_part_cid!="")
		{
			$ret_body.=$cid_body.$logo_body.$pwd_body;
			$ret_body.="--Message-Boundary$boun_related_part--\n";
		}
		return $ret_body;
	}

	function prepareBodyA($matter,&$mailheaders,$mailtype)
	{
		global $maildb,$db,$companyuser,$msgs,$docs,$sesstr,$boun_mixed_part,$boun_related_part,$boun_alternative_part,$inlinemailid,$CharSet_mail;

		$CharSet_mail=AssignEmailCharset($CharSet_mail);
		$mailtype=$mailtype=="" ? "text/html" : $mailtype;
		$related_part_logo=strpos($matter,"logo.php?id=$companyuser") ? "logo" : "";
		$related_part_cid=strpos($matter,"getcid.php") ? "cid" : "";
		$matter=str_replace("<!--mail-->","",$matter);
		$matter=str_replace("<!--oldmail-->","",$matter);

		if($related_part_logo!="" || $related_part_cid!="")
		{
			if($related_part_cid!="")
				prepareCID($matter,$cid_body,$msgs);

			if($related_part_logo!="")
			{
				$lque="select image_data,image_type,image_size from company_logo";
				$lres=mysql_query($lque,$db);
				$lrow=mysql_fetch_row($lres);
				$logocontent=chunk_split(base64_encode($lrow[0]));

				$cid = md5(uniqid("$companyuser"));
				$matter = str_replace("/BSOS/logo.php?id=$companyuser","cid:$cid",$matter);

				$strLIT = strtolower($lrow[1]);
				$strFileType = ".jpg";
				if(strpos($strLIT,"jpg") || strpos($strLIT,"jpeg"))
					$strFileType=".jpg";
				else if(strpos($strLIT,"gif"))
					$strFileType=".gif";

				$logo_body.="--Message-Boundary$boun_related_part\n";
				$logo_body.="Content-Type: $lrow[1]; name=\"".$companyuser.$strFileType."\"\n";
				$logo_body.="Content-Transfer-Encoding: base64\n";
				$logo_body.="Content-ID: <$cid>\n\n";
				$logo_body.=$logocontent."\n\n";
			}
		}
		if($docs!="" || $sesstr!="" || ((int)$inlinemailid>0))
		{
			array_push($mailheaders,"Content-Type: multipart/mixed; boundary=\"Message-Boundary$boun_mixed_part\"","Content-Transfer-Encoding: 8bit");
			$ret_body.="--Message-Boundary$boun_mixed_part\n";
		}
		else if($related_part_logo!="" || $related_part_cid!="")
		{
			array_push($mailheaders,"Content-Type: multipart/related; boundary=\"Message-Boundary$boun_related_part\"");
		}
		else if($mailtype=="text/html")
		{
			array_push($mailheaders,"Content-Type: multipart/alternative; boundary=\"Message-Boundary$boun_alternative_part\"");
		}
		else
		{
			array_push($mailheaders,"Content-Type: text/plain; Charset=\"$CharSet_mail\";");
			array_push($mailheaders,"Content-Transfer-encoding: 7bit");
		}

		if(($docs!="" || $sesstr!="" || ((int)$inlinemailid>0)) && ($related_part_logo!="" || $related_part_cid!=""))
			$ret_body.="Content-Type: multipart/related; boundary=\"Message-Boundary$boun_related_part\"\n\n";

		if($related_part_logo!="" || $related_part_cid!="")
			$ret_body.="--Message-Boundary$boun_related_part\n";

		if($mailtype=="text/html" && ($related_part_logo!="" || $related_part_cid!="" || ((int)$inlinemailid>0) || $docs!="" || $sesstr!=""))
			$ret_body.="Content-type: multipart/alternative; boundary=\"Message-Boundary$boun_alternative_part\"\n\n";

		if($mailtype=="text/html")
			$ret_body.="--Message-Boundary$boun_alternative_part\n";

		if($mailtype=="text/html" || $related_part_logo!="" || $related_part_cid!="" || $docs!="" || $sesstr!="")
		{
			$ret_body.="Content-Type: text/plain; Charset=\"".$CharSet_mail."\";\n";
			$ret_body.="Content-Transfer-encoding: 7bit\n\n";
			
			if($mailtype=="text/plain")
				$ret_body.=strip_tags($matter)."\n\n";
			else
				$ret_body.=html2text($matter)."\n\n";
		}
		else
		{
			$ret_body=strip_tags($matter)."\n\n";
		}

		if($mailtype=="text/html")
		{
			$ret_body.="--Message-Boundary$boun_alternative_part\n";
			$ret_body.="Content-type: text/html; Charset=\"".$CharSet_mail."\";\n";
			$ret_body.="Content-Transfer-encoding: 7bit\n\n";

			$ret_body.=wordwrap($matter,750)."\n\n";
			$ret_body.="--Message-Boundary$boun_alternative_part--\n";
		}

		if($related_part_logo!="" || $related_part_cid!="")
		{
			$ret_body.=$cid_body.$logo_body;
			$ret_body.="--Message-Boundary$boun_related_part--\n";
		}
		return $ret_body;
	}

	Function prepareCID(&$matter,&$cid_body,$mesid)
	{
		global $maildb,$db,$fromOutbox,$boun_related_part;

		$que="select body from mail_headers_body where id='".$mesid."'";
		$res=mysql_query($que,$maildb);
		$row=mysql_fetch_row($res);
		$realmes=$row[0];

		if (preg_match_all("/educeit-cid:(.[^\">]*)/", $realmes, $matches))
		{
			foreach($matches[1] as $cid)
			{
				$filename=$cid;
				$find_url="/BSOS/getcid.php?mesid=$mesid&amp;lookfor=mail_attachs&amp;filename=$filename";

				if(strpos($matter,$find_url))
				{
					$que="select filecontent from mail_attachs where mailid=$mesid and filename='".$filename."'";
					$res=mysql_query($que,$maildb);
					$row=mysql_fetch_row($res);

					$cidcontent=chunk_split(base64_encode($row[0]));
					$temp_cid = md5(uniqid("$filename"));
					$matter = str_replace($find_url,"cid:$temp_cid",$matter);

					$strLIT = strtolower($filename);
					$strFileType = "jpg";
					if(strpos($strLIT,"jpg") || strpos($strLIT,"jpeg"))
						$strFileType="jpg";
					else if(strpos($strLIT,"png"))
						$strFileType="png";
					else if(strpos($strLIT,"gif"))
						$strFileType="gif";

                    $cid_body.="--Message-Boundary$boun_related_part\n";
					$cid_body.= "Content-Type: image/$strFileType; name=\"$filename\"\n";
					$cid_body.= "Content-Transfer-Encoding: base64\n";
					$cid_body.= "Content-ID: <$temp_cid>\n\n";
					$cid_body.= $cidcontent."\n\n";
				}
			}
		}
        if($fromOutbox=="YES")
        {
            $inlineque="select filename from mail_attachs where mailid=$mesid and inline='true'";
            $inlineres=mysql_query($inlineque,$maildb);
            while($inliners=mysql_fetch_array($inlineres))
            {
                $find_url="/BSOS/getcid.php?mesid=$mesid&amp;lookfor=mail_attachs&amp;filename=".$inliners[0]."";
    			if(strpos($matter,$find_url))
    			{
                    $que="select filecontent from mail_attachs where mailid=$mesid and filename='".$inliners[0]."'";
    				$res=mysql_query($que,$maildb);
    				$row=mysql_fetch_row($res);

    				$cidcontent=chunk_split(base64_encode($row[0]));
    				$temp_cid = md5(uniqid("$filename"));
    				$matter = str_replace($find_url,"cid:$temp_cid",$matter);

    				$strLIT = strtolower($filename);
    				$strFileType = "jpg";
    				if(strpos($strLIT,"jpg") || strpos($strLIT,"jpeg"))
    					$strFileType="jpg";
    				else if(strpos($strLIT,"png"))
    					$strFileType="png";
    				else if(strpos($strLIT,"gif"))
    					$strFileType="gif";

                    $cid_body.="--Message-Boundary$boun_related_part\n";
    				$cid_body .= "Content-Type: image/$strFileType; name=\"$filename\"\n";
    				$cid_body .= "Content-Transfer-Encoding: base64\n";
    				$cid_body .= "Content-ID: <$temp_cid>\n\n";
    				$cid_body .= $cidcontent."\n\n";
    			 }
             }
         }
    }

	Function preparePoweredBy(&$matter,&$pwd_body)
	{
		global $poweredby_logo,$poweredby_name,$poweredby_link,$boun_related_part;

		$related_part_logo=strpos($matter,"logo.php?id=$companyuser") ? "logo" : "";
		$related_part_cid=strpos($matter,"getcid.php") ? "cid" : "";

		$filename=$poweredby_name;
		$pwd_cid = md5(uniqid("$filename"));

		if($related_part_logo=="" || $related_part_cid=="")
			$pwd_text="<table width=650><tr><td>&nbsp;</td></tr></table>";

		$pwd_text.="<div width=650 align=right><br><hr><a href=\"$poweredby_link\" target=_blank><img src=\"cid:$pwd_cid\" width=100 height=40 border=0></a></div>";
		$matter.=$pwd_text;

		$fd=fopen($poweredby_logo,"r");
		$content = fread ($fd, filesize ($poweredby_logo));
		fclose($fd);
		$pwdcontent=chunk_split(base64_encode($content));

		$pwd_body .= "--Message-Boundary$boun_related_part\n";
		$pwd_body .= "Content-Type: image/gif; name=\"$filename\"\n";
		$pwd_body .= "Content-Transfer-Encoding: base64\n";
		$pwd_body .= "Content-ID: <$pwd_cid>\n\n";
		$pwd_body .= $pwdcontent."\n\n";
	}

	function insertCmnt($arrstat,$username,$db,$statusc,$cmntid)
	{
		$que="select cmngmt_pr.tysno,cmngmt_pr.subject,campaign_list.tolist,campaign_list.camptype from cmngmt_pr LEFT JOIN campaign_list  ON cmngmt_pr.tysno = campaign_list.sno where cmngmt_pr.sno=".$cmntid;
		$res=mysql_query($que,$db);
		$row=mysql_fetch_array($res);

		$cid=$row[0];
		$subject=$row[1];
		
		if($row[3]=="C")
		   $sub_type="Candidates";
		else if($row[3]=="J")
		   $sub_type="Job orders";
		   
		$ins="";
		$cn_lead="";
		
		// To eleminate address book conids from sendlist, and to add candidates and contacts conids
		$delelements = array();
		$sendlist_array = array();
		$sendlist_array = explode(",",$row[2]);
		for($i=0;$i<count($sendlist_array);$i++)
		{
			if(substr($sendlist_array[$i],0,2) == 'ab')
			{
				array_push($delelements,$i);
			}
		}
		for ($i=0;$i<count($delelements);$i++)
		{
			unset($sendlist_array[$delelements[$i]]);
		}
		$sendlist_array=array_values($sendlist_array);
		$sendlist = implode(",",$sendlist_array);
		
		if($sendlist != '')
			 return cmngmt_ins($sendlist, $cid, $statusc, $subject,$sub_type);
    }

	function insertPost($arrstat,$username,$db,$statusp,$cmntid,$matter2)
	{
		$sendlist="";

		$que="SELECT cmngmt_pr.tysno, cmngmt_pr.subject, job_post_det.posid, job_post_det.sendlist
FROM cmngmt_pr
LEFT JOIN job_post_det ON cmngmt_pr.tysno = job_post_det.sno
WHERE cmngmt_pr.sno =".$cmntid;
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
        $cid=$row[0];
        $subject=$row[1];
		$postid="req".$row[2]; 
		
		// To eleminate address book conids from sendlist, and to add candidates and contacts conids
		$delelements = array();
		$sendlist_array = array();
		$sendlist_array = explode(",",$row[3]);
		for($i=0;$i<count($sendlist_array);$i++)
		{
			if(substr($sendlist_array[$i],0,2) == 'ab')
			{
				array_push($delelements,$i);
			}
		}
		for ($i=0;$i<count($delelements);$i++)
		{
			unset($sendlist_array[$delelements[$i]]);
		}
		$sendlist_array=array_values($sendlist_array);
		$sendlist = implode(",",$sendlist_array);
		
		if($sendlist!="")
			$sendlist.=",".$postid;
		else
			$sendlist=$postid;
			
        if($sendlist!="")
		  return cmngmt_ins($sendlist, $cid, $statusp, $subject,'');
    }

    function mail_insert($folder,$from,$to,$cc,$bcc,$matter,$subject,$xmltype,$xmlbody,$attach,$mailtype,$sent,$status,$x="")
	{
		global $realusername, $companyuser,$username,$folder,$maildb,$db,$CharSet_mail;

		$mailtype=$mailtype=="" ? "text/html" : $mailtype;

		$msg_id=md5(time().rand(1,10000).rand(1,10000).rand(1,10000));

		$date=date(r);
		$udate=strtotime("now");
		if($folder=="sentmessages")
			$seen="S";
		else
			$seen="U";
		
		$que="insert into mail_headers (mailid,username,folder,messageid,attach,seen,reply,forward,flag,fromadd,toadd,ccadd,bccadd,subject,date,udate,size,mailtype,inlineid,conid,status,sent,xmltype,xmlbody,charset) values ('','$username','$folder','$msg_id','$attach','".$seen."','N','N','NF','".addslashes($from)."','".addslashes($to)."','".addslashes($cc)."','".addslashes($bcc)."','".addslashes($subject)."','$date','$udate','','$mailtype','0','".$x."','$status','".$sent."','".addslashes($xmltype)."','".$xmlbody."','".addslashes($CharSet_mail)."')";
		mysql_query($que,$db);
        $insid=mysql_insert_id($db);

        if($insid>0)
		{
			$urm=($folder=="sentmessages" || $status=='CampaignIP' || $status=='PostingIP')?0:1;
			$UpEfldString=$folder."|^**^|1|^**^|".$urm."|^**^|false^fasle";
			update_efolder_operations($UpEfldString);
		}
		
		$que="insert into mail_headers_body(id,body) values ('".$insid."', '".addslashes($matter)."')";
		mysql_query($que,$maildb);

		return $insid;
	}

	function mail_attach($last_id,$file_con,$file_name,$file_size,$file_type1,$xmlfile)
	{
		global $maildb,$db;

		$que="insert into mail_attachs (attachid,mailid,filecontent,filename,filesize,filetype,inline) values ('','$last_id','".addslashes($file_con)."','".addslashes($file_name)."','$file_size','".addslashes($file_type1)."','false')";
		mysql_query($que,$maildb);
		$lastAttId=mysql_insert_id($maildb);

		if($file_name==$xmlfile.".xml")
		{
            $que="update mail_headers set xmlbody='".$lastAttId."' where mailid='".$last_id."'";
            mysql_query($que,$db);
        }
	}

	function cmngmt_ins($con_id, $cmid, $status, $subject,$subtype)
	{
		global $username,$maildb,$db;

		$que="insert into cmngmt_pr(sno, con_id, username, tysno, title, sdate, subject,  lmuser, subtype) values('','".$con_id."','".$username."','".$cmid."','".$status."',NOW(),'".addslashes($subject)."','".$username."','".$subtype."')";
		mysql_query($que, $db);

		return mysql_insert_id($db);
	}

	//encoding mail subject
	function encodedMailsubject($charset,$data,$Decode='B')
	{
		$charset=AssignEmailCharset($charset);
		$Decode=$Decode!=''?$Decode:'B';
		
		$decodeStr="=?".$charset."?".$Decode."?";
		if($Decode == "B")
		{
			$decodeStr.=base64_encode($data);
		}
		else if($Decode == "Q")
		{
			$decodeStr.=preg_replace("/(=([0-9A-F]){1,1}([0-9A-F]){1,1})/e", "chr(hexdec('\\2'.'\\3'))", $data);//quoted_printable_decode($data);
		}
			
		return $decodeStr."?=";
	}
	
	//Assigning charset
	function AssignEmailCharset($charset)
	{	
		$charset=(trim($charset)!='')?$charset:SEND_DEFAULT_MAIL_CHAR;
		return $charset;
	}
?>
