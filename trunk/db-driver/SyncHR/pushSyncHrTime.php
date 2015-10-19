<?php
	ini_set("display_errors","0");

	$include_path="/usr/bin/db-driver";
	ini_set("include_path",$include_path);

	require("global.inc");
	require("SyncHR/class.SyncHR.php");

	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where capp_info.comp_id='inforlinx' AND options.synchr='Y' AND company_info.status='ER' ".$version_clause." ORDER BY company_info.sno";
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);

		require("database.inc");

		$shque="SELECT serial_no,synchr_username,synchr_password,synchr_apiKey FROM contact_manage WHERE synchr_apiKey!=''";
		$shres=mysql_query($shque,$db);
		while($shrow=mysql_fetch_assoc($shres))
		{
			if($shrow['synchr_username']!="" && $shrow['synchr_password']!="" && $shrow['synchr_apiKey']!="")
			{
				$synchr=new SyncHR();
				$synchr->apiKey=$shrow['synchr_apiKey'];

				$timeControlBatchData=$synchr->getTimeControlBatchData($shrow['serial_no']);
				for($i=0;$i<count($timeControlBatchData);$i++)
				{
					$authData['credentials']['user']=$shrow['synchr_username'];
					$authData['credentials']['password']=$shrow['synchr_password'];
					$authData['credentials']['apiKey']=$shrow['synchr_apiKey'];
	
					$shToken=$synchr->doAuth($authData);
					if($shToken['token']!="")
					{
						$synchr->token=$shToken['token'];

						/************ DELETING EXISTING BATCHES *****************/
						/*
						$tcBatch = $synchr->checkTimeControlBatchStatus($timeControlBatchData[$i]['payUnitCode'],"payTimeControlBatch");
						if(count($tcBatch['payTimeControlBatch'])>0)
						{
							for($b=0;$b<count($tcBatch['payTimeControlBatch']);$b++)
								$synchr->deletePayTimeControlBatch($tcBatch['payTimeControlBatch'][$b]);
						}
						*/
						/************ DELETING EXISTING BATCHES ENDS *****************/

						$synchr->createPayTimeControlBatch($timeControlBatchData[$i]);
						if($synchr->client->res_status)
						{
							$synchr->updatePayTimeControlBatchStatus($timeControlBatchData[$i],"Y");

							$personTimeData=$synchr->getPersonTimeData($timeControlBatchData[$i]['sno'],$timeControlBatchData[$i]['payUnitCode'],$timeControlBatchData[$i]['payTimeControlBatchCode'],$timeControlBatchData[$i]['periodEndDate'],$timeControlBatchData[$i]['payProcessType']);
							for($j=0;$j<count($personTimeData);$j++)
							{
								$synchr->createPersonTime($personTimeData[$j]);
								if($synchr->client->res_status)
									$synchr->updatePersonTimeStatus($personTimeData[$j],$timeControlBatchData[$i]['payTimeControlBatchCode'],"Y");
								else
									$synchr->updatePersonTimeStatus($personTimeData[$j],$timeControlBatchData[$i]['payTimeControlBatchCode'],"F");
							}
						}
						else
						{
							$synchr->updatePayTimeControlBatchStatus($timeControlBatchData[$i],"F");
						}

						$synchr->notifyTimeBatchStatus($timeControlBatchData[$i]['sno']);
					}
				}
			}
		}
	}
?>