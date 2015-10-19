<?php
	ini_set("display_errors","0");

	$include_path="/usr/bin/db-driver";
	ini_set("include_path",$include_path);

	require("global.inc");
	require("SyncHR/class.SyncHR.php");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where options.synchr='Y' AND company_info.status='ER' ".$version_clause." ORDER BY company_info.sno";
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);

		require("database.inc");

		$shque="SELECT serial_no,synchr_username,synchr_password,synchr_apiKey,CONCAT(loccode,'_',SUBSTRING(MD5(UNIX_TIMESTAMP()),1,8)) as bcode FROM contact_manage WHERE synchr_apiKey!=''";
		$shres=mysql_query($shque,$db);
		while($shrow=mysql_fetch_assoc($shres))
		{
			if($shrow['synchr_username']!="" && $shrow['synchr_password']!="" && $shrow['synchr_apiKey']!="")
			{
				$synchr=new SyncHR();
				$synchr->apiKey=$shrow['synchr_apiKey'];
				$synchr->bcode=$shrow['bcode'];

				$authData['credentials']['user']=$shrow['synchr_username'];
				$authData['credentials']['password']=$shrow['synchr_password'];
				$authData['credentials']['apiKey']=$shrow['synchr_apiKey'];

				// Employee Data push will consider of pushing Assignments data same time.
				$personsData=$synchr->getPersonsData($shrow['serial_no']);
				for($i=0;$i<count($personsData);$i++)
				{
					$shToken=$synchr->doAuth($authData);
					if($shToken['token']!="")
					{
						$synchr->clear_debug();
						$synchr->token=$shToken['token'];

						$ssn = $synchr->checkPersonIdentity($personsData[$i]['SSN'],"SSN");
						if(trim($ssn['personIdentity'][0]['identity'])=="")
						{
							if($synchr->insertPersonData($personsData[$i]))
							{
								$synchr->updateSyncHRCheck($personsData[$i]['empNo']);
								$synchr->insertPositionData($personsData[$i]);

								if($personsData[$i]['acStatus']=="T")
								{
									$synchr->terminateEmployment($personsData[$i]);
									$synchr->terminatePersonPosition($personsData[$i]);
									$synchr->updatePersonLog($personsData[$i]);
								}
							}
							else
							{
								$synchr->client->res_error.="\n"."Unable to create PersonData in SyncHR. We do not process this further.";
								$synchr->updatePersonPositionStatus($personsData[$i],"F");
							}
						}
						else
						{
							if($synchr->updateSyncHREmpNo($ssn['personIdentity'][0]['empNo'],$personsData[$i]['empNo']))
							{
								$personsData[$i]['empNo']=$ssn['personIdentity'][0]['empNo'];

								if($synchr->checkPersonEmploymentStatus($personsData[$i]))
								{
									$synchr->client->res_error.="\n"."Employee has been Terminated in SyncHR, but not in Akken. We do not process this request further.";
									$synchr->updatePersonPositionStatus($personsData[$i],"F");
								}
								else
								{
									$synchr->updatePerson($personsData[$i]);
									if($personsData[$i]['acStatus']!="T")
									{
										$positionCode=$synchr->checkPersonPositionStatus($personsData[$i]);
										if(!$positionCode)
										{
											if($personPositionData=$synchr->getPersonPositionData($personsData[$i]))
												$synchr->updatePosition($personPositionData,"");
										}
										else
										{
											if($personPositionData=$synchr->getPersonPositionData($personsData[$i]))
												$synchr->updatePosition($personPositionData,$positionCode);
										}
									}
								}
							}
							else
							{
								$synchr->client->res_error.="\n"."We found a matching personData record in SyncHR using SSN, how ever we are not able make a relation between the two systems for the employee record. We do not process this further.";
								$synchr->updatePersonPositionStatus($personsData[$i],"F");
							}
						}
					}
				}
				$synchr->notifyBatchStatus();
			}
		}
	}
?>