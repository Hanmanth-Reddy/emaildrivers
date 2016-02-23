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
			$udfupdatstatus = mysql_query("UPDATE udf_form_details SET sphinx_indexing=1, sphinx_searchable=1 WHERE module=1 and element!='textarea' AND status='Active' AND sphinx_indexing=0",$db);
		}
		
		//Get Active UDF filter items
		$sqludf_columns = mysql_query("SELECT CONCAT_WS('_','cust',id) AS udf_colums, element_lable AS udf_dbcolums, CONCAT_WS('=>',element_lable,element) AS udf_elements, element FROM udf_form_details WHERE sphinx_indexing=1 AND sphinx_searchable=1 AND element!='textarea' AND `status`='Active' AND module=1", $db);
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
		
		$userdefine_m = array('contact_type',
								'ytitle',
								'cname',
								'category',
								'dep_name',
								'hrm_deptname',
								'source_name',
								'address',
								'state',
								'city',
								'country_name',
								'zip',
								'areacode',
								'wareacode',
								'hareacode',
								'mareacode',
								'owname',
								'ascand_name',
								'createdby',
								'modifiedby',
								'importance',
								'dontcall',
								'dontemail',
								'reportto_name',
								'certifications',
								'accessto',
								'profile_data',
								'notes',
								'label_akken',
								);
	$finalStringArray = array_merge($userdefine_m,$set_udf_string_columns);
	$finalNumericArray = array(
						  array('name' => 'snoid', 'type' => 'bigint'),
						  array('name' => 'csno', 'type' => 'bigint'),
						  array('name' => 'ctype', 'type' => 'bigint'),
						  array('name' => 'cat_id', 'type' => 'multi'),
						  array('name' => 'dep_id', 'type' => 'bigint'),
						  array('name' => 'hrm_deptid', 'type' => 'bigint'),
						  array('name' => 'sourcetype', 'type' => 'bigint'),
						  array('name' => 'country_id', 'type' => 'bigint'),
						  array('name' => 'crc_accessto', 'type' => 'multi'),
						  array('name' => 'owner', 'type' => 'bigint'),
						  array('name' => 'cuser', 'type' => 'bigint'),
						  array('name' => 'muser', 'type' => 'bigint'),
						  array('name' => 'ascandidate', 'type' => 'bigint'),		  
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
						  array('name' => 'role_types', 'type' => 'multi'),
						  array('name' => 'role_persons', 'type' => 'multi'),
						  array('name' => 'role_rates', 'type' => 'multi'),
						  array('name' => 'role_commtype', 'type' => 'multi'), 
						  array('name' => 'crmgroups', 'type' => 'multi'),		  
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
		mysql_query("REPLACE INTO sph_counter SELECT 'contacts_list',MAX(sno),'contacts',MAX(mdate) FROM staffoppr_contact");

		// instantiate the class
		$doc = new SphinxXMLFeed();

		// set the fields we will be indexing
		$doc->setFields($finalStringArray);
		// set any attributes
		
		$doc->setAttributes($finalNumericArray);
		$doc->beginOutput();
		// or other data source
		$maxidQ = mysql_fetch_array(mysql_query("select max(sno) from staffoppr_contact"));
		$maxid = $maxidQ[0];
		//$maxid = 100;
		$range = 1000;
		$x=0; $y=$range;  
		while($x <= $maxid)
		{
			$select_Sql = "SELECT DISTINCT(sc.sno),sc.fname,sc.mname,sc.lname,sc.nickname, sc.csno,t1.cname,sc.ytitle,CONCAT(sc.address1, ' ', sc.address2) AS address,sc.state,sc.city,sc.country AS country_id,t3.country AS country_name,sc.zipcode,sc.email,sc.email_2,sc.email_3,sc.accessto,CONCAT(IF(sc.accessto = 'ALL',CRC32('ALL'),IF(sc.accessto='', sc.owner, sc.accessto ))) AS crc_accessto,sc.cat_id,getContactCategory(sc.cat_id) AS category,sc.certifications,sc.source_name,sc.sourcetype AS sourcetype,t8.name AS sourcetype_name,sc.ctype,t2.name AS ctype_name,sc.department AS dep_id,t5.name AS dep_name,sc.deptid AS hrm_deptid,t6.deptname AS hrm_deptname,sc.description,sc.fax,sc.hphone_extn,sc.hphone,sc.mobile,sc.wphone,sc.wphone_extn,IF(areacode_fun(sc.hphone)!='',areacode_fun(sc.hphone),IF(areacode_fun(sc.wphone)!='',areacode_fun(sc.wphone),IF(areacode_fun(sc.mobile)!='',areacode_fun(sc.mobile),''))) AS areacode,areacode_fun(sc.wphone) AS wareacode,areacode_fun(sc.hphone) AS hareacode,areacode_fun(sc.mobile) AS mareacode,sc.importance,sc.dontcall,sc.dontemail,sc.other_extn,sc.other_info,sc.other,sc.spouse_name,sc.reportto_name,(SELECT buildContNotes_fun(sc.sno)) AS notes,(SELECT buildContactSearch_fun(sc.sno)) AS profile_data,IF(t10.contact_id!='', 1, 0)  AS ascandidate,sc.owner,t9.name AS owname,sc.approveuser AS cuser,t4.name AS createdby,sc.muser,t7.name AS modifiedby,UNIX_TIMESTAMP(sc.stime) AS ctime,(SELECT GROUP_CONCAT(DISTINCT(entity_roledetails.roleId)) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMContact' GROUP BY entity_roles.entityId) AS role_types,  (SELECT GROUP_CONCAT(DISTINCT(CRC32(empId))) AS e_ids FROM entity_roles WHERE entityId = sc.sno AND entityType='CRMContact' GROUP BY entityId) AS role_persons, (SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.rate))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMContact' GROUP BY entity_roles.entityId) AS role_rates, (SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.commissionType))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roledetails.commissionType!='' AND entity_roles.entityType='CRMContact' GROUP BY entity_roles.entityId) AS role_commtype,(SELECT GROUP_CONCAT(DISTINCT(gd.groupid)) AS e_ids FROM crmgroup_details AS gd JOIN crmgroups cg ON cg.sno = gd.groupid WHERE gd.refid = sc.sno AND cg.grouptype = 'Contact') AS crmgroupids,UNIX_TIMESTAMP(sc.mdate) AS mtime,udf.* FROM staffoppr_contact as sc LEFT JOIN staffoppr_cinfo t1 ON sc.csno = t1.sno LEFT JOIN manage t2 ON sc.ctype = t2.sno LEFT JOIN countries t3 ON sc.country = t3.sno LEFT JOIN users t4 ON sc.approveuser = t4.username LEFT JOIN manage t5 ON sc.department = t5.sno LEFT JOIN department t6 ON sc.deptid = t6.sno LEFT JOIN users t7 ON sc.muser = t7.username LEFT JOIN manage t8 ON sc.sourcetype = t8.sno LEFT JOIN users t9 ON sc.owner = t9.username LEFT JOIN candidate_list t10 ON sc.sno = t10.contact_id LEFT JOIN udf_form_details_contact_values udf ON sc.sno = udf.rec_id WHERE sc.status= 'ER' AND sc.sno > $x and sc.sno <= $y AND sc.sno <= (SELECT max_id FROM sph_counter WHERE counter_id='contacts_list' and module_id='contacts') order by sc.sno";			
			$results = $mysqli->query($select_Sql);
			if($results->num_rows!=0)
			{
				while($fetchRes=$results->fetch_assoc())
				{
					if($fetchRes['zipcode']!='')
					{
						$latandlong = getZipcodeLatLongitude($fetchRes['zipcode']);
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
									
									$buildcrc32_sql = "SELECT CONCAT(\"CRC32('\",REPLACE(".$getElementName['element_lable'].",\",\",\"'),CRC32('\"),\"')\") FROM udf_form_details_contact_values WHERE rec_id=".$fetchRes['sno'];
									
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
						'ytitle' => cleentext($fetchRes['ytitle'],'yes','yes'),
						'cname' => cleentext($fetchRes['cname'],'yes'),
						'csno' => $fetchRes['csno'],
						'contact_type' => cleentext($fetchRes['ctype_name'],'yes'),
						'ctype' => $fetchRes['ctype'],
						'category' => cleentext($fetchRes['category'],'yes'),
						'cat_id' => ($fetchRes['cat_id']==''?'0':$fetchRes['cat_id']),
						'dep_name' => cleentext($fetchRes['dep_name'],'yes'),
						'dep_id' => $fetchRes['dep_id'],
						'hrm_deptname' => cleentext($fetchRes['hrm_deptname'],'yes'),
						'hrm_deptid' => $fetchRes['hrm_deptid'],
						'source_name' => cleentext($fetchRes['sourcetype_name'],'yes'),
						'sourcetype' => $fetchRes['sourcetype'],
						'reportto_name' => cleentext($fetchRes['reportto_name'],'yes','yes'),
						'certifications' => cleentext($fetchRes['certifications'],'yes','yes'),
						'importance' => cleentext($fetchRes['importance'],'yes','yes'),
						'owner' => $fetchRes['owner'],
						'owname' => cleentext($fetchRes['owname'],'yes'),
						'cuser' => $fetchRes['cuser'],
						'createdby' => cleentext($fetchRes['createdby'],'yes'),
						'muser' => $fetchRes['muser'],
						'modifiedby' => cleentext($fetchRes['modifiedby'],'yes'),						
						'ascandidate' => $fetchRes['ascandidate'],
						'ascand_name' => ($fetchRes['ascandidate']==1?'Candidates':'Not Candidates'),
						'address' => cleentext($fetchRes['address'],'yes','yes'),
						'city' => cleentext($fetchRes['city'],'yes','yes'),
						'state' => cleentext($fetchRes['state'],'yes','yes'),
						'country_name' => cleentext($fetchRes['country_name'],'yes'),
						'country_id' => $fetchRes['country_id'],
						'zip' => cleentext($fetchRes['zipcode'],'yes','yes'),
						'areacode' => cleentext($fetchRes['areacode'],'yes','yes'),
						'wareacode' => cleentext($fetchRes['wareacode'],'yes','yes'),
						'hareacode' => cleentext($fetchRes['hareacode'],'yes','yes'),
						'mareacode' => cleentext($fetchRes['mareacode'],'yes','yes'),
						'label_akken' => '__AKKEN__',
						'dontcall' => cleentext(($fetchRes['dontcall']=='Y'?'Yes':'No'),'yes','yes'),
						'dontemail' => cleentext(($fetchRes['dontemail']=='Y'?'Yes':'No'),'yes','yes'),
						'crc_accessto' => $fetchRes['crc_accessto'],
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
				}  // While End
			} // if end for total rows
			$x=$y;
			$y=$y+$range;
			
			// Frees the memory associated with a result
			$results->free();
		}
		// Render the XML
		$doc->endOutput();
	}else if($mode=="delta"){
		
		//Activated the New UDF filter columns
		$getudfactivestatus = mysql_query("UPDATE sphinx_filter_columns SET status='1' WHERE index_col_name IN (SELECT CONCAT_WS('_','cust',id) FROM udf_form_details WHERE sphinx_indexing=1 AND sphinx_searchable=1 AND module=1 and element!='textarea' AND status='Active')",$db);
		
		// instantiate the class
		$doc = new SphinxXMLFeed();

		// set the fields we will be indexing
		$doc->setFields($finalStringArray);
		// set any attributes
		
		$doc->setAttributes($finalNumericArray);
		$doc->beginOutput();
		// or other data source
		$select_Sql = "SELECT DISTINCT(sc.sno),sc.fname,sc.mname,sc.lname,sc.nickname, sc.csno,t1.cname,sc.ytitle,CONCAT(sc.address1, ' ', sc.address2) AS address,sc.state,sc.city,sc.country AS country_id,t3.country AS country_name,sc.zipcode,sc.email,sc.email_2,sc.email_3,sc.accessto,CONCAT(IF(sc.accessto = 'ALL',CRC32('ALL'),IF(sc.accessto='', sc.owner, sc.accessto ))) AS crc_accessto,sc.cat_id,getContactCategory(sc.cat_id) AS category,sc.certifications,sc.source_name,sc.sourcetype AS sourcetype,t8.name AS sourcetype_name,sc.ctype,t2.name AS ctype_name,sc.department AS dep_id,t5.name AS dep_name,sc.deptid AS hrm_deptid,t6.deptname AS hrm_deptname,sc.description,sc.fax,sc.hphone_extn,sc.hphone,sc.mobile,sc.wphone,sc.wphone_extn,IF(areacode_fun(sc.hphone)!='',areacode_fun(sc.hphone),IF(areacode_fun(sc.wphone)!='',areacode_fun(sc.wphone),IF(areacode_fun(sc.mobile)!='',areacode_fun(sc.mobile),''))) AS areacode,areacode_fun(sc.wphone) AS wareacode,areacode_fun(sc.hphone) AS hareacode,areacode_fun(sc.mobile) AS mareacode,sc.importance,sc.dontcall,sc.dontemail,sc.other_extn,sc.other_info,sc.other,sc.spouse_name,sc.reportto_name,(SELECT buildContNotes_fun(sc.sno)) AS notes,(SELECT buildContactSearch_fun(sc.sno)) AS profile_data,IF(t10.contact_id!='', 1, 0)  AS ascandidate,sc.owner,t9.name AS owname,sc.approveuser AS cuser,t4.name AS createdby,sc.muser,t7.name AS modifiedby,UNIX_TIMESTAMP(sc.stime) AS ctime,(SELECT GROUP_CONCAT(DISTINCT(entity_roledetails.roleId)) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMContact' GROUP BY entity_roles.entityId) AS role_types,  (SELECT GROUP_CONCAT(DISTINCT(CRC32(empId))) AS e_ids FROM entity_roles WHERE entityId = sc.sno AND entityType='CRMContact' GROUP BY entityId) AS role_persons, (SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.rate))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roles.entityType='CRMContact' GROUP BY entity_roles.entityId) AS role_rates, (SELECT GROUP_CONCAT(DISTINCT(CRC32(entity_roledetails.commissionType))) AS e_ids FROM entity_roledetails, entity_roles WHERE entity_roles.entityId = sc.sno AND entity_roles.crsno=entity_roledetails.crsno AND entity_roledetails.commissionType!='' AND entity_roles.entityType='CRMContact' GROUP BY entity_roles.entityId) AS role_commtype,(SELECT GROUP_CONCAT(DISTINCT(gd.groupid)) AS e_ids FROM crmgroup_details AS gd JOIN crmgroups cg ON cg.sno = gd.groupid WHERE gd.refid = sc.sno AND cg.grouptype = 'Contact') AS crmgroupids, UNIX_TIMESTAMP(sc.mdate) AS mtime,udf.* FROM staffoppr_contact as sc LEFT JOIN staffoppr_cinfo t1 ON sc.csno = t1.sno LEFT JOIN manage t2 ON sc.ctype = t2.sno LEFT JOIN countries t3 ON sc.country = t3.sno LEFT JOIN users t4 ON sc.approveuser = t4.username LEFT JOIN manage t5 ON sc.department = t5.sno LEFT JOIN department t6 ON sc.deptid = t6.sno LEFT JOIN users t7 ON sc.muser = t7.username LEFT JOIN manage t8 ON sc.sourcetype = t8.sno LEFT JOIN users t9 ON sc.owner = t9.username LEFT JOIN candidate_list t10 ON sc.sno = t10.contact_id LEFT JOIN udf_form_details_contact_values udf ON sc.sno = udf.rec_id WHERE sc.status= 'ER' AND (sc.sno > (SELECT max_id FROM sph_counter WHERE counter_id='contacts_list' and module_id='contacts') OR sc.mdate > (SELECT last_updated FROM sph_counter WHERE counter_id='contacts_list' and module_id='contacts')) order by sc.sno";
			$results = $mysqli->query($select_Sql);
			if($results->num_rows!=0)
			{
				while($fetchRes=$results->fetch_assoc())
				{
					if($fetchRes['zipcode']!='')
					{
						$latandlong = getZipcodeLatLongitude($fetchRes['zipcode']);
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
									
									$buildcrc32_sql = "SELECT CONCAT(\"CRC32('\",REPLACE(".$getElementName['element_lable'].",\",\",\"'),CRC32('\"),\"')\") FROM udf_form_details_contact_values WHERE rec_id=".$fetchRes['sno'];
									
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
						'ytitle' => cleentext($fetchRes['ytitle'],'yes','yes'),
						'cname' => cleentext($fetchRes['cname'],'yes'),
						'csno' => $fetchRes['csno'],
						'contact_type' => cleentext($fetchRes['ctype_name'],'yes'),
						'ctype' => $fetchRes['ctype'],
						'category' => cleentext($fetchRes['category'],'yes'),
						'cat_id' => ($fetchRes['cat_id']==''?'0':$fetchRes['cat_id']),
						'dep_name' => cleentext($fetchRes['dep_name'],'yes'),
						'dep_id' => $fetchRes['dep_id'],
						'hrm_deptname' => cleentext($fetchRes['hrm_deptname'],'yes'),
						'hrm_deptid' => $fetchRes['hrm_deptid'],
						'source_name' => cleentext($fetchRes['sourcetype_name'],'yes'),
						'sourcetype' => $fetchRes['sourcetype'],
						'reportto_name' => cleentext($fetchRes['reportto_name'],'yes','yes'),
						'certifications' => cleentext($fetchRes['certifications'],'yes','yes'),
						'importance' => cleentext($fetchRes['importance'],'yes','yes'),
						'owner' => $fetchRes['owner'],
						'owname' => cleentext($fetchRes['owname'],'yes'),
						'cuser' => $fetchRes['cuser'],
						'createdby' => cleentext($fetchRes['createdby'],'yes'),
						'muser' => $fetchRes['muser'],
						'modifiedby' => cleentext($fetchRes['modifiedby'],'yes'),						
						'ascandidate' => $fetchRes['ascandidate'],
						'ascand_name' => ($fetchRes['ascandidate']==1?'Candidates':'Not Candidates'),
						'address' => cleentext($fetchRes['address'],'yes','yes'),
						'city' => cleentext($fetchRes['city'],'yes','yes'),
						'state' => cleentext($fetchRes['state'],'yes','yes'),
						'country_name' => cleentext($fetchRes['country_name'],'yes'),
						'country_id' => $fetchRes['country_id'],
						'zip' => cleentext($fetchRes['zipcode'],'yes','yes'),
						'areacode' => cleentext($fetchRes['areacode'],'yes','yes'),
						'wareacode' => cleentext($fetchRes['wareacode'],'yes','yes'),
						'hareacode' => cleentext($fetchRes['hareacode'],'yes','yes'),
						'mareacode' => cleentext($fetchRes['mareacode'],'yes','yes'),
						'label_akken' => '__AKKEN__',
						'dontcall' => cleentext(($fetchRes['dontcall']=='Y'?'Yes':'No'),'yes','yes'),
						'dontemail' => cleentext(($fetchRes['dontemail']=='Y'?'Yes':'No'),'yes','yes'),
						'crc_accessto' => $fetchRes['crc_accessto'],
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
		/*** Print kill list*/
		$killList_Sql = "SELECT staffoppr_contact.sno FROM staffoppr_contact WHERE staffoppr_contact.status = 'INACTIVE' AND (staffoppr_contact.sno > (SELECT max_id FROM sph_counter WHERE counter_id='contacts_list' and module_id='contacts') OR staffoppr_contact.mdate > (SELECT last_updated FROM sph_counter WHERE counter_id='contacts_list' and module_id='contacts')) ORDER BY staffoppr_contact.sno";
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