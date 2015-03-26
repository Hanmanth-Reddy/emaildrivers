<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set("include_path",$include_path);

	require("global.inc");
	require("smtp.inc");
	require("html2text.inc");
	require("saveemails.inc");

	$smtp=new smtp_class;
	$smtp->host_name="localhost";
	$smtp->host_port="25";
	$smtp->localhost="localhost";

	/***************
	1) IF THE FILE IS RELEASED TO DEV ROOT THEN
	$dque="select capp_info.comp_id, company_info.company_name from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where capp_info.comp_id='naveend' ".$version_clause; 
	2) IF THE FILE IS RELEASED TO ALPHA ROOT THEN
	$dque="select capp_info.comp_id, company_info.company_name from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where capp_info.comp_id='alphaasd' ".$version_clause;
	3) IF THE FILE IS RELEASED TO PRODUCTION/BETA ROOT THEN
	$dque="select capp_info.comp_id, company_info.company_name from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	*******************/
	
	$dque="select capp_info.comp_id, company_info.company_name from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno LEFT JOIN options ON options.sno=company_info.sno where company_info.status='ER' ".$version_clause;	
	
	$dres=mysql_query($dque,$maindb);
	while($drow=mysql_fetch_row($dres))
	{
		$companyuser=strtolower($drow[0]);
		$companyname = $drow[1];
		require("database.inc");
		
		/*
		* JOB BOARDS: EMAIL NOTIFICATION TO APPLICANT
		* Send an email to an applicant when a job order is created/updated
		*/		
		
		// Required to perform full text search
		require("include/quickSubCandidates.inc");
		
		// Array for holding recent job orders' ids
		$recent_jo_arr = array();
		
		// Array for holding applicants' details coming from Job Boards
		$applicants_arr = array();
		
		// Find all job orders posted/updated within last 5 minutes
		$job_order_query = "SELECT posid FROM posdesc WHERE (UNIX_TIMESTAMP(posted_date) >= UNIX_TIMESTAMP(CURRENT_TIMESTAMP - INTERVAL 5 MINUTE) OR UNIX_TIMESTAMP(refresh_date) >= UNIX_TIMESTAMP(CURRENT_TIMESTAMP - INTERVAL 5 MINUTE)) AND post_job_chk = 'Y'";
		$job_order_rs = mysql_query($job_order_query,$db);		
		while($job_order_row = mysql_fetch_assoc($job_order_rs)){
			array_push($recent_jo_arr, $job_order_row['posid']);
		}
		
		if(count($recent_jo_arr) > 0){
			// Find all applicants coming from job boards [sourcetype = 169 i.e website]
			// Also, all applicants subscribed through talentpool even those with no keywords specified.
			$applicant_search_query = "SELECT							
							api_consultant_list.name,
							api_consultant_general.lname,
							api_consultant_list.email,
							api_consultant_list.jobboards_keywords,
							'' AS country,
							'' AS state,
							'' AS zip,
							'' AS miles
						      FROM api_consultant_list,
							api_consultant_general
							WHERE api_consultant_list.username=api_consultant_general.username
							AND api_consultant_general.sourcetype = 169
							AND api_consultant_list.email != ''
							AND api_consultant_list.jobboards_optin_email = 'Y'
							AND api_consultant_list.jobboards_keywords != ''
						    UNION
						    SELECT
                                                        talentpool_list.fname,
							talentpool_list.lname,
							talentpool_list.email,
							talentpool_list.job_alerts AS jobboards_keywords,
							talentpool_list.country AS country,
							talentpool_list.state AS state,
                                                        talentpool_list.zip AS zip,
                                                        talentpool_list.miles AS miles
						      FROM talentpool_list
                                                        WHERE talentpool_list.email != ''
                                                        AND talentpool_list.jobboards_optin_email = 'Y'";
			
			$applicant_search_rs = mysql_query($applicant_search_query,$db);	
			
			while($applicant_search_row = mysql_fetch_assoc($applicant_search_rs))
			{				
				$applicants_arr['lname'][] = $applicant_search_row['lname'];
				$applicants_arr['email'][] = $applicant_search_row['email'];
				$applicants_arr['jobboards_keywords'][] = $applicant_search_row['jobboards_keywords'];
				$applicants_arr['country'][] = $applicant_search_row['country'];
				$applicants_arr['state'][] = $applicant_search_row['state'];
				$applicants_arr['zip'][] = $applicant_search_row['zip'];
				$applicants_arr['miles'][] = $applicant_search_row['miles'];
			}
			
			$applicants_arr_length = count($applicants_arr['email']);
			for($i=0; $i< $applicants_arr_length; $i++)
			{
				$zipCodesInProximity = "";
				$zipCodesInProximityStr = "";
				$whereCond = "";
				
				// Finds whether keywords saved by applicant coming from job boards matches the job orders' ids posted to website in the past 5 mins -  searches in posdesc.search_data column in DB
				if(!empty($applicants_arr['jobboards_keywords'][$i])){
					$jobboards_searchstr = searchSubResume($applicants_arr['jobboards_keywords'][$i]);
				}else{
					$jobboards_searchstr = "";
				}
				$recent_jo_ids = implode(',',$recent_jo_arr);
				
				// Check if preferences for miles set
				if(!empty($applicants_arr['miles'][$i]) && $applicants_arr['miles'][$i]!=0){
					$zipCodesInProximity = getZipCodeRadius($applicants_arr['zip'][$i],$applicants_arr['miles'][$i],$applicants_arr['country'][$i]);
					$zipCodesInProximityStr = implode(',',$zipCodesInProximity);
					if(!empty($zipCodesInProximityStr)){
						$whereCond = " AND zipcode in (".$zipCodesInProximityStr.")";
					}
				}else{
					// Check if preferences for state set
					if(!empty($applicants_arr['state'][$i])){					
						$whereCond = " AND state LIKE '%".$applicants_arr['state'][$i]."%'";					
					}
					// Check if preferences for country set
					if(!empty($applicants_arr['country'][$i])){					
						$whereCond .= " AND country = '".$applicants_arr['country'][$i]."'";					
					}
				}
				if(!empty($jobboards_searchstr)){
					$jobboards_keywords_match_query = "SELECT posid, postitle, posdesc, requirements, post_job_chk, posted_date, company, location, joblocation FROM posdesc WHERE MATCH (posdesc.search_data) AGAINST ('".$jobboards_searchstr."' IN BOOLEAN MODE) AND posdesc.posid in (".$recent_jo_ids.")";
				}else{
					$jobboards_keywords_match_query = "SELECT posid, postitle, posdesc, requirements, post_job_chk, posted_date, company, location, joblocation FROM posdesc WHERE posdesc.posid in (".$recent_jo_ids.")";
				}
				
				$jobboards_keywords_match_rs = mysql_query($jobboards_keywords_match_query,$db);
				while($jobboards_keywords_match_row = mysql_fetch_assoc($jobboards_keywords_match_rs))
				{
					$jo_title = $jobboards_keywords_match_row['postitle'];
					$jo_posteddate = $jobboards_keywords_match_row['posted_date'];
					$jo_job_location = $jobboards_keywords_match_row['joblocation'];
					if($jo_job_location != ""){
						$jo_job_location = "(".$jo_job_location.") ";
					}
					
					// Get Company Name
					$company_name_query = "SELECT cname FROM staffoppr_cinfo WHERE sno = '".$jobboards_keywords_match_row['company']."'";
					$company_name_rs = mysql_query($company_name_query,$db);
					$company_name_row = mysql_fetch_row($company_name_rs);
					$jo_companyname = $company_name_row[0];			
					
					// Get Company Location
					$company_location_query ="select CONCAT(title,' - ',address1,' ',address2,' ',city,' ',state,' ',zipcode) company_location from staffoppr_location where sno='".$jobboards_keywords_match_row['location']."' AND ltype in ('com','loc') AND status='A' ".$whereCond;
					$company_location_rs = mysql_query($company_location_query,$db);
					$company_location_row = mysql_fetch_row($company_location_rs);
					$jo_location = $company_location_row[0];
					
					// Donot send mail alerts if applicant's preferred job location zipcode not within X radius or state and country not matching.
					if(empty($jo_location) && $whereCond != ""){
						break;
					}
					
					// Sends e-mail alert to applicant
					$mailtype = "text/html";
					$from = $companyname." Job Alert <donot-reply@akken.com>";					
					$to = $applicants_arr['email'][$i];
					$subject = $jo_title." ".date('m/d/Y',strtotime($jo_posteddate));
					$message = "Dear ".$applicants_arr['lname'][$i]."\n\n  A new job order matching your skills/preferences has been posted on ".$companyname;
					$message .= "\n\n Please see below for more details".
							"\n\n Job Title: ".$jo_title.
							"\n Company Name: ".$jo_companyname.
							"\n Job Location: ".$jo_job_location.$jo_location;
							
					$unsubscibe_link = "https://".$companyuser.".akken.com/unsubscribe/".strtolower($to);
							
					$message .= "\n\n\n Disclaimer: You have received this mail because you are a registered member on ".$jo_companyname.". This is a system generated email, please don't reply to this message. If you do not want to receive this mailer, <a target=_blank href='".$unsubscibe_link."'>Unsubscribe</a>";
					$message = "<div style='font-family: arial; font-size: 10pt;'>".nl2br($message)."</div>";
					
					$ato = explode(",",$to);
					
					if($to != "")
					{
						$mailheaders=array("Date: $curtime_header","From: $from","To: $to","Subject: $subject","MIME-Version: 1.0");
						$msg_body=prepareBodyA($message,$mailheaders,$mailtype);
						$smtp->SendMessage($from,$ato,$mailheaders,$msg_body);
					}
				}
				
			}
		}
		
		/*
		 * END OF CODE
		 */	
	}
	
	/*
	 *  Finds the nearest zipcodes within proximity of user's specified zipcode and radius preferences
	 */
	function getZipCodeRadius($zipcode,$zipmiles,$zipCountry)
	{
		global $maindb;
		
		$questr = "";
		$zipCodes = array();
		
		// Checks whether Country selected is United States (code = 243) or Canada (code = 42)
		if($zipCountry == "243")
			$questr	= " AND Country='U'";
		else if($zipCountry == "42")
			$questr	= " AND Country='C'";
		else
			$questr = "";
		
		$que = "SELECT Latitude,Longitude FROM zipcodedb WHERE ZipCode = '$zipcode'".$questr;
		$res = mysql_query($que, $maindb);
		$row = mysql_fetch_assoc($res);

		$zipCodeLatitude 	= $row['Latitude'];
		$zipCodeLongitude 	= $row['Longitude'];		
		
		$iDistance = $zipmiles; // user preferred zipcode radius in miles
		//$iRadius = 6371; // earth radius in km
		$iRadius = 3958; // earth radius in miles
		$fLat = $zipCodeLatitude; // user preferred zipcode's latitude
		$fLon = $zipCodeLongitude; // user preferred zipcode's longitude		
		
		$selQuery = "SELECT
				Latitude, Longitude, ZipCode,
				$iRadius * 2 * ASIN(SQRT(POWER(SIN(( $fLat - ABS(Latitude)) * PI() / 180 / 2),2) + COS( $fLat * PI()/180) * COS(ABS(Latitude) * PI() / 180) * POWER(SIN(( $fLon - Longitude) * PI() / 180 / 2), 2) )) AS distance
				FROM zipcodedb
				HAVING distance < $iDistance 
				ORDER BY distance";
				
		$resGeo = mysql_query($selQuery, $maindb);
		while($rowGeo = mysql_fetch_assoc($resGeo))
		{
			$zipCodes[] = $rowGeo['ZipCode'];			
		}
		$zipCodes = array_unique($zipCodes);
		return $zipCodes;			
			      
	}
	
?>