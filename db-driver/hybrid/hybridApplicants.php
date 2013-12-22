<?php
	// Set include folder
	$include_path="/usr/bin/db-driver";
	ini_set("include_path",$include_path);

	require_once("global.inc");
	require_once("broadbean_config.inc");
	require_once("array2xml.inc");
	require_once("ImportHybridResume.php");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where options.jobboard='Y' AND company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$aque="SELECT jad.posid, jad.jobboardacc_id, jad.bb_hybrid_id, jad.bb_timein, jad.bb_timeout, jad.sno FROM jobboard_access_details jad LEFT JOIN jobboard_accounts ja ON jad.jobboardacc_id=ja.sno LEFT JOIN jobboards j ON j.sno=ja.jobboard_id LEFT JOIN posdesc p ON p.posid=jad.posid WHERE j.jobboard_name='hybrid' AND p.status='approve' AND ja.status='ACTIVE' AND jad.status!='RM' AND jad.bb_hybrid_id!=''";
		$ares=mysql_query($aque,$db);
		while($arow=mysql_fetch_row($ares))
		{
			$posid=$arow[0];
			$sourceID = $arow[1];
			$bb_hybrid_id = $arow[2];
			$bb_timein = $arow[3];
			$bb_timeout = $arow[4];
			$jadid = $arow[5];

			if($bb_timeout>0)
			{
				$bb_timein=$bb_timeout;
				$bb_timeout=time();
			}
			else
			{
				$bb_timeout=time();
			}

			print $companyuser." :: ".$posid." :: ".$bb_hybrid_id." :: ".$bb_timein." :: ".$bb_timeout."\n";

			$timefrom = date("Y-m-d",$bb_timein)."T".date("H:i:sO",$bb_timein);
			$timeto = date("Y-m-d",$bb_timeout)."T".date("H:i:sO",$bb_timeout);

			$query="SELECT a.username, a.password, b.jobboard_name, a.company_id FROM jobboard_accounts a LEFT JOIN jobboards b ON b.sno=a.jobboard_id WHERE a.sno=$sourceID";
			$res=mysql_query($query,$db);
			$row = mysql_fetch_row($res);

			$hybrid_api_username = $row[0];
			$hybrid_api_password = $row[1];
			$companyID = trim($row[3]);

			$hybridParam = array();

			$hybridParam['Method'] = "RetrieveApplications";
			$hybridParam['APIKey'] = $hybrid_api_key;

			$hybridParam['Account'] = array();
			$hybridParam['Account']['UserName'] = $hybrid_api_username;
			$hybridParam['Account']['Password'] = $hybrid_api_password;

			$hybridParam['Options'] = array();

			//$hybridParam['Options']['IncludeAddress'] = "true";

			$hybridParam['Options']['Filter']['JobReference'] = $companyuser."-".$posid;
			$hybridParam['Options']['Filter']['Times']['TimeFrom'] = $timefrom;
			$hybridParam['Options']['Filter']['Times']['TimeTo'] = $timeto;

			$hybridParam['Options']['EmbedDocuments']['@attributes'] = array('EncodingType' => 'Base64');
			$hybridParam['Options']['EmbedDocuments']['@value'] = "true";

			$hybridParam['Options']['XMLFormat']['@attributes'] = array('EncodingType' => 'Base64');
			$hybridParam['Options']['XMLFormat']['@value'] = "HR";

			//$hybridParam['Options']['IncludeAddress'] = "True";

			$xml_obj = Array2XML::createXML('AdCourierAPI', $hybridParam);
			$xml_con = $xml_obj->saveXML();

			$post_data = array('xml' => $xml_con);
			$stream_options = array('http' => array('method'  => 'POST','header'  => 'Content-type: application/x-www-form-urlencoded' . "\r\n",'content' =>  http_build_query($post_data)));

			$context  = stream_context_create($stream_options);
			$data=file_get_contents($hybrid_api_url, null, $context);
			$resp = new SimpleXMLElement($data);

			$error = $resp->Failed->Message;
			if($error!="")
			{
				print "ERROR : ".addslashes($error)."\n";
			}
			else
			{
				if(count($resp->RetrieveApplicationsResponse)>0)
					procHybridResume($resp,$data);
				else
					print "No candidates were found for this PULL\n";
			}
		}
	}
?>
