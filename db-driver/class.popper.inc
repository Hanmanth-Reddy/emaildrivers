<?php
/*
Modified By:	Sundar Kota(May 11, 2009)
Purpose:		Modified code for parse inline images correctly
TS Task Id:		4420
*/
require("class.mimepart.inc");
class popper extends mimepart
{
	var $folder = "";

	function popper()
	{
		$this->headers = new headers;
		$this->folder = "inbox";
	}

	function load($mail)
	{
		$this->from_text($mail);
	}

	function handle_related_part ($cid,$name,$rel_data)
	{
		$cid = str_replace(">","",str_replace("<","",$cid));
		$retString="";
		if(strpos($rel_data,"cid:")==false)
		{
			$retString=str_replace($cid,"educeit-cid:$name",$rel_data);	
		}
		else
		{	
			$retString=str_replace("cid:$cid","educeit-cid:$name",$rel_data);			
		}
		return $retString;
	}

	function get_body($of_part = 0,&$text_body,&$html_body,&$calendar_body)
	{
		if ($of_part != 0)
			$mailer = $of_part;
		else
			$mailer = $this;

		$i = 1;
		while($mailer->extract_part($i))
		{
			if (sizeof($mailer->mimeparts[$i]->mimeparts) > 0)
			{
				$this->get_body($mailer->mimeparts[$i],&$text_body,&$html_body);
				$i++;
				continue;
			}

			$type = $mailer->decode_header($mailer->get_attachment_type($i));
			$cid = $mailer->decode_header($mailer->get_attachment_cid($i));
			$name = $mailer->decode_header($mailer->get_attachment_filename($i));
			$data = $mailer->get_attachment_data($i);

			if(($name=="") && (strtolower($type)=="text/plain" || strtolower($type)=="text/html" ||  strtolower($type)=="text/enriched") || strtolower($type)=="text/calendar")
			{
				if((strtolower($type)=="text/plain" || strtolower($type)=="text/html" ||  strtolower($type)=="text/enriched") || strtolower($type)=="text/calendar")
				{
					$this->Body_Charset=$mailer->get_Charset($i);
				}
				if(strtolower($type)=="text/plain")
					$text_body.=$data;
				else if(strtolower($type)=="text/calendar")
					$calendar_body.=$data;
				else
					$html_body.=$data;
			}
			$i++;
		}
	}

	function get_attachments($of_part = 0,$body,$last_id,$xmlattachid)
	{
		global $maildb,$db,$username;

		if ($of_part != 0)
			$mailer = $of_part;
		else
			$mailer = $this;

		$i = 1;
		while($mailer->extract_part($i))
		{
			if (sizeof($mailer->mimeparts[$i]->mimeparts) > 0)
			{
				$this->get_attachments($mailer->mimeparts[$i],$body,$last_id,$xmlattachid);
				$i++;
				continue;
			}

			$type = $mailer->decode_header($mailer->get_attachment_type($i));
			$data = $mailer->get_attachment_data($i);
			$name = $mailer->decode_header($mailer->get_attachment_filename($i));
			$method = $mailer->decode_header($mailer->get_attachment_method($i));

 			/* Parse ".eml" attachment as a inline attachment. bugid: 3667 ; Dev: Sundar. Dt 20 Nov, 2008 */
 			$nameExtenArr = explode(".",$name);
 			$fileExtension = $nameExtenArr[count($nameExtenArr)-1];
			if($type=="message/rfc822" || $fileExtension=="eml")
			{
				insertMainData($data,$last_id,$xmlattachid);
				$que="update mail_headers set attach='A' where mailid='$last_id' and attach='N'";
				mysql_query($que,$db);
			}
			else if($type=="application/ms-tnef")
			{
 				$que="update mail_headers set attach='A' where mailid='$last_id' and attach='N'";
				mysql_query($que,$db);
				$this->parseWinmailAttachment($name,$data,$last_id,$xmlattachid);
			}
			else if($type=="text/calendar" && $method!="")
			{
				$que="update mail_headers set calendar='Y' where mailid='$last_id'";
				mysql_query($que,$db);

				$que="insert into mail_calendar (cid,mailid,content,type) values ('','$last_id','".addslashes($data)."','".addslashes($method)."')";
				mysql_query($que,$db);
				$cid=mysql_insert_id($db);

				if(strtoupper($method)=="REPLY")
					$this->processInvitationReply($cid);
				else if(strtoupper($method)=="REQUEST")
					$this->processInvitationRequest($cid);
				else if(strtoupper($method)=="CANCEL")
					$this->deleteInvitation($cid);
			}
			else
			{
				$name = $mailer->decode_header($mailer->get_attachment_filename($i));
				if($name!="" || !(strpos($type,"image/")===false))
				{
					$name=($name!="") ? $name : $this->get_dynamic_attachment_name($type);
					$que="update mail_headers set attach='A' where mailid='$last_id' and attach='N'";
					mysql_query($que,$db);

					$cid=$mailer->decode_header($mailer->get_attachment_cid($i));
					$cid = str_replace(">","",str_replace("<","",$cid));
					$inline="false";
					if($cid=="" || (strpos($type,"image/")===false))
					{
						$inline="false";
					}
					else if(!(strpos($body,$cid)===false))
					{
						$inline="true";
						$body = $this->handle_related_part($cid,$name,$body);

						$que="update mail_headers_body set body='".addslashes($body)."' where id='$last_id'";
						mysql_query($que,$maildb);
					}

					$que="insert into mail_attachs (attachid,mailid,filecontent,filename,filesize,filetype,inline) values ('','$last_id','".addslashes($data)."','".addslashes($name)."','".addslashes($file_size)."','".addslashes($type)."','".addslashes($inline)."')";
					mysql_query($que,$maildb);

					if($xmlattachid.".xml"==$name)
					{
						$xmlid=mysql_insert_id($db);
						$que="update mail_headers set xmlbody='$xmlid' where mailid='$last_id'";
						mysql_query($que,$db);
					}
				}
			}
			$i++;
		}
	}

	function get_only_attachments($last_id,$only_attach)
	{
		global $maildb,$db;

		$que="update mail_headers set attach='A' where mailid='$last_id' and attach='N'";
		mysql_query($que,$db);

		$type=$only_attach["type"];
		$name=$only_attach["name"];
		$data=$only_attach["body"];
		
		$nameExtenArr = explode(".",$name);
 		$fileExtension = $nameExtenArr[count($nameExtenArr)-1];
		/* Parse ".eml" attachment as a inline attachment. bugid: 3667 ; Dev: Sundar. Dt 20 Nov, 2008 */
		if($type=="message/rfc822" || $fileExtension=="eml")
		{
			insertMainData($data,$last_id,$xmlattachid);
			$que="update mail_headers set attach='A' where mailid='$last_id' and attach='N'";
			mysql_query($que,$db);
		}
		else if($type=="application/ms-tnef")
		{
			$que="update mail_headers set attach='A' where mailid='$last_id' and attach='N'";
			mysql_query($que,$db);
			$this->parseWinmailAttachment($name,$data,$last_id,$xmlattachid);
		}
		else
		{

			$que="insert into mail_attachs (attachid,mailid,filecontent,filename,filesize,filetype,inline) values ('','$last_id','".addslashes($data)."','".addslashes($name)."','".addslashes($file_size)."','".addslashes($type)."','0')";
			mysql_query($que,$maildb);
		}
	}

	function get_only_calendar($last_id,$only_calendar)
	{
		global $maildb,$db;

		$method=$only_calendar["method"];
		$data=$only_calendar["body"];

		$que="update mail_headers set calendar='Y' where mailid='$last_id'";
		mysql_query($que,$db);

		$que="insert into mail_calendar (cid,mailid,content,type) values ('','$last_id','".addslashes($data)."','".addslashes($method)."')";
		mysql_query($que,$db);
		$cid=mysql_insert_id($db);

		if(strtoupper($method)=="REPLY")
			$this->processInvitationReply($cid);
		else if(strtoupper($method)=="REQUEST")
			$this->processInvitationRequest($cid);
		else if(strtoupper($method)=="CANCEL")
			$this->deleteInvitation($cid);
	}

	function processInvitationRequest($cid)
	{
		global $maildb,$db,$username;

		$imcParse = new IMC_Parse();
		$calinfo = $imcParse->parseInvCalendar($cid);

		$uid=$calinfo["VCALENDAR"]["VEVENT"]["UID"][0]["value"];
		$sequence = $calinfo["VCALENDAR"]["VEVENT"]["SEQUENCE"][0]["value"];
		$rczid = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["param"]["TZID"];

		$que="update mail_calendar set username='$username', uid='$uid', sequence='$sequence' where cid=$cid";
		mysql_query($que,$db);

		$org_email = trim($calinfo["VCALENDAR"]["VEVENT"]["ORGANIZER"][0]["value"]);
		preg_match(EMAIL_REG_EXP,$org_email,$eemail);
		$omail=trim($eemail[0]);

		$mque="select TRIM(external_mail.mailid) from external_mail LEFT JOIN mail_headers ON external_mail.sno=mail_headers.extid LEFT JOIN mail_calendar ON mail_headers.mailid=mail_calendar.mailid where mail_calendar.cid=$cid";
		$mres=mysql_query($mque,$db);
		$mrow=mysql_fetch_row($mres);
		if(strtolower($mrow[0]) == strtolower($omail))
		{
			if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
			{
				$aque="select sno from appointments where status='active' AND uid='".addslashes($uid)."' AND parid=0";
			}
			else
			{
				$odates = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"];
				$temp = $imcParse->extractDateTime($odates[$r],$rczid,"");
				$rcdates = $temp[0];

				$aque="select sno from appointments where status='active' AND uid='".addslashes($uid)."' AND exptime=$rcdates";
			}
			$ares=mysql_query($aque,$db);
			$arow=mysql_fetch_row($ares);
			$aid = $arow[0];
			if($aid=="")
				$aid=0;

			// The Organizer sent him/her self an invitation. Do not require to provide option for ACEEPT, DECLINE, TENTATIVE.
			$uque="update mail_calendar set aid=$aid, status='O' where cid = $cid";
			mysql_query($uque,$db);
		}
		else
		{
			if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
			{
				$uque="update mail_calendar set status='N' where type='REQUEST' AND status!='O' AND username='$username' AND uid='$uid' AND sequence < '$sequence'";
				mysql_query($uque,$db);
			}
		}
	}

	function processInvitationReply($cid)
	{
		global $maildb,$db,$username;

		$imcParse = new IMC_Parse();
		$calinfo = $imcParse->parseInvCalendar($cid);

		if($imcParse->sdt=="S")
			$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
		else
			$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"];

		if($sdtosf!="")
			$imcParse->sdtosf = $sdtosf;

		if(strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["VALUE"])=="date" || strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["VALUE"])=="date")
			$event = "allday";
		else
			$event = "none";

		$uid=$calinfo["VCALENDAR"]["VEVENT"]["UID"][0]["value"];
		$sequence = $calinfo["VCALENDAR"]["VEVENT"]["SEQUENCE"][0]["value"];
		$rczid = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["param"]["TZID"];

		$que="update mail_calendar set username='$username', uid='$uid', sequence='$sequence' where cid=$cid";
		mysql_query($que,$db);

		$raction=$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][0]["param"]["PARTSTAT"];
		$attendee=$calinfo["VCALENDAR"]["VEVENT"]["ATTENDEE"][0]["value"];
		preg_match(EMAIL_REG_EXP,$attendee,$eemail);
		$aemail=trim($eemail[0]);

		if($aemail!="" && $raction!="")
		{
			$que="update mail_calendar set status='".$raction[0]."' where cid='$cid'";
			mysql_query($que,$db);

			if(($uid!="") && ($raction=="ACCEPTED" || $raction=="TENTATIVE" || $raction=="DECLINED"))
			{
				if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
				{
					$aque="select sno from appointments where status='active' AND uid='".addslashes($uid)."' AND parid=0";
				}
				else
				{
					$odates = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"];
					$temp = $imcParse->extractDateTime($odates,$rczid,"");
					$rcdates = $temp[0];

					$aque="select sno from appointments where status='active' AND uid='".addslashes($uid)."' AND exptime=$rcdates";
				}

				$ares=mysql_query($aque,$db);
				$arow=mysql_fetch_row($ares);
				$aid = $arow[0];
				if($aid=="")
					$aid=0;

				if($aid>0)
				{
					$hque="select username from external_mail where deflt='Yes' AND TRIM(mailid)='".addslashes($aemail)."'";
					$hres=mysql_query($hque,$db);
					$hrow=mysql_fetch_row($hres);
					$iuser=$hrow[0];

					if($iuser!="")
					{
						$que="update mail_calendar set aid='$aid' where cid='$cid'";
						mysql_query($que,$db);

						if($raction=="ACCEPTED")
							$imcParse->acceptInv($iuser,$uid,$aid,$aemail,$raction[0]);
						else if($raction=="TENTATIVE")
							$imcParse->tentativeInv($iuser,$uid,$aid,$aemail,$raction[0]);
						else
							$imcParse->declineInv($iuser,$uid,$aid,$aemail,$raction[0]);
					}
					else
					{
						$uque="update invite_calendar set status='".$raction[0]."' where appno='$aid' AND email='$aemail'";
						mysql_query($uque,$db);
					}
				}
			}
		}
	}

	function deleteInvitation($cid)
	{
		global $maildb,$db,$username,$companyuser;

		$imcParse = new IMC_Parse();
		$calinfo = $imcParse->parseInvCalendar($cid);

		if($imcParse->sdt=="S")
			$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["STANDARD"]["TZOFFSETTO"][0]["value"];
		else
			$sdtosf = $calinfo["VCALENDAR"]["VTIMEZONE"]["DAYLIGHT"]["TZOFFSETTO"][0]["value"];

		if($sdtosf!="")
			$imcParse->sdtosf = $sdtosf;

		if(strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTSTART"][0]["param"]["VALUE"])=="date" || strtolower($calinfo["VCALENDAR"]["VEVENT"]["DTEND"][0]["param"]["VALUE"])=="date")
			$event = "allday";
		else
			$event = "none";

		$uid=$calinfo["VCALENDAR"]["VEVENT"]["UID"][0]["value"];
		$sequence = $calinfo["VCALENDAR"]["VEVENT"]["SEQUENCE"][0]["value"];
		$rczid = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["param"]["TZID"];

		if($uid!="")
		{
			$aque="select sno from appointments where status='active' AND uid='".addslashes($uid)."' AND parid=0";
			$ares=mysql_query($aque,$db);
			$arow=mysql_fetch_row($ares);
			$aid = $arow[0];
			if($aid=="")
				$aid=0;

			if($aid>0)
			{
				if(strpos("*".$uid,"AKKENICS")<=0)
				{
					if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]!="")
					{
						$odates = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"];
						$temp = $imcParse->extractDateTime($odates,$rczid,$event);
						$rcdates = $temp[0];

						$que="delete from recurrences where ano=$aid AND otime='".$rcdates."'";
						mysql_query($que,$db);

						$que="update appointments set exception = IF(exception='','".$rcdates."',CONCAT(exception,',','".$rcdates."')) where sno=$aid";
						mysql_query($que,$db);

						$aque="select sno from appointments where parid=$aid AND exptime=$rcdates";
						$ares=mysql_query($aque,$db);
						$arow=mysql_fetch_row($ares);
						if($arow[0]!="")
						{
							$que="delete from invite_calendar where appno=".$arow[0];
							mysql_query($que,$db);

							$que="delete from tabappoint_attach where appt_appno=".$arow[0];
							mysql_query($que,$db);

							$que="delete from calendar_notes where appid=".$arow[0];
							mysql_query($que,$db);

							$que="delete from appointments where sno=".$arow[0];
							mysql_query($que,$db);
						}
					}
					else
					{
						$aque="select sno from appointments where sno=$aid OR parid=$aid";
						$ares=mysql_query($aque,$db);
						while($arow=mysql_fetch_row($ares))
						{
							$que="delete from recurrences where ano=".$arow[0];
							mysql_query($que,$db);

							$que="delete from invite_calendar where appno=".$arow[0];
							mysql_query($que,$db);

							$que="delete from tabappoint_attach where appt_appno=".$arow[0];
							mysql_query($que,$db);

							$que="delete from calendar_notes where appid=".$arow[0];
							mysql_query($que,$db);
						}

						$que="delete from appointments where sno=$aid OR parid=$aid";
						mysql_query($que,$db);
					}
				}
				else
				{
					if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]!="")
					{
						$odates = $calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"];
						$temp = $imcParse->extractDateTime($odates,$rczid,$event);
						$rcdates = $temp[0];

						$cque="select sno from appointments where parid=$aid AND exptime=$rcdates";
						$cres=mysql_query($cque,$db);
						$crow=mysql_fetch_row($cres);
						if($crow[0]>0)
							$imcParse->cancelInv($username,$uid,$crow[0],$aid,$rcdates);
					}
					else
					{
						$imcParse->cancelInv($username,$uid,$aid,$aid,"all");
					}
				}
			}

			if($calinfo["VCALENDAR"]["VEVENT"]["RECURRENCE-ID"][0]["value"]=="")
			{
				$uque="update mail_calendar set status='N' where type='REQUEST' AND username='$username' AND uid='$uid' AND sequence < '$sequence'";
				mysql_query($uque,$db);
			}

			$que="update mail_calendar set username='$username', uid='$uid', sequence='$sequence',status='C' where cid=$cid";
			mysql_query($que,$db);
		}
	}

	//Get Attachmnet file type if attachmnet don't have file.
	function get_dynamic_attachment_name($filemimetype)
	{
		$fileext="";
		$Filename=$this->generate_name();

		// create mimetypes array
		$FileExttype=$this->privBuildExtArray();

		// return file extension for mimi type
		if (isset($FileExttype[$filemimetype]))
			$fileext=$FileExttype[$filemimetype];

		return $Filename.".".$fileext;
	}

	//Get Attachmnet file type if attachmnet don't have file.
	function generate_name()
	{
		return substr(md5(microtime()),-8);
	}

	//Prepare  mimetype's file extensions array
	function privBuildExtArray()
	{
		return array(
	  	'application/andrew-inset'=>'ez',
		'application/mac-binhex40'=>'hqx',
		'application/mac-compactpro'=>'cpt',
		'application/msword'=>'doc',
		'application/octet-stream'=>'bin',
		'application/octet-stream'=>'dms',
		'application/octet-stream'=>'lha',
		'application/octet-stream'=>'lzh',
		'application/octet-stream'=>'exe',
		'application/octet-stream'=>'class',
		'application/octet-stream'=>'so',
		'application/octet-stream'=>'dll',
		'application/oda'=>'oda',
		'application/pdf'=>'pdf',
		'application/postscript'=>'ai',
		'application/postscript'=>'eps',
		'application/smil'=>'smil',
		'application/vnd.wap.wbxml'=>'wbxml',
		'application/vnd.wap.wmlc'=>'wmlc',
		'application/vnd.wap.wmlscriptc'=>'wmlsc',
		'application/x-bcpio'=>'bcpio',
		'application/x-cdlink'=>'vcd',
		'application/x-chess-pgn'=>'pgn',
		'application/x-cpio'=>'cpio',
		'application/x-csh'=>'csh',
		'application/x-director'=>'dir',
		'application/x-dvi'=>'dvi',
		'application/x-futuresplash'=>'spl',
		'application/x-gtar'=>'gtar',
		'application/x-hdf'=>'hdf',
		'application/x-javascript'=>'js',
		'application/x-koan'=>'skp',
		'application/x-latex'=>'latex',
		'application/x-netcdf'=>'nc',
		'application/x-netcdf'=>'cdf',
		'application/x-sh'=>'sh',
		'application/x-shar'=>'shar',
		'application/x-shockwave-flash'=>'swf',
		'application/x-stuffit'=>'sit',
		'application/x-sv4cpio'=>'sv4cpio',
		'application/x-sv4crc'=>'sv4crc',
		'application/x-tar'=>'tar',
		'application/x-tcl'=>'tcl',
		'application/x-tex'=>'tex',
		'application/x-texinfo'=>'texi',
		'application/x-troff'=>'t',
		'application/x-troff'=>'tr',
		'application/x-troff'=>'roff',
		'application/x-troff-man'=>'man',
		'application/x-troff-me'=>'me',
		'application/x-troff-ms'=>'ms',
		'application/x-ustar'=>'ustar',
		'application/x-wais-source'=>'src',
		'application/xhtml+xml'=>'xhtml',
		'application/zip'=>'zip',
		'audio/basic'=>'au',
		'audio/basic'=>'snd',
		'audio/midi'=>'mid',
		'audio/midi'=>'kar',
		'audio/mpeg'=>'mp3',
		'audio/x-aiff'=>'aif',
		'audio/x-mpegurl'=>'m3u',
		'audio/x-pn-realaudio'=>'ram',
		'audio/x-pn-realaudio-plugin'=>'rpm',
		'audio/x-realaudio'=>'ra',
		'audio/x-wav'=>'wav',
		'chemical/x-pdb'=>'pdb',
		'chemical/x-xyz'=>'xyz',
		'image/bmp'=>'bmp',
		'image/gif'=>'gif',
		'image/ief'=>'ief',
		'image/jpeg'=>'jpg',
		'image/png'=>'png',
		'image/tiff'=>'tiff',
		'image/tif'=>'tif',
		'image/vnd.djvu'=>'djv',
		'image/vnd.wap.wbmp'=>'wbmp',
		'image/x-cmu-raster'=>'ras',
		'image/x-portable-anymap'=>'pnm',
		'image/x-portable-bitmap'=>'pbm',
		'image/x-portable-graymap'=>'pgm',
		'image/x-portable-pixmap'=>'ppm',
		'image/x-rgb'=>'rgb',
		'image/x-xbitmap'=>'xbm',
		'image/x-xpixmap'=>'xpm',
		'image/x-windowdump'=>'xwd',
		'model/iges'=>'igs',
		'model/iges'=>'iges',
		'model/mesh'=>'msh',
		'model/vrml'=>'vrml',
		'text/css'=>'css',
		'text/html'=>'html',
		'text/plain'=>'txt',
		'text/richtext'=>'rtx',
		'text/rtf'=>'rtf',
		'text/sgml'=>'sgm',
		'text/tab-seperated-values'=>'tsv',
		'text/vnd.wap.wml'=>'wml',
		'text/vnd.wap.wmlscript'=>'wmls',
		'text/x-setext'=>'etx',
		'text/xml'=>'xml',
		'video/mpeg'=>'mpg',
		'video/quicktime'=>'qt',
		'video/quicktime'=>'mov',
		'video/vnd.mpegurl'=>'mxu',
		'video/x-msvideo'=>'avi',
		'video/x-sgi-movie'=>'movie',
		'x-conference-xcooltalk'=>'ice'
		);
	}
	
	//function for degenerat the winmail.dat attachment file
	function parseWinmailAttachment($filename,$WinMailcontent,$last_id,$xmlattachid)
	{
			global $WDOCUMENT_ROOT;
			//Global constants
			$TNEF = TNEF_EXC_PATH."tnef --overwrite --debug";
			//location and filename of the tnef executable and options if you want
			$FILEPROGRAM = TNEF_EXC_PATH."file -b";
			//The program used for determining the file type, additional options
		
			$TEMPDIR_BASE=$WDOCUMENT_ROOT."/WinMail_Degenerator/"; //"/var/www/fs/WinMail_Degenerator/";
			////var_dump(rmdir($TEMPDIR_BASE));
			//Where you want to have your temporary files - read the PHP documentation of tempnam()
			//This must be also the directory where your PHP saves uploaded files
			if(!is_dir($TEMPDIR_BASE))
				mkdir($TEMPDIR_BASE, 0777);
		
			/*==============================Exc===========================*/
			 $tmpdirname = tempnam($TEMPDIR_BASE, "tnefhandler");
			//A unique temporary directory name
			unlink($tmpdirname);
			//tempnam() created the file, however, we need a directory of this name.
			//So the file is deleted first
			mkdir($tmpdirname, 0777);
			//Create this directory so that only the web server can access it

			// Creating the file with mail attachment content
			$filePath=$tmpdirname."/".$filename;  //$filename this original file name
			$WinMail_fp = fopen($filePath,"w");
			fwrite($WinMail_fp, $WinMailcontent);
			chmod($filePath, 0777);
			fclose($WinMail_fp);

			if(is_file($filePath)) :
			//Checking the file is available or not
		  
				chdir($tmpdirname);
				//from now on, everything happens in this directory
				exec("$TNEF $filename");
				//Execute tnef on this winmail.dat. Other files should be created.
				unlink($filename);
				//the winmail.dat is not needed anymore

				$files_Array = array();
				//an array for the filenames extracted from winmail.dat

				$WinDirPath = opendir(".");
				//open the directory.
				while (false!==($ExtfileName = readdir($WinDirPath)))
				{  
						if ($ExtfileName != "." && $ExtfileName != ".." && is_file($ExtfileName))
						{ //This filename is not an unwanted directory entry and this is really a file
			
								$filetype = exec("$FILEPROGRAM ".escapeshellarg($ExtfileName));
								//The output of file(1) - a user-readable description of the file's contents
								$filesize = number_format(filesize($ExtfileName));
								$FileData=file_get_contents($ExtfileName);
								$this->findWinmailAttachType($last_id,$xmlattachid,$FileData,$ExtfileName,$filesize,$filetype,$inline="");
						}
				}
				closedir($WinDirPath);
			endif;
				
	
	}
	
	//function for checking attachment type
	function findWinmailAttachType($last_id,$xmlattachid,$FileData,$fileName,$file_size,$fileType,$inline="")
	{
		global $maildb,$db;
		$FileNameExtenArr = explode(".",$name);
		$fileExtension = $FileNameExtenArr[count($FileNameExtenArr)-1];
		/* Parse ".eml" attachment as a inline attachment. bugid: 3667 ; Dev: Sundar. Dt 20 Nov, 2008 */	
		if($fileType=="message/rfc822" || $FileNameExtenArr=="eml")
		{
			insertMainData($data,$last_id,$xmlattachid);
		}
		else if($fileType=="application/ms-tnef")
		{
			$this->parseWinmailAttachment($fileName,$FileData,$last_id,$xmlattachid);
		}
		else
		{
			$this->saveWinamilAttachments($last_id,$xmlattachid,$FileData,$fileName,$file_size,$fileType,$inline="");
		}
	}
	
	//function for saving attachment into database.
	function saveWinamilAttachments($last_id,$xmlattachid,$FileData,$fileName,$file_size,$fileType,$inline="")
	{
	 	global $maildb,$db;

		$que="insert into mail_attachs (attachid,mailid,filecontent,filename,filesize,filetype,inline) values ('','$last_id','".addslashes($FileData)."','".addslashes($fileName)."','".addslashes($file_size)."','".addslashes($fileType)."','".addslashes($inline)."')";
		mysql_query($que,$maildb);

		if($xmlattachid.".xml"==$fileName)
		{
			$xmlid=mysql_insert_id($maildb);
			$que="update mail_headers set xmlbody='$xmlid' where mailid='$last_id'";
			mysql_query($que,$db);
		}
	}
}
?>
