<?php
	// Set include folder
	$include_path="/usr/bin/db-driver";
	ini_set("include_path",$include_path);

	require("global.inc");
	require("callemall/CallEmAll.php");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where options.cea='Y' AND company_info.status='ER' ".$version_clause." ORDER BY company_info.sno";
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		require("database.inc");

		$uque="SELECT c.sno, c.username, c.userid FROM ceaUsers c LEFT JOIN users u ON c.username=u.username WHERE u.status!='DA'";
		$ures=mysql_query($uque,$db);
		while($urow=mysql_fetch_row($ures))
		{
			$ceaAcId=$urow[0];
			$username=$urow[1];
			$ceaUserId=$urow[2];

			$ceaObj = new CallEmAll();
			$cpage=1;
			$npage=true;
			$dbtrid=$ceaObj->getTextResponseID();

			while($npage)
			{
				$truri="textresponses?textresponseid~gt~$dbtrid&sortby=textresponseid&page=$cpage";

				$ceaObj = new CallEmAll();
				$c_consumer = new OAuthConsumer($ceaObj->ckey, $ceaObj->csecret, NULL);
				$c_token = new OAuthToken($ceaUserId, NULL);

				$ceaObj->endpoint=str_replace("[[ENDPOINT]]",$truri,$ceaObj->endpoint);
				$reqObj=OAuthRequest::from_consumer_and_token($c_consumer, $c_token, "GET", $ceaObj->endpoint);
				$reqObj->sign_request($sig_method, $c_consumer, $c_token);
				$ceaObj->client->setOAUTHAuthorization($reqObj->to_header());
			
				$data=array();
				$textResponses = $ceaObj->getTextResponses($truri,$data);
				$ceaObj->saveTextResponses($textResponses['Items']);
		
				//print "<PRE>";
				//print_r($textResponses);
				//print "</PRE>";
		
				if(trim($textResponses['Next'])=="")
					$npage=false;
				else
					$cpage++;
			}
		}
	}
?>