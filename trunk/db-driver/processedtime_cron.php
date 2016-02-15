<?php
	
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");	
	        
	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);
	if(mysql_num_rows($dres)>0)
	{
		// Checking the count of Companies
		while($drow=mysql_fetch_row($dres))
		{
			// Fetch the Companies one by one
			$companyuser=strtolower($drow[0]);
			require("maildatabase.inc");
			require("database.inc");
			
			// code for checking customer eCampaigns in processing state
			$status_processing="'CampaignIP-l','PostingIP-l'";
			$query="SELECT DISTINCT users.username FROM users LEFT JOIN mail_headers ON users.username=mail_headers.username WHERE users.usertype!='' AND users.status!='DA' AND mail_headers.folder='outbox' AND mail_headers.status in (".$status_processing.")";
			$queryres=mysql_query($query,$db);
			$previous_processingecampaigncount=mysql_num_rows($queryres);
			// end of code for checking customer eCampaigns in processing state

			if($previous_processingecampaigncount>0){  // condition checking no other ecampaigns processing for the company
			
				//Checking the Mails count
				while($ubrow=mysql_fetch_row($queryres))
				{
					$username=$ubrow[0];

					$olSync="N";
					$mailidList="";
					
					$oque="SELECT plugin_outlook FROM sysuser WHERE username='$username'";
					$ores=mysql_query($oque,$db);
					$orow=mysql_fetch_row($ores);
					$olSync=$orow[0];

					$queid="select mailid,status from mail_headers where folder='outbox' and status in (".$status_processing.") and username='$username'";
					$resid=mysql_query($queid,$db);
					while($rsid=mysql_fetch_row($resid))
					{
						$mailidList.=$rsid[0].",";
						
					}

					$mailidList=substr($mailidList,0,(strlen($mailidList)-1));					

					$que="select a.bccadd,a.fromadd,a.subject,'',a.mailid,a.attach,a.conid,a.status,a.xmltype,a.charset as charset from mail_headers a where a.mailid IN (".$mailidList.")";
					$res=mysql_query($que,$db);
					if(mysql_num_rows($res)>0)
					{
						//Checking the Mails count
						while($row=mysql_fetch_array($res))
						{
						 $mailid=$row[4];
						 $mailstatus=$row[7];
							
							//Current date time 
							$currentdatetime = date("Y-m-d H:i:s"); 
							
							//Query for getting the processed time
							$processedque = "SELECT mailid,status,mail_processedtime FROM recipient_info WHERE mailid='$mailid'";
							$processedexe = mysql_query($processedque,$db);
							$processedres = mysql_fetch_array($processedexe);
							$processedtime = $processedres['mail_processedtime'];
							
							//Checks if processedtime is not Empty in recipient_info table
							if($processedtime != '')
							{
								$date1 = $processedtime;
								$date2 = $currentdatetime; 
								$diff = strtotime($date2) - strtotime($date1);
								$diff_in_hrs = $diff/3600;
								
								//if hours greater than 1 the status gets changed to Campaign 	
								if($diff_in_hrs >= 1)
								{	
									if($mailstatus =='CampaignIP-l'){
										$que="UPDATE mail_headers SET status='Campaign' where mailid='$mailid'";
										mysql_query($que,$db);	
									} 
									else {
										$que="UPDATE mail_headers SET status='Posting' where mailid='$mailid'";
										mysql_query($que,$db);
									}
								}
							}
						}
					}
				}
			
			}
		}
	}			
?>