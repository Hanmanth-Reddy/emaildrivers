<?php 
	while (@ob_end_flush());
	// Set include folder	
	$include_path=dirname(__FILE__);
	ini_set('memory_limit',-1);
	ini_set("max_execution_time", -1);
	ini_set('display_errors', 0);	
	ini_set("include_path",$include_path);
	$DOCUMENT_ROOT = '';
	if($_GET['companyuser']!='')
	{
		$companyuser=strtolower($_GET['companyuser']);
	}else
	{
		echo "Error - The selected company is not configured.";
		exit;
	}
	require("../global.inc");
	require("SphinxXMLFeed.php");
	
	require("../maildatabase.inc");
	require("../database.inc");
	
	$mode = $_GET['mode'];
	
	$mysqli = new mysqli($int_master_db[0],$db_user,$db_pass,$companyuser);
	//Output any connection error
	if ($mysqli->connect_error) {
		die('Error : ('. $mysqli->connect_errno .') '. $mysqli->connect_error);
	}
	function cleentext($resumeData,$nullCase='no',$replaceLR='no')
	{
			$resumeData = str_replace(array(">","<","'",'"','|','-','^'),array("","","",'','',' ',''),$resumeData);
			$resumeData = preg_replace("/[\\x00-\\x1F\\x80-\\xFF]/", " ", $resumeData);
			$resumeData = strip_tags($resumeData);
			$resumeData = preg_replace('/[^A-Za-z0-9\. -]/', ' ', $resumeData);
			$resumeData = trim($resumeData);
			$resumeData = preg_replace('/\s+/', ' ',$resumeData);
			
			if($nullCase=="yes" && $resumeData==''){ $resumeData = 'null';}
			if($replaceLR=="yes"){ 
				$resumeData = str_replace(' ','_',$resumeData);
				$resumeData = '_'.$resumeData.'_';
			}
			return $resumeData;
	}
	
	function getZipcodeLatLongitude($zipcode)
	{
		global $maindb;
		$que = '';
		$latlongCodes = '';  $latitude = ''; $longitude = '';
		if($zipcode!="")
		{
			$que = "SELECT RADIANS(Latitude) latitude, RADIANS(Longitude) longitude FROM zipcodedb WHERE ZipCode='".trim($zipcode)."'  ";
			$res = mysql_query($que, $maindb);
			if(mysql_num_rows($res)!=0)
			{
				$row = mysql_fetch_assoc($res);
				$latitude = $row["latitude"];
				$longitude = $row["longitude"];
			}
			if($latitude!='' && $longitude!='')
			{
				$latlongCodes = $latitude."#".$longitude;
			}else
			{
				$latlongCodes = '';
			}
		}
		return $latlongCodes;
	}
	
	function getAreaCodeLatLongitude($areacode)
	{
		global $maindb;
		$que = '';
		$ArealatlongCodes = '';  $latitude = ''; $longitude = '';
		if($areacode!="")
		{
			$que = "SELECT (MAX(Latitude)-((MAX(Latitude)-MIN(Latitude))/2)) AS latitude, (MAX(Longitude)-((MAX(Longitude)-MIN(Longitude))/2)) AS longitude FROM zipcodedb WHERE AreaCode='".trim($areacode)."' AND Longitude <> 0 AND Latitude <> 0";
			$res = mysql_query($que, $maindb);
			if(mysql_num_rows($res)!=0)
			{
				$rows = mysql_fetch_assoc($res);
				$latitude = $rows["latitude"];
				$longitude = $rows["longitude"];
			}
			if($latitude!='' && $longitude!='')
			{
				$ArealatlongCodes = $latitude."#".$longitude;
			}else
			{
				$ArealatlongCodes = '';
			}
		}
		return $ArealatlongCodes;
	}
	
		$setQry = "SET SESSION group_concat_max_len=1073741824";
		mysql_query($setQry);
		
		if($mode=="main"){
			//Activated the New UDF filter columns
			$udfupdatstatus = mysql_query("UPDATE udf_form_details SET sphinx_indexing=1, sphinx_searchable=1 WHERE module=4 and element!='textarea' AND status='Active' AND sphinx_indexing=0",$db);
		}
		
		//Get Active UDF filter items
		$sqludf_columns = mysql_query("SELECT CONCAT_WS('_','cust',id) AS udf_colums, element_lable AS udf_dbcolums, CONCAT_WS('=>',element_lable,element) AS udf_elements, element FROM udf_form_details WHERE sphinx_indexing=1 AND sphinx_searchable=1 AND element!='textarea' AND `status`='Active' AND module=4", $db);
			$set_udf_string_columns = array();
			$set_udf_numeric_columns = array();
			$set_udf_numeric_mcolumns = array();
			$set_udf_dbcolumns = array();
			$set_udf_elements = array();
			$set_udf_columns =  array();
			if(mysql_num_rows($sqludf_columns)!=0)
			{
				while($getudf_columns = mysql_fetch_array($sqludf_columns))
				{
						if($getudf_columns['element']=='checkbox')
						{
							$set_udf_numeric_mcolumns[] = $getudf_columns['udf_colums'];
						}else if($getudf_columns['element']=='date')
						{
							$set_udf_numeric_columns[] = $getudf_columns['udf_colums'];
						}else
						{	
							$set_udf_string_columns[] = $getudf_columns['udf_colums'];
						}
						$set_udf_columns[] = $getudf_columns['udf_colums'];
						$set_udf_dbcolumns[] = $getudf_columns['udf_dbcolums'];
						$set_udf_elements[$getudf_columns['udf_dbcolums']] = $getudf_columns['element'];
				}				
				
			}		
		
	$getmultiplerates_columns = mysql_fetch_row(mysql_query("SELECT GROUP_CONCAT(CONCAT_WS('_',rateid, 'pay'),',',CONCAT_WS('_', rateid, 'bill') ORDER BY rateid ASC) as mrates FROM multiplerates_master WHERE status='ACTIVE' AND rateid!='rate4' ", $db));
    $get_mrates_columns = $getmultiplerates_columns[0];
	
	$userdefine_m = array(			
			'postitle',
			'jobtype',
			'status_name',
			'cname',
			'jostage_name',
			'category',
			'hrm_deptname',
			'sourcetype_name',
			'education',
			'experience',
			'address',
			'city',
			'state',
			'zip',			
			'country_name',
			'tsapp',					
			'wcomp_code_name',
			'pterms',
			'po_num',
			'bill_deptname',
			'billterms',			
			'wtravle',
			'wlocate',			
			'accessto',	
			'owname',
			'createdby',
			'modifiedby',
			'search_data',
			'notes',
			'label_akken',
		);
	$finalArray_withoutudf = array_merge($userdefine_m,explode(",",$get_mrates_columns));
	
	$finalStringArray = array_merge($finalArray_withoutudf,$set_udf_string_columns);
	$finalNumericArray = array(
		  array('name' => 'snoid', 'type' => 'bigint'),
		  array('name' => 'jobtype_id', 'type' => 'bigint'),
		  array('name' => 'posstatus', 'type' => 'bigint'),
		  array('name' => 'sourcetype', 'type' => 'bigint'),		 
		  array('name' => 'jostage', 'type' => 'bigint'),
		  array('name' => 'catid', 'type' => 'bigint'),
		  array('name' => 'hrm_deptid', 'type' => 'bigint'),
		  array('name' => 'country_id', 'type' => 'bigint'),
		  array('name' => 'start_date', 'type' => 'timestamp'),
		  array('name' => 'end_date', 'type' => 'timestamp'),
		  array('name' => 'due_date', 'type' => 'timestamp'),
		  array('name' => 'owner', 'type' => 'bigint'),
		  array('name' => 'cuser', 'type' => 'bigint'),
		  array('name' => 'muser', 'type' => 'bigint'),
		  array('name' => 'skills', 'type' => 'multi'),
		  array('name' => 's_lastused', 'type' => 'multi'),
		  array('name' => 's_level', 'type' => 'multi'),		 
		  array('name' => 'crc_accessto', 'type' => 'multi'),
		  array('name' => 'zip_latitude', 'type' => 'float'),
		  array('name' => 'zip_longitude', 'type' => 'float'),
		  array('name' => 'role_types', 'type' => 'multi'),
		  array('name' => 'role_persons', 'type' => 'multi'),
		  array('name' => 'role_rates', 'type' => 'multi'),
		  array('name' => 'role_commtype', 'type' => 'multi'), 
		  array('name' => 'ctime', 'type' => 'timestamp'),
		  array('name' => 'mtime', 'type' => 'timestamp'),
		);
		if(count($set_udf_numeric_columns)!=0)
		{
			foreach($set_udf_numeric_columns as $nudfcolumns)
			{
				$finalNumericArray[] = array('name' => $nudfcolumns, 'type' => 'bigint');
			}
		}
		if(count($set_udf_numeric_mcolumns)!=0)
		{
			foreach($set_udf_numeric_mcolumns as $mudfcolumns)
			{
				$finalNumericArray[] = array('name' => $mudfcolumns, 'type' => 'multi');
			}
		}
	$mode = $_GET['mode']; 
	if($mode=="main"){
		mysql_query("REPLACE INTO sph_counter SELECT 'joborders_list',MAX(posid),'joborders',MAX(mdate) FROM posdesc");

		// instantiate the class
		$doc = new SphinxXMLFeed();		
		// set the fields we will be indexing
		$doc->setFields($finalStringArray);
		// set any attributes
		
		$doc->setAttributes($finalNumericArray);
		$doc->beginOutput();
		// or other data source
		$maxidQ = mysql_fetch_array(mysql_query("select max(posid) from posdesc"));
		$maxid = $maxidQ[0];
		//$maxid = 100;
		$range = 1000;
		$x=0; $y=$range;  
		while($x <= $maxid)
		{
			$select_Sql = "SELECT  DISTINCT(job.posid) AS job_id,job.postype AS jobtype_id,t1.name AS jobtype,job.postitle,CONCAT(t4.address1, ' ', t4.address2) AS address,t4.state, t4.city, t4.country AS country_id,t18.country AS country_name,t4.zipcode as zip,t2.cname, job.posstatus,t7.name AS status_name, job.education,job.experience, job.sourcetype,t6.name AS sourcetype_name,job.jostage, t17.name AS jostage_name, job.catid, t16.name AS category, job.deptid AS hrm_deptid,t13.deptname AS hrm_deptname, t5.tsapp, t5.bamount AS regularbillrate, t5.pamount AS regularpayrate,  t5.otbrate_amt AS otbillrate,  t5.otprate_amt AS otpayrate,  t5.double_brate_amt AS dtbillrate,t5.double_prate_amt AS dtpayrate, job.wcomp_code, t10.code AS wcomp_code_name, t5.pterms, t5.po_num, t5.department AS bill_deptname, job.bill_req AS billtermscode,t9.billpay_code AS billterms, t5.wtravle, t5.wlocate, IF(job.no_of_pos - job.closepos < 0,0,job.no_of_pos - job.closepos) openpos,t5.payrollpid, job.service_terms,t5.tmax,t2.directions,t2.dress_code,t2.phone,t2.phone_extn,  t2.culture,t2.parking,t2.smoke_policy,t2.compsummary,t2.tele_policy,(SELECT CONCAT(fname, ' ', lname) bilname FROM staffoppr_contact WHERE staffoppr_contact.sno = job.contact) AS contact_a,job.refcode,(IF(sal_type = 'range',IF(salary = 0 && sal_range_to = 0,'',CONCAT(salary, ' - ', sal_range_to)),IF(salary = 0, '', salary))) AS salary,job.posworkhr, (SELECT buildJobOrderSearch_fun(job.posid)) AS search_data, (SELECT buildJobsNotes_fun(job.posid)) AS notes, UNIX_TIMESTAMP(job.posstartdate) AS start_date, UNIX_TIMESTAMP(job.responsedate) AS end_date,UNIX_TIMESTAMP(job.duedate) AS due_date, job.accessto, CONCAT(IF(job.accessto = 'ALL',CRC32('ALL'),IF(job.accessto='', job.owner, job.accessto))) AS crc_accessto,job.owner, t8.name AS owname,  job.username AS cuser,  t12.name AS createdby,  job.muser,  t14.name AS modifiedby, (SELECT GROUP_CONCAT(DISTINCT(a.sno)) as e_ids FROM req_master AS a JOIN req_skills AS b ON b.skill_name = a.mvalue WHERE a.mtype='skillname' AND b.skill_name != '' AND b.rid=job.posid) as skills, (SELECT GROUP_CONCAT(DISTINCT(a.sno)) as e_ids FROM req_master AS a  JOIN req_skills AS b ON b.last_used = a.mvalue WHERE a.mtype='lastused' AND b.last_used != '' AND b.rid = job.posid) as s_lastused, (SELECT GROUP_CONCAT(DISTINCT(a.sno)) as e_ids FROM req_master AS a JOIN req_skills AS b ON b.skill_level = a.mvalue WHERE a.mtype='skilllevel' AND b.skill_level != '' AND b.rid=job.posid) as s_level, (SELECT GROUP_CONCAT(DISTINCT(roleid)) AS e_ids FROM assign_commission WHERE assignid = job.posid AND assigntype='JO' GROUP BY assignid) as role_types, (SELECT GROUP_CONCAT(DISTINCT(CRC32(person))) AS e_ids FROM assign_commission WHERE assignid = job.posid AND assigntype='JO' GROUP BY assignid) as role_persons, (SELECT GROUP_CONCAT(DISTINCT(CRC32(amount))) AS e_ids FROM assign_commission WHERE assignid = job.posid AND assigntype='JO' GROUP BY assignid) as role_rates, (SELECT GROUP_CONCAT(DISTINCT(comm_calc)) AS e_ids FROM assign_commission WHERE assignid = job.posid AND assigntype='JO' AND comm_calc!='' GROUP BY assignid) as role_commtype, UNIX_TIMESTAMP(job.stime) AS ctime,  UNIX_TIMESTAMP(job.mdate) AS mtime,udf.* FROM posdesc as job LEFT JOIN manage t1 ON job.postype = t1.sno LEFT JOIN staffoppr_cinfo t2 ON job.company = t2.sno LEFT JOIN staffoppr_location t4 ON job.location = t4.sno LEFT JOIN countries t18 ON t18.sno = t4.country LEFT JOIN req_pref t5 ON job.posid = t5.posid LEFT JOIN manage t6 ON job.sourcetype = t6.sno LEFT JOIN manage t7 ON job.posstatus = t7.sno LEFT JOIN users t8 ON job.owner = t8.username LEFT JOIN bill_pay_terms t9 ON job.bill_req = t9.billpay_termsid LEFT JOIN workerscomp t10 ON job.wcomp_code = t10.workerscompid LEFT JOIN users t12 ON job.username = t12.username LEFT JOIN department t13 ON job.deptid = t13.sno LEFT JOIN users t14 ON job.muser = t14.username LEFT JOIN manage t16 ON t16.sno = job.catid LEFT JOIN manage t17 ON t17.sno = job.jostage LEFT JOIN udf_form_details_joborder_values udf ON job.posid = udf.rec_id WHERE job.status IN ('approve', 'Accepted') AND job.posid > $x and job.posid <= $y AND job.posid <= (SELECT max_id FROM sph_counter WHERE counter_id='joborders_list' and module_id='joborders') order by job.posid ";
			$results = $mysqli->query($select_Sql);
			if($results->num_rows!=0)
			{
				while($fetchRes=$results->fetch_assoc())
				{					
		
					if($fetchRes['zip']!='')
					{
						$latandlong = getZipcodeLatLongitude($fetchRes['zip']);
						if($latandlong!='')
						{
							$lalo = explode("#",$latandlong);
							$getlat = $lalo[0];
							$getlong = $lalo[1];
						}else
						{
							$getlat = '';
							$getlong = '';
						}
					}else
					{
						$getlat = '';
						$getlong = '';
					}
					
					$regularbillrate =  cleentext($fetchRes['regularbillrate']);
					$regularpayrate =  cleentext($fetchRes['regularpayrate']);
					$otbillrate =  cleentext($fetchRes['otbillrate']);
					$otpayrate =  cleentext($fetchRes['otpayrate']);
					$dtbillrate =  cleentext($fetchRes['dtbillrate']);
					$dtpayrate =  cleentext($fetchRes['dtpayrate']);					
					
					
					$joborderrates_sql = mysql_query("SELECT CONCAT_WS('_',ratemasterid, REPLACE(ratetype,'rate','')) AS rate_type, rate FROM multiplerates_joborder  WHERE  joborderid='".$fetchRes['job_id']."' AND jo_mode='joborder' AND ratemasterid!='rate4' AND status='ACTIVE' ", $db);
					$ratepair =   explode(",",$get_mrates_columns);
					$ratesNewarray1 = array();
					foreach ($ratepair as $ratepairs)
					{
						$ratesNewarray1[$ratepairs] = '0.00';
					}
					$ratesNewarray2 = array();
					if(mysql_num_rows($joborderrates_sql)!=0)
					{
						while($fetchrates = mysql_fetch_array($joborderrates_sql))
						{
							$ratesNewarray2[$fetchrates['rate_type']] = $fetchrates['rate'];
						}
					}
					$ratesNewarray = array_merge($ratesNewarray1,$ratesNewarray2);
					
					$udfpair 		=   $set_udf_columns;
					$udfdbpair 		=   $set_udf_dbcolumns;					
					$udfNewarray1 = array();
					if(!empty($set_udf_columns) && count($udfpair)!=0)
					{
						foreach ($udfpair as $pairid=>$udfpairs)
						{
							$getudfval = 'null';
							if($set_udf_elements[$udfdbpair[$pairid]]=="date")
							{
								$getudfval = '';
								$getudfval = strtotime($fetchRes[$udfdbpair[$pairid]]);
							}else if($set_udf_elements[$udfdbpair[$pairid]]=="checkbox")
							{	
								$getudfval = '';
								if(!empty($fetchRes[$udfdbpair[$pairid]]))
								{
									$getElementName = mysql_fetch_array(mysql_query("SELECT element_lable FROM udf_form_details WHERE CONCAT_WS('_','cust',id)='".$udfpairs."'", $db));
									
									$buildcrc32_sql = "SELECT CONCAT(\"CRC32('\",REPLACE(".$getElementName['element_lable'].",\",\",\"'),CRC32('\"),\"')\") FROM udf_form_details_joborder_values WHERE rec_id=".$fetchRes['job_id'];
									
									$udf_checkbox_items_without_crc32 = mysql_fetch_array(mysql_query($buildcrc32_sql, $db));
									
									$convertcrc32_sql = "SELECT CONCAT_WS (',',".$udf_checkbox_items_without_crc32[0].") as optionslist ";
									
									$udf_checkbox_items_with_crc32 = mysql_fetch_array(mysql_query($convertcrc32_sql, $db));
									 
									$getudfval =  $udf_checkbox_items_with_crc32['optionslist'];
								}
							}else
							{
								$getudfval = cleentext($fetchRes[$udfdbpair[$pairid]],'yes','yes');
							}
							$udfNewarray1[$udfpairs] = $getudfval;
						}
					}
					$udfNewarray2 = array();					
					$udfNewarray = array_merge($udfNewarray1,$udfNewarray2);
					
					$masterJobData = '';
					$masterJobData = array(
						'id' => $fetchRes['job_id'],
						'snoid' => $fetchRes['job_id'],					
						'search_data' => cleentext($fetchRes['search_data']),
						'notes' => cleentext($fetchRes['notes']),
						'accessto' => cleentext($fetchRes['accessto']),
						'postitle' => cleentext($fetchRes['postitle'],'yes','yes'),
						'cname' => cleentext($fetchRes['cname'],'yes','yes'),						
						'jobtype_id' => $fetchRes['jobtype_id'],
						'jobtype' => cleentext($fetchRes['jobtype'],'yes'),						
						'posstatus' => $fetchRes['posstatus'],
						'status_name' => cleentext($fetchRes['status_name'],'yes'),						
						'jostage' => $fetchRes['jostage'],
						'jostage_name' => cleentext($fetchRes['jostage_name'],'yes'),						
						'sourcetype' => $fetchRes['sourcetype'],
						'sourcetype_name' => cleentext($fetchRes['sourcetype_name'],'yes'),						
						'catid' => $fetchRes['catid'],
						'category' => cleentext($fetchRes['category'],'yes'),						
						'hrm_deptid' => $fetchRes['hrm_deptid'],
						'hrm_deptname' => cleentext($fetchRes['hrm_deptname'],'yes'),						
						'country_id' => $fetchRes['country_id'],
						'country_name' => cleentext($fetchRes['country_name'],'yes'),						
						'owner' => $fetchRes['owner'],
						'owname' => cleentext($fetchRes['owname'],'yes'),
						'cuser' => $fetchRes['cuser'],
						'createdby' => cleentext($fetchRes['createdby'],'yes'),
						'muser' => $fetchRes['muser'],
						'modifiedby' => cleentext($fetchRes['modifiedby'],'yes'),						
						'address' => cleentext($fetchRes['address'],'yes','yes'),
						'city' => cleentext($fetchRes['city'],'yes','yes'),
						'state' => cleentext($fetchRes['state'],'yes','yes'),	
						'zip' => cleentext($fetchRes['zip'],'yes','yes'),
						'label_akken' => '__AKKEN__',					
						'education' => cleentext($fetchRes['education'],'yes','yes'),
						'experience' => cleentext($fetchRes['experience'],'yes','yes'),
						'tsapp' => cleentext($fetchRes['tsapp'],'yes','yes'),
						/*'regularbillrate' => $regularbillrate,
						'regularpayrate' => $regularpayrate,
						'otbillrate' => $otbillrate,
						'otpayrate' => $otpayrate,
						'dtbillrate' => $dtbillrate,
						'dtpayrate' => $dtpayrate,*/
						'wcomp_code_name' => cleentext($fetchRes['wcomp_code_name'],'yes','yes'),
						'pterms' => cleentext($fetchRes['pterms'],'yes','yes'),
						'po_num' => cleentext($fetchRes['po_num'],'yes','yes'),
						'bill_deptname' => cleentext($fetchRes['bill_deptname'],'yes','yes'),
						'billterms' => cleentext($fetchRes['billterms'],'yes','yes'),	
						'wtravle' => cleentext($fetchRes['wtravle'],'yes','yes'),
						'wlocate' => cleentext($fetchRes['wlocate'],'yes','yes'),					
						'crc_accessto' => $fetchRes['crc_accessto'],
						'skills' => $fetchRes['skills'],
						's_lastused' => $fetchRes['s_lastused'],
						's_level' => $fetchRes['s_level'],						
						'zip_latitude' => $getlat,
						'zip_longitude' => $getlong,
						'start_date' => $fetchRes['start_date'],
						'end_date' => $fetchRes['end_date'],
						'due_date' => $fetchRes['due_date'],
						'role_types' => $fetchRes['role_types'],
						'role_persons' => $fetchRes['role_persons'],
						'role_rates' => $fetchRes['role_rates'],
						'role_commtype' => $fetchRes['role_commtype'],
						'ctime' => $fetchRes['ctime'],
						'mtime' => $fetchRes['mtime'],						
					);
					$newJobvalues_withoutudf = array_merge($masterJobData,$ratesNewarray);	
					$newJobvalues = array_merge($masterJobData,$udfNewarray);
					$doc->addDocument($newJobvalues);
				}
			}
			$x=$y;
			$y=$y+$range;
			
			// Frees the memory associated with a result
			$results->free();
		}
		// Render the XML
		$doc->endOutput();
	}else if($mode=="delta"){
		
		//Activated the New UDF filter columns
		$getudfactivestatus = mysql_query("UPDATE sphinx_filter_columns SET status='1' WHERE index_col_name IN (SELECT CONCAT_WS('_','cust',id) FROM udf_form_details WHERE sphinx_indexing=1 AND sphinx_searchable=1 AND module=4 and element!='textarea' AND status='Active')",$db);
		
		// instantiate the class
		$doc = new SphinxXMLFeed();

		// set the fields we will be indexing
		$doc->setFields($finalStringArray);
		// set any attributes
		
		$doc->setAttributes($finalNumericArray);
		$doc->beginOutput();
		// or other data source
		$select_Sql = "SELECT  DISTINCT(job.posid) AS job_id,job.postype AS jobtype_id,t1.name AS jobtype,job.postitle,CONCAT(t4.address1, ' ', t4.address2) AS address,t4.state, t4.city, t4.country AS country_id,t18.country AS country_name,t4.zipcode as zip,t2.cname, job.posstatus,t7.name AS status_name, job.education,job.experience, job.sourcetype,t6.name AS sourcetype_name,job.jostage, t17.name AS jostage_name, job.catid, t16.name AS category, job.deptid AS hrm_deptid,t13.deptname AS hrm_deptname, t5.tsapp, t5.bamount AS regularbillrate, t5.pamount AS regularpayrate,  t5.otbrate_amt AS otbillrate,  t5.otprate_amt AS otpayrate,  t5.double_brate_amt AS dtbillrate,t5.double_prate_amt AS dtpayrate, job.wcomp_code, t10.code AS wcomp_code_name, t5.pterms, t5.po_num, t5.department AS bill_deptname, job.bill_req AS billtermscode,t9.billpay_code AS billterms, t5.wtravle, t5.wlocate, IF(job.no_of_pos - job.closepos < 0,0,job.no_of_pos - job.closepos) openpos,t5.payrollpid, job.service_terms,t5.tmax,t2.directions,t2.dress_code,t2.phone,t2.phone_extn,  t2.culture,t2.parking,t2.smoke_policy,t2.compsummary,t2.tele_policy,(SELECT CONCAT(fname, ' ', lname) bilname FROM staffoppr_contact WHERE staffoppr_contact.sno = job.contact) AS contact_a,job.refcode,(IF(sal_type = 'range',IF(salary = 0 && sal_range_to = 0,'',CONCAT(salary, ' - ', sal_range_to)),IF(salary = 0, '', salary))) AS salary,job.posworkhr, (SELECT buildJobOrderSearch_fun(job.posid)) AS search_data, (SELECT buildJobsNotes_fun(job.posid)) AS notes, UNIX_TIMESTAMP(job.posstartdate) AS start_date, UNIX_TIMESTAMP(job.responsedate) AS end_date,UNIX_TIMESTAMP(job.duedate) AS due_date, job.accessto, CONCAT(IF(job.accessto = 'ALL',CRC32('ALL'),IF(job.accessto='', job.owner, job.accessto))) AS crc_accessto,job.owner, t8.name AS owname,  job.username AS cuser,  t12.name AS createdby,  job.muser,  t14.name AS modifiedby, (SELECT GROUP_CONCAT(DISTINCT(a.sno)) as e_ids FROM req_master AS a JOIN req_skills AS b ON b.skill_name = a.mvalue WHERE a.mtype='skillname' AND b.skill_name != '' AND b.rid=job.posid) as skills, (SELECT GROUP_CONCAT(DISTINCT(a.sno)) as e_ids FROM req_master AS a  JOIN req_skills AS b ON b.last_used = a.mvalue WHERE a.mtype='lastused' AND b.last_used != '' AND b.rid = job.posid) as s_lastused, (SELECT GROUP_CONCAT(DISTINCT(a.sno)) as e_ids FROM req_master AS a JOIN req_skills AS b ON b.skill_level = a.mvalue WHERE a.mtype='skilllevel' AND b.skill_level != '' AND b.rid=job.posid) as s_level, (SELECT GROUP_CONCAT(DISTINCT(roleid)) AS e_ids FROM assign_commission WHERE assignid = job.posid AND assigntype='JO' GROUP BY assignid) as role_types, (SELECT GROUP_CONCAT(DISTINCT(CRC32(person))) AS e_ids FROM assign_commission WHERE assignid = job.posid AND assigntype='JO' GROUP BY assignid) as role_persons, (SELECT GROUP_CONCAT(DISTINCT(CRC32(amount))) AS e_ids FROM assign_commission WHERE assignid = job.posid AND assigntype='JO' GROUP BY assignid) as role_rates, (SELECT GROUP_CONCAT(DISTINCT(comm_calc)) AS e_ids FROM assign_commission WHERE assignid = job.posid AND assigntype='JO' AND comm_calc!='' GROUP BY assignid) as role_commtype, UNIX_TIMESTAMP(job.stime) AS ctime,  UNIX_TIMESTAMP(job.mdate) AS mtime,udf.* FROM posdesc as job LEFT JOIN manage t1 ON job.postype = t1.sno LEFT JOIN staffoppr_cinfo t2 ON job.company = t2.sno LEFT JOIN staffoppr_location t4 ON job.location = t4.sno LEFT JOIN countries t18 ON t18.sno = t4.country LEFT JOIN req_pref t5 ON job.posid = t5.posid LEFT JOIN manage t6 ON job.sourcetype = t6.sno LEFT JOIN manage t7 ON job.posstatus = t7.sno LEFT JOIN users t8 ON job.owner = t8.username LEFT JOIN bill_pay_terms t9 ON job.bill_req = t9.billpay_termsid LEFT JOIN workerscomp t10 ON job.wcomp_code = t10.workerscompid LEFT JOIN users t12 ON job.username = t12.username LEFT JOIN department t13 ON job.deptid = t13.sno LEFT JOIN users t14 ON job.muser = t14.username LEFT JOIN manage t16 ON t16.sno = job.catid LEFT JOIN manage t17 ON t17.sno = job.jostage LEFT JOIN udf_form_details_joborder_values udf ON job.posid = udf.rec_id WHERE job.status IN ('approve', 'Accepted') AND (job.posid > (SELECT max_id FROM sph_counter WHERE counter_id='joborders_list' and module_id='joborders') OR job.mdate > (SELECT last_updated FROM sph_counter WHERE counter_id='joborders_list' and module_id='joborders')) order by job.posid";
		$results = $mysqli->query($select_Sql);
			if($results->num_rows!=0)
			{
				while($fetchRes=$results->fetch_assoc())
				{
				
					if($fetchRes['zip']!='')
					{
						$latandlong = getZipcodeLatLongitude($fetchRes['zip']);
						if($latandlong!='')
						{
							$lalo = explode("#",$latandlong);
							$getlat = $lalo[0];
							$getlong = $lalo[1];
						}else
						{
							$getlat = '';
							$getlong = '';
						}
					}else
					{
						$getlat = '';
						$getlong = '';
					}
				
				
				$regularbillrate =  cleentext($fetchRes['regularbillrate']);
				$regularpayrate =  cleentext($fetchRes['regularpayrate']);
				$otbillrate =  cleentext($fetchRes['otbillrate']);
				$otpayrate =  cleentext($fetchRes['otpayrate']);
				$dtbillrate =  cleentext($fetchRes['dtbillrate']);
				$dtpayrate =  cleentext($fetchRes['dtpayrate']);
					
				/*$role_types = mysql_fetch_array(mysql_query("SELECT GROUP_CONCAT(DISTINCT(roleid)) AS e_ids FROM assign_commission WHERE assignid = '".$fetchRes['job_id']."' AND assigntype='JO' GROUP BY assignid", $db));
				
				$role_persons = mysql_fetch_array(mysql_query("SELECT GROUP_CONCAT(DISTINCT(CRC32(person))) AS e_ids FROM assign_commission WHERE assignid = '".$fetchRes['job_id']."' AND assigntype='JO' GROUP BY assignid", $db));
				
				$role_rates = mysql_fetch_array(mysql_query("SELECT GROUP_CONCAT(DISTINCT(CRC32(amount))) AS e_ids FROM assign_commission WHERE assignid = '".$fetchRes['job_id']."' AND assigntype='JO' GROUP BY assignid", $db));
				
				$role_commtype = mysql_fetch_array(mysql_query("SELECT GROUP_CONCAT(DISTINCT(comm_calc)) AS e_ids FROM assign_commission WHERE assignid = '".$fetchRes['job_id']."' AND assigntype='JO' AND comm_calc!='' GROUP BY assignid", $db));*/
				
				$joborderrates_sql = mysql_query("SELECT CONCAT_WS('_',ratemasterid, REPLACE(ratetype,'rate','')) AS rate_type, rate FROM multiplerates_joborder  WHERE  joborderid='".$fetchRes['job_id']."' AND jo_mode='joborder' AND ratemasterid!='rate4' AND status='ACTIVE' ", $db);
					$ratepair =   explode(",",$get_mrates_columns);
					$ratesNewarray1 = array();
					foreach ($ratepair as $ratepairs)
					{
						$ratesNewarray1[$ratepairs] = '0.00';
					}
					$ratesNewarray2 = array();
					if(mysql_num_rows($joborderrates_sql)!=0)
					{
						while($fetchrates = mysql_fetch_array($joborderrates_sql))
						{
							$ratesNewarray2[$fetchrates['rate_type']] = $fetchrates['rate'];
						}
					}
					$ratesNewarray = array_merge($ratesNewarray1,$ratesNewarray2);
					
					$udfpair 		=   $set_udf_columns;
					$udfdbpair 		=   $set_udf_dbcolumns;					
					$udfNewarray1 = array();
					if(!empty($set_udf_columns) && count($udfpair)!=0)
					{
						foreach ($udfpair as $pairid=>$udfpairs)
						{
							$getudfval = 'null';
							if($set_udf_elements[$udfdbpair[$pairid]]=="date")
							{
								$getudfval = '';
								$getudfval = strtotime($fetchRes[$udfdbpair[$pairid]]);
							}else if($set_udf_elements[$udfdbpair[$pairid]]=="checkbox")
							{	
								$getudfval = '';
								if(!empty($fetchRes[$udfdbpair[$pairid]]))
								{
									$getElementName = mysql_fetch_array(mysql_query("SELECT element_lable FROM udf_form_details WHERE CONCAT_WS('_','cust',id)='".$udfpairs."'", $db));
									
									$buildcrc32_sql = "SELECT CONCAT(\"CRC32('\",REPLACE(".$getElementName['element_lable'].",\",\",\"'),CRC32('\"),\"')\") FROM udf_form_details_joborder_values WHERE rec_id=".$fetchRes['job_id'];
									
									$udf_checkbox_items_without_crc32 = mysql_fetch_array(mysql_query($buildcrc32_sql, $db));
									
									$convertcrc32_sql = "SELECT CONCAT_WS (',',".$udf_checkbox_items_without_crc32[0].") as optionslist ";
									
									$udf_checkbox_items_with_crc32 = mysql_fetch_array(mysql_query($convertcrc32_sql, $db));
									 
									$getudfval =  $udf_checkbox_items_with_crc32['optionslist'];
								}
							}else
							{
								$getudfval = cleentext($fetchRes[$udfdbpair[$pairid]],'yes','yes');
							}
							$udfNewarray1[$udfpairs] = $getudfval;
						}
					}
					$udfNewarray2 = array();					
					$udfNewarray = array_merge($udfNewarray1,$udfNewarray2);
					
					$masterJobData = '';
					$masterJobData = array(
						'id' => $fetchRes['job_id'],
						'snoid' => $fetchRes['job_id'],					
						'search_data' => cleentext($fetchRes['search_data']),
						'notes' => cleentext($fetchRes['notes']),
						'accessto' => cleentext($fetchRes['accessto']),
						'postitle' => cleentext($fetchRes['postitle'],'yes','yes'),
						'cname' => cleentext($fetchRes['cname'],'yes','yes'),						
						'jobtype_id' => $fetchRes['jobtype_id'],
						'jobtype' => cleentext($fetchRes['jobtype'],'yes'),						
						'posstatus' => $fetchRes['posstatus'],
						'status_name' => cleentext($fetchRes['status_name'],'yes'),						
						'jostage' => $fetchRes['jostage'],
						'jostage_name' => cleentext($fetchRes['jostage_name'],'yes'),						
						'sourcetype' => $fetchRes['sourcetype'],
						'sourcetype_name' => cleentext($fetchRes['sourcetype_name'],'yes'),						
						'catid' => $fetchRes['catid'],
						'category' => cleentext($fetchRes['category'],'yes'),						
						'hrm_deptid' => $fetchRes['hrm_deptid'],
						'hrm_deptname' => cleentext($fetchRes['hrm_deptname'],'yes'),						
						'country_id' => $fetchRes['country_id'],
						'country_name' => cleentext($fetchRes['country_name'],'yes'),						
						'owner' => $fetchRes['owner'],
						'owname' => cleentext($fetchRes['owname'],'yes'),
						'cuser' => $fetchRes['cuser'],
						'createdby' => cleentext($fetchRes['createdby'],'yes'),
						'muser' => $fetchRes['muser'],
						'modifiedby' => cleentext($fetchRes['modifiedby'],'yes'),						
						'address' => cleentext($fetchRes['address'],'yes','yes'),
						'city' => cleentext($fetchRes['city'],'yes','yes'),
						'state' => cleentext($fetchRes['state'],'yes','yes'),	
						'zip' => cleentext($fetchRes['zip'],'yes','yes'),
						'label_akken' => '__AKKEN__',					
						'education' => cleentext($fetchRes['education'],'yes','yes'),
						'experience' => cleentext($fetchRes['experience'],'yes','yes'),
						'tsapp' => cleentext($fetchRes['tsapp'],'yes','yes'),
						/*'regularbillrate' => $regularbillrate,
						'regularpayrate' => $regularpayrate,
						'otbillrate' => $otbillrate,
						'otpayrate' => $otpayrate,
						'dtbillrate' => $dtbillrate,
						'dtpayrate' => $dtpayrate,*/
						'wcomp_code_name' => cleentext($fetchRes['wcomp_code_name'],'yes','yes'),
						'pterms' => cleentext($fetchRes['pterms'],'yes','yes'),
						'po_num' => cleentext($fetchRes['po_num'],'yes','yes'),
						'bill_deptname' => cleentext($fetchRes['bill_deptname'],'yes','yes'),
						'billterms' => cleentext($fetchRes['billterms'],'yes','yes'),	
						'wtravle' => cleentext($fetchRes['wtravle'],'yes','yes'),
						'wlocate' => cleentext($fetchRes['wlocate'],'yes','yes'),						
						'crc_accessto' => $fetchRes['crc_accessto'],
						'skills' => $fetchRes['skills'],
						's_lastused' => $fetchRes['s_lastused'],
						's_level' => $fetchRes['s_level'],						
						'zip_latitude' => $getlat,
						'zip_longitude' => $getlong,
						'start_date' => $fetchRes['start_date'],
						'end_date' => $fetchRes['end_date'],
						'due_date' => $fetchRes['due_date'],
						'role_types' => $fetchRes['role_types'],
						'role_persons' => $fetchRes['role_persons'],
						'role_rates' => $fetchRes['role_rates'],
						'role_commtype' => $fetchRes['role_commtype'],
						'ctime' => $fetchRes['ctime'],
						'mtime' => $fetchRes['mtime'],						
					);
					
					$newJobvalues_withoutudf = array_merge($masterJobData,$ratesNewarray);	
					$newJobvalues = array_merge($masterJobData,$udfNewarray);
					$doc->addDocument($newJobvalues);
				}
			}
		/*** Print kill list*/
		$killList_Sql = "SELECT posdesc.posid AS sno FROM posdesc WHERE posdesc.status IN ('deleted') AND (posdesc.posid > (SELECT max_id FROM sph_counter WHERE counter_id='joborders_list' and module_id='joborders') OR posdesc.mdate > (SELECT last_updated FROM sph_counter WHERE counter_id='joborders_list' and module_id='joborders')) order by posdesc.posid";
		$killListRes_Sql = $mysqli->query($killList_Sql);
		if($killListRes_Sql->num_rows!=0)
		{
			$kill_list = array();
			while($fetchIds=$killListRes_Sql->fetch_assoc())
			{	
				$kill_list[] = $fetchIds['sno'];
			}
			$doc->addKillList($kill_list);
		}
		// Render the XML
		$doc->endOutput();
	}
	// close connection 
	$mysqli->close();
?>