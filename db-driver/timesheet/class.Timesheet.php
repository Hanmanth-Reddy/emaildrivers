<?php
class AkkenTimesheet
{
    public $db;
    public $userName;
    public $module;
    public $mysqlobj;
    public $new_first_user;
    public $assignments;
    public $assignmentIds;
    public $rateTypeCountSingle;
	public $accountingExport;
    public $mystr = array();
    public $hiddenBillable = array();
	
    function __construct($db)
    {
		global $db;
	$this->db = $db;
	require_once('timesheet/class.MysqlWraper.php');
	$this->mysqlobj = new MysqlWraper();
    }
    
    function sel($a,$b)
    {
	if($a==$b)
	{
	    return "selected";
	}
	else
	{
	    return "";
	}
    }
    
    function chk($a)
    {
	if($a=='N' || $a=='')
	{
	    return "";
	}
	else
	{
	    return "checked";
	}
    }    

    function disable($a)
    {
	if($a!='Y')
	{
	    return "disabled";    
	}
	else
	{
	    return "";
	}
    }
    
    function output($data)
    {
	echo "<pre>";
	print_r($data);
	echo "</pre>";
	echo "------------------------------<br>";
    }
    
    function buildEmpList($emp_array, $selectedEmpList='')
    {
	foreach ($emp_array as $emp_id => $emps)
	{
	    $selEmp = $this->sel($emp_id, $selectedEmpList);		
	    $emp_list .= '<option value="'.$emp_id.'" '.$selEmp.'>'.$emps.'</option>';
	}
	return $emp_list;
    }
    
    function getAccountingEmployeeNames($username, $assign_start_date, $assign_end_date)
    {
	$assign_start_date = date('Y-m-d', strtotime($assign_start_date));
	$assign_end_date = date('Y-m-d', strtotime($assign_end_date));
	
	$query="SELECT emp_list.username uid, emp_list.name name,
	CONCAT(hrcon_general.lname,' ', hrcon_general.fname,' ',hrcon_general.mname,'-', emp_list.sno)
	FROM emp_list, hrcon_jobs, hrcon_compen, hrcon_general
	WHERE emp_list.username = hrcon_jobs.username
	AND emp_list.username = hrcon_compen.username
	AND emp_list.username = hrcon_general.username
	AND emp_list.lstatus != 'DA'
	AND emp_list.lstatus != 'INACTIVE'
	AND (emp_list.empterminated != 'Y' || (UNIX_TIMESTAMP(DATE_FORMAT(emp_list.tdate,'%Y-%m-%d'))-UNIX_TIMESTAMP())>0)
	AND ((hrcon_jobs.ustatus IN ('active','closed','cancel') AND (hrcon_jobs.s_date IS NULL OR hrcon_jobs.s_date='' OR hrcon_jobs.s_date='0-0-0' OR (DATE(STR_TO_DATE(s_date,'%m-%d-%Y')) <= '".$assign_end_date."'))) AND (IF(hrcon_jobs.ustatus='closed',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assign_start_date."'),1)) AND (IF(hrcon_jobs.ustatus='cancel',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assign_start_date."'),1)))
	AND hrcon_jobs.ustatus != ''
	AND hrcon_compen.ustatus != 'backup'
	AND hrcon_jobs.jtype != ''
	AND hrcon_jobs.pusername != ''
	AND hrcon_compen.timesheet != 'Y'
	AND hrcon_general.ustatus != 'backup'
	GROUP BY emp_list.username, emp_list.name
	ORDER BY trim(hrcon_general.lname),trim(hrcon_general.fname),trim(hrcon_general.mname)";	
	$result=$this->mysqlobj->query($query,$this->db);
	
	$new_first_user = "";
	$empCount = 0;
	$names = array();
	while($myrow=$this->mysqlobj->fetch_row($result))
	{
	    if($empCount == 0)
	    {
		$this->new_first_user = $myrow[0];
	    }
	    $names[$myrow[0]] = $myrow[2];	    
	    $empCount++;
	}
	return $names;
    }
    
    function getClientEmployeeNames($username, $assign_start_date=NULL, $assign_end_date=NULL)
    {
	$assign_start_date = date('Y-m-d', strtotime($assign_start_date));
	$assign_end_date = date('Y-m-d', strtotime($assign_end_date));
	
	$sel="SELECT t2.con_id, t3.sno FROM staffacc_contact t1 INNER JOIN staffacc_contactacc t2
	ON t1.sno = t2.con_id INNER JOIN staffacc_cinfo t3 ON t3.username = t1.username WHERE t3.TYPE IN ('CUST', 'BOTH')
	AND t2.username = ".$username;
	$resselSno=$this->mysqlobj->query($sel, $this->db);
	$rsselSno=$this->mysqlobj->fetch_array($resselSno);
	
	$CtVal=$rsselSno['con_id'];
	$ClVal=$rsselSno['sno'];
	
	$sqlSelfPref="select timesheet from selfservice_pref where username='".$username."'";
	$resSelfPref=$this->mysqlobj->query($sqlSelfPref,$this->db);
	$userSelfServicePref=$this->mysqlobj->fetch_row($resSelfPref);

	if(strpos($userSelfServicePref[0],"+4+") || strpos($userSelfServicePref[0],"+5+") )
	{
		if(strpos($userSelfServicePref[0],"+4+"))
			$chkContact = "OR hrcon_jobs.contact = ".$CtVal."";
			
		$showEmplyoees="AND (hrcon_jobs.manager=".$CtVal." ".$chkContact.")";
	}

	$condCk_comp=" hrcon_jobs.client=".$ClVal." AND";

	$query="SELECT emp_list.username uid, emp_list.name name,
	CONCAT(hrcon_general.lname,' ', hrcon_general.fname,' ',hrcon_general.mname,'-', emp_list.sno)
	FROM emp_list, hrcon_jobs, hrcon_compen, hrcon_general
	WHERE
	$condCk_comp
	emp_list.username = hrcon_jobs.username
	AND emp_list.username = hrcon_compen.username
	AND emp_list.username = hrcon_general.username
	AND emp_list.lstatus != 'DA'
	AND emp_list.lstatus != 'INACTIVE'
	AND (emp_list.empterminated != 'Y' || (UNIX_TIMESTAMP(DATE_FORMAT(emp_list.tdate,'%Y-%m-%d'))-UNIX_TIMESTAMP())>0)
	AND hrcon_jobs.ustatus IN ('active','closed','cancel')
	AND hrcon_jobs.ustatus != ''
	AND hrcon_compen.ustatus != 'backup'
	AND hrcon_jobs.jtype != ''
	AND hrcon_jobs.pusername!=''
	AND hrcon_general.ustatus != 'backup'
	AND hrcon_compen.timesheet != 'Y'
	$showEmplyoees 		
	AND ((hrcon_jobs.ustatus IN ('active','closed','cancel')
	AND (hrcon_jobs.s_date IS NULL OR hrcon_jobs.s_date='' OR hrcon_jobs.s_date='0-0-0' OR DATE(STR_TO_DATE(s_date,'%m-%d-%Y'))
	<= '".$assign_end_date."')))
	AND (IF(hrcon_jobs.ustatus='closed',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>''
	AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assign_start_date."'),1))
	AND (IF(hrcon_jobs.ustatus='cancel',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>''
	AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assign_start_date."'),1))	
	GROUP BY emp_list.username, emp_list.name
	ORDER BY trim(hrcon_general.lname),trim(hrcon_general.fname),trim(hrcon_general.mname)";
	$result=$this->mysqlobj->query($query,$this->db);

	$new_first_user = "";
	$empCount = 0;
	$names = array();
	while($myrow=$this->mysqlobj->fetch_row($result))
	{
	    if($empCount == 0)
	    {
		$this->new_first_user = $myrow[0];
	    }
	    $names[$myrow[0]] = $myrow[2];	    
	    $empCount++;
	}
	return $names;		
    }
    
    function getMyProfileEmployeeNames($username, $assign_start_date, $assign_end_date)
    {
	$this->new_first_user = $username;
	return $username;	
    }
    
   /* function GetDays($sStartDate, $sEndDate)
    {  
	//$sStartDate = gmdate("Y-m-d", strtotime($sStartDate));  
	//$sEndDate = gmdate("Y-m-d", strtotime($sEndDate));
	$sStartDate = gmdate("m/d/Y", strtotime($sStartDate));  
	$sEndDate = gmdate("m/d/Y", strtotime($sEndDate));  
	$aDays[] = $sStartDate." ".date('l', strtotime($sStartDate)); 
	$sCurrentDate = $sStartDate;  

	while($sCurrentDate < $sEndDate){  
		//$sCurrentDate = gmdate("Y-m-d", strtotime("+1 day", strtotime($sCurrentDate)));
		$sCurrentDate = gmdate("m/d/Y", strtotime("+1 day", strtotime($sCurrentDate)));  
		$aDays[] = $sCurrentDate." ".date('l', strtotime( $sCurrentDate));
	} 
	return $aDays;  
    }  */
    
	function getPayrollWeekendDay(){
		$sel="SELECT payperiod, stdhours,wdays,pdays,paydays,weekend_day,taxbasedon FROM cpaysetup WHERE STATUS='ACTIVE'";
		$res=$this->mysqlobj->query($sel, $this->db);
		$getWeekendDay= $this->mysqlobj->fetch_array($res);
		$WeekendDay = $getWeekendDay['weekend_day'];
		return $WeekendDay;
		
	}

function GetDays($strDateFrom,$strDateTo)
        {
            // takes two dates formatted as YYYY-MM-DD and creates an
            // inclusive array of the dates between the from and to dates.

            // could test validity of dates here but I'm already doing
            // that in the main script
            $strDateFrom = gmdate("Y-m-d", strtotime($strDateFrom));
            $strDateTo = gmdate("Y-m-d", strtotime($strDateTo));
            $aryRange=array();

            $iDateFrom=mktime(1,0,0,substr($strDateFrom,5,2),     substr($strDateFrom,8,2),substr($strDateFrom,0,4));
            $iDateTo=mktime(1,0,0,substr($strDateTo,5,2),     substr($strDateTo,8,2),substr($strDateTo,0,4));

            if ($iDateTo>=$iDateFrom)
            {
                array_push($aryRange,date('m/d/Y',$iDateFrom).' '.date('l', strtotime( date('m/d/Y',$iDateFrom))));// first entry
                while ($iDateFrom<$iDateTo)
                {
                    $iDateFrom+=86400; // add 24 hours
                    //$next = $iDateFrom+=86400;
                    array_push($aryRange,date('m/d/Y',$iDateFrom).' '.date('l', strtotime( date('m/d/Y',$iDateFrom))));
                }
            }
            return $aryRange;
        }


    function getWeekdays($day)
    {
	$getWeekDays = array();
	$d = $this->getPayrollWeekendDay();
	#echo $last_monday = date("m/d/Y",strtotime($day." last Monday "));
	$last_monday = date("m/d/Y",strtotime($day." last ".$d ));
	$start_day = date('m/d/Y', strtotime(" -6 day",strtotime($last_monday)));
	    
	if(date('l', strtotime($day))=='Monday')
	{
	    $monday = date('m/d/Y', strtotime($last_monday))." ".date('l', strtotime($last_monday)); 
	}
	else
	{
	    $monday = date('m/d/Y', strtotime(' -7 day',strtotime($last_monday)))." ".date('l', strtotime($last_monday)); 
	}
	for($i=0;$i<7; $i++)
	{
	    $getWeekDays[] = date('m/d/Y', strtotime(" +".$i." day",strtotime($start_day)))." ".date('l', strtotime(" +".$i." day",strtotime($start_day)));
	}
	return $getWeekDays;
    }
    
    function getAssignments($employee, $asgnid='', $assignStartDate0, $assignEndDate0, $rowid,$module='', $tab_index = '', $inout_flag = false, $cval = '')
    {
	global $companyname;
	$assignOptions = '';
	
	$assignStartDate = date('Y-m-d', strtotime($assignStartDate0));
	$assignEndDate = date('Y-m-d', strtotime($assignEndDate0));
	
	if($module == "Client" && $cval != ''){
	    $client_cond = " AND client = '".$cval."' ";
	}
	
	$zque = "SELECT sno, client, project, jtype, pusername,jotype, date_format(str_to_date(s_date,'%m-%d-%Y'),'%m/%d/%Y'), date_format(str_to_date(e_date,'%m-%d-%Y'),'%m/%d/%Y') FROM hrcon_jobs WHERE username = '".$employee."' AND pusername!='' AND ((hrcon_jobs.ustatus IN ('active','closed','cancel') AND (hrcon_jobs.s_date IS NULL OR hrcon_jobs.s_date='' OR hrcon_jobs.s_date='0-0-0' OR (DATE(STR_TO_DATE(s_date,'%m-%d-%Y'))<='".$assignEndDate."'))) AND (IF(hrcon_jobs.ustatus='closed',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assignStartDate."'),1)) AND (IF(hrcon_jobs.ustatus='cancel',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assignStartDate."'),1))) AND hrcon_jobs.jtype!='' ".$client_cond." ORDER BY pusername";
	
	$zres=$this->mysqlobj->query($zque,$this->db);
	$zrowCount = mysql_num_rows($zres);
	
	$this->assignments = array();
	$this->assignmentIds = array();
	    
	while($zrow=$this->mysqlobj->fetch_array($zres))
	{
	    $this->assignments[] = $zrow[4];
	    $this->assignmentIds[] = $zrow[0];
	    
	    if($zrow[1] != '0')
	    {
		$que = "SELECT cname, ".getEntityDispName('sno', 'cname', 1)." FROM staffacc_cinfo WHERE type IN ('CUST', 'BOTH') AND sno=".$zrow[1];
		$res=$this->mysqlobj->query($que,$this->db);
		$row=$this->mysqlobj->fetch_row($res);
		$companyname1=$row[1];
	    }
	    else
	    {
		$companyname1=$companyname;
	    }
	    
	    if($zrow[6] == '00/00/2000' || $zrow[6] == NULL || $zrow[6] == '')
	    {
		$asgnStartDate = "No Start Date";		
	    }
	    else
	    {
		$asgnStartDate = $zrow[6];
	    }
							    
	    if($zrow[7] == '00/00/2000' || $zrow[7] == NULL || $zrow[7] == '')
	    {
		$asgnEndDate = "No End Date";
	    }
	    else
	    {
		$asgnEndDate = $zrow[7];
	    }    
	    if($asgnStartDate == "No Start Date" && $asgnEndDate == "No End Date")
	    {
		    $startEnddate = "";
	    }
	    else
	    {
		$startEnddate = "(".$asgnStartDate." - ".$asgnEndDate.")";
	    }
	    
	    if($zrow[3]=="AS")
	    {
		$flg = $this->sel("AS",$zrow[4]);
		$assignOptions.= "<option ".sel("AS",$zrow[4])." id=".$zrow[0]."-".$zrow[1]." value='AS' title='".$companyname1." (Administrative Staff)'>".$companyname1." (Administrative Staff)</option>";
	    }
	    else if($zrow[3]=="OB")
	    {
		$flg = $this->sel("OB",$zrow[4]);
		$assignOptions.= "<option ".$this->sel("OB",$zrow[4])." id=".$zrow[0]."-".$zrow[1]." value='OB' title='".$companyname1." (On Bench)'>".$companyname1." (On Bench)</option>";
	    }
	    else if($zrow[3]=="OV")
	    {
		$flg = $this->sel("OV",$zrow[4]);
		$assignOptions.= "<option ".$this->sel("OV",$zrow[4])." id=".$zrow[0]."-".$zrow[1]." value='OV' title='".$companyname1." (On Vacation)'>".$companyname1." (On Vacation)</option>";
	    }
	    else
	    {
		$lque="SELECT cname, ".getEntityDispName('sno', 'cname', 1)." FROM staffacc_cinfo WHERE type IN ('CUST', 'BOTH') AND  sno=".$zrow[1];
		$lres=$this->mysqlobj->query($lque,$this->db);
		$lrow=$this->mysqlobj->fetch_row($lres);
		$clname=$lrow[1];

		if($zrow[4]=="")
			$zrow[4]=" N/A ";

		$flg = $this->sel($zrow[4],$zrow[4]);
		$selAsgnId = '';
		if($asgnid == '')
		{
		    $selAsgnId = $this->assignments[0];
		}
		else
		{
		    $selAsgnId = $asgnid;
		}
		
		if($clname != '' && $zrow[2] != '')
		{
		    $assignOptions.="<option ".$this->sel($selAsgnId,$zrow[4])." id='".$zrow[0]."-".$zrow[1]."' title='(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".htmlspecialchars($clname,ENT_QUOTES)." - ".htmlspecialchars($zrow[2],ENT_QUOTES)."' value='".$zrow[4]."'>(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".$clname." - ".$zrow[2]."</option>";
		}
		else if($clname != '' && $zrow[2] == '')
		{
		    $assignOptions.="<option ".$this->sel($selAsgnId,$zrow[4])." id='".$zrow[0]."-".$zrow[1]."' title='(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".htmlspecialchars($clname,ENT_QUOTES)."' value='".$zrow[4]."'>(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".$clname."</option>";
		}
		else if($clname == '' && $zrow[2] != '')
		{
		    $assignOptions.="<option ".$this->sel($selAsgnId,$zrow[4])." id='".$zrow[0]."-".$zrow[1]."' title='(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".htmlspecialchars($zrow[2],ENT_QUOTES)."' value='".$zrow[4]."'>(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".$zrow[2]."</option>";
		}
		else if($clname == '' && $zrow[2] == '')
		{
		    $assignOptions.="<option ".$this->sel($selAsgnId,$zrow[4])." id='".$zrow[0]."-".$zrow[1]."' title='(".$zrow[4].") ".$startEnddate."' value='".$zrow[4]."'>(".$zrow[4].") ".$startEnddate."</option>";		
		}
	    }
	}
      
	if($module != 'Client') {
	$que="SELECT eartype FROM hrcon_benifit WHERE username='".$employee."' AND ustatus='active'";
	$res=$this->mysqlobj->query($que,$this->db);
	while($data=$this->mysqlobj->fetch_row($res))
	{
	    $chk = '';
	    if(strpos($asgnid, $data[0]))
	    {
		$chk = 'selected';
	    }
	    $assignOptions.= "<option id='(earn)$data[0]' value='(earn)$data[0]' $chk>$data[0]</option>";
	}
	}
	if($zrowCount < 1)
	{
	    $assignOptions.="<option value='0-0' id='0-0'>No Assignment Found</option>";
	    
	}

	if($zrowCount > 1){
		$multicss = "multiselect";
	}
	
	if(!empty($tab_index)) { $tab_index = 'tabindex='.$tab_index; } else { $tab_index = '';}

	$onchange	= '';

	if ($inout_flag) {

		$onchange	= 'onchange="javascript:getDataOnAssignment(this.id);"';
	}

	$AssignmentDropdown = '<select '.$onchange.' id="daily_assignemnt_'.$rowid.'" name="daily_assignemnt[0]['.$rowid.']" class="daily_assignemnt afontstylee " style="width:400px;padding:0px;" '.$tab_index.'>';
	$AssignmentDropdown .= $assignOptions;
	$AssignmentDropdown .= '</select>';
	
	return $AssignmentDropdown;
	
	//return $assignOptions;
    }
    
    function getAssignmentsAjax($employee, $asgnid='', $assignStartDate0, $assignEndDate0, $rowid,$module)
    {
	global $companyname;
	$assignOptions = '';
	
	$assignStartDate = date('Y-m-d', strtotime($assignStartDate0));
	$assignEndDate = date('Y-m-d', strtotime($assignEndDate0));
		
	$zque = "SELECT sno, client, project, jtype, pusername,jotype, date_format(str_to_date(s_date,'%m-%d-%Y'),'%m/%d/%Y'), date_format(str_to_date(e_date,'%m-%d-%Y'),'%m/%d/%Y') FROM hrcon_jobs WHERE username = '".$employee."' AND pusername!='' AND ((hrcon_jobs.ustatus IN ('active','closed','cancel') AND (hrcon_jobs.s_date IS NULL OR hrcon_jobs.s_date='' OR hrcon_jobs.s_date='0-0-0' OR (DATE(STR_TO_DATE(s_date,'%m-%d-%Y'))<='".$assignEndDate."'))) AND (IF(hrcon_jobs.ustatus='closed',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assignStartDate."'),1)) AND (IF(hrcon_jobs.ustatus='cancel',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assignStartDate."'),1))) AND hrcon_jobs.jtype!='' ORDER BY udate";
	
	$zres=$this->mysqlobj->query($zque,$this->db);
	$zrowCount = mysql_num_rows($zres);
	
	$this->assignmentsajax = array();
	$this->assignmentIdsajax = array();
	    
	while($zrow=$this->mysqlobj->fetch_array($zres))
	{
	    $this->assignmentsajax[] = $zrow[4];
	    $this->assignmentIdsajax[] = $zrow[0];
	    
	    if($zrow[1] != '0')
	    {
		$que = "SELECT cname, ".getEntityDispName('sno', 'cname', 1)." FROM staffacc_cinfo WHERE type IN ('CUST', 'BOTH') AND sno=".$zrow[1];
		$res=$this->mysqlobj->query($que,$this->db);
		$row=$this->mysqlobj->fetch_row($res);
		$companyname1=$row[1];
	    }
	    else
	    {
		$companyname1=$companyname;
	    }
	    
	    if($zrow[6] == '00/00/2000' || $zrow[6] == NULL || $zrow[6] == '')
	    {
		$asgnStartDate = "No Start Date";		
	    }
	    else
	    {
		$asgnStartDate = $zrow[6];
	    }
							    
	    if($zrow[7] == '00/00/2000' || $zrow[7] == NULL || $zrow[7] == '')
	    {
		$asgnEndDate = "No End Date";
	    }
	    else
	    {
		$asgnEndDate = $zrow[7];
	    }    
	    if($asgnStartDate == "No Start Date" && $asgnEndDate == "No End Date")
	    {
		    $startEnddate = "";
	    }
	    else
	    {
		$startEnddate = "(".$asgnStartDate." - ".$asgnEndDate.")";
	    }
	    
	    if($zrow[3]=="AS")
	    {
		$flg = $this->sel("AS",$zrow[4]);
		$assignOptions.= "<option ".sel("AS",$zrow[4])." id=".$zrow[0]."-".$zrow[1]." value='AS' title='".$companyname1." (Administrative Staff)'>".$companyname1." (Administrative Staff)</option>";
	    }
	    else if($zrow[3]=="OB")
	    {
		$flg = $this->sel("OB",$zrow[4]);
		$assignOptions.= "<option ".$this->sel("OB",$zrow[4])." id=".$zrow[0]."-".$zrow[1]." value='OB' title='".$companyname1." (On Bench)'>".$companyname1." (On Bench)</option>";
	    }
	    else if($zrow[3]=="OV")
	    {
		$flg = $this->sel("OV",$zrow[4]);
		$assignOptions.= "<option ".$this->sel("OV",$zrow[4])." id=".$zrow[0]."-".$zrow[1]." value='OV' title='".$companyname1." (On Vacation)'>".$companyname1." (On Vacation)</option>";
	    }
	    else
	    {
		$lque="SELECT cname, ".getEntityDispName('sno', 'cname', 1)." FROM staffacc_cinfo WHERE type IN ('CUST', 'BOTH') AND  sno=".$zrow[1];
		$lres=$this->mysqlobj->query($lque,$this->db);
		$lrow=$this->mysqlobj->fetch_row($lres);
		$clname=$lrow[1];

		if($zrow[4]=="")
			$zrow[4]=" N/A ";

		$flg = $this->sel($zrow[4],$zrow[4]);
		$selAsgnId = '';
		if($asgnid == '')
		{
		    $selAsgnId = $this->assignments[0];
		}
		else
		{
		    $selAsgnId = $asgnid;
		}
		
		if($clname != '' && $zrow[2] != '')
		{
		    $assignOptions.="<option ".$this->sel($selAsgnId,$zrow[4])." id='".$zrow[0]."-".$zrow[1]."' title='(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".htmlspecialchars($clname,ENT_QUOTES)." - ".htmlspecialchars($zrow[2],ENT_QUOTES)."' value='".$zrow[4]."'>(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".$clname." - ".$zrow[2]."</option>";
		}
		else if($clname != '' && $zrow[2] == '')
		{
		    $assignOptions.="<option ".$this->sel($selAsgnId,$zrow[4])." id='".$zrow[0]."-".$zrow[1]."' title='(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".htmlspecialchars($clname,ENT_QUOTES)."' value='".$zrow[4]."'>(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".$clname."</option>";
		}
		else if($clname == '' && $zrow[2] != '')
		{
		    $assignOptions.="<option ".$this->sel($selAsgnId,$zrow[4])." id='".$zrow[0]."-".$zrow[1]."' title='(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".htmlspecialchars($zrow[2],ENT_QUOTES)."' value='".$zrow[4]."'>(".$zrow[4].") ".$startEnddate."&nbsp;&nbsp;".$zrow[2]."</option>";
		}
		else if($clname == '' && $zrow[2] == '')
		{
		    $assignOptions.="<option ".$this->sel($selAsgnId,$zrow[4])." id='".$zrow[0]."-".$zrow[1]."' title='(".$zrow[4].") ".$startEnddate."' value='".$zrow[4]."'>(".$zrow[4].") ".$startEnddate."</option>";		
		}
	    }
	}
	if($module != 'Client') {
	$que="SELECT eartype FROM hrcon_benifit WHERE username='".$employee."' AND ustatus='active'";
	$res=$this->mysqlobj->query($que,$this->db);
	while($data=$this->mysqlobj->fetch_row($res))
	{
	    $chk = '';
	    if(strpos($asgnid, $data[0]))
	    {
		$chk = 'selected';
	    }
	    $assignOptions.= "<option id='(earn)$data[0]' value='(earn)$data[0]' $chk>$data[0]</option>";
	}
	}
	if($zrowCount < 1)
	{
	    $assignOptions.="<option value='0-0' id='0-0'>No Assignment Found</option>";
	}
	if(count($this->assignmentsajax) > 1)
	{
	   /*  $multi = '<span class=afontstylee><img src="/PSOS/images/arrow-multiple-16.png" width="12px" height="10px" title="Multiple Assignments"></span>&nbsp;';
		$multcss = 'class="daily_assignemnt afontstylee multiselect"'; */
	}
	else
	{
	    $multi = '';
		$multcss = 'class="daily_assignemnt afontstylee"';
	}
	$AssignmentDropdown = $multi.'<select id="daily_assignemnt_'.$rowid.'" name="daily_assignemnt[0]['.$rowid.']"  style="width:400px;" class="afontstylee" >';
	$AssignmentDropdown .= $assignOptions;
	$AssignmentDropdown .= '</select>';
	return $AssignmentDropdown;
	
	//return $assignOptions;
    }
    
    function checkAssignmentExists($employee, $assignStartDate0, $assignEndDate0)
    {
	$assignStartDate = date('Y-m-d', strtotime($assignStartDate0));
	$assignEndDate = date('Y-m-d', strtotime($assignEndDate0));
		
	$zque = "SELECT sno FROM hrcon_jobs WHERE username = '".$employee."' AND pusername!='' AND ((hrcon_jobs.ustatus IN ('active','closed','cancel') AND (hrcon_jobs.s_date IS NULL OR hrcon_jobs.s_date='' OR hrcon_jobs.s_date='0-0-0' OR (DATE(STR_TO_DATE(s_date,'%m-%d-%Y'))<='".$assignEndDate."'))) AND (IF(hrcon_jobs.ustatus='closed',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assignStartDate."'),1)) AND (IF(hrcon_jobs.ustatus='cancel',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assignStartDate."'),1))) AND hrcon_jobs.jtype!='' ORDER BY udate";
	
	$zres=$this->mysqlobj->query($zque,$this->db);
	$zrowCount = mysql_num_rows($zres);
	if($zrowCount > 0)
	{
	    return true;
	}
	else
	{
	    return false;
	}
    }
    
    function getClasses($cond='')
    {
	$classes = array();
	$sel="SELECT sno, classname, IFNULL(parent,0) AS parent FROM class_setup WHERE status = 'ACTIVE' ".$cond." ORDER BY classname ASC";
	$ressel=$this->mysqlobj->query($sel,$this->db);
	
	while($myrow=$this->mysqlobj->fetch_array($ressel))
	{
		$classes[] = $myrow;
	}
	return $classes;
    }
    
	//$rangRow .= $this->buildDropDown('daily_dates', $rowid, $assignStartEndDate, $assignStartDate, $script='', $key='', $val='');
    function buildDropDown($name, $rowid, $data, $selected='', $script='', $key='', $val='', $weeklyrange)
    {
	if($name == 'daily_dates')
	{
	    $rowid1 = 0;
	}
	else
	{
	    $rowid1 = $rowid;
	}
	$options = array();
	if( ($name!='weekly_dates') && ($name!='daily_dates') )
	{
	    $options[] = '<option value="">select</option>';
	}
	
	foreach ( $data as $k => $v )
	{
	    $varr = explode(" ", $v);
	    
	    if($key!='' || $val!='')
	    {
			$sel = ($v[$key] == $selected)? 'selected' : '';
			$options[] = "<option value='$v[$key]' $sel>$v[$val]</option>";
	    }
	    else
	    {
		
		$sel = ($selected==$varr[0]) ? 'selected' : '';
		
		if(substr_count($v, '-range-') > 0)
		{
		    $range = explode("-range-", $v);
		    $d1 = date('m/d/Y', strtotime($range[0]));
		    $d2 = date('m/d/Y', strtotime($range[1]));
		    $v1 = str_replace("-range-", " - ", $v);
			if($weeklyrange=='yes'){
				$options[] = "<option value='$d1-range-$d2' selected='selectd'>$v1</option>";
			}else{
				$options[] = "<option value='$d1-range-$d2' $sel>$v1</option>";
			}
		}
		else
		{
		    //$vi = date('Y-m-d', strtotime($v));
		    $vi = date('m/d/Y', strtotime($v));
		    $options[] = "<option value='$vi' $sel>$v</option>";
		}
	    }
	}
	//$dropdown = "<div style='clear:both;>";
	//$dropdown .= "<label class='cf_label' style='width: 100%;'></label>";
	$dropdown .= "<select {$script} class='{$name} afontstylee' id='{$name}_{$rowid}' size='1' name='{$name}[{$rowid1}][{$rowid}]' >";
	$dropdown .= implode("\n", $options);
	$dropdown .= '</select>';
	//$dropdown .= '</div>';
	
	return $dropdown;
    }
    
    function buildDropDownCheck($name, $rowid, $data, $selected='', $script='', $key='', $val='', $weeklyrange, $employee, $inout_flag = false, $tab_index = '')
    {
	if($name == 'daily_dates')
	{
	    $rowid1 = 0;
	}
	else
	{
	    $rowid1 = $rowid;
	}
	$options = array();
	if( ($name!='weekly_dates') && ($name!='daily_dates') )
	{
	    $options[] = '<option value="">select</option>';
	}
	
	foreach ( $data as $k => $v )
	{
	    $varr = explode(" ", $v);
	    
	    if($key!='' || $val!='')
	    {
			
		$sel = ($v[$key] == $selected)? 'selected' : '';
		$options[] = "<option value='$v[$key]' $sel>$v[$val]</option>";
			
		}
		else
		{
			$sel = ($selected==$varr[0]) ? 'selected' : '';

			if ($inout_flag) {

				$vi	= date('m/d/Y', strtotime($v));

				if ($this->checkAssignmentExists($employee, $v, $v)) {

					$options[] = "<option value='$vi' $sel>$v</option>";
				}

			} else {

				if (substr_count($v, '-range-') > 0) {

					$range	= explode("-range-", $v);
					$d1		= date('m/d/Y', strtotime($range[0]));
					$d2		= date('m/d/Y', strtotime($range[1]));
					$v1		= str_replace("-range-", " - ", $v);

					if ($weeklyrange=='yes') {

						if ($this->checkAssignmentExists($employee, $d1, $d2)) {

							$options[] = "<option value='$d1-range-$d2' selected='selectd'>$v1</option>";
						}

					} else {

						if ($this->checkAssignmentExists($employee, $d1, $d2)) {

							$options[] = "<option value='$d1-range-$d2' $sel>$v1</option>";
						}
					}

				} else {

					$vi	= date('m/d/Y', strtotime($v));

					if ($this->checkAssignmentExists($employee, $v, $v)) {

						$options[] = "<option value='$vi' $sel>$v</option>";
					}
				}
			}
	    }
	}
	//$dropdown = "<div style='clear:both;>";
	//$dropdown .= "<label class='cf_label' style='width: 100%;'></label>";
	$dropdown .= "<select {$script} class='{$name} afontstylee' id='{$name}_{$rowid}' size='1' name='{$name}[{$rowid1}][{$rowid}]' tabindex='".$tab_index++."'>";
	$dropdown .= implode("\n", $options);
	$dropdown .= '</select>';
	//$dropdown .= '</div>';
	
	return $dropdown;
    }
    
    function buildDropDownClasses($name, $rowid, $data, $selected='', $script='', $key='', $val='', $weeklyrange, $tab_index='')
    {
	if($name == 'daily_dates')
	{
	    $rowid1 = 0;
	}
	else
	{
	    $rowid1 = $rowid;
	}
	$options = array();
	if( ($name!='weekly_dates') && ($name!='daily_dates') )
	{
	    $options[] = '<option  value="0">--Select--</option>';
	}
	
	foreach ( $data as $k => $v )
	{
	    $varr = explode(" ", $v);
	    
	    if($key!='' || $val!='')
	    {
			$sel = ($v[$key] == $selected)? 'selected' : '';
			$options[] = "<option value='$v[$key]' $sel>$v[$val]</option>";
	    }
	    else
	    {
		
		$sel = ($selected==$varr[0]) ? 'selected' : '';
		
		if(substr_count($v, '-range-') > 0)
		{
		    $range = explode("-range-", $v);
		    $d1 = date('m/d/Y', strtotime($range[0]));
		    $d2 = date('m/d/Y', strtotime($range[1]));
		    $v1 = str_replace("-range-", " - ", $v);
			if($weeklyrange=='yes'){
				$options[] = "<option value='$d1-range-$d2' selected='selectd'>$v1</option>";
			}else{
				$options[] = "<option value='$d1-range-$d2' $sel>$v1</option>";
			}
		}
		else
		{
		    //$vi = date('Y-m-d', strtotime($v));
		    $vi = date('m/d/Y', strtotime($v));
		    $options[] = "<option value='$vi' $sel>$v</option>";
		}
	    }
	}
	//$dropdown = "<div style='clear:both;>";
	//$dropdown .= "<label class='cf_label' style='width: 150px;'></label>";
	$dropdown .= "<select {$script} class='{$name} afontstylee' style='height:17px;' id='{$name}_{$rowid}' size='1' name='{$name}[{$rowid1}]' tabindex='".$tab_index."'>";
	$dropdown .= implode("\n", $options);
	$dropdown .= '</select>';
	//$dropdown .= '</div>';
	
	return $dropdown;
    }
    
	function buildDatesdropdown($timesheet_date_arr, $timesheet_start_date, $timesheet_end_date, $inout_flag = false)
	{
		if ($inout_flag) {

			return $timesheet_date_arr;

		} else {

			$summary_dates = $timesheet_start_date."-range-".$timesheet_end_date;
			array_push($timesheet_date_arr, $summary_dates);
		}

		return $timesheet_date_arr;
	}
    
    function getRateTypes($asignid='')
    {
	$ratetypes = array();
	$select_que="SELECT sno,rateid,name,status,default_status from multiplerates_master where rateid !='rate4' and status = 'Active' order by sno";
	$ressel=$this->mysqlobj->query($select_que,$this->db);
	
	while($myrow=$this->mysqlobj->fetch_array($ressel))
	{
		$ratetypes[] = $myrow;			
	}
	return $ratetypes;
    }
    
    function getRateTypesWithPayNBill($asignid, $rates='', $rowid, $parid, $mode='', $req_str='', $type='', $disableFlag='')
    {
	//echo "asgn id is ".$asignid."<br>";
	$req_bill_arr = explode(',',$req_str[4]);
	$req_rate_arr = explode(',',$req_str[5]);
	
	$rateHourArr = array();
	
	if($rates == '')
	{
	    $req_bill_arr = explode(',',$req_str[4]);
	    $req_rate_arr = explode(',',$req_str[5]);
	}
	else
	{
	    $ratesArr = explode(",", $rates);
	    foreach($ratesArr as $val)
	    {
		$valArr = explode("|", $val);
		if($valArr[0] == '')
		{
		    $rate = 'rate1';
		}
		else
		{
		    $rate = $valArr[0];
		}
		$rateHourArr[$rate] = $valArr[1];
		$billArr[$rate] = $valArr[2];	
	    }
	}
	$select_ratemaster = "SELECT t1.ratemasterid, t1.ratetype, t1.rate, t2.jtype FROM multiplerates_assignment t1 INNER JOIN hrcon_jobs t2 ON t1.asgnid = t2.sno where ratemasterid != 'rate4' AND asgnid = '".$asignid."' AND period =  'HOUR' ORDER BY t1.sno";

	$result_ratemaster=$this->mysqlobj->query($select_ratemaster,$this->db);
	
	while($row_ratemaster=$this->mysqlobj->fetch_array($result_ratemaster))
	{
	    $rateArr[$row_ratemaster['ratemasterid']][$row_ratemaster['ratetype']] = $row_ratemaster['rate'];
	    $jtype = $row_ratemaster['jtype'];
	}
	
	$ratetypes = array();
	$select_que="SELECT t1.rateid, (IF((SELECT COUNT(1) FROM multiplerates_assignment t2 WHERE t2.asgnid = '".$asignid."' AND t2.ratemasterid = t1.rateid) = 0,'N','Y')) AS required, (SELECT t3.billable FROM multiplerates_assignment t3 WHERE t3.asgnid = '".$asignid."' AND t3.ratemasterid = t1.rateid  AND ratetype='billrate' AND asgn_mode = 'hrcon') AS billable FROM multiplerates_master t1 WHERE t1.rateid != 'rate4' and status = 'Active' ORDER BY t1.sno";
	
	$ressel=$this->mysqlobj->query($select_que,$this->db);
	$rowcount = mysql_num_rows($ressel);
	
	$r = 0;
	$ratetype = '';

	while($myrow=$this->mysqlobj->fetch_array($ressel))
	{
	    $pay = ($rateArr[$myrow['rateid']]['payrate'] == '')?'0.00':$rateArr[$myrow['rateid']]['payrate'];
	    $bill = ($rateArr[$myrow['rateid']]['billrate'] == '')?'0.00':$rateArr[$myrow['rateid']]['billrate'];
	    $ratetype .= '<td valign="top" class="afontstylee">';
	    
	    if($mode!='' && $req_str!='')
	    {
		if($req_str[4] != '')
		{
		    $hidbillchk = $req_bill_arr[$r];
		}
		else
		{
		    $hidbillchk = $myrow['billable'];
		}
		    if($myrow['rateid'] == 'rate1' || $myrow['rateid'] == 'rate2' || $myrow['rateid'] == 'rate3')
		    {
			$xyz = ($rateHourArr[$myrow['rateid']] != '')?$rateHourArr[$myrow['rateid']]:$req_rate_arr[$r];
			$ratetype .= '<input '.$disableFlag.'style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$xyz.'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalcMuti(\'timesheetRate'.$r.'\', '.$rowid.', '.$rowcount.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" >';
		    }
		    else
		    {
			$xyz = ($rateHourArr[$myrow['rateid']] != '')?$rateHourArr[$myrow['rateid']]:$req_rate_arr[$r];
			$ratetype .= '<input '.$disableFlag.' style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$xyz.'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalcMuti(\'timesheetRate'.$r.'\', '.$rowid.', '.$rowcount.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" '.$this->disable($myrow['required']).'>';
			 
			
		    }
		    if($jtype == 'OP')
		    {
			$ratetype .= '<input '.$disableFlag.' style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Y" '.$this->chk($hidbillchk).' '.$this->disable($myrow['required']).'>';    
		    }
		    else
		    {
			$ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Y" '.$this->disable($myrow['required']).'>';
		    }		    
			
	    }
	    else
	    {
		    if($myrow['rateid'] == 'rate1' || $myrow['rateid'] == 'rate2' || $myrow['rateid'] == 'rate3')
		    {
			$ratetype .= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$rateHourArr[$myrow['rateid']].'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalc(\'timesheetRate'.$r.'\', this.id, '.$rowcount.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" >';
		    }
		    else
		    {
			$ratetype .= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$rateHourArr[$myrow['rateid']].'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalc(\'timesheetRate'.$r.'\', this.id, '.$rowcount.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" '.$this->disable($myrow['required']).'>';
		    }
		    if($jtype == 'OP')
		    {
			if(count($billArr) > 0)
			{
			    $ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Y" '.$this->chk(substr($billArr[$myrow['rateid']], 0, 1)).' '.$this->disable($myrow['required']).'>';
			}
			else
			{
			$ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Y" '.$this->chk($myrow['billable']).' '.$this->disable($myrow['required']).'>';
			}
		    }
		    else
		    {
			$ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Y" disabled>';
		    }
		
	    }

	    if($type != 'single')
	    {
		if(SHOWPAYANDBILL == 'Y' && ($_SESSION['sess_usertype'] == 'UL' || $_SESSION['sess_usertype'] == 'BO'))
		{
		    $ratetype .= "<br />P <span id='daily_rate_pay_".$r."_".$rowid."' name='daily_rate_pay_".$r."_".$rowid."'>".$pay."</span> <br />B <span id='daily_rate_bill_".$r."_".$rowid."' name='daily_rate_bill_".$r."_".$rowid."'>".$bill."</span><span class='daily_rate_pay_link_".$rowid."'>";
		    if($asignid != '')
		    {
			$ratetype .= $this->getAssignEditLink($asignid, $rowid);
		    }
		}
	    }
	    
	    $ratetype .= '</span></td>';
	    $r++;
	}
	return $ratetype;
    }
    
    function getAssignEditLink($hrsno, $rowid)
    {
	$que12 = "select contactsno,appno from assignment_schedule where contactsno like '%".$hrsno."|%' AND modulename='HR->Assignments' AND invapproved='active'";
	$res12 = mysql_query($que12,$this->db);
	$row12 = mysql_fetch_row($res12);
	$aid = explode("|",$row12[0]);
		
	$ratetype ="<font class=afontstyle>&nbsp;&nbsp;<a href=\"javascript:doEditAssign('".$hrsno."','".$aid[1]."','".$row12[1]."', '".$rowid."');\"><img src='/PSOS/images/assignments10x10.png' border='0'></a></font>";
	
	return $ratetype;
    }
    
    function getAssignEditLinkAjax($hrsno, $rowid)
    {
	$que12 = "select contactsno,appno from assignment_schedule where contactsno like '%".$hrsno."|%' AND modulename='HR->Assignments' AND invapproved='active'";
	$res12 = mysql_query($que12,$this->db);
	$row12 = mysql_fetch_row($res12);
	$aid = explode("|",$row12[0]);
		
	$ratetype ="<font class=afontstyle>&nbsp;&nbsp;<a href=\"javascript:doEditAssign('".$hrsno."','".$aid[1]."','".$row12[1]."', '".$rowid."');\"><img src='/PSOS/images/assignments10x10.png' border='0'></a></font>";
	
	return $ratetype;
    }
    
    function getRateTypesWithPayNBillSingle($asignid, $rates='', $rowid, $parid, $mode='', $req_str='', $type='', $ratesAvail)
    {
	$req_bill_arr = explode(',',$req_str[4]);
	$req_rate_arr = explode(',',$req_str[5]);
	
	$rateHourArr = array();

	if($rates != '')
	{
	    $ratesArr = explode(",", $rates);
	    foreach($ratesArr as $val)
	    {
		$valArr = explode("|", $val);
		if($valArr[0] == '')
		{
		    $rate = 'rate1';
		}
		else
		{
		    $rate = $valArr[0];
		}
		$rateHourArr[$rate] = $valArr[1];
		$billArr[$rate] = $valArr[2];	
	    }
	}
	$select_ratemaster = "SELECT t1.ratemasterid, t1.ratetype, t1.rate, t2.jtype FROM multiplerates_assignment t1 INNER JOIN hrcon_jobs t2 ON t1.asgnid = t2.sno where ratemasterid != 'rate4' AND asgnid = '".$asignid."' AND period =  'HOUR' ORDER BY t1.sno";

	$result_ratemaster=$this->mysqlobj->query($select_ratemaster,$this->db);
	
	while($row_ratemaster=$this->mysqlobj->fetch_array($result_ratemaster))
	{
	    $rateArr[$row_ratemaster['ratemasterid']][$row_ratemaster['ratetype']] = $row_ratemaster['rate'];
	    $jtype = $row_ratemaster['jtype'];
	}
	
	$ratetypes = array();
	$select_que="SELECT t1.rateid, (IF((SELECT COUNT(1) FROM multiplerates_assignment t2 WHERE t2.asgnid = '".$asignid."' AND t2.ratemasterid = t1.rateid) = 0,'N','Y')) AS required, (SELECT t3.billable FROM multiplerates_assignment t3 WHERE t3.asgnid = '".$asignid."' AND t3.ratemasterid = t1.rateid  AND ratetype='billrate' AND asgn_mode = 'hrcon') AS billable FROM multiplerates_master t1 WHERE t1.rateid != 'rate4' and status = 'Active' ORDER BY t1.sno";
	
	//echo $select_que = "SELECT  distinct ratemasterid as rateid, 'Y' AS required, billable AS billable FROM multiplerates_assignment WHERE asgnid in(".$AsgnIdStr.") AND ratetype='billrate' AND asgn_mode = 'hrcon'";
	
	$ressel=$this->mysqlobj->query($select_que,$this->db);
	$rowcount = mysql_num_rows($ressel);
	
	$r = 0;
	$ratetype = '';

	while($myrow=$this->mysqlobj->fetch_array($ressel))
	{
	    $hiddenBillable[$myrow['rateid']] = ($myrow['billable'] == '')?'N':$myrow['billable'];
	    if(in_array($myrow['rateid'], $ratesAvail))
	    {
		$pay = ($rateArr[$myrow['rateid']]['payrate'] == '')?'0.00':$rateArr[$myrow['rateid']]['payrate'];
		$bill = ($rateArr[$myrow['rateid']]['billrate'] == '')?'0.00':$rateArr[$myrow['rateid']]['billrate'];
		$ratetype .= '<td valign="top" class="afontstylee">';
		
		if($mode!='' && $req_str!='')
		{
		    if($req_str[4] != '')
		    {
			$hidbillchk = $req_bill_arr[$r];
		    }
		    else
		    {
			$hidbillchk = $myrow['billable'];
		    }
		//    if($asignid == '')
		//    {
		//	$ratetype .= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" disabled>';
		//	$ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Y" disabled>';
		//    }
		//    else
		//    {
			if($myrow['rateid'] == 'rate1' || $myrow['rateid'] == 'rate2' || $myrow['rateid'] == 'rate3')
			{
			    $ratetype .= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$req_rate_arr[$r].'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalcMuti(\'timesheetRate'.$r.'\', '.$rowid.', '.$rowcount.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" >';
			}
			else
			{
			    $ratetype .= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$req_rate_arr[$r].'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalcMuti(\'timesheetRate'.$r.'\', '.$rowid.', '.$rowcount.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" '.$this->disable($myrow['required']).'>';
			}
			//if($jtype == 'OP')
			//{
			    $ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Yes" '.$this->chk($hidbillchk).' '.$this->disable($myrow['required']).'>';    
			//}
			//else
			//{
			//    $ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Yes" disabled>';
			//}		    
		    //}		
		}
		else
		{
		//    if($asignid == '')
		//    {
		//	$ratetype .= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalc(\'timesheetRate'.$r.'\', this.id, '.$this->rateTypeCountSingle.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" disabled>';
		//	$ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Y" disabled>';
		//    }
		//    else
		//    {
			if($myrow['rateid'] == 'rate1' || $myrow['rateid'] == 'rate2' || $myrow['rateid'] == 'rate3')
			{
			    $ratetype .= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$rateHourArr[$myrow['rateid']].'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalc(\'timesheetRate'.$r.'\', this.id, '.$this->rateTypeCountSingle.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" >';
			}
			else
			{
			    $ratetype .= '<input  style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$rateHourArr[$myrow['rateid']].'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalc(\'timesheetRate'.$r.'\', this.id, '.$this->rateTypeCountSingle.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" '.$this->disable($myrow['required']).'>';
			}
			//if($jtype == 'OP')
			//{
			    if(count($billArr) > 0)
			    {
				$ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Yes" '.$this->chk(substr($billArr[$myrow['rateid']], 0, 1)).' >';
			    }
			    else
			    {
			    $ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Yes" '.$this->chk($myrow['billable']).'>';
			    }
			//}
			//else
			//{
			//    $ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Yes" disabled>';
			//}
		    //}
		}
    
		if($type != 'single')
		{
		    if(SHOWPAYANDBILL == 'Y' && ($_SESSION['sess_usertype'] == 'UL' || $_SESSION['sess_usertype'] == 'BO'))
		    {
			$ratetype .= "<br />P <span id='daily_rate_pay_".$r."_".$rowid."' name='daily_rate_pay_".$r."_".$rowid."'>".$pay."</span> <br />B <span id='daily_rate_bill_".$r."_".$rowid."' name='daily_rate_bill_".$r."_".$rowid."'>".$bill."<span>";
		    }
		}
		
		$ratetype .= '</td>';
		$r++;
	    }
	}
	
	$this->hiddenBillable[] = $hiddenBillable;
	    
	return $ratetype;
    }
	
    function getRateTypesWithPayNBill_multi($asignid, $rates='', $rowid, $mode='', $req_str='')
    {
		$req_bill_arr = explode(',',$req_str[4]);
		$req_rate_arr = explode(',',$req_str[5]);
		
		/* echo "<pre>";
			print_r($req_rate_arr);
		echo "</pre>"; */
		
		$rateHourArr = array();
	
		if($rates != '')
		{
			$ratesArr = explode(",", $rates);
			foreach($ratesArr as $val)
			{
			$valArr = explode("|", $val);
			if($valArr[0] == '')
			{
				$rate = 'rate1';
			}
			else
			{
				$rate = $valArr[0];
			}
			$rateHourArr[$rate] = $valArr[1];
			$billArr[$rate] = $valArr[2];	
			}
		}
		$select_ratemaster = "SELECT t1.ratemasterid, t1.ratetype, t1.rate, t2.jtype FROM multiplerates_assignment t1 INNER JOIN hrcon_jobs t2 ON t1.asgnid = t2.sno where ratemasterid != 'rate4' AND asgnid = '".$asignid."' AND period =  'HOUR' ORDER BY t1.sno";

		$result_ratemaster=$this->mysqlobj->query($select_ratemaster,$this->db);
		
		while($row_ratemaster=$this->mysqlobj->fetch_array($result_ratemaster))
		{
			$rateArr[$row_ratemaster['ratemasterid']][$row_ratemaster['ratetype']] = $row_ratemaster['rate'];
			$jtype = $row_ratemaster['jtype'];
		}
	
		$ratetypes = array();
		$select_que="SELECT t1.rateid, (IF((SELECT COUNT(1) FROM multiplerates_assignment t2 WHERE t2.asgnid = '".$asignid."' AND t2.ratemasterid = t1.rateid) = 0,'N','Y')) AS required, (SELECT t3.billable FROM multiplerates_assignment t3 WHERE t3.asgnid = '".$asignid."' AND t3.ratemasterid = t1.rateid  AND ratetype='billrate' AND asgn_mode = 'hrcon') AS billable FROM multiplerates_master t1 WHERE t1.rateid != 'rate4' and status = 'Active' ORDER BY t1.sno";

		$ressel=$this->mysqlobj->query($select_que,$this->db);
		$rowcount = mysql_num_rows($ressel);
	
		($jtype == 'OP')?$asgn = 'Y' : $asgn = 'N';
		$r = 0;
		$ratetype = '';
		//$ratetype .= '<table id="table_'.$rowid.'" cellPadding=0 cellSpacing=0 border=0 class="afontstylee" style="width: 100%">';
		//$ratetype .= '<tr>';
		
		while($myrow=$this->mysqlobj->fetch_array($ressel))
		{
			if($rates == '')
			{
			$bill1 = $myrow['billable'];		
			}
			else
			{
			if($billArr[$myrow['rateid']] != '')
			{
				$bill1 = $billArr[$myrow['rateid']];
				$bill1 = substr($bill1, 0, 1);
			}
			else
			{
				$bill1 = 'N';
			}
			
			}
			if($asignid == 0)
			{
			$asgn = 'N';
			}

			$pay = ($rateArr[$myrow['rateid']]['payrate'] == '')?'0.00':$rateArr[$myrow['rateid']]['payrate'];
			$bill = ($rateArr[$myrow['rateid']]['billrate'] == '')?'0.00':$rateArr[$myrow['rateid']]['billrate'];
			//$ratetype .= '<td valign="top" style="padding-right:4px;display:inline-block;display:-moz-inline-stack;">';
			$ratetype .= '<td valign="top" class="afontstylee">';
			
			if($mode!='' && $req_str!=''){
			
				/* $hidratevalues = $request['daily_rate_'.$rowid][$rowid][$myrow['rateid']];
				$hidbillvalues = $request['daily_rate_billable_'.$rowid][$myrow['rateid']];
				$hidbillchk = ($hidbillvalues=='Y') ? 'checked':''; */
				
					$hidbillchk = ($req_bill_arr[$r]=='Y') ? 'checked':'';
				// on keyup added timesheetcalcmuti instead of timesheetcal function
				$ratetype .= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;" type="text" value="'.$req_rate_arr[$r].'" size="3" max_length="5" maxlength="6" class="timesheetRate'.$r.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" onkeyup="TimesheetCalcMuti(\'timesheetRate'.$r.'\', '.$rowid.', '.$rowcount.', \'daily_'.$myrow['rateid'].'_'.$r.'_'.$rowid.'\')" '.$this->disable($myrow['required']).'>';
				
				$ratetype .= '<input style="margin-top:0px;vertical-align:top" type="checkbox"  '.$hidbillchk.'  name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Y" '.$hidbillchk.' '.$this->disable($myrow['required']).'>';

				
			}
			
			$ratetype .= "<br />P <span id='daily_rate_pay_".$r."_".$rowid."' name='daily_rate_pay_".$r."_".$rowid."'>".$pay."</span> <br />B <span id='daily_rate_bill_".$r."_".$rowid."' name='daily_rate_bill_".$r."_".$rowid."'>".$bill."<span>";
			$ratetype .= '</td>';
			$r++;
		}
		return $ratetype;
    }
    
    function getRateTypesWithPayNBillAjax($asignid, $rowid)
    {
	if(strpos($asignid , 'earn'))
	{
	    $rtype = count($this->getRateTypes());
	    
	    for($i = 0; $i < $rtype; $i++)
	    {
		if($i < 3)
		{
		    $required = 'Y';
		    $billable = 'N';
		}
		else
		{
		    $required = 'N';
		    $billable = 'N';
		}
		$ratesArr[] = "daily_rate_".$i."_".$rowid.",".$required.",daily_rate_billable_".$i."_".$rowid.",".$billable.", daily_rate_pay_".$i."_".$rowid.",".$rateArr[$myrow['rateid']]['payrate'].", daily_rate_bill_".$i."_".$rowid.",".$rateArr[$myrow['rateid']]['billrate'];
	    }
	}
	else
	{
	    $select_ratemaster = "SELECT t1.ratemasterid, t1.ratetype, t1.rate, t2.jtype FROM multiplerates_assignment t1 INNER JOIN hrcon_jobs t2 ON t1.asgnid = t2.sno where ratemasterid != 'rate4' AND asgnid = '".$asignid."' AND period =  'HOUR' ORDER BY t1.sno";
	    $result_ratemaster=$this->mysqlobj->query($select_ratemaster,$this->db);
	    
	    while($row_ratemaster=$this->mysqlobj->fetch_array($result_ratemaster))
	    {
		$rateArr[$row_ratemaster['ratemasterid']][$row_ratemaster['ratetype']] = $row_ratemaster['rate'];
		$jtype = $row_ratemaster['jtype'];
	    }

	    $ratetypes = array();
	    $select_que="SELECT t1.rateid, (IF((SELECT COUNT(1) FROM multiplerates_assignment t2 WHERE t2.asgnid = '".$asignid."' AND t2.ratemasterid = t1.rateid) = 0,'N','Y')) AS required, (SELECT t3.billable FROM multiplerates_assignment t3 WHERE t3.asgnid = '".$asignid."' AND t3.ratemasterid = t1.rateid  AND ratetype='billrate' AND asgn_mode = 'hrcon') AS billable FROM multiplerates_master t1 WHERE t1.rateid != 'rate4' AND status='ACTIVE' ORDER BY t1.sno";
	    $ressel=$this->mysqlobj->query($select_que,$this->db);
	    $rowcount = mysql_num_rows($ressel);
	    
	    $r = 0;
	    $ratetype = '';
    
	    while($myrow=$this->mysqlobj->fetch_array($ressel))
	    {
		if($asignid == '')
		{
		    $required = 'N';
		    $billable = 'N';
		}
		else
		{
		    if($jtype != 'OP')
		    {
			if($myrow['rateid'] == 'rate1' || $myrow['rateid'] == 'rate2' || $myrow['rateid'] == 'rate3')
			{
			    $required = 'Y';
			    $billable = 'N';
			}
			else
			{
			    $required = 'N';
			    $billable = 'N';
			}
			
		    }
		    else
		    {
			$required = $myrow['required'];
			$billable = $myrow['billable'];
		    }
		}
		$ratesArr[] = "daily_rate_".$r."_".$rowid.",".$required.",daily_rate_billable_".$r."_".$rowid.",".$billable.", daily_rate_pay_".$r."_".$rowid.",".$rateArr[$myrow['rateid']]['payrate'].", daily_rate_bill_".$r."_".$rowid.",".$rateArr[$myrow['rateid']]['billrate'];
		$r++;
	    }
	}
	$ratesArr[] = $asignid;
	return implode("|", $ratesArr);
    }
    
//    function buildCheckBox_TotalHours($name)
//    {
//	$select_que="SELECT sno,rateid,name,status,default_status from multiplerates_master where rateid !='rate4' and status='Active' order by sno";
//	$ressel=$this->mysqlobj->query($select_que,$this->db);
//	
//	while($myrow=$this->mysqlobj->fetch_array($ressel))
//	{
//	    $data[] = $myrow;
//	}
//	$r = 0;
//	foreach($data as $key => $val)
//	{
//	    echo "<td class='totbg' align='left'><input type='hidden' name='{$name}{$key}_input' value='0.00' size='5'><div id='{$name}{$key}_div' style='display:none;'>0.00</div></td>";
//	    $r++;
//	}
//	
//
//    }
    
    function buildCheckBox_TotalHours($name, $asgnids_all)
    {
	$asgnArr = $this->getRateTypesForAllAsgnnames($asgnids_all);
	foreach($asgnArr as $key => $val)
	{
	    echo "<td class='totbg' align='left' style='padding-left: 20px'><input type='hidden' name='{$name}{$key}_input' value='0.00' size='5'><div id='{$name}{$key}_div' style='display:;'>0.00</div></td>";
	}
    }
    
    function getEmployees($module, $username, $assign_start_date, $assign_end_date)
    {
	$fun = 'get'.$module.'EmployeeNames';
	$names = $this->{$fun}($username, $assign_start_date, $assign_end_date);
	return $names;
    }
    
    function getAssignId($assngid)
    {
	$sql = "select sno from hrcon_jobs where pusername = '".$assngid."'";
	$result = $this->mysqlobj->query($sql,$this->db);
	while($result=$this->mysqlobj->fetch_array($result))
	{
	    $id = $result['sno'];
	}
	
	return $id;
    }

    function getRangeRow($employee, $assign_id = '', $rtype = '', $task='', $assignStartEndDate, $assignStartDate, $assignEndDate, $classid, $rowid, $range='no', $timesheet_hours_sno = '', $edit_string = '', $editRowid='',$module='', $rowtotal='0.00', $cval = '')
    {
	$this->mystr[] = $timesheet_hours_sno;
	$rangRow = "<tr id='row_".$rowid."' class='tr_clone'>";
	////////////////// Dates dropdown ///////////////////////////
	$rangRow .= "<td valign='top' width='2%'>
	<input type='hidden' id='edit_string' name='edit_string[".$rowid."]' value='".$edit_string."'>
	<input type='hidden' id='edit_snos_new' name='edit_snos_new[".$rowid."]' value='".$timesheet_hours_sno."'>
	<input type='checkbox' name='daily_check[".$rowid."][]' id='check_".$rowid."' value='".$timesheet_hours_sno."' class='chremove' style='margin-top:0px;' ></td>";
	$rangRow .= "<td valign='top' align='left' width='10%'>";
	//buildDropDown($name, $rowid, $data, $selected='', $script='', $key='', $val='')
	//$dates_array = $this->buildDatesdropdown($assignStartEndDate, $assignStartDate, $assignEndDate);
	
	$rangRow .= $this->buildDropDownCheck('daily_dates', $rowid, $assignStartEndDate, $assignStartDate, $script='', $key='', $val='', $range, $employee);
	$rangRow .= "<br /><font title='click here to add task details' onclick='javascript:AddTaskDetails(this.id)' id='addtaskdetails_".$rowid."' class='addtaskBtn' style='padding-top: 0px; white-space:nowrap;'>Click to Add Task Details </font>";
	$rangRow .= "</td>";
	////////////////// Assignments dropdown ///////////////////////////
	$asgnDropDown = $this->getAssignments($employee, $assign_id, $assignStartDate, $assignEndDate, $rowid,$module,'','',$cval);
	if(count($this->assignments) > 1)
	{
		$multicss = "background='/PSOS/images/arrow-multiple-12-red.png' style='background-repeat:no-repeat;background-position:left top; padding-left: 17px;'";
	}
	$rangRow .= "<td valign='top' class='nowrap' width='32%' ".$multicss." >";
	$rangRow .= '<span id="span_'.$rowid.'">';
	$rangRow .= $asgnDropDown;
	$rangRow .= '</span>';
	//$rangRow .= "<div id='assgnrow_".$rowid."'>".$this->getAssignments($employee, $assignStartDate, $assignEndDate, $rowid)."</div>";
	$rangRow .= "<br />";
	$rangRow .= "<label id='textlabel_".$rowid."' title='click here to add task details' class=afontstylee onclick='javascript:AddTaskDetails(this.id)'  style='display:inline;padding-top: 0px;float:left'>".$task."</label>";
	$rangRow .= "<input style='display: none;padding-top:5px;width:400px;' class='addtaskdetails' type='text' class=afontstylee name='daily_task[0][".$rowid."]'  value='".$task."' id='np_".$rowid."' tabindex='10'>";
	$rangRow .= "</td>";
	if(MANAGE_CLASSES == 'Y')
	{
	////////////////// Classes dropdown ///////////////////////////
	$rangRow .= "<td valign='top' width='8%'>";
	//$rangRow .= $this->buildDropDownClasses('daily_classes', '0', $this->getClasses(), $classid, '','sno', 'classname');
	$rangRow .= $this->buildDropDownClasses('daily_classes', $rowid, $this->getClasses(), $classid, '','sno', 'classname');
	$rangRow .= "</td>";
	}
	//$assignment_id = ($assign_id=='')?$this->assignmentIds[0]:$this->getAssignId($assign_id);
	if(strpos($assign_id, 'earn'))
	{
	    $assignment_id = ($assign_id=='')?$this->assignmentIds[0]:$assign_id;
	}
	else
	{
	    $assignment_id = ($assign_id=='')?$this->assignmentIds[0]:$this->getAssignId($assign_id);
	}
	
	$rangRow .= "<div id='raterow_".$rowid."'>".$this->getRateTypesWithPayNBillSingle($assignment_id, $rtype, $rowid, $par_id, '', '', 'single', $this->getRateTypesForAllAsgnnames($this->assignments))."<div>";
	
	
	///////////////////////// Total hours /////////////////////////
	//$rangRow .= "<td valign='top' class='afontstylee' width='3%'><input type='hidden' name='daytotalhrs_".$rowid."' id='daytotalhrs_".$rowid."' value='0.00' ><div id='daytotalhrsDiv_".$rowid."' style='display:none;'>0.00</div></td>";
	$rangRow .= "<td valign='top' class='afontstylee' width='3%'><input type='hidden' name='daytotalhrs_".$rowid."' id='daytotalhrs_".$rowid."' value='".$rowtotal."' ><input type='hidden' name='editrow[]' id='editrow_".$rowid."' value='".$editRowid."' ><input type='text' name='daytotalhrsDiv_".$rowid."' id='daytotalhrsDiv_".$rowid."' value='".$rowtotal."' style='display:none;'></td>";
	
	$rangRow .= '</tr>';
	
	return $rangRow;
    }
    
    function buildHeaders($headers, $types)
    {
		$str='';
		foreach($types as $typekey => $typeval){
			//array_push($headers,$typeval['name']."<br/> Hours Billable");
			array_push($headers,"<table border=0><tr><td colspan='2' align='center' ><font class=afontstylee>".$typeval['name']."</font></td></tr><tr style='line-height:10px;'><td><font class=afontstylee>Hours</font></td><td align='left'>
			<a href='#' style='text-decoration:none;' class=afontstylee>
    <span class='caption afontstylee'>Billable</span>
<img src='/BSOS/images/dollar.png' width='10px' />
</a>
			</td></tr></table>");
		}
		
		foreach($headers as $key => $val){
			$str .= "<td><font class=afontstyle>".$val."</font></td>";
		}
		return $str;
    }
    function buildHeadersDis($headers, $types)
    {
		$str='';
		foreach($types as $typekey => $typeval){
			//array_push($headers,$typeval['name']."<br/> Hours Billable");
			array_push($headers,"<th valign='top' class='nowrap' valign='left'><font class=afontstylee>".$typeval['name']."</font></th>");
		}
		
		foreach($headers as $key => $val){
			$str .= $val;
		}
		return $str;
    }
    function buildHeadersSym($headersSym, $types)
    {
	    $str='';
		foreach($types as $typekey => $typeval){
			array_push($headersSym,"<td valign='top' class='bold'><table><tr><td><font class=afontstylee><b> Hours </font></td><td class='t-r'><font class=afontstylee><label title='Billable'>$</label></b></span></font></td></tr></table></td>");
		}
		
		foreach($headersSym as $key => $val){
			$str .= $val;
		}
		return $str;
    }

    function buildDynamicHeaders($defHeaders, $asgnids_all)
    {
	//$asgnArr = $this->getRateTypesForAllAsgn($asgnids_all);
	$asgnArr = $this->getRateTypesForAllAsgnnames($asgnids_all);
	
	$str = '';
	$headerCount = count($defHeaders);
	$ratetype = $this->getRateTypes();

	foreach($ratetype as $val)
	{
	    if(in_array($val['rateid'], $asgnArr))
	    {
		array_push($defHeaders, $val['name']);
	    }
	}
	$str .= '<tr class=hthbgcolor><th >&nbsp;</th>';
	foreach($defHeaders as $val)
	{
	    $str .= '<th valign="top" class="nowrap" align="left"><font class=afontstylee>'.$val.'</font></th>';
		
	}	
	$str .= "<th>&nbsp;</th></tr>";
	$str .= '<tr class=hthbgcolor><td style="background-color: white">&nbsp;</td>';
	$header = 0;
	foreach($defHeaders as $val)
	{
	   if($header >= $headerCount)
	    {
		/* $str .= '<td valign="top" style="background-color: white"><table><tr><td align="left" width="20%"><font class=afontstylee><b> Hours </font></td><td width="20%"><font class=afontstylee> <label  style="margin-left:4px;" title="Billable">$</label></b></font></td></tr></table></td>'; */
		
		$str .= '<td valign="top" style="background-color: white"><font class=afontstylee><b> Hours </font><font class=afontstylee> <label  title="Billable"><span style="margin-left:18px; -moz-margin-start:0px;">$</span></label></b></font></td>';
		
	    }
	    else
	    {
		$str .= '<td valign="top" style="background-color: white">&nbsp;</td>';
	    }
	    $header++;
		
	}
	$str .= "<td style='background-color: white'>&nbsp;</td></tr>";
	return $str;
    }
    
    function buildDynamicHeaders_multi($defHeaders)
    {
	$str = '';
	$headerCount = count($defHeaders);
	$ratetype = $this->getRateTypes();
	foreach($ratetype as $val)
	{
	    array_push($defHeaders, $val['name']);
	}
	$str .= '<tr class=hthbgcolor><th >&nbsp;</th>';
	foreach($defHeaders as $val)
	{
	    $str .= '<th valign="top" class="nowrap" align="left"><font class=afontstylee>'.$val.'</font></th>';
		
	}	
	$str .= "<th valign='top' class='nowrap' align='left'><font class=afontstylee>Total</font></th></tr>";
	$str .= '<tr class=hthbgcolor><td style="background-color: white">&nbsp;</td>';
	$header = 0;
	foreach($defHeaders as $val)
	{
	   if($header >= $headerCount)
	    {
		/* $str .= '<td valign="top" style="background-color: white"><table width="100%"><tr><td align="left" width="50%"><font class=afontstylee><b> Hours </font></td><td align="left" width="50%" align="right" ><font class=afontstylee> <label  title="Billable" style="text-align: -webkit-right;">$</label></b></font></td></tr></table></td>'; */
		$str .= '<td valign="top" style="background-color: white"><font class=afontstylee><b> Hours </font><font class=afontstylee> <label  title="Billable"><span style="margin-left:18px; -moz-margin-start:10px;">$</span></label></b></font></td>';
		
	    }
	    else
	    {
		$str .= '<td valign="top" style="background-color: white">&nbsp;</td>';
	    }
	    $header++;
		
	}
	$str .= "<td style='background-color: white'>&nbsp;</td></tr>";
	return $str;
    }
    
    function buildMainHeaders($mainHeaders,$mode)
    {
	$arrMode = array('approved' => 'Approved','exported' => 'Approved','rejected' => 'Rejected','deleted' => 'Deleted','Rejected' => 'Rejected');
	$str = '<tr class=hthbgcolorr>';
	if($mode == 'create')
	{
	    $str .= '<th valign="top" class="nowrap">&nbsp;</th>';
	}
	foreach($mainHeaders as $val)
	{
	    $str .= '<th valign="top" class="nowrap"><font class=afontstylee>'.$val.'</font></th>';
	}
	if(trim($mode) != 'pending' && trim($mode) !='errejected' && trim($mode) !='erer' && trim($mode) !='create' && trim($mode) !='Saved' && trim($mode) != '' && trim($mode)!= 'backup')
	  {
	    $str .= '<th valign="top" class="nowrap"><font class=afontstylee>'.$arrMode[$mode].' By</font></th>';
		$str .= '<th valign="top" class="nowrap"><font class=afontstylee>'.$arrMode[$mode].' Time</font></th>';
	  }
	
	$str .= '</tr>';
	
	return $str;
    }
    
    function buildSubHeaders($mainHeaders, $headerCount,$mode)
    {
	$arrMode = array('approved' => 'Approved','exported' => 'Approved','rejected' => 'Rejected','deleted' => 'Deleted');
	$str = '<tr>';
	if($mode == 'create')
	{
	    $str .= '<th valign="top" class="nowrap">&nbsp;</th>';
	}
	$header = 0;
	foreach($mainHeaders as $val)
	{
	    if($header >= $headerCount)
	    {
		$str .= '<td valign="top" class="bold"><table><tr><td><font class=afontstylee><b> Hours </font></td><td class="t-r"><font class=afontstylee><label  style="margin-left:4px;" title="Billable">$</label></b></span></font></td></tr></table></td>';
		
	    }
	    else
	    {
		$str .= '<td valign="top" class="nowrap">&nbsp;</td>';
	    }
	    $header++;
	}
	if(trim($mode) != 'pending' && trim($mode) !='errejected' && trim($mode) !='erer' && trim($mode) !='create' && trim($mode) !='Saved' && trim($mode) != '' && trim($mode)!= 'backup')
	{
	    $str .= '<th valign="top" class="nowrap"><font class=afontstylee>&nbsp;</font></th>';
		$str .= '<th valign="top" class="nowrap"><font class=afontstylee>&nbsp;</font></th>';
	  }
	$str .= '</tr>';
	
	return $str;
    }
    
    function getTimesheetDetails($sno, $mode,$condinvoice,$conjoin,$module='')
    {
		global  $accountingExport;
		
	$conjoin=str_replace("hrcon_jobs.","hj.",$conjoin);

	//$modeArr = array('pending'=>' and pt.astatus="ER" and th.status ="ER"','approved' =>'AND pt.astatus IN ("Approved","Billed","ER") AND th.status IN ("Approved","Billed")','exported' =>'AND pt.astatus IN ("Approved","Billed","ER") AND th.status IN ("Approved","Billed") and th.exported_status ="YES"','deleted'=>'AND pt.astatus IN ("Deleted") and th.status IN ("Deleted")','rejected'=>'AND pt.astatus IN ("Rejected") and th.status IN ("Rejected")','backup'=>' and th.status IN ("Backup")','errejected'=>'AND pt.astatus IN ("ER","Rejected") and th.status IN ("Rejected")','erer'=>'AND pt.astatus IN ("ER","Rejected") and th.status IN ("ER")');
	
	if($accountingExport == 'Exported' && $module != 'Client' && $module != 'MyProfile' && $module != 'Invoice') {
	$modeArr = array('pending'=>' and th.status ="ER"','approved' =>' AND th.status IN ("Approved","Billed") and th.exported_status !="YES"','exported' =>' AND th.status IN ("Approved","Billed") and th.exported_status ="YES"','deleted'=>' and th.status IN ("Deleted")','rejected'=>' and th.status IN ("Rejected")','backup'=>' and th.status IN ("Backup")','errejected'=>' and th.status IN ("Rejected")','erer'=>' and th.status IN ("ER")');
	} else {
     $modeArr = array('pending'=>' and th.status ="ER"','approved' =>' AND th.status IN ("Approved","Billed") ','exported' =>' AND th.status IN ("Approved","Billed") and th.exported_status ="YES"','deleted'=>' and th.status IN ("Deleted")','rejected'=>' and th.status IN ("Rejected")','backup'=>' and th.status IN ("Backup")','errejected'=>' and th.status IN ("Rejected")','erer'=>' and th.status IN ("ER")');

    }
	
	if($sno != 0)
	/*echo $sql = "SELECT count(*),th.assid, th.client, sc.cname, GROUP_CONCAT(th.sno) as sno, hj.sno as asgnsno, th.sdate, th.type, th.username, th.status, hj.project,".tzRetQueryStringDate('pt.sdate','Date','/')." AS pstartdate,DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, ".tzRetQueryStringDate('th.edate','Date','/')." AS enddate, DATE_FORMAT( th.sdate, '%W' ) AS weekday,".tzRetQueryStringDate('th.sdate','Date','/')." AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y %H:%i:%s' ) AS starttimedate, SUM( th.hours ) AS sumhours, th.classid,th.auser, u.name,".tzRetQueryStringDTime('th.approvetime','DateTime24','/')." AS approvetime,pt.issues,pt.astatus,pt.pstatus,pt.atime,pt.ptime,pt.puser,pt.notes,u.type as utype,th.payroll,th.task,DATE_FORMAT( th.edate, '%W' ) AS eweekday
 FROM par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno INNER JOIN emp_list el ON el.username = pt.username
LEFT JOIN users u ON u.username = th.auser 
".$conjoin."
 WHERE th.parid = '".$sno."'  ".$modeArr[$mode]." ".$condinvoice." and th.username = pt.username GROUP BY th.rowid"; */

 $sql = "SELECT count(*),th.assid, th.client, sc.cname, GROUP_CONCAT(th.sno) as sno, hj.sno as asgnsno, th.sdate, th.type, th.username, th.status, hj.project,".tzRetQueryStringDate('pt.sdate','Date','/')." AS pstartdate,DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, ".tzRetQueryStringDate('th.edate','Date','/')." AS enddate, DATE_FORMAT( th.sdate, '%W' ) AS weekday,".tzRetQueryStringDate('th.sdate','Date','/')." AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y %H:%i:%s' ) AS starttimedate,
	GROUP_CONCAT( DISTINCT CONCAT( hourstype, '|', hours, '|', billable ) ) AS time_data, SUM( th.hours ) AS sumhours, th.classid,th.auser, u.name,".tzRetQueryStringDTime('th.approvetime','DateTime24','/')." AS approvetime,pt.issues,pt.astatus,pt.pstatus,pt.atime,pt.ptime,pt.puser,pt.notes,u.type as utype,th.payroll,th.task,DATE_FORMAT( th.edate, '%W' ) AS eweekday
 FROM par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno INNER JOIN emp_list el ON el.username = pt.username
LEFT JOIN users u ON u.username = th.auser 
".$conjoin."
 WHERE th.parid = '".$sno."'  ".$modeArr[$mode]." ".$condinvoice." and th.username = pt.username GROUP BY th.rowid";

    else
	/*echo $sql = "SELECT count(*),th.assid, th.client, sc.cname, th.sno, hj.sno as asgnsno, th.sdate, th.type, th.username, th.status, hj.project,".tzRetQueryStringDate('pt.sdate','Date','/')." AS pstartdate,DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, ".tzRetQueryStringDate('th.edate','Date','/')." AS enddate, DATE_FORMAT( th.sdate, '%W' ) AS weekday,".tzRetQueryStringDate('th.sdate','Date','/')." AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y' ) AS starttimedate,
	 SUM( th.hours ) AS sumhours, th.classid,th.auser, u.name,".tzRetQueryStringDTime('th.approvetime','DateTime24','/')." AS approvetime,pt.issues,pt.astatus,pt.pstatus,pt.atime,pt.ptime,pt.puser,pt.notes,u.type as utype,th.payroll,th.task,DATE_FORMAT( th.edate, '%W' ) AS eweekday
 FROM par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno INNER JOIN emp_list el ON el.username = pt.username
LEFT JOIN users u ON u.username = th.auser ".$conjoin."
 WHERE 1=1  ".$modeArr[$mode]." ".$condinvoice." and th.username = pt.username GROUP BY th.rowid"; */

 $sql = "SELECT count(*),th.assid, th.client, sc.cname, th.sno, hj.sno as asgnsno, th.sdate, th.type, th.username, th.status, hj.project,".tzRetQueryStringDate('pt.sdate','Date','/')." AS pstartdate,DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, ".tzRetQueryStringDate('th.edate','Date','/')." AS enddate, DATE_FORMAT( th.sdate, '%W' ) AS weekday,".tzRetQueryStringDate('th.sdate','Date','/')." AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y' ) AS starttimedate,
	GROUP_CONCAT( DISTINCT CONCAT( hourstype, '|', hours, '|', billable) ) AS time_data, SUM( th.hours ) AS sumhours, th.classid,th.auser, u.name,".tzRetQueryStringDTime('th.approvetime','DateTime24','/')." AS approvetime,pt.issues,pt.astatus,pt.pstatus,pt.atime,pt.ptime,pt.puser,pt.notes,u.type as utype,th.payroll,th.task,DATE_FORMAT( th.edate, '%W' ) AS eweekday
 FROM par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno INNER JOIN emp_list el ON el.username = pt.username
LEFT JOIN users u ON u.username = th.auser ".$conjoin."
 WHERE 1=1  ".$modeArr[$mode]." ".$condinvoice." and th.username = pt.username GROUP BY th.rowid";
 
	//echo $sql;
	$result = $this->mysqlobj->query($sql,$this->db);
	
	while($row = $this->mysqlobj->fetch_array($result))
	{
	    $data[] = $row;
	}
	
	return $data;
    }
    
    function getRatevalues($data, $rateCount, $alldata)
    {
	foreach($alldata as $val)
	{
	       $usernamedb = $val['username'];
	      $servicedateto = $val['penddate'];
           $servicedate = $val['pstartdate'];
	}
	$this->getAssignments($usernamedb, '', $servicedate, $servicedateto, '0');
	$ratesArr =     $this->getRateTypesForAllAsgnnames($this->assignments);
	//$ratesArr = $this->getRateTypesForAllAsgn($asgnsnoarr);
	
	$allratearry =explode(",", $data);
	$RateTypes = $this->getRateTypes();
	
	foreach($RateTypes as $key=>$val1)
	{
	    if(!in_array($val1['rateid'], $ratesArr))
	    {
		unset($RateTypes[$key]);
	    }
	}
	
	$i=0;
	foreach($RateTypes as $typekey => $typeval)
	{
	    $i=0;
	      foreach($allratearry as $allratekey => $allrateval)
	      {
		    $eachrate =explode("|",$allratearry[$allratekey]);
			if(trim($typeval['rateid']) == trim($eachrate[0]))
			{
			    $i=1;
			    break;
			}
		    
	       }
	      if($i==1 && in_array($typeval['rateid'], $ratesArr))
	      {
	          $st = "<table><tr><td><font class=afontstylee>".$eachrate[1]."</font></td><td><font class=afontstylee>";
				if(($eachrate[2] == 'Yes' || $eachrate[2] != 'No' ) && $eachrate[2] != '') { $st .= "&nbsp;<input type='checkbox' checked='checked' disabled='disabled'/>"; } else { $st .= "<input type='checkbox'  disabled='disabled'/>";}
		   $st .= "</td></tr></table>";
				
		    $s .= '<td>'.$st.'</td>';
				
	      }
	      else
	      {
		     $s .= '<td>&nbsp;</td>';
	      }
	      
	}
	
	return $s;
    }
    
    function buildRow($data, $rowid, $rateCount,$mode, $module='', $alldata='', $print='')
    {
	//echo $mode;
	$arrMode = array('approved' => 'Approved','exported' => 'Approved','rejected' => 'Rejected','deleted' => 'Deleted');
	
	$class = $this->getClasses(" AND sno = $data[classid]");
	$str = '';
	$str .= '<tr>';
	
	////////////////////////////// Check box ////////////////
	if($print===''){
		if($module=='MyProfile'){
		$str .= '<td><input type="checkbox" onclick="chk_clearTop_TimeSheet()" value="'.$data['sno'].'" id="chk'.$rowid.'" name="auids[]" checked="checked"  class="cb-element" style="display:none;"></td>';
		}else{
			if($mode=='approved' || $mode=='exported' || $mode=='deleted'){
				$str .= '<td><input type="checkbox" onclick="chk_clearTop_TimeSheet()" value="'.$data['sno'].'" id="chk'.$rowid.'" name="auids[]" checked="checked"  class="cb-element" style="display:none;"></td>';
			}else{
				$str .= '<td><input type="checkbox" onclick="chk_clearTop_TimeSheet()" value="'.$data['sno'].'" id="chk'.$rowid.'" name="auids[]" checked="checked"  class="cb-element" "'.$style.'"></td>';
			}
			
		}
	}
	

	/////////////////////////// Dates //////////////////////
	 if($data['enddate'] !='00/00/0000')
	$str .= '<td class="nowrap"><font class=afontstylee>'.$data['startdate'].' - '.$data['enddate'].'</font></td>';
	else
	$str .= '<td class="nowrap"><font class=afontstylee>'.$data['startdate'].' '.$data['weekday'].'</font></td>';
	///////////////////////////// Assignment /////////////////////////////
			if($print===''){
				$str .= '<td ><span class="nowrap"><font class=afontstylee>('.$data['assid'].') '.$data['cname'].' - '.$data['project'].'</span><br/><b>Task Details :</b> '.wordwrap($data['task'], 60, "\n", true);
				$str .='</font></td>';
			}else{
				$str .= '<td ><span class="nowrap"><font class=afontstylee>('.$data['assid'].') '.$data['cname'].' - '.$data['project'].'</span><br/><font class=afontstylee><b>Task Details:</b>'.wordwrap($data['task'], 60, "\n", true);
				$str .='</font></td>';
			}
	/////////////////////////// Classes ////////////////////////////////
	if(MANAGE_CLASSES == 'Y')
	{
	    $str .= '<td class="nowrap"><font class=afontstylee>'.$class[0]['classname'].'</font></td>';
	}
	/////////////////////// Rate types ///////////////
	$str .= $this->getRatevalues($data['time_data'], $rateCount, $alldata);
if($mode != 'pending' && $mode !='errejected' && $mode !='erer')
	  {
	    
	     if($mode == 'approved' || $mode=='backup') {
	     if($data['utype']=="cllacc" && $data['auser']!="")
                    {
                        if($data['status']=="Approved" || $data['status']=="Billed")
						{
							if($data['status']!="Billed" && $data['payroll'] == '')
                           		$disSource="Self Svc (".$data['name'].")";
							else
								$disSource="Self Svc (".$data['name'].") (Billed)";
                        }
						if($data['status']=="Rejected")
                            $disSource="Rejected (".$data['name'].")";
						
                    }
                    else if($data['utype']!="cllacc" && $data['auser']!="")
                    {
						if($data['status']=="Approved" || $data['status']=="Billed")
						{
							if($data['status']!="Billed" && $data['payroll'] == '')
                           		$disSource="Accounting (".$data['name'].")";
							else
								$disSource="Accounting (".$data['name'].") (Billed)";
                        }                        
                        if($data['status']=="Rejected")
                            $disSource="Rejected (".$data['name'].")";
						
                    } else 
					    $disSource = $data['name'];
					$data['name'] =$disSource;
				}
		if(trim($mode) != 'pending' && trim($mode) !='errejected' && trim($mode) !='erer' && trim($mode) !='create' && trim($mode) !='Saved' && trim($mode) != '' && trim($mode)!= 'backup')
		{
	        $str .= '<td  class="nowrap"><font class=afontstylee>'.$data['name'].'</font></th>';
		$str .= '<td  class="nowrap"><font class=afontstylee>'.$data['approvetime'].'</font></td>';
		}
	  }	
	$str .= '</tr>';
	//$str .= '<tr>';
		
	return $str;
    }
    
    function getHoursSumRowPrint($data)
    {
	//$count = $data[0][0];
	$count = count($data);
	$sum = 0;
	for($i = 0; $i <= $count; $i++)
	{
	    $sum = $sum + $data[$i]['sumhours'];
	}
	///////////////////////////// Total hours ////////////////////
	echo '<tr><td colspan="1">';
	echo '<font class=afontstyle>&nbsp;</font></td>';
	echo '<td align=left><font class=afontstylee >Total Hours: &nbsp;&nbsp;</font><font class=afontstylee>&nbsp;</font></td>';
	echo '<td valign="top"><font class=afontstylee>'.number_format($sum,2,'.','').'</font></td>';
	echo '<td><font class=afontstylee>&nbsp;</font></td></tr>';
	//echo   $str;
    }
	
	
	function getHoursSumRowEmail($data)
    {
	//$count = $data[0][0];
	$sum_hours = '';
	$count = count($data);
	$sum = 0;
	for($i = 0; $i <= $count; $i++)
	{
	    $sum = $sum + $data[$i]['sumhours'];
	}
	///////////////////////////// Total hours ////////////////////
	$sum_hours .= '<tr><td colspan="1">';
	$sum_hours .=  '<font class=afontstyle>&nbsp;</font></td>';
	$sum_hours .=  '<td align=left><font class=afontstylee >Total Hours: &nbsp;&nbsp;</font><font class=afontstylee>&nbsp;</font></td>';
	$sum_hours .=  '<td valign="top"><font class=afontstylee>'.number_format($sum,2,'.','').'</font></td>';
	$sum_hours .=  '<td><font class=afontstylee>&nbsp;</font></td></tr>';
	//echo   $str;
	return $sum_hours;
    }
	
	 function getHoursSumRow($data)
    {
	//$count = $data[0][0];
	$count = count($data);
	$sum = 0;
	for($i = 0; $i <= $count; $i++)
	{
	    $sum = $sum + $data[$i]['sumhours'];
	}
	///////////////////////////// Total hours ////////////////////
	$str .= '<td colspan="1">';
	$str .= '<font class=afontstyle>&nbsp;</font></td>';
	$str .= '<td align=left><font class=hfontstyle >Total Hours: &nbsp;&nbsp;</font><font class=hfontstyle>&nbsp;</font></td>';
	$str .= '<td valign="top"><font class=hfontstyle>'.number_format($sum,2,'.','').'</font></td>';
	$str .= '<td><font class=hfontstyle>&nbsp;</font></td></tr>';
	return $str;
    }
    
    function getTimesheetAttachments($sno, $mode='')
    {
	$sql="select sno, name from time_attach where parid='".$sno."'";
	$result = $this->mysqlobj->query($sql,$this->db);
	$str .= '<table border="0" id="attachfiles"><tr><th colspan=2><font class=afontstylee>Attached Time Sheet File:</th><th>&nbsp;</th></tr>';
	$rowcount = 1;
	while($row = $this->mysqlobj->fetch_array($result))
	{
	    if($mode=='edit')
	    {
		$str1 = '<font class=afontstylee><a href="javascript: void(0);" onclick="delTimeAttach('.$row['sno'].', '.$sno.');">Delete file</a><font>';
	    }
	    $str .= '<tr id="'.$row['sno'].'"><td>&nbsp;</td><td><font class=afontstylee><a href="/include/downts.php?id='.$row['sno'].'">'.$row['name'].'</a>&nbsp;&nbsp;'.$str1.'</font></td></tr>';
	    $rowcount++;
	}
	$str .= '</table>';
	if( $rowcount == 1)
	$str ='';
	
	return $str;
    }
	
	function displaysubheading($sno,$mode,$module='')
	{
		global  $accountingExport;
		if($accountingExport == 'Exported' && $module !='Client' && $module !='MyProfile') {
			$modeArr = array('pending'=>' and th.status ="ER"','approved' =>' AND th.status IN ("Approved","Billed") and  th.exported_status !="YES"','exported' =>' AND  th.status IN ("Approved","Billed") and th.exported_status ="YES"','deleted'=>' AND   th.status IN ("Deleted")','rejected'=>' AND th.status IN ("Rejected")','errejected'=>' and th.status IN ("Rejected")','erer'=>' and th.status IN ("ER")','Saved'=>' and  th.status ="Saved"');
		} else {
			 $modeArr = array('pending'=>' and th.status ="ER"','approved' =>' AND th.status IN ("Approved","Billed") ','exported' =>' AND  th.status IN ("Approved","Billed") and th.exported_status ="YES"','deleted'=>' and th.status IN ("Deleted")','rejected'=>' AND th.status IN ("Rejected")','errejected'=>' and th.status IN ("Rejected")','erer'=>' and th.status IN ("ER")','Saved'=>' and  th.status ="Saved"');

		}
	
	$sql = "SELECT el.name, DATE_FORMAT( pt.stime, '%m/%d/%Y %H:%i:%s' ) as stimedate,".tzRetQueryStringDate('pt.sdate','Date','/')." as sdate, ".tzRetQueryStringDate('pt.edate','Date','/')." as edate
 FROM par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno INNER JOIN emp_list el ON el.username = pt.username
LEFT JOIN users u ON u.username = th.auser
 WHERE th.parid = '".$sno."'  ".$modeArr[$mode]." and th.username = pt.username GROUP BY th.parid";
	$result = $this->mysqlobj->query($sql,$this->db);
	$row = $this->mysqlobj->fetch_array($result);
	if($mode == 'pending' || $mode =='Saved') {

		$header_text	= ($mode == 'Saved') ? 'Saved&nbsp;Timesheet' : 'Submitted&nbsp;Timesheet';

		$output =  "<td colspan=2><font class=modcaption>&nbsp;&nbsp;$header_text</font></td>
	            <td align=right><font class=afontstyle color=black>&nbsp;&nbsp;Following are <b>".$row['name']."</b> Time Sheet details from <b>".$row['sdate']."</b> to <b>".$row['edate']."</b>.</font></td>";
	}
	if($mode == 'approved')
	$output =" <td colspan=2><font class=modcaption>&nbsp;&nbsp;Approved&nbsp;Timesheet</font></td>
                <td align=right><font class=afontstyle>Time Sheet Submitted by <b>".$row['name']."</b> on <b>".$row['stimedate']."</b>.</font></td>";
	if($mode == 'deleted' || $mode == 'Deleted')
	 $output ="<td colspan=2><font class=modcaption>&nbsp;&nbsp;Deleted&nbsp;Timesheet</font></td>
                <td align=right><font class=afontstyle>Time Sheet Submitted by <b>".$row['name']."</b> on <b>".$row['stimedate']."</b>.</font></td>";
   	if($mode == 'rejected' || $mode == 'Rejected')
	$output ="<td colspan=2><font class=modcaption>&nbsp;&nbsp;Rejected&nbsp;Timesheet</font></td>
                <td align=right><font class=afontstyle>Time Sheet Submitted by <b>".$row['name']."</b> on <b>".$row['stimedate']."</b>.</font></td>";
	if($mode == 'exported')
	$output ="<td colspan=2><font class=modcaption>&nbsp;&nbsp;Exported&nbsp;Timesheet</font></td>
                <td align=right><font class=afontstyle>Time Sheet Submitted by <b>".$row['name']."</b> on <b>".$row['stimedate']."</b>.</font></td>";
	
	
	$empdata ="<tr>
				<td>
					<table width=100% cellpadding=0 cellspacing=0 border=0>
						<tr>".$output."</tr></table>
				</td>
				</tr>";
	
	   return $empdata;
 
	}
    
function displayTimesheetDetailsPrint($sno, $mode,$condinvoice ='',$conjoin='', $module='', $print=True)
    {
	$table = '<table id="grid_form" class="grid_forms" style="white-space:nowrap">';
	echo '<table cellspacing="1" cellpadding="5" width="100%"  border=0 style="text-align:left;"> ';
	if($module=='MyProfile'){
		$chk_cond = '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none">';
	}else{
			
		if($mode=='approved' || $mode=='exported' || $mode=='deleted'){
			$chk_cond = '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none;">';
		}else{
			$chk_cond = '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()">';
		}
	}
	
	// get the timesheet details
	$data = $this->getTimesheetDetails($sno, $mode,$condinvoice,$conjoin, $module);

	foreach($data as $val)
	{

        $usernamedb = $val['username'];
	    $servicedateto = $val['penddate'];
        $servicedate = $val['pstartdate'];
	    //$asgnsnoarr[] = $val['asgnsno'];
	}
	$this->getAssignments($usernamedb, '', $servicedate, $servicedateto, '0');
	$ratesArr =     $this->getRateTypesForAllAsgnnames($this->assignments);

	//$ratesArr = $this->getRateTypesForAllAsgn($asgnsnoarr);
	if($print===True){
		$headerArr = array('Date', 'Assignments');
	}else{
		$headerArr = array($chk_cond, 'Date', 'Assignments');
	}
	
	if(MANAGE_CLASSES == 'Y')
	{
	    array_push($headerArr, 'Class');
	}
	$headerCount = count($headerArr);
	$ratetype = $this->getRateTypes();
	$rateCount = count($ratesArr);
	foreach($ratetype as $val)
	{
	    if(in_array($val['rateid'], $ratesArr))
	    {
		array_push($headerArr, $val['name']);
	    }
	}
	/////////////////////////// Main Headers ///////////////////////
	
	echo  $this->buildMainHeaders($headerArr,$mode);
	//////////////////////// Sub Headers (Hour & Billable) ////////
	echo  $this->buildSubHeaders($headerArr, $headerCount,$mode);
	
	
	foreach($data as $key=>$val)
	{
	    //////////////////////// Sub Headers (Hour & Billable) ////////
	    echo  $this->buildRow($val, $key, $rateCount,$mode, $module, $data,$print=True);
	}
	////////////// Total hours //////////
	$this->getHoursSumRowPrint($data);
	
	if($mode != 'pending' && $mode != 'errejected' && $mode != 'erer')
	$count = count($headerArr) +2;
	else if($mode == 'pending')
	$count = count($headerArr);
	else if($mode == 'errejected' || $mode =='erer')
	$count = count($headerArr) - 1;
	//////////////////////////// Submitted date ////////////////
	echo '<tr class=hthbgcolor><td colspan='.$count.' class="nowrap"><font class=afontstylee>Submitted Date:&nbsp;<b>'.$data[0]['starttimedate'].'</font></td>';
	if($mode == 'errejected' || $mode == 'erer')
	{
	echo '<td colspan='.$count.' class="nowrap"><font class=afontstylee>'.'</font></td>';
	}
	echo '</tr>';
	echo '</table> ';
	echo '</table>';
	
	//////////////////////////// Remarks ////////////////
	if($data[0]['issues'] != '') {
	echo '<br /><font class=afontstylee><b>Remarks:</b>&nbsp;'.WrapText(htmlspecialchars(stripslashes($data[0]['issues'])),60,'').'</font>';
	}
	
	////////////////notes/////////////////////////////////
	if($data[0]['notes'] !='' || $data[0]['notes'] !=NULL)
	echo '<br /><font class=afontstylee><b>Notes:</b>&nbsp;'.WrapText(htmlspecialchars(stripslashes($data[0]['notes'])),60,'').'</font>';
	///////////////////////backup data////////////////////////////////
	//echo '<br/>'.$this->DisplaybackupTimesheetPrint($sno);
	//$table.='<br />';
	
	///////////////////////// Timesheet Attachments ////////////////
	//echo  $this->getTimesheetAttachments($sno);
	
	//return $table;	
		echo $table;	
    }	
	
	
	function displayTimesheetDetailsEmail($sno, $mode,$condinvoice ='',$conjoin='', $module='')
    {
	$table = '<div id="grid_form" class="grid_forms" style="white-space:nowrap">';
	$table .= '<table cellspacing="1" cellpadding="5" width="100%"  border=0 style="text-align:left;"> ';
		
	// get the timesheet details
	$data = $this->getTimesheetDetails($sno, $mode,$condinvoice,$conjoin,$module);

	foreach($data as $val)
	{

          $usernamedb = $val['username'];
	      $servicedateto = $val['penddate'];
        $servicedate = $val['pstartdate'];
	    //$asgnsnoarr[] = $val['asgnsno'];
	}
	 $this->getAssignments($usernamedb, '', $servicedate, $servicedateto, '0');
	$ratesArr =     $this->getRateTypesForAllAsgnnames($this->assignments);

	//$ratesArr = $this->getRateTypesForAllAsgn($asgnsnoarr);
	
	$headerArr = array('Date', 'Assignments');
	
	if(MANAGE_CLASSES == 'Y')
	{
	    array_push($headerArr, 'Class');
	}
	$headerCount = count($headerArr);
	$ratetype = $this->getRateTypes();
	$rateCount = count($ratesArr);
	foreach($ratetype as $val)
	{
	    if(in_array($val['rateid'], $ratesArr))
	    {
		array_push($headerArr, $val['name']);
	    }
	}
	/////////////////////////// Main Headers ///////////////////////
	
	$table .= $this->buildMainHeaders($headerArr,$mode);
	//////////////////////// Sub Headers (Hour & Billable) ////////
	$table .= $this->buildSubHeaders($headerArr, $headerCount,$mode);
	
	
	foreach($data as $key=>$val)
	{
	    //////////////////////// Sub Headers (Hour & Billable) ////////
	    //$table .= $this->buildRow($val, $key, $rateCount,$mode, $module, $data);
		$table .= $this->buildRow($val, $key, $rateCount,$mode, $module, $data,$print=True);
	}
	////////////// Total hours //////////
	$table .= $this->getHoursSumRowEmail($data);
	
	if($mode != 'pending' && $mode != 'errejected' && $mode != 'erer')
	$count = count($headerArr) +2;
	else if($mode == 'pending')
	$count = count($headerArr);
	else if($mode == 'errejected' || $mode =='erer')
	$count = count($headerArr) - 1;
	//////////////////////////// Submitted date ////////////////
	$table .= '<tr class=hthbgcolor><td colspan='.$count.' class="nowrap"><font class=afontstylee>Submitted Date:&nbsp;<b>'.$data[0]['starttimedate'].'</font></td>';
	if($mode == 'errejected' || $mode == 'erer')
	{
	$table .= '<td colspan='.$count.' class="nowrap"><font class=afontstylee>'.'</font></td>';
	}
	$table .='</tr>';
	$table .= '</table> ';
	$table .= '</div>';
	
	///////////////////////// Timesheet Attachments ////////////////
	//$table .= $this->getTimesheetAttachments($sno);
	
	return $table;	
    }
	
   function displayTimesheetDetails($sno, $mode,$condinvoice ='',$conjoin='', $module='')
    {
	$table = '<div id="grid_form" class="grid_forms" style="white-space:nowrap">';
	$table .= '<table cellspacing="1" cellpadding="5" width="100%"  border=0 style="text-align:left;"> ';
	if($module=='MyProfile'){
		$chk_cond = '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none">';
	}else{
			
		if($mode=='approved' || $mode=='exported' || $mode=='deleted'){
			$chk_cond = '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none;">';
		}else{
			$chk_cond = '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()">';
		}
	}
	
	// get the timesheet details
	$data = $this->getTimesheetDetails($sno, $mode,$condinvoice,$conjoin,$module);

	foreach($data as $val)
	{

          $usernamedb = $val['username'];
	      $servicedateto = $val['penddate'];
        $servicedate = $val['pstartdate'];
	    //$asgnsnoarr[] = $val['asgnsno'];
	}
	 $this->getAssignments($usernamedb, '', $servicedate, $servicedateto, '0');
	$ratesArr =     $this->getRateTypesForAllAsgnnames($this->assignments);

	//$ratesArr = $this->getRateTypesForAllAsgn($asgnsnoarr);
	
	$headerArr = array($chk_cond, 'Date', 'Assignments');
	
	if(MANAGE_CLASSES == 'Y')
	{
	    array_push($headerArr, 'Class');
	}
	$headerCount = count($headerArr);
	$ratetype = $this->getRateTypes();
	$rateCount = count($ratesArr);
	foreach($ratetype as $val)
	{
	    if(in_array($val['rateid'], $ratesArr))
	    {
		array_push($headerArr, $val['name']);
	    }
	}
	/////////////////////////// Main Headers ///////////////////////
	
	$table .= $this->buildMainHeaders($headerArr,$mode);
	//////////////////////// Sub Headers (Hour & Billable) ////////
	$table .= $this->buildSubHeaders($headerArr, $headerCount,$mode);
	
	
	foreach($data as $key=>$val)
	{
	    //////////////////////// Sub Headers (Hour & Billable) ////////
	    $table .= $this->buildRow($val, $key, $rateCount,$mode, $module, $data);
	}
	////////////// Total hours //////////
	$table .= $this->getHoursSumRow($data);
	
	if($mode != 'pending' && $mode != 'errejected' && $mode != 'erer')
	$count = count($headerArr) +2;
	else if($mode == 'pending')
	$count = count($headerArr);
	else if($mode == 'errejected' || $mode =='erer')
	$count = count($headerArr) - 1;
	//////////////////////////// Submitted date ////////////////
	$table .= '<tr class=hthbgcolor><td colspan='.$count.' class="nowrap"><font class=afontstylee>Submitted Date:&nbsp;<b>'.$data[0]['starttimedate'].'</font></td>';
	if($mode == 'errejected' || $mode == 'erer')
	{
	$table .= '<td colspan='.$count.' class="nowrap"><font class=afontstylee>'.'</font></td>';
	}
	$table .='</tr>';
	$table .= '</table> ';
	$table .= '</div>';
	
	//////////////////////////// Remarks ////////////////
	if($data[0]['issues'] != '') {
	$table .= '<br /><font class=afontstylee><b>Remarks:</b>&nbsp;'.WrapText(htmlspecialchars(stripslashes($data[0]['issues'])),60,'').'</font>';
	}
	
	////////////////notes/////////////////////////////////
	if($data[0]['notes'] !='' || $data[0]['notes'] !=NULL)
	$table .= '<br /><font class=afontstylee><b>Notes:</b>&nbsp;'.WrapText(htmlspecialchars(stripslashes($data[0]['notes'])),60,'').'</font>';
	///////////////////////backup data////////////////////////////////
	$table.='<br/>'.$this->DisplaybackupTimesheet($sno);
	//$table.='<br />';
	
	///////////////////////// Timesheet Attachments ////////////////
	$table .= $this->getTimesheetAttachments($sno);
	
	echo $table;	
    }

    function getRateTypesForAllAsgn($asgnIds)
    {
	$AsgnIdStr = implode(",", $asgnIds);
	//$select_ratemaster_asgn = "SELECT distinct ratemasterid as rateid FROM testingnew.multiplerates_assignment t1 inner join multiplerates_master t2 on t1.ratemasterid = t2.rateid WHERE asgnid in(".$AsgnIdStr.") AND ratetype='billrate' AND asgn_mode = 'hrcon' and rateid !='rate4' and t2.status = 'Active'";
	
	$select_ratemaster_asgn = "SELECT DISTINCT ratemasterid AS rateid FROM multiplerates_assignment t1 INNER JOIN hrcon_jobs t3 ON t1.asgnid = t3.sno LEFT JOIN multiplerates_master t2 ON t1.ratemasterid = t2.rateid WHERE t3.sno IN(".$AsgnIdStr.") AND ratetype='billrate' AND asgn_mode = 'hrcon' AND t2.status = 'Active'";

	$result_ratemaster_asgn=mysql_query($select_ratemaster_asgn,$this->db);
	$this->rateTypeCountSingle = mysql_num_rows($result_ratemaster_asgn);
	while($row_ratemaster_asgn=mysql_fetch_array($result_ratemaster_asgn))
	{
	    $rateTypesAsgn[] = $row_ratemaster_asgn['rateid'];
	}
	return $rateTypesAsgn;
    }
    
    function getRateTypesForAllAsgnnames($asgnIds, $inout_flag = false)
    {
	$AsgnIdStr = "'";
	$AsgnIdStr .= implode("','", $asgnIds);
	$AsgnIdStr .= "'";

	// FIXED RATES FOR TIMEINTIMEOUT
	$where_clause	= '';

	if ($inout_flag) {

		$where_clause	= " AND t2.rateid IN ('rate1','rate2','rate3') ";
	}

	//echo $select_ratemaster_asgn = "SELECT distinct ratemasterid as rateid FROM testingnew.multiplerates_assignment t1 inner join multiplerates_master t2 on t1.ratemasterid = t2.rateid inner join hrcon_jobs t3 on t1.asgnid = t3.sno WHERE pusername in(".$AsgnIdStr.") AND ratetype='billrate' AND asgn_mode = 'hrcon' and rateid !='rate4' and t2.status = 'Active'";
	
	$select_ratemaster_asgn = "SELECT DISTINCT ratemasterid AS rateid FROM multiplerates_assignment t1 INNER JOIN hrcon_jobs t3 ON t1.asgnid = t3.sno LEFT JOIN multiplerates_master t2 ON t1.ratemasterid = t2.rateid WHERE pusername IN(".$AsgnIdStr.") AND ratetype='billrate' AND asgn_mode = 'hrcon' AND t2.status = 'Active' $where_clause ";

	$result_ratemaster_asgn=$this->mysqlobj->query($select_ratemaster_asgn,$this->db);
	$this->rateTypeCountSingle = $this->mysqlobj->num_rows($result_ratemaster_asgn);
	while($row_ratemaster_asgn=$this->mysqlobj->fetch_array($result_ratemaster_asgn))
	{
	    $rateTypesAsgn[] = $row_ratemaster_asgn['rateid'];
	}
	return $rateTypesAsgn;
    }
    
    
	function DisplaybackupTimesheetPrint($sno)
	{
		$bakupquery="SELECT ".tzRetQueryStringDTime('approvetime','DateTimeSec','-').",auser,notes,DATE_FORMAT(approvetime,'%Y-%m-%d %H:%i:%s') FROM timesheet_hours WHERE parid='".$sno."' AND status='Backup' GROUP BY approvetime ORDER BY approvetime DESC";
		$backresult = $this->mysqlobj->query($bakupquery,$this->db);
	
		
		$display = "";
		while($backupRow=$this->mysqlobj->fetch_array($backresult))
		{
			$sql_user = "SELECT name,type from users WHERE username='".$backupRow[1]."'";
			$res_user=mysql_query($sql_user,$this->db);
			$nameAndsource=mysql_fetch_row($res_user);
			$backupNotes = htmlspecialchars($backupRow[2],ENT_QUOTES);
			$display .=  "<tr>
							<td class='nowrap'><font class=afontstyle>$backupRow[0]</font></td>
							<td class='nowrap'><font class=afontstyle>$nameAndsource[0]</font></td>
							<td class='nowrap'><font class=afontstyle>{$backupNotes}</font></td>
							
						</tr>";
		}
		if($display !='')
		{
		
		$final_display ='
			<table width="100%" cellpadding="0" cellspacing="0" style="text-align:left">
				<tr class=hthbgcolor>
					<th class="nowrap">
					<font class=afontstyle>Date Updated</font>
					</th>
					<th class="nowrap">
					<font class=afontstyle>Updated By</font>
					</th>
					<th class="nowrap">
					<font class=afontstyle>Notes</font>
					</th>
				</tr>';
				$final_display .= $display.'</table>';
		}
		return $final_display;
	}
	
	
	function DisplaybackupTimesheet($sno)
	{
		$bakupquery="SELECT ".tzRetQueryStringDTime('approvetime','DateTimeSec','-').",auser,notes,DATE_FORMAT(approvetime,'%Y-%m-%d %H:%i:%s') FROM timesheet_hours WHERE parid='".$sno."' AND status='Backup' GROUP BY approvetime ORDER BY approvetime DESC";
		$backresult = $this->mysqlobj->query($bakupquery,$this->db);
	
		
		$display = "";
		while($backupRow=$this->mysqlobj->fetch_array($backresult))
		{
			$sql_user = "SELECT name,type from users WHERE username='".$backupRow[1]."'";
			$res_user=mysql_query($sql_user,$this->db);
			$nameAndsource=mysql_fetch_row($res_user);
			$backupNotes = htmlspecialchars($backupRow[2],ENT_QUOTES);
			$display .=  "<tr>
							<td class='nowrap'><font class=afontstyle><a href='#' onclick=\"javascript:openwin('$backupRow[3]', '$sno');\">$backupRow[0]</a></font></td>
							<td class='nowrap'><font class=afontstyle>$nameAndsource[0]</font></td>
							<td class='nowrap'><font class=afontstyle>{$backupNotes}</font></td>
							
						</tr>";
		}
		if($display !='')
		{
		
		$final_display ='
			<table width="100%" cellpadding="0" cellspacing="0" style="text-align:left">
				<tr class=hthbgcolor>
					<th class="nowrap">
					<font class=afontstyle>Date Updated</font>
					</th>
					<th class="nowrap">
					<font class=afontstyle>Updated By</font>
					</th>
					<th class="nowrap">
					<font class=afontstyle>Notes</font>
					</th>
				</tr>';
				$final_display .= $display.'</table>';
		}
		return $final_display;
	}
	
    function getSubmitedTsDetails($empid, $asgnid, $datefrom, $dateto)
    {
	$assign_start_date = $datefrom;
	$assign_end_date = $dateto;
	
	$sql = "SELECT s.sdate, s.edate, s.task, GROUP_CONCAT(CAST(s.ratetypes AS CHAR)) AS rate, s.assid, s.classid FROM
		    (
			SELECT 	t1.sdate, t1.edate, GROUP_CONCAT(t1.task) AS task, CONCAT(t1.hourstype, '|', SUM(t1.hours), '|', t1.billable) AS ratetypes,  t1.assid, t1.classid, t1.status, t1.rowid FROM timesheet_hours t1 LEFT JOIN hrcon_jobs t2 ON t1.assid = t2.pusername WHERE t1.username = '".$empid."' AND t2.sno = '".$asgnid."' AND (t1.sdate BETWEEN '".$datefrom."' AND '".$dateto."' || t1.edate BETWEEN '".$datefrom."' AND '".$dateto."') AND t1.status IN ('ER', 'Approved', 'Build') GROUP BY t1.assid, t1.hourstype
		    ) s GROUP BY s.assid";
	$result=$this->mysqlobj->query($sql,$this->db);
	
	$row=$this->mysqlobj->fetch_array($result);		
	return $row;
    }
    
    // For getting company id of CSS User
    function getClientId($username){
	
	$sel="select staffacc_contact.username from staffacc_contactacc,staffacc_contact where staffacc_contactacc.con_id=staffacc_contact.sno and staffacc_contactacc.username = '".$username."'";
	$ressel=mysql_query($sel,$this->db);
	$rssel=mysql_fetch_row($ressel);

	$clSelsql = "SELECT sno from staffacc_cinfo WHERE type IN ('CUST', 'BOTH') AND username='".$rssel[0]."'";
	$resselSno=mysql_query($clSelsql,$this->db);
	$rsselSno=mysql_fetch_row($resselSno);
	$Cval=$rsselSno[0];
	return $Cval;
    }
    
    // get Client Id condition - CSS User
    function getClientValCond($username){
	    
	    // find client id based on assignments
	    $sel		=	"select staffacc_contact.username from staffacc_contactacc,staffacc_contact where staffacc_contactacc.con_id=staffacc_contact.sno and staffacc_contactacc.username = '$username'";
	    $ressel		=	mysql_query($sel,$this->db);
	    $rssel		=	mysql_fetch_row($ressel);

	    $clSelsql 	=	"SELECT sno from staffacc_cinfo WHERE type IN ('CUST', 'BOTH') AND username='".$rssel[0]."'";
	    $resselSno	=	mysql_query($clSelsql,$this->db);
	    $rsselSno	=	mysql_fetch_row($resselSno);
	    $Cval		=	$rsselSno[0];
	    
	    $clientcond	=	" AND th.client=$Cval ";
	    return $clientcond;
    }
    
    // get Billable condition - CSS User
    function getBillableCond($username){
	   
	    // Check user preferences For CSS User
	    $sqlSelfPref		= 	"select sno, username, joborders, candidates, assignments, placements, billingmgt, timesheet, invoices, expenses, 	joborders_owner from selfservice_pref where username='".$username."'";
	    $resSelfPref		= 	mysql_query($sqlSelfPref,$this->db);
	    $userSelfServicePref	=	mysql_fetch_row($resSelfPref);
	    
	    if(strpos($userSelfServicePref[7],"+6+"))
		    $billcond		=	" AND th.billable !='' AND th.billable !='no' ";
						    
	    return $billcond;
    }
	
    // get Client Join Table condition - CSS User
    function getClientJoinCond($username){	    
	    
	    $sqlSelfPref		= 	"select sno, username, joborders, candidates, assignments, placements, billingmgt, timesheet, invoices, expenses, joborders_owner from selfservice_pref where username='".$username."'";
	    $resSelfPref		= 	mysql_query($sqlSelfPref,$this->db);
	    $userSelfServicePref	=	mysql_fetch_row($resSelfPref);
	    
	    if(strpos($userSelfServicePref[7],"+4+") || strpos($userSelfServicePref[7],"+5+"))
	    {
		    if(strpos($userSelfServicePref[7],"+4+"))
			    $chkContact = "OR hj.contact = staffacc_contactacc.con_id";
						    
		    $clientjoin 	=	" LEFT JOIN staffacc_contactacc ON hj.manager = staffacc_contactacc.con_id ".$chkContact;
		    $clientcond		=	" AND staffacc_contactacc.username = '$username' ";
	    }
	    return $clientjoin." | ".$clientcond;
    }
    
    function getMaxRowId($parid){
	
	$sel	 	= 	"SELECT MAX(rowid) FROM timesheet_hours WHERE parid=".$parid;
	$ressel 	=	mysql_query($sel,$this->db);
	$rssel		=	mysql_fetch_row($ressel);
	$maxRowId	=	$rssel[0];
	return $maxRowId;
    }
       
}
?>