<?php

	function totalcols($headers, $types){
		$str='';
		foreach($types as $typekey => $typeval){
			array_push($headers,"<table><tr><td colspan='2' align='center'><font class=afontstyleee>".$typeval['name']."</font></td></tr><tr><td><font class=afontstyleee> Hours</font></td><td><font class=afontstyleee> Billable</font></td></tr></table>");
		}
		return count($headers);
	}
	
	function getTimesheetData($sno, $db){
		$time_display_data = array();
		
			$sql = "SELECT count(*), th.assid, th.client, sc.cname, th.sno, th.sdate, th.type, th.username, th.status, hj.project,".tzRetQueryStringDate('pt.sdate','Date','/')." AS pstartdate,DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, ".tzRetQueryStringDate('th.edate','Date','/')." AS enddate, DATE_FORMAT( th.sdate, '%W' ) AS weekday,".tzRetQueryStringDate('th.sdate','Date','/')." AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y' ) AS starttimedate, GROUP_CONCAT( DISTINCT CONCAT( hourstype, '|', hours, '|', IF( billable = '', 'No', billable ) ) ) AS time_data, SUM( th.hours ) AS sumhours 
	FROM par_timesheet pt 
	INNER JOIN timesheet_hours th ON pt.sno = th.parid
			LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername
			LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno
			INNER JOIN emp_list el ON el.username = pt.username WHERE th.parid ='".$sno."' AND pt.astatus IN ('ER','Rejected') and th.status IN ('ER','Rejected') and th.username = pt.username GROUP BY th.sdate;
			";
		
		
		$ressel=mysql_query($sql,$db);
		
		while($myrow=mysql_fetch_array($ressel))
		{
			$time_display_data[] = $myrow;
		}
		
		return $time_display_data;
	}
	
	function essTimesheetData_approved($sno, $db){
		$time_display_data = array();
		
			$sql = "SELECT count(*), th.assid, th.client, sc.cname, th.sno, th.sdate, th.type, th.username, th.status, hj.project,DATE_FORMAT(pt.sdate,'%m/%d/%Y') AS pstartdate,DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, DATE_FORMAT(th.edate,'%m/%d/%Y') AS enddate, DATE_FORMAT( th.sdate, '%W' ) AS weekday,DATE_FORMAT(th.sdate,'%m/%d/%Y') AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y' ) AS starttimedate, GROUP_CONCAT( DISTINCT CONCAT( hourstype, '|', hours, '|', IF( billable = '', 'No', billable ) ) ) AS time_data, SUM( th.hours ) AS sumhours 
		FROM par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername
		LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno INNER JOIN emp_list el ON el.username = pt.username 
	 WHERE th.parid ='".$sno."'  and th.status IN ('Approved','Billed') GROUP BY th.sdate";
		
		$ressel=mysql_query($sql,$db);
		while($myrow=mysql_fetch_array($ressel))
		{
			$time_display_data[] = $myrow;
		}
		
		return $time_display_data;
	}
	 
	function getCssTimesheetData($approvetime, $db){
		$time_display_data = array();
		$sql = "SELECT count(*), th.assid, th.client, sc.cname, th.sno, th.sdate, th.type, th.username, th.status, hj.project,DATE_FORMAT(pt.sdate,'%m/%d/%Y') AS pstartdate,DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, DATE_FORMAT(th.edate,'%m/%d/%Y') AS enddate, DATE_FORMAT( th.sdate, '%W' ) AS weekday,DATE_FORMAT(th.sdate,'%m/%d/%Y') AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y' ) AS starttimedate, GROUP_CONCAT(th.sno) AS time_data, SUM( th.hours ) AS sumhours,th.task as task 
		FROM par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername
		LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno INNER JOIN emp_list el ON el.username = pt.username 
		where th.billable!='' AND th.billable!='no' AND th.status = 'Backup' and th.approvetime = '".$approvetime."' AND hj.ustatus IN ('active','cancel','closed') GROUP BY th.sdate,th.task,th.assid order by th.approvetime DESC,th.edate DESC";
		$ressel=mysql_query($sql,$db);
		while($myrow=mysql_fetch_array($ressel))
		{
			$time_display_data[] = $myrow;
		}
		return $time_display_data;
	}
	
	function formatDispTimeData($data, $RateTypes){$ValueHeaders = array();
		
		$sdatets="";
		$stscount="";
		$sum = 0;
		
		foreach($data as $datakey => $dataval) {
		$ValueHeaders1 = array();
		$ValueHeaders2 = array();
		$flag =0;
		$flag1 =0;
			$sum = $sum +$data[$datakey]['sumhours'];
			if($sdatets==""){
				$sdatets= date('d/m/Y', strtotime($data[$datakey]['startdate']));
			}else{
				$sdatets.="|".date('d/m/Y', strtotime($data[$datakey]['startdate']));
			}
			if($stscount==""){
				$stscount=$data[$datakey][0];
			}else{
				$stscount.="|".$data[$datakey][0];
			}
			$end_date = (date('d/m/Y', strtotime($data[$datakey]['enddate']))=='30/11/1999') ? ' ' : date('d/m/Y', strtotime($data[$datakey]['enddate']));
				
			foreach($RateTypes as $typekey => $typeval){
			    $flag1 = $flag1 +1;
				$i=0;
				$sql_qry = "select sum(hours),billable from timesheet_hours where sno in (".$data[$datakey]['time_data'].") and hourstype='".$typeval['rateid']."' group by billable";
				$ressel_res=mysql_query($sql_qry);
				$ressel_count=mysql_num_rows($ressel_res);
				 if($ressel_count == 2)
				 {
				        
				        $m=-1;
						while($t_res=mysql_fetch_array($ressel_res))
						{
						  $flag = $flag +1;
					    $st = "<table><tr><td><font class=afontstylee>".$t_res[0]."</font></td><td><font class=afontstylee>";
						if($t_res[1] == 'Yes') { $st .= "<input type='checkbox' checked='checked' disabled='disabled'/>"; } else { $st .= "<input type='checkbox'  disabled='disabled'/>";
							}
							$st .= "</td></tr></table>";
							if($m == -1)
							array_push($ValueHeaders1,$st);
							//$st1 .= $st;
							if($m == 0)
							array_push($ValueHeaders2,$st);
							//$st2 .= $st;
							$m=$m+1;
						} //con for while
				} //con for if
				else if($ressel_count == 1){
				         $flag = $flag +1;
						$t_res=mysql_fetch_array($ressel_res);
						$st = "<table><tr><td><font class=afontstylee>".$t_res[0]."</font></td><td><font class=afontstylee>";
						if($t_res[1] == 'Yes') { $st .= "<input type='checkbox' checked='checked' disabled='disabled'/>"; } else { $st .= "<input type='checkbox'  disabled='disabled'/>";
							}
							$st .= "</td></tr></table>";
							array_push($ValueHeaders1,$st);
							array_push($ValueHeaders2,'');
											
				} // else if 
				else if($ressel_count == 0){
				             $flag = $flag +1;
						  array_push($ValueHeaders1,'');
						  array_push($ValueHeaders2,'');
		       } // else if 
				 
			} // foreach for ratetype
			if($flag==$flag1) {
			 array_push($ValueHeaders," <table><tr><td><font class=afontstylee><input type='checkbox' onclick='chk_clearTop_TimeSheet()' value='".$data[$datakey]['time_data']."' id='chk".($datakey+1)."' name='auids[]'></font></td></tr></table>");
			 array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>".date('d/m/Y', strtotime($data[$datakey]['startdate']))."-".$end_date." ".$data[$datakey]['weekday']."</font></td></tr></table>");
			array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>(".$data[$datakey]['assid'].")".$data[$datakey]['cname']."-".$data[$datakey]['project']."-".$data[$datakey]['task']."</font></td></tr></table>");
			if(MANAGE_CLASSES == 'Y')
			array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>".$data[$datakey]['classname']."</font></td></tr></table>");
			foreach($ValueHeaders1 as $datakey1 => $dataval1) {
		    array_push($ValueHeaders,$dataval1);
			}
			} else if($flag > $flag1) {
			     
			 array_push($ValueHeaders," <table><tr><td><font class=afontstylee><input type='checkbox' onclick='chk_clearTop_TimeSheet()' value='".$data[$datakey]['time_data']."' id='chk".($datakey+1)."' name='auids[]'></font></td></tr></table>");
			 array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>".date('d/m/Y', strtotime($data[$datakey]['startdate']))."-".$end_date." ".$data[$datakey]['weekday']."</font></td></tr></table>");
			array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>(".$data[$datakey]['assid'].")".$data[$datakey]['cname']."-".$data[$datakey]['project']."-".$data[$datakey]['task']."</font></td></tr></table>");
			if(MANAGE_CLASSES == 'Y')
			array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>".$data[$datakey]['classname']."</font></td></tr></table>");
		    foreach($ValueHeaders1 as $datakey1 => $dataval1) {
		    array_push($ValueHeaders,$dataval1);
			}
			
			array_push($ValueHeaders," <table><tr><td><font class=afontstylee><input type='checkbox' onclick='chk_clearTop_TimeSheet()' value='".$data[$datakey]['time_data']."' id='chk".($datakey+1)."' name='auids[]'></font></td></tr></table>");
			array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>".date('d/m/Y', strtotime($data[$datakey]['startdate']))."-".$end_date." ".$data[$datakey]['weekday']."</font></td></tr></table>");
			array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>(".$data[$datakey]['assid'].")".$data[$datakey]['cname']."-".$data[$datakey]['project']."-".$data[$datakey]['task']."</font></td></tr></table>");
			if(MANAGE_CLASSES == 'Y')
			array_push($ValueHeaders,"<table><tr><td><font class=afontstylee>".$data[$datakey]['classname']."</font></td></tr></table>");
		   foreach($ValueHeaders2 as $datakey2 => $dataval2) {
		    array_push($ValueHeaders,$dataval2);
			
			}
			}
				
			
		}  // foreach
		return $ValueHeaders;}
	
	function totalSum($data){
		$sum = 0;
		foreach($data as $datakey => $dataval) {

			$sum = $sum +$data[$datakey]['sumhours'];

		}
		return $sum;
	}	
?>

<style type="text/css">
	.afontstylee {
    color: black;
    font-family: Arial;
    font-size: 8pt;
    font-style: normal;
    line-height: 10px;
	padding-left:7px;
	padding-right:7px;
	}
.afontstyleee {
    color: black;
    font-family: Arial;
    font-size: 8pt;
    font-style: normal;
    line-height: 4px;
	padding-left:7px;
	padding-right:7px;
	}
	.modcaption { FONT-SIZE: 12pt; FONT-FAMILY: Arial; FONT-WEIGHT: bold; COLOR: #F02933; }

	.hthbgcolorr {background-color:#78daf7;}
	.tr2bgcolor,tr1bgcolor {background-color:#FFFFFF;}
	.grid_forms {width:auto; overflow-x:scroll; position:relative;}

	.afontstylee {
    color: black;
    font-family: Arial;
    font-size: 8pt;
    font-style: normal;width:30px;width: 31px\9;
   
	
	
}
</style>

<style type="text/css">
a {
    display: inline-block;
    position: relative;
}
.caption {
    display: none;
    position: absolute;
    top:15;
	 font-family: Arial;
    font-size: 8pt;
    font-style: normal;
    left: 20;
    right: 0;
  
    color:#000000;
   
	
}
a:hover .caption {
    display: inline-block;

</style>