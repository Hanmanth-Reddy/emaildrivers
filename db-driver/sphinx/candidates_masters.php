<?php 
	while (@ob_end_flush());
	// Set include folder
	error_reporting(E_ALL ^ E_NOTICE);
	$include_path=dirname(__FILE__);
	ini_set('memory_limit',-1);
	ini_set("max_execution_time", -1);
	ini_set('display_errors', 0);
	ini_set("include_path",$include_path);
	require("../global.inc");
	require("SphinxXMLFeed.php");

	if($_GET['companyuser']!='')
	{
		$companyuser=$_GET['companyuser'];
	}else
	{
		echo "Error - The selected company is not configured.";
		exit;
	}
	
	//$companyuser="nagarajuma";
	require("../maildatabase.inc");
	require("../database.inc");

	function cleentext($resumeData,$nullCase='no')
	{
			$resumeData = str_replace(array(">","<","'",'"','|','-','^'),array("","","",'','',' ',''),$resumeData);
			$resumeData = preg_replace("/[\\x00-\\x1F\\x80-\\xFF]/", " ", $resumeData);
			$resumeData = strip_tags($resumeData);
			$resumeData = preg_replace('/[^A-Za-z0-9\. -]/', ' ', $resumeData);
			$resumeData = trim($resumeData);
			$resumeData = preg_replace('/\s+/', ' ',$resumeData);
			if($nullCase=="yes" && $resumeData==''){ $resumeData = 'null';}
			return $resumeData;
	}

		// instantiate the class
		$doc = new SphinxXMLFeed();

		// set the fields we will be indexing
		$doc->setFields(array(
			'mtype',
			'mvalue'			
		));
		// set any attributes		
		
		$doc->beginOutput();
		// or other data source

		$select_Sql = "SELECT sno,mtype,mvalue FROM candidate_master WHERE mvalue !='' order by sno";
		$res_Sql = mysql_query($select_Sql, $db) or die(mysql_error());
		if(mysql_num_rows($res_Sql)!=0)
		{
			while($fetchRes=mysql_fetch_assoc($res_Sql))
			{

				$doc->addDocument(array(
				'id' => $fetchRes['sno'],
				'mtype' => $fetchRes['mtype'],
				'mvalue' => cleentext($fetchRes['mvalue'])
				));
			}
		}

		// Render the XML
		$doc->endOutput();	
?>
