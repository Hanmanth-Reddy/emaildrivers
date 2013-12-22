<?php
	// Set include folder
	$include_path="/usr/bin/db-driver";
	ini_set("include_path",$include_path);

	require_once("global.inc");
	require_once("array2xml.inc");
	require_once("HrWorkCycles.php");

	$hrwc = new HRWorkCycles;

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where options.onboard='Y' AND company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$cque="SELECT hrwork_empcode FROM company_info WHERE hrwork_empcode!=''";
		$cres=mysql_query($cque,$db);
		$crow=mysql_fetch_row($cres);

		$uque="SELECT userid, passwd, username FROM obUsers LIMIT 1";
		$ures=mysql_query($uque,$db);
		$urow=mysql_fetch_row($ures);

		$HWEMPCODE = $crow[0];
		$HWUSERNAME = $urow[0];
		$HWPASSWD = $urow[1];
		$username = $urow[2];

		$sque="SELECT * FROM (SELECT CONCAT('emp',e.sno) as username, p.ssn as ssn, e.name as name, e.sno as sno,'emp' as type FROM emp_list e LEFT JOIN hrcon_personal p ON e.username=p.username WHERE p.ustatus='active' AND p.ssn!='' AND e.pobstatus='Completed' UNION SELECT e.username as username, p.ssn as ssn, e.name as name, e.serial_no as sno,'hire' as type FROM applicants e LEFT JOIN consultant_personal p ON e.username=p.username WHERE p.ssn!='' AND e.pobstatus='Process Completed') obComp";
		$sres=mysql_query($sque,$db);
		while($srow=mysql_fetch_row($sres))
		{
			$edocs=0;

			$con_id = $srow[0];
			$empssn = $hrwc->getSSN($srow[1]);
			$empname = $srow[2];
			$eid = $srow[3];
			$obfrom = $srow[4];

			$hrwc->api_empcode = $HWEMPCODE;
			$hrwc->api_username = $HWUSERNAME;
			$hrwc->api_password = $HWPASSWD;
			$hrwc->emp_ssn = $empssn;

			print $hrwc->api_empcode." :: ".$hrwc->api_username." :: ".$hrwc->api_password." :: ".$hrwc->emp_ssn."\n";

			$resp = $hrwc->docsResponse();
			if($hrwc->docsParse($resp))
			{
				$docsInfo = $hrwc->docsIDs($resp);
				for($i=0;$i<count($docsInfo);$i++)
				{
					$doc_id = $docsInfo[$i]['id'];
					if($doc_id>0)
					{
						$cque="SELECT COUNT(1) FROM hrwork_docs WHERE doc_id = $doc_id";
						$cres=mysql_query($cque,$db);
						$crow=mysql_fetch_row($cres);
						if($crow[0]==0)
						{
							$hrwc->doc_id = $doc_id;
							$resp = $hrwc->docResponse();
							if($hrwc->docParse($resp))
							{
								$doc_code = $docsInfo[$i]['doc_cd'];
								$content = $hrwc->docContent($resp);

								$dque="INSERT INTO hrwork_docs (doc_id,doc_code,doc_type,doc_mimetype,doc_size,doc_name,doc_desc,doc_rdate,doc_empid,doc_content) VALUES ('".$docsInfo[$i]['id']."','".$docsInfo[$i]['doc_cd']."','".$docsInfo[$i]['doc_type']."','".$docsInfo[$i]['mimetype']."','".$docsInfo[$i]['file_size']."','".$docsInfo[$i]['name']."','".$docsInfo[$i]['description']."','".strtotime($docsInfo[$i]['revision_dt'])."','".$hrwc->emp_ssn."','".addslashes($content)."')";
								mysql_query($dque,$db);
								$lid=mysql_insert_id($db);

								if($lid>0)
								{
									$tque="SELECT title FROM hrwork_doc_codes WHERE code='$doc_code'";
									$tres=mysql_query($tque,$maindb);
									$trow=mysql_fetch_row($tres);
									if($trow[0]=="")
										$doc_title = "UNKNOWN";
									else
										$doc_title = $trow[0];

									$idque = "INSERT INTO contact_doc SELECT '','$con_id','$username','".$doc_title."','".$doc_title.".pdf',doc_content,'On-Boarding Document',NOW(),doc_mimetype FROM hrwork_docs WHERE doc_id=$doc_id";
									mysql_query($idque,$db);
									$did = mysql_insert_id($db);

									$icque = "INSERT INTO cmngmt_pr values ('','$con_id','$username','$did','Document',NOW(),'".$doc_title."','$username','On-Boarding','','','','','','')";
									mysql_query($icque,$db);

									$edocs++;
								}
							}
						}
					}
				}
			}

			if($edocs>0)
			{
				if($obfrom=="hire")
					$uque="UPDATE applicants SET pobstatus = 'POB Completed' WHERE serial_no=$eid";
				else
					$uque="UPDATE emp_list SET pobstatus = 'POB Completed' WHERE sno=$eid";
				mysql_query($uque,$db);
			}
		}
	}
?>