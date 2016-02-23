<?php 
	while (@ob_end_flush());
	// Set include folder	
	$include_path=dirname(__FILE__);
	ini_set('memory_limit',-1);
	ini_set("max_execution_time", -1);
	error_reporting(E_ALL ^ E_NOTICE);
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
	require("TikaClient.php");
	//$egTikaServer = '192.168.2.134:9000'; /* Dev */
	$egTikaServer = '127.0.0.1:9998'; /* Production */
	$egTikaMimeTypes = '
		text/*
		application/*+xml
		application/xml
		application/vnd.oasis.opendocument.*
		application/vnd.openxmlformats
		application/vnd.ms-*
		application/msaccess
		application/msword
		application/pdf
		application/rtf
		application/x-zip
		application/vnd.openxmlformats-officedocument.*';
	$cli = new TikaClient($egTikaServer, $egTikaMimeTypes, NULL, NULL, true);
	
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
			
				if($latitude!='' && $longitude!='')
				{
					$latlongCodes = $latitude."#".$longitude;
				}else
				{
					$latlongCodes = '';
				}
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
			
				if($latitude!='' && $longitude!='')
				{
					$ArealatlongCodes = $latitude."#".$longitude;
				}else
				{
					$ArealatlongCodes = '';
				}
			}
		}
		return $ArealatlongCodes;
	}
		$setQry = "SET SESSION group_concat_max_len=1073741824";
		mysql_query($setQry);
		
		if($mode=="main"){
			//Activated the New UDF filter columns
			$udfupdatstatus = mysql_query("UPDATE udf_form_details SET sphinx_indexing=1, sphinx_searchable=1 WHERE module=3 and element!='textarea' AND status='Active' AND sphinx_indexing=0",$db);
		}
		
		//Get Active UDF filter items
		$sqludf_columns = mysql_query("SELECT CONCAT_WS('_','cust',id) AS udf_colums, element_lable AS udf_dbcolums, CONCAT_WS('=>',element_lable,element) AS udf_elements, element FROM udf_form_details WHERE sphinx_indexing=1 AND sphinx_searchable=1 AND element!='textarea' AND `status`='Active' AND module=3", $db);
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
		
		$userdefine_m = array('profiletitle',
							'candtype',
							'category',
							'deptname',
							'candidate_status',
							'cl_source_type',
							'owname',
							'ascont_name',
							'createdby',
							'modifiedby',
							'address',
							'city',
							'state',
							'zip',
							'areacode',
							'wareacode',
							'hareacode',
							'mareacode',
							'country_name',			
							'desired_job_location',
							'resourcetype',
							'wtravel_a',
							'wlocate_a',
							'amount',
							'accessto',
							'resume_data',
							'profile_data',
							'notes',
							'label_akken',
						);
	$finalStringArray = array_merge($userdefine_m,$set_udf_string_columns);
	
	$finalNumericArray =  array(
		  array('name' => 'snoid', 'type' => 'bigint'),
		  array('name' => 'crc_candtype', 'type' => 'bigint'),
		  array('name' => 'cl_status', 'type' => 'bigint'),
		  array('name' => 'jobcatid', 'type' => 'bigint'),
		  /*array('name' => 'wareacode', 'type' => 'bigint'),
		  array('name' => 'hareacode', 'type' => 'bigint'),
		  array('name' => 'mareacode', 'type' => 'bigint'),*/
		  array('name' => 'deptid', 'type' => 'bigint'),
		  array('name' => 'cl_sourcetype', 'type' => 'bigint'),
		  array('name' => 'country_id', 'type' => 'bigint'),
		  array('name' => 'owner', 'type' => 'bigint'),
		  array('name' => 'cuser', 'type' => 'bigint'),
		  array('name' => 'muser', 'type' => 'bigint'),
		  array('name' => 'ascontact', 'type' => 'bigint'),
		  array('name' => 'skills', 'type' => 'multi'),
		  array('name' => 's_lastused', 'type' => 'multi'),
		  array('name' => 's_level', 'type' => 'multi'),
		  array('name' => 's_type', 'type' => 'multi'),	
		  array('name' => 'edu_country', 'type' => 'multi'),
		  array('name' => 'edudegree_level', 'type' => 'multi'),
		  array('name' => 'employment', 'type' => 'multi'),
		  array('name' => 'employment_type', 'type' => 'multi'),
		  array('name' => 'employment_city', 'type' => 'multi'),
		  array('name' => 'employment_state', 'type' => 'multi'),
		  array('name' => 'employment_country', 'type' => 'multi'),
		  array('name' => 'contact_method', 'type' => 'multi'),
		  array('name' => 'crc_accessto', 'type' => 'multi'),
		  array('name' => 'short_lists', 'type' => 'multi'),		  
		  array('name' => 'zip_latitude', 'type' => 'float'),
		  array('name' => 'zip_longitude', 'type' => 'float'),
		  array('name' => 'area_latitude', 'type' => 'float'),
		  array('name' => 'area_longitude', 'type' => 'float'),  
		  array('name' => 'warea_latitude', 'type' => 'float'),
		  array('name' => 'warea_longitude', 'type' => 'float'),  
		  array('name' => 'harea_latitude', 'type' => 'float'),
		  array('name' => 'harea_longitude', 'type' => 'float'),  
		  array('name' => 'marea_latitude', 'type' => 'float'),
		  array('name' => 'marea_longitude', 'type' => 'float'),
		  array('name' => 'warea_lat_deg', 'type' => 'float'),
		  array('name' => 'warea_long_deg', 'type' => 'float'), 
		  array('name' => 'harea_lat_deg', 'type' => 'float'),
		  array('name' => 'harea_long_deg', 'type' => 'float'),  
		  array('name' => 'marea_lat_deg', 'type' => 'float'),
		  array('name' => 'marea_long_deg', 'type' => 'float'),
		  array('name' => 'max_salary', 'type' => 'float'),
		  array('name' => 'min_salary', 'type' => 'float'),	
		  array('name' => 'currency', 'type' => 'bigint'),
		  array('name' => 'rperiod', 'type' => 'bigint'),		  
		  array('name' => 'cre_type', 'type' => 'multi'),
		  array('name' => 'cre_name', 'type' => 'multi'),
		  array('name' => 'cre_number', 'type' => 'multi'),
		  array('name' => 'cre_acquireddate', 'type' => 'multi'),
		  array('name' => 'cre_validfrom', 'type' => 'multi'),
		  array('name' => 'cre_validto', 'type' => 'multi'),
		  array('name' => 'cre_country', 'type' => 'multi'),
		  array('name' => 'cre_state', 'type' => 'multi'),
		  array('name' => 'role_types', 'type' => 'multi'),
		  array('name' => 'role_persons', 'type' => 'multi'),
		  array('name' => 'role_rates', 'type' => 'multi'),
		  array('name' => 'role_commtype', 'type' => 'multi'),
		  array('name' => 'crmgroups', 'type' => 'multi'),
		  array('name' => 'availsdate', 'type' => 'timestamp'),	
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
		mysql_query("REPLACE INTO sph_counter SELECT 'candidate_list',MAX(sno),'candidates',MAX(mtime) FROM candidate_list");

		// instantiate the class
		$doc = new SphinxXMLFeed();

		// set the fields we will be indexing
		$doc->setFields($finalStringArray);
		// set any attributes
		
		$doc->setAttributes($finalNumericArray);
		$doc->beginOutput();
		// or other data source
		$maxidQ = mysql_fetch_array(mysql_query("select max(sno) from candidate_list"));
		$maxid = $maxidQ[0];
		//$maxid = 100;
		$range = 1000;
		$x=0; $y=$range;  
		while($x <= $maxid)
		{
			$select_Sql = "SELECT DISTINCT(cl.sno),cl.username,cl.profiletitle,cl.fname,cl.lname,cl.mname,cl.nickname,cl.recentemp as company,cl.city,cl.state,cl.zip,cl.areacode,cl.wareacode,cl.hareacode,cl.mareacode,cl.country as country_id,t10.country as country_name,concat(cl.address1, ' ', cl.address2) as address,CONCAT( IF(t6.cphone = 'TRUE', '1001,',''),IF(t6.cmobile = 'TRUE', '2002,',''),IF(t6.cfax = 'TRUE', '3003,',''),IF(t6.cemail = 'TRUE', '4004','')) as contact_method, cl.ctype as candtype,CRC32(cl.ctype) as crc_candtype,cl.cl_status,t1.name as candidate_status,cl.accessto,CONCAT(IF(cl.accessto='ALL',CRC32('ALL'),IF(cl.accessto='', cl.owner, cl.accessto))) AS crc_accessto,IF(cl.accessto='ALL','Public',IF(cl.accessto=cl.owner,'Private','Share')) as shrtype, UNIX_TIMESTAMP(trim(t2.availsdate)) as availsdate,cl.cl_source,cl.cl_sourcetype, t3.name as cl_source_type,t2.addinfo,CONCAT(t4.min_salary,' to ',t4.max_salary) AS amount,t4.min_salary,t4.max_salary,CRC32(t4.currency) as currency,CRC32(t4.rperiod) as rperiod,t4.tmax,t4.distributename,cl.deptid,t7.deptname,t4.desirejob as desired_job_type, t4.desirestatus as desired_job_status,t4.desirelocation as desired_job_location,t4.aramount,t4.iramount,t2.availedate,t2.objective,t4.pramount,cl.jobcatid,t8.name as category,t2.pstatus, IF(t4.wlocate = 'true', 'Yes', 'No') AS wlocate_a, IF(t4.resourcetype = 'true^false', 'Independent Contractor', IF(t4.resourcetype = 'false^true', 'Payrolled Employee', IF(t4.resourcetype = 'true^true', 'Independent Contractor,Payrolled Employee',IF(t4.resourcetype = 'false^false', '','' )))) AS resourcetype,t2.search_tags, t2.summary, IF(t4.wtravle = 'true', 'Yes', 'No') AS wtravel_a, t5.sno as employee_id, cl.owner as owner, t11.name as owname, cl.cuser, t12.name as createdby, cl.muser, t13.name as modifiedby, IF(cl.contact_id!='', 1, 0)  AS ascontact, (SELECT buildCandSearch_fun(cl.sno)) AS profile_data,(SELECT buildCandNotes_fun(cl.sno)) AS notes,t9.sno AS doc_id,t9.res_name AS filename,t9.filetype,t9.filecontent AS content,(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM candidate_master AS a JOIN candidate_skills AS b ON b.skillname = a.mvalue WHERE a.mtype='skillname' AND b.skillname != '' AND b.username=cl.username) AS skills,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM candidate_master AS a  JOIN candidate_skills AS b ON b.lastused = a.mvalue WHERE a.mtype='lastused' AND b.lastused != '' AND b.username = cl.username) AS s_lastused,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM candidate_master AS a JOIN candidate_skills AS b ON b.skilllevel = a.mvalue WHERE a.mtype='skilllevel' AND b.skilllevel != '' AND b.username=cl.username) AS s_level,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM candidate_master AS a JOIN candidate_skills AS b ON IF(b.manage_skills_id=0,'Parsed','Managed')=a.mvalue WHERE a.mtype='skilltype' AND b.skillname != '' AND b.username=cl.username) AS s_type,
(SELECT GROUP_CONCAT(DISTINCT(educountry)) AS e_ids FROM candidate_edu WHERE username = cl.username AND educountry!='0' GROUP BY username) AS edu_country,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_edu AS b ON b.edudegree_level = a.mvalue WHERE a.mtype='edu_level' AND b.edudegree_level != '' AND b.username = cl.username) AS edudegree_level,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_work AS b ON b.cname = a.mvalue WHERE a.mtype='work_cname' AND b.cname != ''  AND b.username =cl.username) AS employment,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_work AS b ON b.ftitle = a.mvalue WHERE a.mtype='work_ftitle' AND b.ftitle != ''  AND b.username =cl.username) AS employment_type,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_work AS b ON b.city = a.mvalue WHERE a.mtype='work_city' AND b.city != ''  AND b.username =cl.username) AS employment_city,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_work AS b ON b.state = a.mvalue WHERE a.mtype='work_state' AND b.state != ''  AND b.username =cl.username) AS employment_state,
(SELECT GROUP_CONCAT(DISTINCT(country)) AS e_ids FROM candidate_work WHERE username = cl.username AND country!='0' GROUP BY username) AS employment_country,
(SELECT GROUP_CONCAT(DISTINCT(cre_type_id)) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND cre_type_id!='0') AS credential_type,
(SELECT GROUP_CONCAT(DISTINCT(cre_name_id)) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND cre_name_id!='0') AS credential_name,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(cre_number))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND cre_number!='') AS credential_number,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(acquired_date))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND acquired_date!='0000-00-00') AS creacquired_date,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(valid_from))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND valid_from!='0000-00-00') AS crevalid_from,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(valid_to))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND valid_to!='0000-00-00') AS crevalid_to,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(country_id))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND country_id!='') AS cre_country,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(state_id))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND state_id!='') AS cre_state,
(SELECT GROUP_CONCAT(DISTINCT(reqid)) AS e_ids FROM short_lists WHERE candid = cl.sno GROUP BY candid) AS short_lists,
(SELECT GROUP_CONCAT(DISTINCT(entity_roledetails.roleId)) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = cl.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMCandidate' GROUP BY entity_roles.entityId) AS role_types,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(empId))) AS e_ids FROM entity_roles WHERE entityId = cl.sno AND entityType='CRMCandidate' GROUP BY entityId) AS role_persons,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.rate))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = cl.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMCandidate' GROUP BY entity_roles.entityId) AS role_rates,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.commissionType))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = cl.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roledetails.commissionType!='' AND entity_roles.entityType='CRMCandidate' GROUP BY entity_roles.entityId) AS role_commtype,(SELECT GROUP_CONCAT(DISTINCT(gd.groupid)) AS e_ids FROM crmgroup_details AS gd JOIN crmgroups cg ON cg.sno = gd.groupid WHERE gd.refid = cl.sno AND cg.grouptype = 'Candidate') AS crmgroupids,
UNIX_TIMESTAMP(cl.ctime) AS ctime,
UNIX_TIMESTAMP(cl.mtime) AS mtime,udf.* 
 FROM candidate_list as cl LEFT JOIN manage t1 ON cl.cl_status = t1.sno LEFT JOIN candidate_prof t2 ON cl.username = t2.username LEFT JOIN manage t3 ON cl.cl_sourcetype = t3.sno LEFT JOIN candidate_pref t4 ON cl.username = t4.username LEFT JOIN emp_list t5 ON REPLACE(cl.candid, 'emp','') = t5.sno LEFT JOIN candidate_general t6 ON cl.username = t6.username LEFT JOIN department t7 ON cl.deptid = t7.sno LEFT JOIN manage t8 ON cl.jobcatid = t8.sno LEFT JOIN con_resumes t9 ON cl.username = t9.username LEFT JOIN countries t10 ON cl.country = t10.sno LEFT JOIN users t11 ON cl.owner = t11.username LEFT JOIN users t12 ON cl.cuser = t12.username LEFT JOIN users t13 ON cl.muser = t13.username LEFT JOIN udf_form_details_candidate_values udf ON cl.sno = udf.rec_id WHERE cl.status= 'ACTIVE'  AND cl.sno > $x and cl.sno <= $y AND cl.sno <= (SELECT max_id FROM sph_counter WHERE counter_id='candidate_list' and module_id='candidates') order by cl.sno";
			$results = $mysqli->query($select_Sql);
			if($results->num_rows!=0)
			{
				while($fetchRes=$results->fetch_assoc())
				{
					if($fetchRes['doc_id']!=''){
					if($fetchRes['filename']=="" or $fetchRes['filetype']=="text/plain"  or $fetchRes['filetype']=="")
					{
						$resumeData = mb_convert_encoding(strip_tags($fetchRes['content']),"ISO-8859-1", "UTF-8");
						file_put_contents("/tmp/error-output.txt","Doc ID : x-".$x."y-".$y." ".$fetchRes['sno']." ".$fetchRes['doc_id']." Plain Text Data \n",FILE_APPEND);
					}else
					{
						//$resumeData = pipesBuffering($fetchRes);
						$resumeData = $cli->extractTextFromFile($fetchRes['filename'],$fetchRes['content'], $fetchRes['filetype']);
						file_put_contents("/tmp/error-output.txt","Doc ID : x-".$x."y-".$y." ".$fetchRes['sno']." ".$fetchRes['doc_id']." ".$fetchRes['filetype']." Data \n",FILE_APPEND);
					}
						
						$resumeData = cleentext($resumeData);
					}else
					{
						$resumeData = '';
					}					
				
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
				if($fetchRes['areacode']!='')
				{
					$areaLatandLong = getAreaCodeLatLongitude($fetchRes['areacode']);
					if($areaLatandLong!='')
					{
						$laloA = explode("#",$areaLatandLong);
						$getArealat = deg2rad($laloA[0]);
						$getArealong = deg2rad($laloA[1]);
						
						$getArealatDegree = $laloA[0];
						$getArealongDegree = $laloA[1];
						
					}else
					{
						$getArealat = '';
						$getArealong = '';
						
						$getArealatDegree = '';
						$getArealongDegree = '';
					}
				}else
				{
					$getArealat = '';
					$getArealong = '';
					
					$getArealatDegree = '';
					$getArealongDegree = '';
				}
				
				if($fetchRes['wareacode']!='')
				{
					$wareaLatandLong = getAreaCodeLatLongitude($fetchRes['wareacode']);
					if($wareaLatandLong!='')
					{
						$lalowA = explode("#",$wareaLatandLong);
						$getWArealat = deg2rad($lalowA[0]);
						$getWArealong = deg2rad($lalowA[1]);
						
						$getWArealatDegree = $lalowA[0];
						$getWArealongDegree = $lalowA[1];
					}else
					{
						$getWArealat = '';
						$getWArealong = '';
						
						$getWArealatDegree = '';
						$getWArealongDegree = '';
					}
				}else
				{
					$getWArealat = '';
					$getWArealong = '';
					
					$getWArealatDegree = '';
					$getWArealongDegree = '';
				}
				
				if($fetchRes['hareacode']!='')
				{
					$hareaLatandLong = getAreaCodeLatLongitude($fetchRes['hareacode']);
					if($hareaLatandLong!='')
					{
						$lalohA = explode("#",$hareaLatandLong);
						$getHArealat = deg2rad($lalohA[0]);
						$getHArealong = deg2rad($lalohA[1]);
						
						$getHArealatDegree = $lalohA[0];
						$getHArealongDegree = $lalohA[1];
						
					}else
					{
						$getHArealat = '';
						$getHArealong = '';
						
						$getHArealatDegree = '';
						$getHArealongDegree = '';
					}
				}else
				{
					$getHArealat = '';
					$getHArealong = '';
					
					$getHArealatDegree = '';
					$getHArealongDegree = '';
				}
				
				if($fetchRes['mareacode']!='')
				{
					$mareaLatandLong = getAreaCodeLatLongitude($fetchRes['mareacode']);
					if($mareaLatandLong!='')
					{
						$lalomA = explode("#",$mareaLatandLong);
						$getMArealat = deg2rad($lalomA[0]);
						$getMArealong = deg2rad($lalomA[1]);
						
						$getMArealatDegree = $lalomA[0];
						$getMArealongDegree = $lalomA[1];
					}else
					{
						$getMArealat = '';
						$getMArealong = '';
						
						$getMArealatDegree = '';
						$getMArealongDegree = '';
					}
				}else
				{
					$getMArealat = '';
					$getMArealong = '';
					
					$getMArealatDegree = '';
					$getMArealongDegree = '';
				}
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
									
									$buildcrc32_sql = "SELECT CONCAT(\"CRC32('\",REPLACE(".$getElementName['element_lable'].",\",\",\"'),CRC32('\"),\"')\") FROM udf_form_details_candidate_values WHERE rec_id=".$fetchRes['sno'];
									
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
					
					$masterData = '';
					$masterData = array(
						'id' => $fetchRes['sno'],
						'snoid' => $fetchRes['sno'],
						'resume_data' => cleentext($resumeData),
						'profile_data' => cleentext($fetchRes['profile_data']),
						'notes' => cleentext($fetchRes['notes']),
						'accessto' => cleentext($fetchRes['accessto']),
						'profiletitle' => cleentext($fetchRes['profiletitle'],'yes','yes'),
						'candtype' => cleentext($fetchRes['candtype'],'yes'),
						'category' => cleentext($fetchRes['category'],'yes'),
						'deptname' => cleentext($fetchRes['deptname'],'yes'),
						'candidate_status' => cleentext($fetchRes['candidate_status'],'yes'),
						'cl_source_type' => cleentext($fetchRes['cl_source_type'],'yes'),
						'owner' => $fetchRes['owner'],
						'owname' => cleentext($fetchRes['owname'],'yes'),
						'cuser' => $fetchRes['cuser'],
						'createdby' => cleentext($fetchRes['createdby'],'yes'),
						'muser' => $fetchRes['muser'],
						'modifiedby' => cleentext($fetchRes['modifiedby'],'yes'),
						'ascontact' => $fetchRes['ascontact'],
						'ascont_name' => ($fetchRes['ascontact']==1?'Contacts':'Not Contacts'),
						'address' => cleentext($fetchRes['address'],'yes','yes'),
						'city' => cleentext($fetchRes['city'],'yes','yes'),
						'state' => cleentext($fetchRes['state'],'yes','yes'),
						'areacode' => cleentext($fetchRes['areacode'],'yes','yes'),
						'wareacode' => cleentext($fetchRes['wareacode'],'yes','yes'),
						'hareacode' => cleentext($fetchRes['hareacode'],'yes','yes'),
						'mareacode' => cleentext($fetchRes['mareacode'],'yes','yes'),
						'country_name' => cleentext($fetchRes['country_name'],'yes'),
						'country_id' => $fetchRes['country_id'],
						'zip' => cleentext($fetchRes['zip'],'yes','yes'),
						'label_akken' => '__AKKEN__',
						'availsdate' => $fetchRes['availsdate'],
						'desired_job_location' => cleentext($fetchRes['desired_job_location'],'yes','yes'),
						'resourcetype' => cleentext($fetchRes['resourcetype'],'yes','yes'),
						'wtravel_a' => cleentext($fetchRes['wtravel_a'],'yes','yes'),
						'wlocate_a' => cleentext($fetchRes['wlocate_a'],'yes','yes'),
						'amount' => cleentext($fetchRes['amount'],'yes','yes'),						
						'max_salary' => $fetchRes['max_salary'],
						'min_salary' => $fetchRes['min_salary'],
						'currency' => $fetchRes['currency'],						
						'rperiod' => $fetchRes['rperiod'],
						'crc_candtype' => cleentext($fetchRes['crc_candtype']),
						'cl_status' => cleentext($fetchRes['cl_status']),
						'jobcatid' => cleentext($fetchRes['jobcatid']),
						'deptid' => cleentext($fetchRes['deptid']),
						'cl_sourcetype' => cleentext($fetchRes['cl_sourcetype']),
						'contact_method' => rtrim($fetchRes['contact_method'],','),
						'crc_accessto' => $fetchRes['crc_accessto'],
						'short_lists' => $fetchRes['short_lists'],
						'skills' => $fetchRes['skills'],
						's_lastused' => $fetchRes['s_lastused'],
						's_level' => $fetchRes['s_level'],		
						's_type' => $fetchRes['s_type'],
						'edu_country' => $fetchRes['edu_country'],
						'edudegree_level' => $fetchRes['edudegree_level'],
						'employment' => $fetchRes['employment'],
						'employment_type' => $fetchRes['employment_type'],
						'employment_city' => $fetchRes['employment_city'],
						'employment_state' => $fetchRes['employment_state'],
						'employment_country' => $fetchRes['employment_country'],
						'zip_latitude' => $getlat,
						'zip_longitude' => $getlong,
						'area_latitude' => $getArealat,
						'area_longitude' => $getArealong,
						'warea_latitude' => $getWArealat,
						'warea_longitude' => $getWArealong,
						'harea_latitude' => $getHArealat,
						'harea_longitude' => $getHArealong,
						'marea_latitude' => $getMArealat,
						'marea_longitude' => $getMArealong,
						'warea_lat_deg' => $getWArealatDegree,
						'warea_long_deg' => $getWArealongDegree,
						'harea_lat_deg' => $getHArealatDegree,
						'harea_long_deg' => $getHArealongDegree,
						'marea_lat_deg' => $getMArealatDegree,
						'marea_long_deg' => $getMArealongDegree,
						'cre_type' => $fetchRes['credential_type'],
						'cre_name' => $fetchRes['credential_name'],
						'cre_number' => $fetchRes['credential_number'],
						'cre_acquireddate' => $fetchRes['creacquired_date'],
						'cre_validfrom' => $fetchRes['crevalid_from'],
						'cre_validto' => $fetchRes['crevalid_to'],
						'cre_country' => $fetchRes['cre_country'],
						'cre_state' => $fetchRes['cre_state'],						
						'role_types' => $fetchRes['role_types'],
						'role_persons' => $fetchRes['role_persons'],
						'role_rates' => $fetchRes['role_rates'],
						'role_commtype' => $fetchRes['role_commtype'],
						'crmgroups' => $fetchRes['crmgroupids'],
						'ctime' => $fetchRes['ctime'],
						'mtime' => $fetchRes['mtime'],
						
					);
					$newIndexvalues = array_merge($masterData,$udfNewarray);	
					$doc->addDocument($newIndexvalues);
				}
			
			// Frees the memory associated with a result
			$results->free();
			
			}
			$x=$y;
			$y=$y+$range;
		}
		// Render the XML
		$doc->endOutput();
	}else if($mode=="delta"){
		
		//Activated the New UDF filter columns
		$getudfactivestatus = mysql_query("UPDATE sphinx_filter_columns SET status='1' WHERE index_col_name IN (SELECT CONCAT_WS('_','cust',id) FROM udf_form_details WHERE sphinx_indexing=1 AND sphinx_searchable=1 AND module=3 and element!='textarea' AND status='Active')",$db);
		
		// instantiate the class
		$doc = new SphinxXMLFeed();

		// set the fields we will be indexing
		$doc->setFields($finalStringArray);
		// set any attributes
		
		$doc->setAttributes($finalNumericArray);
		$doc->beginOutput();
		// or other data source
		$select_Sql = "SELECT DISTINCT(cl.sno),cl.username,cl.profiletitle,cl.fname,cl.lname,cl.mname,cl.nickname,cl.recentemp as company,cl.city,cl.state,cl.zip,cl.areacode,cl.wareacode,cl.hareacode,cl.mareacode,cl.country as country_id,t10.country as country_name,concat(cl.address1, ' ', cl.address2) as address,CONCAT( IF(t6.cphone = 'TRUE', '1001,',''),IF(t6.cmobile = 'TRUE', '2002,',''),IF(t6.cfax = 'TRUE', '3003,',''),IF(t6.cemail = 'TRUE', '4004','')) as contact_method, cl.ctype as candtype,CRC32(cl.ctype) as crc_candtype,cl.cl_status,t1.name as candidate_status,cl.accessto,CONCAT(IF(cl.accessto='ALL',CRC32('ALL'),IF(cl.accessto='', cl.owner, cl.accessto))) AS crc_accessto,IF(cl.accessto='ALL','Public',IF(cl.accessto=cl.owner,'Private','Share')) as shrtype, UNIX_TIMESTAMP(trim(t2.availsdate)) as availsdate,cl.cl_source,cl.cl_sourcetype, t3.name as cl_source_type,t2.addinfo,CONCAT(t4.min_salary,' to ',t4.max_salary) AS amount,t4.min_salary,t4.max_salary,CRC32(t4.currency) as currency,CRC32(t4.rperiod) as rperiod,t4.tmax,t4.distributename,cl.deptid,t7.deptname,t4.desirejob as desired_job_type, t4.desirestatus as desired_job_status,t4.desirelocation as desired_job_location,t4.aramount,t4.iramount,t2.availedate,t2.objective,t4.pramount,cl.jobcatid,t8.name as category,t2.pstatus, IF(t4.wlocate = 'true', 'Yes', 'No') AS wlocate_a, IF(t4.resourcetype = 'true^false', 'Independent Contractor', IF(t4.resourcetype = 'false^true', 'Payrolled Employee', IF(t4.resourcetype = 'true^true', 'Independent Contractor,Payrolled Employee',IF(t4.resourcetype = 'false^false', '','' )))) AS resourcetype,t2.search_tags, t2.summary, IF(t4.wtravle = 'true', 'Yes', 'No') AS wtravel_a, t5.sno as employee_id, cl.owner as owner, t11.name as owname, cl.cuser, t12.name as createdby, cl.muser, t13.name as modifiedby, IF(cl.contact_id!='', 1, 0)  AS ascontact, (SELECT buildCandSearch_fun(cl.sno)) AS profile_data,(SELECT buildCandNotes_fun(cl.sno)) AS notes,t9.sno AS doc_id,t9.res_name AS filename,t9.filetype,t9.filecontent AS content,(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM candidate_master AS a JOIN candidate_skills AS b ON b.skillname = a.mvalue WHERE a.mtype='skillname' AND b.skillname != '' AND b.username=cl.username) AS skills,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM candidate_master AS a  JOIN candidate_skills AS b ON b.lastused = a.mvalue WHERE a.mtype='lastused' AND b.lastused != '' AND b.username = cl.username) AS s_lastused,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM candidate_master AS a JOIN candidate_skills AS b ON b.skilllevel = a.mvalue WHERE a.mtype='skilllevel' AND b.skilllevel != '' AND b.username=cl.username) AS s_level,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM candidate_master AS a JOIN candidate_skills AS b ON IF(b.manage_skills_id=0,'Parsed','Managed')=a.mvalue WHERE a.mtype='skilltype' AND b.skillname != '' AND b.username=cl.username) AS s_type,
(SELECT GROUP_CONCAT(DISTINCT(educountry)) AS e_ids FROM candidate_edu WHERE username = cl.username AND educountry!='0' GROUP BY username) AS edu_country,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_edu AS b ON b.edudegree_level = a.mvalue WHERE a.mtype='edu_level' AND b.edudegree_level != '' AND b.username = cl.username) AS edudegree_level,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_work AS b ON b.cname = a.mvalue WHERE a.mtype='work_cname' AND b.cname != ''  AND b.username =cl.username) AS employment,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_work AS b ON b.ftitle = a.mvalue WHERE a.mtype='work_ftitle' AND b.ftitle != ''  AND b.username =cl.username) AS employment_type,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_work AS b ON b.city = a.mvalue WHERE a.mtype='work_city' AND b.city != ''  AND b.username =cl.username) AS employment_city,
(SELECT GROUP_CONCAT(DISTINCT(a.sno)) AS e_ids FROM  candidate_master AS a  JOIN candidate_work AS b ON b.state = a.mvalue WHERE a.mtype='work_state' AND b.state != ''  AND b.username =cl.username) AS employment_state,
(SELECT GROUP_CONCAT(DISTINCT(country)) AS e_ids FROM candidate_work WHERE username = cl.username AND country!='0' GROUP BY username) AS employment_country,
(SELECT GROUP_CONCAT(DISTINCT(cre_type_id)) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND cre_type_id!='0') AS credential_type,
(SELECT GROUP_CONCAT(DISTINCT(cre_name_id)) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND cre_name_id!='0') AS credential_name,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(cre_number))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND cre_number!='') AS credential_number,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(acquired_date))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND acquired_date!='0000-00-00') AS creacquired_date,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(valid_from))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND valid_from!='0000-00-00') AS crevalid_from,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(valid_to))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND valid_to!='0000-00-00') AS crevalid_to,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(country_id))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND country_id!='') AS cre_country,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(state_id))) AS e_ids FROM candidate_credentials WHERE cand_username = cl.username AND state_id!='') AS cre_state,
(SELECT GROUP_CONCAT(DISTINCT(reqid)) AS e_ids FROM short_lists WHERE candid = cl.sno GROUP BY candid) AS short_lists,
(SELECT GROUP_CONCAT(DISTINCT(entity_roledetails.roleId)) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = cl.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMCandidate' GROUP BY entity_roles.entityId) AS role_types,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(empId))) AS e_ids FROM entity_roles WHERE entityId = cl.sno AND entityType='CRMCandidate' GROUP BY entityId) AS role_persons,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.rate))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = cl.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMCandidate' GROUP BY entity_roles.entityId) AS role_rates,
(SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.commissionType))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = cl.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roledetails.commissionType!='' AND entity_roles.entityType='CRMCandidate' GROUP BY entity_roles.entityId) AS role_commtype,(SELECT GROUP_CONCAT(DISTINCT(gd.groupid)) AS e_ids FROM crmgroup_details AS gd JOIN crmgroups cg ON cg.sno = gd.groupid WHERE gd.refid = cl.sno AND cg.grouptype = 'Candidate') AS crmgroupids,
UNIX_TIMESTAMP(cl.ctime) AS ctime,
UNIX_TIMESTAMP(cl.mtime) AS mtime,udf.* 
 FROM candidate_list as cl LEFT JOIN manage t1 ON cl.cl_status = t1.sno LEFT JOIN candidate_prof t2 ON cl.username = t2.username LEFT JOIN manage t3 ON cl.cl_sourcetype = t3.sno LEFT JOIN candidate_pref t4 ON cl.username = t4.username LEFT JOIN emp_list t5 ON REPLACE(cl.candid, 'emp','') = t5.sno LEFT JOIN candidate_general t6 ON cl.username = t6.username LEFT JOIN department t7 ON cl.deptid = t7.sno LEFT JOIN manage t8 ON cl.jobcatid = t8.sno LEFT JOIN con_resumes t9 ON cl.username = t9.username LEFT JOIN countries t10 ON cl.country = t10.sno LEFT JOIN users t11 ON cl.owner = t11.username LEFT JOIN users t12 ON cl.cuser = t12.username LEFT JOIN users t13 ON cl.muser = t13.username LEFT JOIN udf_form_details_candidate_values udf ON cl.sno = udf.rec_id WHERE cl.status= 'ACTIVE' AND (cl.sno > (SELECT max_id FROM sph_counter WHERE counter_id='candidate_list' and module_id='candidates') OR cl.mtime > (SELECT last_updated FROM sph_counter WHERE counter_id='candidate_list' and module_id='candidates')) order by cl.sno";
			$results = $mysqli->query($select_Sql);
			if($results->num_rows!=0)
			{
				while($fetchRes=$results->fetch_assoc())
				{
					if($fetchRes['doc_id']!=''){
						if($fetchRes['filename']=="" or $fetchRes['filetype']=="text/plain"  or $fetchRes['filetype']=="")
						{
							$resumeData = mb_convert_encoding($fetchRes['content'],"ISO-8859-1", "UTF-8");
							file_put_contents("/tmp/error-output.txt","Doc ID : ".$fetchRes['doc_id']." Plain Text Data \n",FILE_APPEND);
						}else
						{
							//$resumeData = pipesBuffering($fetchRes);
							$resumeData = $cli->extractTextFromFile($fetchRes['filename'],$fetchRes['content'], $fetchRes['filetype']);
							file_put_contents("/tmp/error-output.txt","Doc ID : ".$fetchRes['doc_id']." ".$fetchRes['filetype']." Data \n",FILE_APPEND);
						}
						
						$resumeData = cleentext($resumeData);
					}else
					{
						$resumeData = '';
					}				
					
				
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
				if($fetchRes['areacode']!='')
				{
					$areaLatandLong = getAreaCodeLatLongitude($fetchRes['areacode']);
					if($areaLatandLong!='')
					{
						$laloA = explode("#",$areaLatandLong);
						$getArealat = deg2rad($laloA[0]);
						$getArealong = deg2rad($laloA[1]);
						
						$getArealatDegree = $laloA[0];
						$getArealongDegree = $laloA[1];
						
					}else
					{
						$getArealat = '';
						$getArealong = '';
						
						$getArealatDegree = '';
						$getArealongDegree = '';
					}
				}else
				{
					$getArealat = '';
					$getArealong = '';
					
					$getArealatDegree = '';
					$getArealongDegree = '';
				}
				
				if($fetchRes['wareacode']!='')
				{
					$wareaLatandLong = getAreaCodeLatLongitude($fetchRes['wareacode']);
					if($wareaLatandLong!='')
					{
						$lalowA = explode("#",$wareaLatandLong);
						$getWArealat = deg2rad($lalowA[0]);
						$getWArealong = deg2rad($lalowA[1]);
						
						$getWArealatDegree = $lalowA[0];
						$getWArealongDegree = $lalowA[1];
					}else
					{
						$getWArealat = '';
						$getWArealong = '';
						
						$getWArealatDegree = '';
						$getWArealongDegree = '';
					}
				}else
				{
					$getWArealat = '';
					$getWArealong = '';
					
					$getWArealatDegree = '';
					$getWArealongDegree = '';
				}
				
				if($fetchRes['hareacode']!='')
				{
					$hareaLatandLong = getAreaCodeLatLongitude($fetchRes['hareacode']);
					if($hareaLatandLong!='')
					{
						$lalohA = explode("#",$hareaLatandLong);
						$getHArealat = deg2rad($lalohA[0]);
						$getHArealong = deg2rad($lalohA[1]);
						
						$getHArealatDegree = $lalohA[0];
						$getHArealongDegree = $lalohA[1];
						
					}else
					{
						$getHArealat = '';
						$getHArealong = '';
						
						$getHArealatDegree = '';
						$getHArealongDegree = '';
					}
				}else
				{
					$getHArealat = '';
					$getHArealong = '';
					
					$getHArealatDegree = '';
					$getHArealongDegree = '';
				}
				
				if($fetchRes['mareacode']!='')
				{
					$mareaLatandLong = getAreaCodeLatLongitude($fetchRes['mareacode']);
					if($mareaLatandLong!='')
					{
						$lalomA = explode("#",$mareaLatandLong);
						$getMArealat = deg2rad($lalomA[0]);
						$getMArealong = deg2rad($lalomA[1]);
						
						$getMArealatDegree = $lalomA[0];
						$getMArealongDegree = $lalomA[1];
					}else
					{
						$getMArealat = '';
						$getMArealong = '';
						
						$getMArealatDegree = '';
						$getMArealongDegree = '';
					}
				}else
				{
					$getMArealat = '';
					$getMArealong = '';
					
					$getMArealatDegree = '';
					$getMArealongDegree = '';
				}
					
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
									
									$buildcrc32_sql = "SELECT CONCAT(\"CRC32('\",REPLACE(".$getElementName['element_lable'].",\",\",\"'),CRC32('\"),\"')\") FROM udf_form_details_candidate_values WHERE rec_id=".$fetchRes['sno'];
									
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
					
					$masterData = '';
					$masterData = array(
						'id' => $fetchRes['sno'],
						'snoid' => $fetchRes['sno'],
						'resume_data' => cleentext($resumeData),
						'profile_data' => cleentext($fetchRes['profile_data']),
						'notes' => cleentext($fetchRes['notes']),
						'accessto' => cleentext($fetchRes['accessto']),
						'profiletitle' => cleentext($fetchRes['profiletitle'],'yes','yes'),
						'candtype' => cleentext($fetchRes['candtype'],'yes'),
						'category' => cleentext($fetchRes['category'],'yes'),
						'deptname' => cleentext($fetchRes['deptname'],'yes'),
						'candidate_status' => cleentext($fetchRes['candidate_status'],'yes'),
						'cl_source_type' => cleentext($fetchRes['cl_source_type'],'yes'),
						'owner' => $fetchRes['owner'],
						'owname' => cleentext($fetchRes['owname'],'yes'),
						'cuser' => $fetchRes['cuser'],
						'createdby' => cleentext($fetchRes['createdby'],'yes'),
						'muser' => $fetchRes['muser'],
						'modifiedby' => cleentext($fetchRes['modifiedby'],'yes'),
						'ascontact' => $fetchRes['ascontact'],
						'ascont_name' => ($fetchRes['ascontact']==1?'Contacts':'Not Contacts'),
						'address' => cleentext($fetchRes['address'],'yes','yes'),
						'city' => cleentext($fetchRes['city'],'yes','yes'),
						'state' => cleentext($fetchRes['state'],'yes','yes'),
						'areacode' => cleentext($fetchRes['areacode'],'yes','yes'),
						'wareacode' => cleentext($fetchRes['wareacode'],'yes','yes'),
						'hareacode' => cleentext($fetchRes['hareacode'],'yes','yes'),
						'mareacode' => cleentext($fetchRes['mareacode'],'yes','yes'),
						'country_name' => cleentext($fetchRes['country_name'],'yes'),
						'country_id' => $fetchRes['country_id'],
						'zip' => cleentext($fetchRes['zip'],'yes','yes'),
						'label_akken' => '__AKKEN__',
						'availsdate' => $fetchRes['availsdate'],
						'desired_job_location' => cleentext($fetchRes['desired_job_location'],'yes','yes'),
						'resourcetype' => cleentext($fetchRes['resourcetype'],'yes','yes'),
						'wtravel_a' => cleentext($fetchRes['wtravel_a'],'yes','yes'),
						'wlocate_a' => cleentext($fetchRes['wlocate_a'],'yes','yes'),
						'amount' => cleentext($fetchRes['amount'],'yes','yes'),						
						'max_salary' => $fetchRes['max_salary'],
						'min_salary' => $fetchRes['min_salary'],
						'currency' => $fetchRes['currency'],						
						'rperiod' => $fetchRes['rperiod'],
						'crc_candtype' => cleentext($fetchRes['crc_candtype']),
						'cl_status' => cleentext($fetchRes['cl_status']),
						'jobcatid' => cleentext($fetchRes['jobcatid']),
						'deptid' => cleentext($fetchRes['deptid']),
						'cl_sourcetype' => cleentext($fetchRes['cl_sourcetype']),
						'contact_method' => rtrim($fetchRes['contact_method'],','),
						'crc_accessto' => $fetchRes['crc_accessto'],
						'short_lists' => $fetchRes['short_lists'],
						'skills' => $fetchRes['skills'],
						's_lastused' => $fetchRes['s_lastused'],
						's_level' => $fetchRes['s_level'],		
						's_type' => $fetchRes['s_type'],
						'edu_country' => $fetchRes['edu_country'],
						'edudegree_level' => $fetchRes['edudegree_level'],
						'employment' => $fetchRes['employment'],
						'employment_type' => $fetchRes['employment_type'],
						'employment_city' => $fetchRes['employment_city'],
						'employment_state' => $fetchRes['employment_state'],
						'employment_country' => $fetchRes['employment_country'],
						'zip_latitude' => $getlat,
						'zip_longitude' => $getlong,
						'area_latitude' => $getArealat,
						'area_longitude' => $getArealong,
						'warea_latitude' => $getWArealat,
						'warea_longitude' => $getWArealong,
						'harea_latitude' => $getHArealat,
						'harea_longitude' => $getHArealong,
						'marea_latitude' => $getMArealat,
						'marea_longitude' => $getMArealong,
						'warea_lat_deg' => $getWArealatDegree,
						'warea_long_deg' => $getWArealongDegree,
						'harea_lat_deg' => $getHArealatDegree,
						'harea_long_deg' => $getHArealongDegree,
						'marea_lat_deg' => $getMArealatDegree,
						'marea_long_deg' => $getMArealongDegree,
						'cre_type' => $fetchRes['credential_type'],
						'cre_name' => $fetchRes['credential_name'],
						'cre_number' => $fetchRes['credential_number'],
						'cre_acquireddate' => $fetchRes['creacquired_date'],
						'cre_validfrom' => $fetchRes['crevalid_from'],
						'cre_validto' => $fetchRes['crevalid_to'],
						'cre_country' => $fetchRes['cre_country'],
						'cre_state' => $fetchRes['cre_state'],						
						'role_types' => $fetchRes['role_types'],
						'role_persons' => $fetchRes['role_persons'],
						'role_rates' => $fetchRes['role_rates'],
						'role_commtype' => $fetchRes['role_commtype'],
						'crmgroups' => $fetchRes['crmgroupids'],
						'ctime' => $fetchRes['ctime'],
						'mtime' => $fetchRes['mtime'],
					);
					
					$newIndexvalues = array_merge($masterData,$udfNewarray);	
					$doc->addDocument($newIndexvalues);
				}
			}
		/*** Print kill list*/
		$killList_Sql = "SELECT candidate_list.sno FROM candidate_list WHERE candidate_list.status= 'INACTIVE' AND (candidate_list.sno > (SELECT max_id FROM sph_counter WHERE counter_id='candidate_list' and module_id='candidates') OR candidate_list.mtime > (SELECT last_updated FROM sph_counter WHERE counter_id='candidate_list' and module_id='candidates')) order by candidate_list.sno";
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