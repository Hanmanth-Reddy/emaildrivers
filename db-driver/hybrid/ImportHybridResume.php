<?php
	require_once("globalfun.php");
	require_once("extractResume.php");
	require_once("hybridxmlapi.inc");

	function procHybridResume($resp,$data)
	{
		global $maindb,$db,$posid,$jadid,$bb_timein,$bb_timeout,$WDOCUMENT_ROOT;

		$ique="INSERT INTO hybrid_xml (xmlbody) VALUES ('".addslashes($data)."')";
		mysql_query($ique,$maindb);

		//print "ORIG XML :: $data\n\n\n\n=======\n\n\n\n";

		$JobCode=$posid;

		for($vrc=0;$vrc<count($resp->RetrieveApplicationsResponse);$vrc++)
		{
			$CompanyID=""; $Recruitername=""; $CompanyKey=""; $StatusCodeId="";

			for($i=0;$i<count($resp->RetrieveApplicationsResponse[$vrc]->Advert->CustomField);$i++)
			{
				$cfields = $resp->RetrieveApplicationsResponse[$vrc]->Advert->CustomField[$i]->attributes();
				foreach($cfields as $key => $val)
					$$val = $resp->RetrieveApplicationsResponse[$vrc]->Advert->CustomField[$i];
			}

			$ApplicationTime = strtotime($resp->RetrieveApplicationsResponse[$vrc]->Applicant->ApplicationTime);
			$ApplicationRank = $resp->RetrieveApplicationsResponse[$vrc]->Applicant->Rank;

			if($CompanyID=="" || $Recruitername=="" || $CompanyKey=="" || $StatusCodeId=="")
			{
				print "ERROR :: No CompanyID or CompanyKey or Recruitername or StatusCodeId defined";
			}
			else if($ApplicationTime>=$bb_timein && $ApplicationTime<=$bb_timeout)
			{
				$resname = "";
				$shareType = "ALL";
				$ctype = "Candidate";
				$jobcatid =  "";
				$cand_table = "candidate";
				$jobflag = true;
				$pstatus = "";
				$recruiterid = $Recruitername;
				$status = 'ACTIVE';				

				$fullname = $resp->RetrieveApplicationsResponse[$vrc]->Applicant->Name;

				$hrxmldoc = $resp->RetrieveApplicationsResponse[$vrc]->Applicant->Doc[1];
				if($hrxmldoc!="")
					$ResCandData = HRBResumeParse(base64_decode($hrxmldoc));

				//print "HRXML :: ".base64_decode($hrxmldoc)."\n\n\n\n=======\n\n\n\n\n";

				$origdoc = $resp->RetrieveApplicationsResponse[$vrc]->Applicant->Doc[0];

				$ResCandName = explode("|",$ResCandData['personinfo']);
				$ResCandPostInfo = explode("|",$ResCandData['postalinfo']);

				$resname = ($resname=="") ? $fullname.".doc" : $resname;
				$resume_content = base64_decode($origdoc);

				$fname = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->FirstName,$ResCandName[1]);
				$lname = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->LastName,$ResCandName[3]);
				$name = implode(" ",array_values(array($fname,$mname,$lname)));

				$title = $resp->RetrieveApplicationsResponse[$vrc]->Advert->JobTitle;
				$email = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->Email,$ResCandData['hemail']);
				$wphone = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->ContactTelephone,$ResCandData['wphone']);
				$hphone = getNotNullVal('',$ResCandData['hphone']);
				$cphone = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->Mobile,$ResCandData['mobile']);
				$channelID = $resp->RetrieveApplicationsResponse[$vrc]->Applicant->ChannelId;
				$channelName = $resp->RetrieveApplicationsResponse[$vrc]->Applicant->ChannelName;
				$address1 = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->LocationAddress,$ResCandPostInfo[4]);
				$city = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->LocationCity,$ResCandPostInfo[3]);
				$state = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->LocationCounty,$ResCandPostInfo[2]);
				$country = getCountryCode(getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->LocationCountry,$ResCandPostInfo[0]));
				$zip = getNotNullVal($resp->RetrieveApplicationsResponse[$vrc]->Applicant->LocationPostCode,$ResCandPostInfo[1]);
				if($fname=="" && $lname=="")
				{
					$sname=explode(" ",$fullname);
					$fname=$sname[0];
					$lname=$sname[count($sname)-1];
				}

				$SourcsID = ($channelName=="") ? $channelID : $channelName;

				$newobjective = $ResCandData['objective'];
				$newsummary = $ResCandData['executivesummary'];

				$candStatus = CandidateDupCheck($fname, $lname, $email, $recruiterid);
				if($candStatus['status']=='no' && $candStatus['ex_candid']!='')
				{
				  	$status = 'DUPLICATE';
					$jobflag = false;
				}

				$candPrefStatusVal = getNewManageSno('New','candstatus');
				$managesno = getNewManageSno('BroadBean - Hybrid','candsourcetype');
				$deptid = getHybridOwnerDepartment($recruiterid);

				$query1 = "INSERT INTO ".$cand_table."_list (username, status, cuser, ctime, accessto, ctype, resid, owner, mtime, muser, cl_status) VALUES ('','".$status."','".$recruiterid."',NOW(),'".$shareType."','".$ctype."','','".$recruiterid."',NOW(),'".$recruiterid."','".$candPrefStatusVal."')";
				mysql_query($query1,$db);

				$last_id = mysql_insert_id($db);
				$userlead = "cand".$last_id;

				$qs = "'','".$userlead."','".$fname."','".$mname."','".$lname."','".$email."','".$title."','".$address1."','".$address2."','".$city."','".$state."','".$country."','".$zip."','".$wphone."','".$hphone."','".$cphone."','".$fax."','".$SourcsID."','".$managesno."','".$jobcatid."','".$deptid."'";
				$qs1 = "INSERT INTO ".$cand_table."_general (sno, username, fname, mname, lname, email, profiletitle, address1, address2, city, state, country, zip, wphone, hphone, mobile, fax, cg_source, cg_sourcetype, jobcatid,deptid) VALUES (".$qs.")";
				mysql_query($qs1,$db);

				$qs = "'', '".$userlead."', '".addslashes(trim(str_replace("^|^","\r\n",$newobjective)))."', '".addslashes(trim(str_replace("^|^","\r\n",$newsummary)))."','".$pstatus."'";
				$qs2 = "INSERT INTO ".$cand_table."_prof (sno, username, objective, summary, pstatus) VALUES (".$qs.")"; // removed  search_tags
				mysql_query($qs2,$db);

				$commaVal = "";
				$valueString = "";
				$ChkSkillsCnt = 0;

				$candSkills = addslashes($ResCandData['qualifications']);
				if(trim($candSkills) != "")
				{
					$candSkillsArr = explode("^",$candSkills);
					$sklen = count($candSkillsArr);

					for($j = 0; $j < $sklen; $j++)
					{
						$fdata = array();
						$fdata = explode("|",$candSkillsArr[$j]);

						if($fdata[0] != "" || $fdata[1] != "" || $fdata[2] != "" || $fdata[3] != "")
						{
							$ChkSkillsCnt++;	

							$qs="'','".$userlead."','".$fdata[0]."','".$fdata[1]."','".$fdata[2]."','".$fdata[3]."'";
							$valueString = $valueString.$commaVal."(".$qs.")";
							$commaVal = ",";
						}	
					}
				}

				if($ChkSkillsCnt>0)
				{
					$qs3 = "INSERT INTO ".$cand_table."_skills (sno, username, skillname, lastused, skilllevel, skillyear)  VALUES ".$valueString;
					mysql_query($qs3,$db);
				}	

				$commaVal = "";
				$valueString = "";
				$ChkEduCnt = 0;

				$eduh = explode("^",$ResCandData['edhist']);				
				for($i = 0; $i < count($eduh); $i++)
				{
					$schprogram = array();
					$schprogram = explode("|",$eduh[$i]);
					if($schprogram[0] != "" || $schprogram[1] !="" || $schprogram[2] != "" || $schprogram[3] != "")
					{
						$ChkEduCnt++;

						$qs = "'','".$userlead."','".$schprogram[0]."','','','','".$schprogram[1]."-".$schprogram[2]."','".$schprogram[3]."'";
						$valueString = $valueString.$commaVal."(".$qs.")";
						$commaVal = ",";				
					}
				}

				if($ChkEduCnt > 0)
				{
					$qs4 = "INSERT INTO ".$cand_table."_edu ( sno , username, heducation , educity , edustate, educountry, edudegree_level, edudate) VALUES ".$valueString;
					mysql_query($qs4,$db);
				}

				$commaVal = "";
				$valueString = "";
				$ChkExpCnt = 0;

				$arrdate = array("01"=>"January","02"=>"February","03"=>"March","04"=>"April","05"=>"May","06"=>"June","07"=>"July","08"=>"August","09"=>"September","10"=>"October","11"=>"November","12"=>"December");
				$CandExp = explode("^",addslashes($ResCandData['emphist']));

				if(count($CandExp) > 0)
				{
					for($j = 0; $j < count($CandExp); $j++)
					{
						$fdata = explode("|",$CandExp[$j]);

						if($fdata[0] != "" || $fdata[1] != "")
						{
							$ChkExpCnt++;

							$empsdate = explode("-",$fdata[5]);
							$empedate = explode("-",$fdata[6]);

							if($fdata[5]!="current")
								$sdate = $arrdate[$empsdate[1]]."-".$empsdate[0];
							else
								$sdate = "Present";

							if($fdata[6]!="current")
								$edate = $arrdate[$empedate[1]]."-".$empedate[0];
							else
								$edate = "Present";

							$qs = "'','".$userlead."','".$fdata[0]."','".$fdata[3]."','".$fdata[2]."','".$countrySno3."','".$fdata[1]."','".$sdate."','".$edate."','$fdata[4]'";
							$valueString = $valueString.$commaVal."(".$qs.")";
							$commaVal = ",";
						}
					}
				}

				if($ChkExpCnt>0)
				{
					$qs5 = "INSERT INTO ".$cand_table."_work (sno, username, cname, city, state, country,  ftitle , sdate, edate, wdesc) VALUES ".$valueString;
					mysql_query($qs5,$db);
				}

				$commaVal = "";
				$valueString = "";

				$page8="||||||||";
				$page81=explode("|",addslashes($page8));

				$que = "'','$userlead','".$page81[0]."|".$page81[1]."|".$page81[2]."','".$page81[3]."|".$page81[4]."','$page81[5]','$page81[6]','$page81[7]','$page81[8]'";
				$qs8 = "INSERT INTO ".$cand_table."_pref (sno, username, desirejob, desirestatus, amount, currency, period, desirelocation) VALUES ($que)";
				mysql_query($qs8,$db);

				$temp_filename = md5(time()).rand(1,10000);

				$file = fopen($WDOCUMENT_ROOT."/".$temp_filename, "w");
				fwrite($file, $resume_content);
				fclose($file);

				$file_type=mime_content_type($WDOCUMENT_ROOT."/".$temp_filename);
				unlink($WDOCUMENT_ROOT."/".$temp_filename);

				$qs7 = "INSERT INTO con_resumes(sno, username, res_name, type, status, added, markadd, filetype, filesize, filecontent) VALUES('','".$userlead."','".addslashes($resname)."','con','default','','".addslashes($resname)."','".addslashes($file_type)."','','".addslashes($resume_content)."')";
				mysql_query($qs7, $db);
				$res_id = mysql_insert_id($db);

				$query_upd = "update ".$cand_table."_list set username='".$userlead."',resid='$res_id' where sno='".$last_id."'";
				mysql_query($query_upd,$db);

				updatecand_search($last_id);

				if($JobCode!='' && $jobflag == true)
				{
					if($ApplicationRank=="Unranked")
						ApplyCandidate($JobCode, $last_id, $recruiterid,$ApplicationTime);
					else
						ShortlistCandidate($JobCode, $last_id, $recruiterid,$ApplicationTime);
				}
			}
		}

		if($bb_timeout>0)
		{
			$uque="UPDATE jobboard_access_details SET bb_timeout=$bb_timeout WHERE sno=$jadid";
			mysql_query($uque,$db);
		}

		print "Candidate(s) have been imported successfully.\n";
	}

	function ShortlistCandidate($posid, $candid, $suser,$ApplicationTime)
	{
		global $db;

		$que = "INSERT INTO short_lists (sno, reqid, candid, suser, sdate, source) VALUES ('','$posid','$candid','$suser',FROM_UNIXTIME(".$ApplicationTime."), 'BroadBean - Hybrid')";
		mysql_query($que, $db);
	}

	function ApplyCandidate($posid, $candid, $suser,$ApplicationTime)
	{
		global $db;

		$sqlManageStatus="SELECT sno FROM manage where type='interviewstatus' AND name='Applied'";
		$resManageStatus=mysql_query($sqlManageStatus,$db);
		$resStatus=mysql_fetch_row($resManageStatus);
		$statusNo=$resStatus[0];
		$msgunumber=mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"));

		$sel_cand_app_job="SELECT count(1) FROM candidate_appliedjobs WHERE req_id ='".$posid."' AND candidate_id='".$candid."'";
		$res_cand_app = mysql_query($sel_cand_app_job,$db);
		$candjobsCount = mysql_fetch_row($res_cand_app);
		if($candjobsCount[0] ==  0)
		{
			$que="INSERT INTO candidate_appliedjobs(username,candidate_id,req_id,applied_date,status) VALUES ('','".$candid."','".$posid."',FROM_UNIXTIME(".$ApplicationTime."),'applied')";
			mysql_query($que,$db);

			$que="INSERT INTO resume_status (res_id, req_id, appuser, appdate, status, pstatus, muser, mdate) VALUES('".$candid."','".$posid."','".$suser."',FROM_UNIXTIME(".$ApplicationTime."),'".$statusNo."','A','".$suser."',NOW())";
			mysql_query($que,$db);
	
			$que="INSERT INTO  resume_history(req_id, res_id, appuser, appdate, status, type,  muser, mdate) VALUES ('".$posid."','".$candid."','".$suser."',FROM_UNIXTIME(".$ApplicationTime."),'".$statusNo."','cand','".$suser."',FROM_UNIXTIME(".$ApplicationTime."))";
			mysql_query($que,$db);
	
			$que="INSERT INTO  reqresponse( posid, resumeid, rdate, par_id, username,  sub_status, seqnumber) VALUES('".$posid."','cand".$candid."',FROM_UNIXTIME(".$ApplicationTime."),'-1','".$suser."','A','".$msgunumber."')";
			mysql_query($que,$db);
		}
	}

	function CandidateDupCheck($candFname, $candLname, $candEmailCheck, $username)
	{
		global $db;

		$candidateNameEmail = "";
		$allowduplicates = "";

		$ownerConditionCheckCand = " AND (owner = '$username' OR FIND_IN_SET('$username', accessto )>0 OR accessto = 'ALL')";

		if($candFname!="" && $candLname!="" && $candEmailCheck!="")
		{
			$candidateNameEmail = " AND ((fname='".$candFname."' AND lname='".$candLname."') OR email='".$candEmailCheck."') ";
		}
		else if($candEmailCheck!="" && ($candFname=="" || $candLname==""))
		{
			$candidateNameEmail = " AND email='".$candEmailCheck."' ";
		}
		else if(($candFname!="" || $candLname!="") && $candEmailCheck=="")
		{
			if($candFname!="" && $candLname!="")
				$candidateNameEmail = " AND ((fname='".$candFname."' AND lname='".$candLname."')) ";
			else if($candFname!="")
				$candidateNameEmail = " AND fname='".$candFname."' ";
			else if($candLname!="")
				$candidateNameEmail = " AND lname='".$candLname."' ";
		}

		$chkCrmQue = "select sno FROM candidate_list WHERE status='ACTIVE' $candidateNameEmail $ownerConditionCheckCand";
		$chkCrmRes = mysql_query($chkCrmQue,$db);
		$chkCrmRes_Data = mysql_fetch_row($chkCrmRes); 

		$allowduplicates = 'no';
		$can = array ("status" => $allowduplicates,"ex_candid"=>$chkCrmRes_Data[0] );

		return $can;
	}

	function updatecand_search($cand_sno)
	{
		global $db,$WDOCUMENT_ROOT;

		if($cand_sno=='')
			$cand_sno=0;

		$que="select username,supid,sutype,resid,sno from candidate_list where sno = $cand_sno";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);

		$conusername=$row[0];
		$cand_sno_id=$row[4];
		$cand_table="candidate";
		$conid="cand".$cno;
			
		$query="select sno,username,fname,mname,lname,email,profiletitle,prefix from ".$cand_table."_general where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		$data=mysql_fetch_row($dres);
		$page1=$data[2]."|".$data[3]."|".$data[4]."|".$data[5]."|".$data[6]."|".$data[7];
			
		$query="select ".$cand_table."_general.sno,address1,address2,city,state,country,zip,wphone,hphone,mobile,fax,CONCAT_WS('-',cphone,cmobile,cfax,cemail),other,wphone_extn,hphone_extn,other_extn,cg_source,manage.name from ".$cand_table."_general LEFT JOIN manage ON ".$cand_table."_general.cg_sourcetype = manage.sno and manage.type='candsourcetype' where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		$data=mysql_fetch_row($dres);
		$page2=$data[1]."|".$data[2]."|".$data[3]."|".$data[4]."|".getCountryNameMain($data[5])."|".$data[6]."|".$data[7]."|".$data[8]."|".$data[9]."|".$data[10]."|".$row[2]."-".$row[1]."|".$data[11]."|".$data[12]."|".$data[13]."|".$data[14]."|".$data[15]."|".$data[16]."|".$data[17];
			
		$query="select objective,summary,pstatus,ifother ,addinfo, availsdate, availedate from ".$cand_table."_prof where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		$datapro=mysql_fetch_row($dres);
		$page3=$datapro[0]."|".$datapro[1];

		$page4="";			
		$query="select skillname,lastused,skilllevel,skillyear,sno from ".$cand_table."_skills where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		while($data=mysql_fetch_row($dres))
		{
			if($page4=="")
				$page4=$data[0]."|".$data[1]."|".$data[2]."|".$data[3]."|".$data[4];
			else
				$page4.="^".$data[0]."|".$data[1]."|".$data[2]."|".$data[3]."|".$data[4];
		}

		$page5="";			
		$query="select heducation,educity,edustate,educountry,edudegree_level,edudate from ".$cand_table."_edu where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		while($data=mysql_fetch_row($dres))
		{
			if($page5=="")
				$page5=$data[0]."|".$data[1]."|".$data[2]."|".getCountryNameMain($data[3])."|".$data[4]."|".$data[5];
			else
				$page5.="^".$data[0]."|".$data[1]."|".$data[2]."|".getCountryNameMain($data[3])."|".$data[4]."|".$data[5];
		}

		$page6="";		
		$query="select cname,ftitle,wdesc,sdate,edate,city,state,country,csno,sno from ".$cand_table."_work where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		while($data=mysql_fetch_row($dres))
		{
			if($page6=="")
				$page6=$data[0]."|".$data[1]."|".$data[2]."|".$data[3]."|".$data[4]."|".$data[5]."|".$data[6]."|".getCountryNameMain($data[7])."|".$data[8]."|".$data[9];
			else
				$page6.="^".$data[0]."|".$data[1]."|".$data[2]."|".$data[3]."|".$data[4]."|".$data[5]."|".$data[6]."|".getCountryNameMain($data[7])."|".$data[8]."|".$data[9];
		}
			
		$sql = "select desirejob, desirelocation, desirestatus, resourcetype, wtravle, ptravle, tcomments, wlocate, city, state, country, lcomments, tmax, dmax, ccomments, distributename, amount, currency, period, compcomments, rperiod, rcurrency, pramount, poamount, iramount, ioamount, aramount, aoamount from candidate_pref where username='$conusername'";
		$pres=mysql_query($sql);
		$pdata=mysql_fetch_assoc($pres);
		
		$datapro[2]=$datapro[2]."^".$datapro[3];
			
		$pdata['desirejob']=($pdata['desirejob']=="")?"||":$pdata['desirejob'];
		$pdata['desirestatus']=($pdata['desirestatus']=="")?"|":$pdata['desirestatus'];

		$page7=$pdata['desirejob']."|".$pdata['desirestatus']."|".$pdata['rcurrency']."|".$pdata['rperiod']."|".$pdata['pramount']."|".$pdata['poamount']."|".$pdata['iramount']."|".$pdata['ioamount']."|".$pdata['aramount']."|".$pdata['aoamount']."|".$pdata['desirelocation']."|".$datapro[5]."|".$datapro[6]."|".$datapro[5]."|".$datapro[2]."|".$pdata['wtravle']."|".$pdata['city']."|".$pdata['state']."|".getCountryNameMain($pdata['country'])."|".$pdata['ptravle']."|".$pdata['tcomments']."|".$pdata['wlocate']."|".$pdata['lcomments']."|".$pdata['tmax']."|".$pdata['dmax']."|".$pdata['ccomments']."|".$pdata['amount']."|".$pdata['currency']."|".$pdata['period']."|".$pdata['compcomments']."|".$pdata['resourcetype'];
			
		$ldndis="";
		$ldndis=$pdata['distributename'];

		$query="select pstatus,ifother from ".$cand_table."_prof where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		$data=mysql_fetch_row($dres);
		$page9=$data[0]."|".$data[1]."|".$data[2];

		$page10="";			
		$query="select affcname,affrole,affsdate,affedate from ".$cand_table."_aff where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		while($data=mysql_fetch_row($dres))
		{
			if($page10=="")
				$page10=$data[0]."|".$data[1]."|".$data[2]."|".$data[3];
			else
				$page10.="^".$data[0]."|".$data[1]."|".$data[2]."|".$data[3];
		}

		$query="select addinfo from ".$cand_table."_prof where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		$data=mysql_fetch_row($dres);
		$page111=$data[0];

		$page12="";
		$query="select name,company,title,phone,secondary,mobile,email,rship,csno,sno from ".$cand_table."_ref where username='".$conusername."'";
		$dres=mysql_query($query,$db);
		while($data=mysql_fetch_row($dres))
		{
			if($page12=="")
				$page12=$data[0]."|".$data[1]."|".$data[2]."|".$data[3]."|".$data[4]."|".$data[5]."|".$data[6]."|".$data[7]."|".$data[8]."||".$data[9];
			else
				$page12.="^".$data[0]."|".$data[1]."|".$data[2]."|".$data[3]."|".$data[4]."|".$data[5]."|".$data[6]."|".$data[7]."|".$data[8]."||".$data[9];
		}

		$contents="";
		
		//Candidate Info
		$cand_cinfo = explode("|",$page1);
		for($i=0;$i<count($cand_cinfo);$i++)
		{
			if($i == 5)
			{
				if($cand_cinfo[5]!=0)
					$contents.= getHybridManage($cand_cinfo[5])." ";
			}
			else
			{
				$contents.=$cand_cinfo[$i]." ";
			}
		}
		
		//Contact Info
		$cand_continfo = explode("|",$page2);
		for($i=0;$i<count($cand_continfo);$i++)
		{
			if($i == 10)
			{
				//For getting the suffix for the contact name(i.e Recruiter)
				if($cand_continfo[10]!="")
				{
					$cid = explode("-",$cand_continfo[10]);
					$contents.= getHybridContactName($cid[1])." ";
				}
			}
			else if($i == 11)
			{
				$cont_method = explode("-",$cand_continfo[11]);

				//For Contact Method
				if($cont_method[0]=="true")
					$contents.="Phone ";
				if($cont_method[1]=="true")
					$contents.="Mobile ";
				if($cont_method[2]=="true")
					$contents.="Fax ";
				if($cont_method[3]=="true")
					$contents.="Email ";
			}
			else
			{
				$contents.=$cand_continfo[$i]." ";
			}
		}
		
		//Introduction
		$contents.= str_replace("|"," ",$page3)." ";

		//Skills
		$contents.= str_replace("^"," ",str_replace("|"," ",$page4))." ";

		//Education
		$contents.= str_replace("^"," ",str_replace("|"," ",$page5))." ";
		
		//Experience
		if($page6!="")
		{
			$tok1=explode("^",$page6);
			for($j=0;$j<count($tok1);$j++)
			{
				$cand_exp[$j]=explode("|",$tok1[$j]);
				$contents.=$cand_exp[$j][0]." ".$cand_exp[$j][1]." ".$cand_exp[$j][2]." ".$cand_exp[$j][3]." ".$cand_exp[$j][4]." ".$cand_exp[$j][5]." ".$cand_exp[$j][6]." ".$cand_exp[$j][7]." ";
			}
		}

		//Affiliation
		$contents.= str_replace("^"," ",str_replace("|"," ",$page10))." ";

		//Additioanl Info
		$contents.= $page111;

		//References
		if($page12!="")
		{
			$tok2=explode("^",$page12);
			for($j=0;$j<count($tok2);$j++)
			{
				$cand_ref[$j]=explode("|",$tok2[$j]);
				$contents.=$cand_ref[$j][0]." ".$cand_ref[$j][1]." ".$cand_ref[$j][2]." ".$cand_ref[$j][3]." ".$cand_ref[$j][4]." ".$cand_ref[$j][5]." ".$cand_ref[$j][6]." ".$cand_ref[$j][7]." ";
			}
		}

		//Preferences
		$pagepref = $pagepref=explode("|",$page7);
		$cand_pref="";

		for($i=0;$i<count($pagepref);$i++)
		{
			//For Travel in Preferences
			if($i==18)
			{
				if($pagepref[18] == "true")
					$cand_pref.= "Willing to Travel ";
				else
					$cand_pref.= "Not Willing to Travel ";
			}
			//For Relocate in Preferences
			else if($i==24)
			{
				if($pagepref[24] == "true")
					$cand_pref.= "Willing to Relocate any where ";
				else
					$cand_pref.= "Not Willing to Relocate any where ";
			}
			else
			{
				$cand_pref.= $pagepref[$i]." ";
			}
		}
		
		$contents.= $cand_pref;

		$userlead=$conusername;
		$contents1 = extractResume($row[3]);

		// Inserting profile data and resume data seperately instead of storing it in cand_data in cadidate_list table.
		$search_que="insert into search_data (uid,type,profile_data,resume_data) VALUES ('$cand_sno_id','cand','".addslashes($contents)."','".addslashes($contents1)."')";
		mysql_query($search_que,$db);
	}

 	function getNewManageSno($name,$type)
 	{
 		global $db;

 		$sql="SELECT sno FROM manage WHERE name='".$name."' AND type='".$type."'";
 		$res=mysql_query($sql,$db);
		$fetch=mysql_fetch_row($res);

 		return($fetch[0]);
 	}

	function getHybridManage($sno)
	{
		global $db;

		$manage_sql="select  name from manage where sno=$sno";
		$manage_res=mysql_query($manage_sql,$db);
		$manage_Data=mysql_fetch_row($manage_res);

		return $manage_Data[0];
	}

	function getHybridContactName($contid)
	{
		global $db;

		$cont_sql="select concat_ws(' ',fname,mname,lname) from staffoppr_contact where sno=$contid";
		$cont_res=mysql_query($cont_sql,$db);
		$cont_data=mysql_fetch_row($cont_res);

		return $cont_data[0];
	}

	function getCountryNameMain($countryid)
	{
		global $maindb;

		$cque="SELECT country FROM countries WHERE sno='".$countryid."'";
		$cres=mysql_query($cque,$maindb);
		$crow=mysql_fetch_row($cres);

		return $crow[0];
	}

	function getHybridOwnerDepartment($ownerid)
	{
		global $db;

		$queryDept = "SELECT dept FROM hrcon_compen WHERE ustatus = 'active' AND username = '".$ownerid."'";
		$resDept = mysql_query($queryDept, $db);
		$rowDept = mysql_fetch_assoc($resDept);

		if($rowDept['dept'] != '' && $rowDept['dept'] != 0)
		{
			return $rowDept['dept'];
		}
		else
		{
			$queryDept = "SELECT sno FROM department WHERE deflt = 'Y'";
			$resDept = mysql_query($queryDept, $db);
			$rowDept = mysql_fetch_assoc($resDept);	
			return $rowDept['sno'];			
		}
	}

	function HRBResumeParse($xmlbody)
	{
		$XMLtree=new XMLtree;
		$XMLtree->parseData($xmlbody);
		$ResBodyxmlbodyArr=$XMLtree->showData($XMLtree,$rtitle);

		return $ResBodyxmlbodyArr[1];
	}

	function getNotNullVal($Str,$Str1)
	{
		return ((trim($Str)!='') ? trim($Str) : trim($Str1));
	}
?>