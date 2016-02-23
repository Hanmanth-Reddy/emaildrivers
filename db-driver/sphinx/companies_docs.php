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
		$latlongCodes = ''; $latitude = ''; $longitude = '';		
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
		$ArealatlongCodes = ''; $latitude = ''; $longitude = '';
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
			$udfupdatstatus = mysql_query("UPDATE udf_form_details SET sphinx_indexing=1, sphinx_searchable=1 WHERE module=2 and element!='textarea' AND status='Active' AND sphinx_indexing=0",$db);
		}
		
		//Get Active UDF filter items
		$sqludf_columns = mysql_query("SELECT CONCAT_WS('_','cust',id) AS udf_colums, element_lable AS udf_dbcolums, CONCAT_WS('=>',element_lable,element) AS udf_elements, element FROM udf_form_details WHERE sphinx_indexing=1 AND sphinx_searchable=1 AND element!='textarea' AND `status`='Active' AND module=2", $db);
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
		
		$userdefine_m = array(
							'cname',
							'ctype_name',
							'cinfostatus',
							'industry',
							'dep_name',
							'hrm_deptname',
							'csource_name',
							'billcontactname',
							'compowner',
							'com_revenue',
							'csize',
							'yearfounded',
							'paymentterms',
							'dress_code',			
							'smoke_policy',
							'tele_policy',			
							'address',
							'state',
							'city',
							'country_name',
							'zip',
							'areacode',			
							'owname',
							'createdby',
							'modifiedby',			
							'accessto',
							'profile_data',
							'notes',
							'label_akken',
						);
	$finalStringArray = array_merge($userdefine_m,$set_udf_string_columns);
	$finalNumericArray =  array(
							  array('name' => 'snoid', 'type' => 'bigint'),		 
							  array('name' => 'ctype', 'type' => 'bigint'),
							  array('name' => 'compstatus', 'type' => 'bigint'),
							  array('name' => 'hrm_deptid', 'type' => 'bigint'),
							  array('name' => 'csource', 'type' => 'bigint'),
							  array('name' => 'country_id', 'type' => 'bigint'),
							  array('name' => 'crc_accessto', 'type' => 'multi'),
							  array('name' => 'owner', 'type' => 'bigint'),
							  array('name' => 'cuser', 'type' => 'bigint'),
							  array('name' => 'muser', 'type' => 'bigint'),
							  array('name' => 'zip_latitude', 'type' => 'float'),
							  array('name' => 'zip_longitude', 'type' => 'float'),
							  array('name' => 'area_latitude', 'type' => 'float'),
							  array('name' => 'area_longitude', 'type' => 'float'), 
							  array('name' => 'area_lat_deg', 'type' => 'float'),
							  array('name' => 'area_long_deg', 'type' => 'float'),
							  array('name' => 'role_types', 'type' => 'multi'),
							  array('name' => 'role_persons', 'type' => 'multi'),
							  array('name' => 'role_rates', 'type' => 'multi'),
							  array('name' => 'role_commtype', 'type' => 'multi'),
							  array('name' => 'opp_name', 'type' => 'multi'),
							  array('name' => 'opp_steps', 'type' => 'multi'),
							  array('name' => 'opp_stage', 'type' => 'multi'),
							  array('name' => 'opp_otype', 'type' => 'multi'),
							  array('name' => 'opp_lead', 'type' => 'multi'),
							  array('name' => 'opp_reason', 'type' => 'multi'),	
							  array('name' => 'opp_probability', 'type' => 'multi'),
							  array('name' => 'opp_amount', 'type' => 'multi'),
							  array('name' => 'opp_ecdate', 'type' => 'multi'),
							  array('name' => 'opp_products', 'type' => 'multi'),
							  array('name' => 'opp_other', 'type' => 'multi'),
							  array('name' => 'opp_createdby', 'type' => 'multi'),
							  array('name' => 'opp_modifiedby', 'type' => 'multi'),		  
							  array('name' => 'ctime', 'type' => 'timestamp'),
							  array('name' => 'mtime', 'type' => 'timestamp')
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
		mysql_query("REPLACE INTO sph_counter SELECT 'companies_list',MAX(sno),'companies',MAX(mdate) FROM staffoppr_cinfo");

		// instantiate the class
		$doc = new SphinxXMLFeed();

		// set the fields we will be indexing
		$doc->setFields($finalStringArray);
		// set any attributes
		
		$doc->setAttributes($finalNumericArray);
		$doc->beginOutput();
		// or other data source
		$maxidQ = mysql_fetch_array(mysql_query("select max(sno) from staffoppr_cinfo"));
		$maxid = $maxidQ[0];
		//$maxid = 100;
		$range = 1000;
		$x=0; $y=$range;  
		while($x <= $maxid)
		{
			$select_Sql = "SELECT DISTINCT(sc.sno), sc.cname, sc.city, sc.state, sc.phone, areacode_fun(sc.phone) AS areacode, sc.accessto, CONCAT(IF(sc.accessto = 'ALL',CRC32('ALL'),IF(sc.accessto='', sc.owner, sc.accessto ))) AS crc_accessto, CONCAT(sc.address1,' ',sc.address2) AS address, sc.alternative_id, sc.bill_address, (SELECT CONCAT(fname, ' ', lname) bilname FROM staffoppr_contact WHERE staffoppr_contact.sno = sc.bill_contact) AS billcontactname, sc.compbrief, sc.compowner,  sc.com_revenue, sc.csize, sc.csource, t1.name AS csource_name, sc.compsummary, sc.ctype, t2.name AS ctype_name, sc.country AS country_id, t3.country AS country_name, IF(sc.acc_comp != 0 || sc.acc_comp != '',sc.acc_comp,'') AS acc_comp, sc.department, sc.directions, sc.dress_code, sc.fax, sc.federalid, sc.deptid AS hrm_deptid, t5.deptname AS hrm_deptname,  sc.industry, sc.phone_extn, sc.nemployee, sc.nloction, sc.culture, sc.parking, sc.park_rate, sc.bill_req AS paymenttermscode, t7.billpay_code as paymentterms,  sc.keytech, sc.service_terms, sc.siccode, sc.smoke_policy,  sc.tele_policy, sc.ticker, sc.curl, sc.nbyears, sc.zip, sc.compstatus, t8.name AS cinfostatus,(SELECT buildCompNotes_fun(sc.sno)) AS notes, (SELECT  buildCompSearch_fun(sc.sno)) AS profile_data, sc.owner, t9.name AS owname, sc.approveuser AS cuser, t4.name AS createdby, sc.muser, t6.name AS modifiedby,  (SELECT GROUP_CONCAT(DISTINCT(entity_roledetails.roleId)) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMCompany' GROUP BY entity_roles.entityId) AS role_types,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(empId))) AS e_ids FROM entity_roles WHERE entityId = sc.sno AND entityType='CRMCompany' GROUP BY entityId) AS role_persons,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.rate))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMCompany' GROUP BY entity_roles.entityId) AS role_rates,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.commissionType))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roledetails.commissionType!='' AND entity_roles.entityType='CRMCompany' GROUP BY entity_roles.entityId) AS role_commtype,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(NAME))) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND NAME!='' GROUP BY csno) AS opp_name,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(steps))) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND steps!='' GROUP BY csno) AS opp_steps,
  (SELECT GROUP_CONCAT(DISTINCT(stage)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND stage!=0 GROUP BY csno) AS opp_stage,
  (SELECT GROUP_CONCAT(DISTINCT(otype)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND otype!=0 GROUP BY csno) AS opp_otype,
  (SELECT GROUP_CONCAT(DISTINCT(lead)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND lead!=0 GROUP BY csno) AS opp_lead,
  (SELECT GROUP_CONCAT(DISTINCT(reason)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND reason!=0 GROUP BY csno) AS opp_reason,
  (SELECT GROUP_CONCAT(DISTINCT(probability)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND probability!=0 GROUP BY csno) AS opp_probability,
  (SELECT GROUP_CONCAT(DISTINCT(CAST(amount_clear(ammount) AS DECIMAL(25,0)))) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND ammount!='' AND ammount!='0' GROUP BY csno) AS opp_amount,
  (SELECT GROUP_CONCAT(DISTINCT(UNIX_TIMESTAMP(cdate))) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND cdate!='0000-00-00' GROUP BY csno) AS opp_ecdate,
  (SELECT GROUP_CONCAT(DISTINCT(products)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND products!='' AND products!='0' GROUP BY csno) AS opp_products,
  (SELECT GROUP_CONCAT(DISTINCT(other)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND other!='' AND other!='0' GROUP BY csno) AS opp_other,
  (SELECT GROUP_CONCAT(DISTINCT(cuser)) AS e_ids FROM staffoppr_oppr WHERE csno =sc.sno GROUP BY csno) AS opp_c_reatedby,
  (SELECT GROUP_CONCAT(DISTINCT(muser)) AS e_ids FROM staffoppr_oppr WHERE csno =sc.sno GROUP BY csno) AS opp_m_reatedby, UNIX_TIMESTAMP(sc.cdate) AS ctime, UNIX_TIMESTAMP(sc.mdate) AS mtime,udf.* FROM staffoppr_cinfo as sc LEFT JOIN manage t1 ON sc.csource = t1.sno LEFT JOIN manage t2 ON sc.ctype = t2.sno LEFT JOIN countries t3 ON sc.country = t3.sno LEFT JOIN users t4 ON sc.approveuser = t4.username LEFT JOIN department t5 ON sc.deptid = t5.sno LEFT JOIN users t6 ON sc.muser = t6.username LEFT JOIN bill_pay_terms t7 ON sc.bill_req = t7.billpay_termsid LEFT JOIN manage t8 ON sc.compstatus = t8.sno LEFT JOIN users t9 ON sc.owner = t9.username LEFT JOIN udf_form_details_companie_values udf ON sc.sno = udf.rec_id WHERE sc.status = 'ER' AND sc.crmcompany = 'Y' AND sc.sno > $x and sc.sno <= $y AND sc.sno <= (SELECT max_id FROM sph_counter WHERE counter_id='companies_list' and module_id='companies') order by sc.sno";
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
									
									$buildcrc32_sql = "SELECT CONCAT(\"CRC32('\",REPLACE(".$getElementName['element_lable'].",\",\",\"'),CRC32('\"),\"')\") FROM udf_form_details_companie_values WHERE rec_id=".$fetchRes['sno'];
									
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
						'profile_data' => cleentext($fetchRes['profile_data']),
						'notes' => cleentext($fetchRes['notes']),
						'accessto' => cleentext($fetchRes['accessto']),
						'cname' => cleentext($fetchRes['cname'],'yes','yes'),					
						'ctype_name' => cleentext($fetchRes['ctype_name'],'yes'),
						'ctype' => $fetchRes['ctype'],					
						'cinfostatus' => cleentext($fetchRes['cinfostatus'],'yes'),
						'compstatus' => $fetchRes['compstatus'],
						'industry' => cleentext($fetchRes['industry'],'yes','yes'),
						'dep_name' => cleentext($fetchRes['department'],'yes','yes'),
						'hrm_deptname' => cleentext($fetchRes['hrm_deptname'],'yes'),
						'hrm_deptid' => $fetchRes['hrm_deptid'],
						'csource_name' => cleentext($fetchRes['csource_name'],'yes'),
						'csource' => $fetchRes['csource'],
						'billcontactname' => cleentext($fetchRes['billcontactname'],'yes','yes'),					
						'compowner' => cleentext($fetchRes['compowner'],'yes','yes'),
						'com_revenue' => cleentext($fetchRes['com_revenue'],'yes','yes'),
						'csize' => cleentext($fetchRes['csize'],'yes','yes'),	
						'yearfounded' => cleentext($fetchRes['nbyears'],'yes','yes'),						
						'paymentterms' => cleentext($fetchRes['paymentterms'],'yes','yes'),
						'dress_code' => cleentext($fetchRes['dress_code'],'yes','yes'),						
						'smoke_policy' => cleentext($fetchRes['smoke_policy'],'yes','yes'),
						'tele_policy' => cleentext($fetchRes['tele_policy'],'yes','yes'),						
						'owner' => $fetchRes['owner'],
						'owname' => cleentext($fetchRes['owname'],'yes'),
						'cuser' => $fetchRes['cuser'],
						'createdby' => cleentext($fetchRes['createdby'],'yes'),
						'muser' => $fetchRes['muser'],
						'modifiedby' => cleentext($fetchRes['modifiedby'],'yes'),					
						'address' => cleentext($fetchRes['address'],'yes','yes'),
						'city' => cleentext($fetchRes['city'],'yes','yes'),
						'state' => cleentext($fetchRes['state'],'yes','yes'),
						'country_name' => cleentext($fetchRes['country_name'],'yes'),
						'country_id' => $fetchRes['country_id'],
						'zip' => cleentext($fetchRes['zip'],'yes','yes'),
						'areacode' => cleentext($fetchRes['areacode'],'yes','yes'),
						'label_akken' => '__AKKEN__',
						'crc_accessto' => $fetchRes['crc_accessto'],
						'zip_latitude' => $getlat,
						'zip_longitude' => $getlong,
						'area_latitude' => $getArealat,
						'area_longitude' => $getArealong,
						'area_lat_deg' => $getArealatDegree,
						'area_long_deg' => $getArealongDegree,
						'role_types' => $fetchRes['role_types'],
						'role_persons' => $fetchRes['role_persons'],
						'role_rates' => $fetchRes['role_rates'],
						'role_commtype' => $fetchRes['role_commtype'],
						'opp_name' => $fetchRes['opp_name'],
						'opp_steps' => $fetchRes['opp_steps'],
						'opp_stage' => $fetchRes['opp_stage'],
						'opp_otype' => $fetchRes['opp_otype'],
						'opp_lead' => $fetchRes['opp_lead'],
						'opp_reason' => $fetchRes['opp_reason'],
						'opp_probability' => $fetchRes['opp_probability'],
						'opp_amount' => $fetchRes['opp_amount'],
						'opp_ecdate' => $fetchRes['opp_ecdate'],
						'opp_products' => $fetchRes['opp_products'],
						'opp_other' => $fetchRes['opp_other'],
						'opp_createdby' => $fetchRes['opp_c_reatedby'],
						'opp_modifiedby' => $fetchRes['opp_m_reatedby'],
						'ctime' => $fetchRes['ctime'],
						'mtime' => $fetchRes['mtime'],
					);
					
					$newIndexvalues = array_merge($masterData,$udfNewarray);	
					$doc->addDocument($newIndexvalues);
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
		$getudfactivestatus = mysql_query("UPDATE sphinx_filter_columns SET status='1' WHERE index_col_name IN (SELECT CONCAT_WS('_','cust',id) FROM udf_form_details WHERE sphinx_indexing=1 AND sphinx_searchable=1 AND module=2 and element!='textarea' AND status='Active')",$db);
		
		// instantiate the class
		$doc = new SphinxXMLFeed();

		// set the fields we will be indexing
		$doc->setFields($finalStringArray);
		// set any attributes
		
		$doc->setAttributes($finalNumericArray);
		$doc->beginOutput();
		// or other data source
		$select_Sql = "SELECT DISTINCT(sc.sno), sc.cname, sc.city, sc.state, sc.phone, areacode_fun(sc.phone) AS areacode, sc.accessto, CONCAT(IF(sc.accessto = 'ALL',CRC32('ALL'),IF(sc.accessto='', sc.owner, sc.accessto ))) AS crc_accessto, CONCAT(sc.address1,' ',sc.address2) AS address, sc.alternative_id, sc.bill_address, (SELECT CONCAT(fname, ' ', lname) bilname FROM staffoppr_contact WHERE staffoppr_contact.sno = sc.bill_contact) AS billcontactname, sc.compbrief, sc.compowner,  sc.com_revenue, sc.csize, sc.csource, t1.name AS csource_name, sc.compsummary, sc.ctype, t2.name AS ctype_name, sc.country AS country_id, t3.country AS country_name, IF(sc.acc_comp != 0 || sc.acc_comp != '',sc.acc_comp,'') AS acc_comp, sc.department, sc.directions, sc.dress_code, sc.fax, sc.federalid, sc.deptid AS hrm_deptid, t5.deptname AS hrm_deptname,  sc.industry, sc.phone_extn, sc.nemployee, sc.nloction, sc.culture, sc.parking, sc.park_rate, sc.bill_req AS paymenttermscode, t7.billpay_code as paymentterms,  sc.keytech, sc.service_terms, sc.siccode, sc.smoke_policy,  sc.tele_policy, sc.ticker, sc.curl, sc.nbyears, sc.zip, sc.compstatus, t8.name AS cinfostatus,(SELECT buildCompNotes_fun(sc.sno)) AS notes, (SELECT  buildCompSearch_fun(sc.sno)) AS profile_data, sc.owner, t9.name AS owname, sc.approveuser AS cuser, t4.name AS createdby, sc.muser, t6.name AS modifiedby,  (SELECT GROUP_CONCAT(DISTINCT(entity_roledetails.roleId)) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMCompany' GROUP BY entity_roles.entityId) AS role_types,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(empId))) AS e_ids FROM entity_roles WHERE entityId = sc.sno AND entityType='CRMCompany' GROUP BY entityId) AS role_persons,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.rate))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMCompany' GROUP BY entity_roles.entityId) AS role_rates,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.commissionType))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roledetails.commissionType!='' AND entity_roles.entityType='CRMCompany' GROUP BY entity_roles.entityId) AS role_commtype,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(NAME))) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND NAME!='' GROUP BY csno) AS opp_name,
  (SELECT GROUP_CONCAT(DISTINCT(CRC32(steps))) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND steps!='' GROUP BY csno) AS opp_steps,
  (SELECT GROUP_CONCAT(DISTINCT(stage)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND stage!=0 GROUP BY csno) AS opp_stage,
  (SELECT GROUP_CONCAT(DISTINCT(otype)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND otype!=0 GROUP BY csno) AS opp_otype,
  (SELECT GROUP_CONCAT(DISTINCT(lead)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND lead!=0 GROUP BY csno) AS opp_lead,
  (SELECT GROUP_CONCAT(DISTINCT(reason)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND reason!=0 GROUP BY csno) AS opp_reason,
  (SELECT GROUP_CONCAT(DISTINCT(probability)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND probability!=0 GROUP BY csno) AS opp_probability,
  (SELECT GROUP_CONCAT(DISTINCT(CAST(amount_clear(ammount) AS DECIMAL(25,0)))) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND ammount!='' AND ammount!='0' GROUP BY csno) AS opp_amount,
  (SELECT GROUP_CONCAT(DISTINCT(UNIX_TIMESTAMP(cdate))) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND cdate!='0000-00-00' GROUP BY csno) AS opp_ecdate,
  (SELECT GROUP_CONCAT(DISTINCT(products)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND products!='' AND products!='0' GROUP BY csno) AS opp_products,
  (SELECT GROUP_CONCAT(DISTINCT(other)) AS e_ids FROM staffoppr_oppr WHERE csno = sc.sno AND other!='' AND other!='0' GROUP BY csno) AS opp_other,
  (SELECT GROUP_CONCAT(DISTINCT(cuser)) AS e_ids FROM staffoppr_oppr WHERE csno =sc.sno GROUP BY csno) AS opp_c_reatedby,
  (SELECT GROUP_CONCAT(DISTINCT(muser)) AS e_ids FROM staffoppr_oppr WHERE csno =sc.sno GROUP BY csno) AS opp_m_reatedby, UNIX_TIMESTAMP(sc.cdate) AS ctime, UNIX_TIMESTAMP(sc.mdate) AS mtime,udf.* FROM staffoppr_cinfo as sc LEFT JOIN manage t1 ON sc.csource = t1.sno LEFT JOIN manage t2 ON sc.ctype = t2.sno LEFT JOIN countries t3 ON sc.country = t3.sno LEFT JOIN users t4 ON sc.approveuser = t4.username LEFT JOIN department t5 ON sc.deptid = t5.sno LEFT JOIN users t6 ON sc.muser = t6.username LEFT JOIN bill_pay_terms t7 ON sc.bill_req = t7.billpay_termsid LEFT JOIN manage t8 ON sc.compstatus = t8.sno LEFT JOIN users t9 ON sc.owner = t9.username LEFT JOIN udf_form_details_companie_values udf ON sc.sno = udf.rec_id WHERE sc.status = 'ER' AND sc.crmcompany = 'Y' AND (sc.sno > (SELECT max_id FROM sph_counter WHERE counter_id='companies_list' and module_id='companies') OR sc.mdate > (SELECT last_updated FROM sph_counter WHERE counter_id='companies_list' and module_id='companies')) order by sc.sno";
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
									
									$buildcrc32_sql = "SELECT CONCAT(\"CRC32('\",REPLACE(".$getElementName['element_lable'].",\",\",\"'),CRC32('\"),\"')\") FROM udf_form_details_companie_values WHERE rec_id=".$fetchRes['sno'];
									
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
						'profile_data' => cleentext($fetchRes['profile_data']),
						'notes' => cleentext($fetchRes['notes']),
						'accessto' => cleentext($fetchRes['accessto']),
						'cname' => cleentext($fetchRes['cname'],'yes','yes'),					
						'ctype_name' => cleentext($fetchRes['ctype_name'],'yes'),
						'ctype' => $fetchRes['ctype'],					
						'cinfostatus' => cleentext($fetchRes['cinfostatus'],'yes'),
						'compstatus' => $fetchRes['compstatus'],
						'industry' => cleentext($fetchRes['industry'],'yes','yes'),
						'dep_name' => cleentext($fetchRes['department'],'yes','yes'),
						'hrm_deptname' => cleentext($fetchRes['hrm_deptname'],'yes'),
						'hrm_deptid' => $fetchRes['hrm_deptid'],
						'csource_name' => cleentext($fetchRes['csource_name'],'yes'),
						'csource' => $fetchRes['csource'],
						'billcontactname' => cleentext($fetchRes['billcontactname'],'yes','yes'),					
						'compowner' => cleentext($fetchRes['compowner'],'yes','yes'),
						'com_revenue' => cleentext($fetchRes['com_revenue'],'yes','yes'),
						'csize' => cleentext($fetchRes['csize'],'yes','yes'),	
						'yearfounded' => cleentext($fetchRes['nbyears'],'yes','yes'),						
						'paymentterms' => cleentext($fetchRes['paymentterms'],'yes','yes'),
						'dress_code' => cleentext($fetchRes['dress_code'],'yes','yes'),						
						'smoke_policy' => cleentext($fetchRes['smoke_policy'],'yes','yes'),
						'tele_policy' => cleentext($fetchRes['tele_policy'],'yes','yes'),						
						'owner' => $fetchRes['owner'],
						'owname' => cleentext($fetchRes['owname'],'yes'),
						'cuser' => $fetchRes['cuser'],
						'createdby' => cleentext($fetchRes['createdby'],'yes'),
						'muser' => $fetchRes['muser'],
						'modifiedby' => cleentext($fetchRes['modifiedby'],'yes'),					
						'address' => cleentext($fetchRes['address'],'yes','yes'),
						'city' => cleentext($fetchRes['city'],'yes','yes'),
						'state' => cleentext($fetchRes['state'],'yes','yes'),
						'country_name' => cleentext($fetchRes['country_name'],'yes'),
						'country_id' => $fetchRes['country_id'],
						'zip' => cleentext($fetchRes['zip'],'yes','yes'),
						'areacode' => cleentext($fetchRes['areacode'],'yes','yes'),
						'label_akken' => '__AKKEN__',
						'crc_accessto' => $fetchRes['crc_accessto'],
						'zip_latitude' => $getlat,
						'zip_longitude' => $getlong,
						'area_latitude' => $getArealat,
						'area_longitude' => $getArealong,
						'area_lat_deg' => $getArealatDegree,
						'area_long_deg' => $getArealongDegree,
						'role_types' => $fetchRes['role_types'],
						'role_persons' => $fetchRes['role_persons'],
						'role_rates' => $fetchRes['role_rates'],
						'role_commtype' => $fetchRes['role_commtype'],
						'opp_name' => $fetchRes['opp_name'],
						'opp_steps' => $fetchRes['opp_steps'],
						'opp_stage' => $fetchRes['opp_stage'],
						'opp_otype' => $fetchRes['opp_otype'],
						'opp_lead' => $fetchRes['opp_lead'],
						'opp_reason' => $fetchRes['opp_reason'],
						'opp_probability' => $fetchRes['opp_probability'],
						'opp_amount' => $fetchRes['opp_amount'],
						'opp_ecdate' => $fetchRes['opp_ecdate'],
						'opp_products' => $fetchRes['opp_products'],
						'opp_other' => $fetchRes['opp_other'],
						'opp_createdby' => $fetchRes['opp_c_reatedby'],
						'opp_modifiedby' => $fetchRes['opp_m_reatedby'],
						'ctime' => $fetchRes['ctime'],
						'mtime' => $fetchRes['mtime'],
					);
					
					$newIndexvalues = array_merge($masterData,$udfNewarray);	
					$doc->addDocument($newIndexvalues);
				}
			}
		/*** Print kill list*/
		$killList_Sql = "SELECT staffoppr_cinfo.sno FROM staffoppr_cinfo WHERE staffoppr_cinfo.status = 'INACTIVE' AND staffoppr_cinfo.crmcompany = 'Y' AND (staffoppr_cinfo.sno > (SELECT max_id FROM sph_counter WHERE counter_id='companies_list' and module_id='companies') OR staffoppr_cinfo.mdate > (SELECT last_updated FROM sph_counter WHERE counter_id='companies_list' and module_id='companies')) order by staffoppr_cinfo.sno";
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