<?php
require_once('timesheet/class.Timesheet.php');

/**
 * This class implements the methods for handling the TimeInTimeOut - Timesheets
 */

class TimeInTimeOut extends AkkenTimesheet {

	public function __construct($db) {

		parent::__construct($db);
	}

	public function buildTimeInTimeOutHeaders($defHeaders, $asgnids_all) {

		$str		= '';
		$asgnArr	= $this->getRateTypesForAllAsgnnames($asgnids_all, true);
		$ratetype	= $this->getRateTypes();

		foreach ($ratetype as $val) {

			if (in_array($val['rateid'], $asgnArr)) {

				array_push($defHeaders, $val['name']);
			}
		}

		$str	.= '<tr class=hdrbgcolor><th>&nbsp;</th>';

		foreach ($defHeaders as $val) {

			$str .= '<th valign="top" class="nowrap" align="left"><font class=afontstylee>'.$val.'</font></th>';
		}

		$str	.= "<th>&nbsp;</th></tr>";
		$str	.= '<tr><td style="background-color: white">&nbsp;</td>';

		foreach ($defHeaders as $val) {

			if (strtolower($val) == 'regular' || strtolower($val) == 'overtime' || strtolower($val) == 'doubletime') {

				$str	.= '<td valign="top" style="background-color:white;text-align:center"><font class="smalltextfont">Hours</font></td>';

			} else {

				$str	.= '<td valign="top" style="background-color:white">&nbsp;</td>';
			}
		}

		$str	.= "<td style='background-color: white'>&nbsp;</td></tr>";

		return $str;
	}

	public function getAssignmentsByEmp($employee, $assignStartDate0, $assignEndDate0) {
  
		$assignStartDate	= date('Y-m-d', strtotime($assignStartDate0));
		$assignEndDate		= date('Y-m-d', strtotime($assignEndDate0));

		$zque	= "SELECT 
					sno, client, project, jtype, pusername,jotype, DATE_FORMAT(str_to_date(s_date,'%m-%d-%Y'),'%m/%d/%Y'), 
					DATE_FORMAT(str_to_date(e_date,'%m-%d-%Y'),'%m/%d/%Y') 
				FROM 
					hrcon_jobs 
				WHERE 
					username = '".$employee."' AND pusername!='' AND ((hrcon_jobs.ustatus IN ('active','closed','cancel') 
					AND (hrcon_jobs.s_date IS NULL OR hrcon_jobs.s_date='' OR hrcon_jobs.s_date='0-0-0' OR (DATE(STR_TO_DATE(s_date,'%m-%d-%Y'))<='".$assignEndDate."'))) 
					AND (IF(hrcon_jobs.ustatus='closed',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assignStartDate."'),1)) 
					AND (IF(hrcon_jobs.ustatus='cancel',(hrcon_jobs.e_date IS NOT NULL AND hrcon_jobs.e_date<>'' AND hrcon_jobs.e_date<>'0-0-0' AND DATE(STR_TO_DATE(e_date,'%m-%d-%Y'))>='".$assignStartDate."'),1))) 
					AND hrcon_jobs.jtype!='' 
				ORDER BY 
					pusername";

		$zres	= $this->mysqlobj->query($zque,$this->db);
		$zrowCount	= mysql_num_rows($zres);

		$this->assignments		= array();
		$this->assignmentIds	= array();

		while ($zrow = $this->mysqlobj->fetch_array($zres)) {

			$this->assignments[] = $zrow[4];
			$this->assignmentIds[] = $zrow[0];
		}
	}

	public function getCreateTimesheetHTML($employee, $assign_id = '', $rtype = '', $task='', $assignStartEndDate, $assignStartDate, $assignEndDate, $classid, $rowid, $range='no', $timesheet_hours_sno = '', $edit_string = '', $editRowid='',$module='', $rowtotal='0.00', $parid = '', $tab_index = '', $cval = '') {

		$this->mystr[]	= $timesheet_hours_sno;

		$rangRow	= "<tr id='row_".$rowid."' class='tr_clone'>";

		////////////////// Dates dropdown ///////////////////////////
		$rangRow	.= "<td valign='top' width='2%'>
		<input type='hidden' id='edit_string' name='edit_string[".$rowid."]' value='".$edit_string."'>
		<input type='hidden' id='edit_snos_new' name='edit_snos_new[".$rowid."]' value='".$timesheet_hours_sno."'>
		<input type='checkbox' name='daily_check[".$rowid."][]' id='check_".$rowid."' value='".$timesheet_hours_sno."' class='chremove' style='margin-top:0px;' tabindex='".$tab_index++."'></td>"; 

		$rangRow	.= "<td valign='top' align='left' width='10%'>";
		$valrow_id = "daily_dates_".$rowid;
		$script = 'onchange="getDataOnDate(this.id);"';

		$rangRow	.= $this->buildDropDownCheck('daily_dates', $rowid, $assignStartEndDate, $assignStartDate, $script, $key='', $val='', $range, $employee, true, $tab_index++);
		$rangRow	.= "<br /><font title='click here to add task details' onclick='javascript:AddTaskDetails(this.id)' id='addtaskdetails_".$rowid."' class='addtaskBtn' style='padding-top: 0px; white-space:nowrap;'>Click to Add Task Details </font>";
		$rangRow	.= "</td>";

		////////////////// Assignments dropdown ///////////////////////////
		$asgnDropDown	= $this->getAssignments($employee, $assign_id, $assignStartDate, $assignEndDate, $rowid, $module, $tab_index++, true, $cval);

		if (count($this->assignments) > 1) {

			$multicss = "background='/PSOS/images/arrow-multiple-12-red.png' style='background-repeat:no-repeat;background-position:left top; padding-left: 17px;'";
		}

		$scriptOB = "onblur='javascript:hideTaskDetailsTextBox(".$rowid.");'";

		$rangRow	.= "<td valign='top' class='nowrap' width='30%' ".$multicss." >";
		$rangRow	.= '<span id="span_'.$rowid.'">'.$asgnDropDown.'</span><br/>';
		$rangRow	.= "<label id='textlabel_".$rowid."' title='Click here to add task details' class=afontstylee onClick='javascript:AddTaskDetails(this.id)'  style='display:inline;padding-top:5px;float:left'>".$task."</label>";
		$rangRow	.= "<input style='display:none;padding-top:3px;width:400px;' class='addtaskdetails' type='text' name='daily_task[0][".$rowid."]' value='".$task."' id='taskTB_".$rowid."' ".$scriptOB.">";
		$rangRow	.= "</td>";

		if (MANAGE_CLASSES == 'Y') {

			////////////////// Classes dropdown ///////////////////////////
			$rangRow	.= "<td valign='top' width='8%'>";
			$rangRow	.= $this->buildDropDownClasses('daily_classes', $rowid, $this->getClasses(), $classid, '','sno', 'classname', '', $tab_index++);
			$rangRow	.= "</td>";
		}

		if (strpos($assign_id, 'earn')) {

			$assignment_id	= ($assign_id=='') ? $this->assignmentIds[0]:$assign_id;

		} else {

			$assignment_id	= ($assign_id=='') ? $this->assignmentIds[0]:$this->getAssignId($assign_id);
		}

		$rangRow	.= $this->getTimeInTimeOutHTML($rowid, $parid, $tab_index);

		$rangRow	.= "<div id='raterow_".$rowid."'>".$this->getTimeInTimeOutRateTypes($assignment_id, $rtype, $rowid, $par_id, '', '', 'single', $this->getRateTypesForAllAsgnnames($this->assignments, true))."</div>";

		///////////////////////// Total hours /////////////////////////
		$rangRow	.= "<td><div id='tot_hours'><input type='hidden' name='daytotalhrs_".$rowid."' id='daytotalhrs_".$rowid."' value='' ><input type='hidden' value='".$editRowid."' id='editrow_".$rowid."' name='editrow[]'></div></td></tr>";

		return $rangRow;
	}

	private function getTimeInTimeOutHTML($row_id, $parid, $tab_index) {

		$pre_in_time	= '';
		$pre_out_time	= '';
		$break_hours	= '';
		$post_in_time	= '';
		$post_out_time	= '';

		if (isset($parid) && !empty($parid)) {

			$tito_details	= $this->getTimeInTimeOutDetails($parid, $row_id);

			if (!empty($tito_details)) {

				foreach ($tito_details as $key => $object) {

					$pre_in_time	= $object->pre_in_time;
					$pre_out_time	= $object->pre_out_time;
					$break_hours	= $object->break_time;
					$post_in_time	= $object->post_in_time;
					$post_out_time	= $object->post_out_time;
				}
			}
		}

		$create_ts_html	.= "
			<td class='afontstylee' valign='top' width='8%' align='center'>
				<input type='text' id='pre_intime_".$row_id."' name='pre_intime[0][".$row_id."]' value='".$pre_in_time."' size='7' class='rowIntime' style='font-family:Arial;font-size:9pt;' onchange='javascript:calculateTime(this.id);' tabindex='".$tab_index++."'>
			</td>
			<td class='afontstylee' valign='top' width='8%' align='center'>
				<input type='text' id='pre_outtime_".$row_id."' name='pre_outtime[0][".$row_id."]' value='".$pre_out_time."' size='7' class='rowOuttime' style='font-family:Arial;font-size:9pt;' onchange='javascript:calculateTime(this.id);' tabindex='".$tab_index++."'>
			</td>
			<td class='afontstylee' valign='top' align='center' width='7%'>
				<input type='text' id='break_hours_".$row_id."' name='break_hours[0][".$row_id."]' value='".$break_hours."' size='4' class='rowBreaktime' style='font-family:Arial;font-size:9pt;background-color:#EDE9E9;' readonly>
			</td>
			<td class='afontstylee' valign='top' width='8%' align='center'>
				<input type='text' id='post_intime_".$row_id."' name='post_intime[0][".$row_id."]' value='".$post_in_time."' size='7' class='rowIntime' style='font-family:Arial;font-size:9pt;' onchange='javascript:calculateTime(this.id);' tabindex='".$tab_index++."'>
			</td>
			<td class='afontstylee' valign='top' width='8%' align='center'>
				<input type='text' id='post_outtime_".$row_id."' name='post_outime[0][".$row_id."]' value='".$post_out_time."' size='7' class='rowOuttime' style='font-family:Arial;font-size:9pt;' onchange='javascript:calculateTime(this.id);' tabindex='".$tab_index++."'>
			</td>";

		return $create_ts_html;
	}

	public function getTimeInTimeOutRateTypes($asignid, $rates='', $rowid, $parid, $mode='', $req_str='', $type='', $ratesAvail) {

		$req_bill_arr	= explode(',',$req_str[4]);
		$req_rate_arr	= explode(',',$req_str[5]);

		$ratetypes	= array();
		$rateHourArr	= array();

		$hide_chkbox	= ($inout_flag) ? 'display:none;' : '';

		if (!empty($rates)) {

			$ratesArr	= explode(",", $rates);

			foreach ($ratesArr as $val) {

				$valArr	= explode("|", $val);

				if (empty($valArr[0])) {

					$rate	= 'rate1';

				} else {

					$rate	= $valArr[0];
				}

				$rateHourArr[$rate]	= $valArr[1];
				$billArr[$rate]		= $valArr[2];
			}
		}

		$select_ratemaster	= "SELECT t1.ratemasterid, t1.ratetype, t1.rate, t2.jtype FROM multiplerates_assignment t1 INNER JOIN hrcon_jobs t2 ON t1.asgnid = t2.sno where ratemasterid != 'rate4' AND asgnid = '".$asignid."' AND period =  'HOUR' ORDER BY t1.sno";

		$result_ratemaster	= $this->mysqlobj->query($select_ratemaster,$this->db);

		while ($row_ratemaster=$this->mysqlobj->fetch_array($result_ratemaster)) {

			$rateArr[$row_ratemaster['ratemasterid']][$row_ratemaster['ratetype']] = $row_ratemaster['rate'];
			$jtype	= $row_ratemaster['jtype'];
		}

		$select_que	= "SELECT t1.rateid, (IF((SELECT COUNT(1) FROM multiplerates_assignment t2 WHERE t2.asgnid = '".$asignid."' AND t2.ratemasterid = t1.rateid) = 0,'N','Y')) AS required, (SELECT t3.billable FROM multiplerates_assignment t3 WHERE t3.asgnid = '".$asignid."' AND t3.ratemasterid = t1.rateid  AND ratetype='billrate' AND asgn_mode = 'hrcon') AS billable FROM multiplerates_master t1 WHERE t1.rateid != 'rate4' and status = 'Active' ORDER BY t1.sno";

		$ressel		= $this->mysqlobj->query($select_que, $this->db);
		$rowcount	= mysql_num_rows($ressel);

		$r	= 0;
		$ratetype	= '';

		while ($myrow = $this->mysqlobj->fetch_array($ressel)) {

			if ($myrow['rateid'] == 'rate1') {

				$class	= 'rowRegularHours';

			} elseif ($myrow['rateid'] == 'rate2') {

				$class	= 'rowOverTimeHours';

			} elseif ($myrow['rateid'] == 'rate3') {

				$class	= 'rowDoubleTimeHours';

			} else {

				$class	= 'timesheetRate'.$r;
			}

			$hiddenBillable[$myrow['rateid']]	= ($myrow['billable'] == '')?'N':$myrow['billable'];

			if (in_array($myrow['rateid'], $ratesAvail)) {

				$pay	= ($rateArr[$myrow['rateid']]['payrate'] == '')?'0.00':$rateArr[$myrow['rateid']]['payrate'];
				$bill	= ($rateArr[$myrow['rateid']]['billrate'] == '')?'0.00':$rateArr[$myrow['rateid']]['billrate'];

				$ratetype	.= '<td valign="top" class="afontstylee" align="center">';

				if (!empty($mode) && !empty($req_str)) {

					if (!empty($req_str[4])) {

						$hidbillchk	= $req_bill_arr[$r];

					} else {

						$hidbillchk	= $myrow['billable'];
					}

					if ($myrow['rateid'] == 'rate1' || $myrow['rateid'] == 'rate2' || $myrow['rateid'] == 'rate3') {

						$ratetype	.= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;background-color:#EDE9E9;" type="text" value="'.$req_rate_arr[$r].'" size="3" max_length="5" maxlength="6" class="'.$class.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" readonly>';

					} else {

						$ratetype	.= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;background-color:#EDE9E9;" type="text" value="'.$req_rate_arr[$r].'" size="3" max_length="5" maxlength="6" class="'.$class.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" readonly>';
					}

					$ratetype	.= '<input style="display:none;" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Yes" '.$this->chk($hidbillchk).' '.$this->disable($myrow['required']).'>';    

				} else {

					if ($myrow['rateid'] == 'rate1' || $myrow['rateid'] == 'rate2' || $myrow['rateid'] == 'rate3') {

						$ratetype	.= '<input style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;background-color:#EDE9E9;" type="text" value="'.$rateHourArr[$myrow['rateid']].'" size="3" max_length="5" maxlength="6" class="'.$class.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" readonly>';

					} else {

						$ratetype	.= '<input  style="height:18px; height:16px \0/; padding-top:0px;padding-top:0px \0/;vertical-align:top;background-color:#EDE9E9;" type="text" value="'.$rateHourArr[$myrow['rateid']].'" size="3" max_length="5" maxlength="6" class="'.$class.'" name="daily_rate_'.$rowid.'['.$rowid.']['.$myrow['rateid'].']" id="daily_rate_'.$r.'_'.$rowid.'" readonly>';
					}

					if (count($billArr) > 0) {

						$ratetype .= '<input style="display:none;" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Yes" '.$this->chk(substr($billArr[$myrow['rateid']], 0, 1)).' >';

					} else {

						$ratetype .= '<input style="display:none;" type="checkbox" name="daily_rate_billable_'.$rowid.'['.$myrow['rateid'].']" id="daily_rate_billable_'.$r.'_'.$rowid.'" value="Yes" '.$this->chk($myrow['billable']).'>';
					}
				}

				$ratetype	.= '</td>';
				$r++;
			}
		}

		$this->hiddenBillable[]	= $hiddenBillable;

		return $ratetype;
	}

	/*
	* This function builds the total hours block
	*
	* param		string	$regular_hours
	* param		string	$overtime_hours
	* param		string	$doubletime_hours
	* param		string	$total_hours
	* return	string
	*/
	public function getTotalHoursHTML($regular_hours = '0.00', $overtime_hours = '0.00', $doubletime_hours = '0.00', $total_hours = '0.00') {

		$colspan	= 7;

		if (MANAGE_CLASSES == 'Y') {

			$colspan += 1;
		}

		return '
			<tr style="background-color:#D3D3D3;" class="totalhours">
				<td colspan="'.$colspan.'">&nbsp;</td>
				<td width="10%" align="left"><b>Total Hours</b></td>
				<td width="10%" align="center">
					<div id="final_regular_hours" style="font-weight:bold;">'.number_format($regular_hours,2,'.','').'</div>
				</td>
				<td width="10%" align="center">
					<div id="final_overtime_hours" style="font-weight:bold;">'.number_format($overtime_hours,2,'.','').'</div>
				</td>
				<td width="10%" align="center">
					<div id="final_doubletime_hours" style="font-weight:bold;">'.number_format($doubletime_hours,2,'.','').'</div>
				</td>
				<td width="10%" align="center">
					<div id="final_total_hours" style="font-weight:bold;">'.number_format($total_hours,2,'.','').'</div>
				</td>
			</tr>';
	}

	/*
	* This function returns the timesheet layout preference
	*
	* return	string	$layout_pref
	*/
	public function getTSLayoutPreference() {

		$layout_pref	= '';

		$sel_pref_query	= "SELECT timesheeetpref FROM cpaysetup WHERE status='ACTIVE'";
		$res_pref_query	= $this->mysqlobj->query($sel_pref_query, $this->db);

		if (mysql_num_rows($res_pref_query) > 0) {

			$row_pref_query	= $this->mysqlobj->fetch_object($res_pref_query);
			$layout_pref	= $row_pref_query->timesheeetpref;
		}

		return $layout_pref;
	}

	/*
	* This function returns the max regular hours for timesheet
	*
	* return	integer	$max_reg_hours
	*/
	public function getMaxRegularHours() {

		$max_reg_hours	= 0;

		$sel_rhours_query	= "SELECT maxregularhours FROM cpaysetup WHERE status='ACTIVE'";
		$res_rhours_query	= $this->mysqlobj->query($sel_rhours_query, $this->db);

		if (mysql_num_rows($res_rhours_query) > 0) {

			$row_rhours_query	= $this->mysqlobj->fetch_object($res_rhours_query);
			$max_reg_hours		= $row_rhours_query->maxregularhours;
		}

		return $max_reg_hours;
	}

	/*
	* This function returns the max over time hours for timesheet
	*
	* return	integer	$max_ovt_hours
	*/
	public function getMaxOverTimeHours() {

		$max_ovt_hours	= 0;

		$sel_ohours_query	= "SELECT maxovertimehours FROM cpaysetup WHERE status='ACTIVE'";
		$res_ohours_query	= $this->mysqlobj->query($sel_ohours_query, $this->db);

		if (mysql_num_rows($res_ohours_query) > 0) {

			$row_ohours_query	= $this->mysqlobj->fetch_object($res_ohours_query);
			$max_ovt_hours		= $row_ohours_query->maxovertimehours;
		}

		return $max_ovt_hours;
	}

	/*
	* This function returns the rounding of time increment for timesheet
	*
	* return	integer	$time_increment
	*/
	public function getTimeIncrement() {

		$time_increment	= 0;

		$sel_tincmt_query	= "SELECT timeincrements FROM cpaysetup WHERE status='ACTIVE'";
		$res_tincmt_query	= $this->mysqlobj->query($sel_tincmt_query, $this->db);

		if (mysql_num_rows($res_tincmt_query) > 0) {

			$row_tincmt_query	= $this->mysqlobj->fetch_object($res_tincmt_query);
			$time_increment		= $row_tincmt_query->timeincrements;
		}

		return $time_increment;
	}

	/*
	* This function deletes the attachment for the give parid
	*
	* param		integer	$parid
	* return	boolean	true/false
	*/
	public function deleteAttachment($parid) {

		if (!empty($parid)) {

			$del_att_query	= "DELETE FROM time_attach WHERE parid='".$parid."'";
			$res_att_query	= $this->mysqlobj->query($del_att_query, $this->db);

			if (!$res_att_query) {

				die('Could not connect: ' . mysql_error());
			}

			if (mysql_affected_rows()) {

				return true;
			}
		}

		return false;
	}

	/*
	* This function gets details from par_timesheet table for the given sno
	*
	* param		integer	$sno
	* return	array	$par_timesheet
	*/
	public function getDetailsFromParTimeSheet($sno) {

		$par_timesheet	= array();

		if (!empty($sno)) {

			$sel_par_query	= "SELECT
									pt.username, MIN(pt.sdate) AS servicedate, MAX(pt.edate) AS servicedateto, pt.issues, pt.notes
								FROM
									par_timesheet pt
								WHERE
									pt.sno='".$sno."'
								GROUP BY
									pt.sdate";

			$res_par_query	= $this->mysqlobj->query($sel_par_query,  $this->db);

			if (!$res_par_query) {

				die('Could not connect: ' . mysql_error());
			}

			if (mysql_num_rows($res_par_query) > 0) {

				while ($row_par_query = $this->mysqlobj->fetch_object($res_par_query)) {

					$par_timesheet[]	= $row_par_query;
				}
			}
		}

		return $par_timesheet;
	}

	/*
	* This function gets timesheet details for the given sno
	*
	* param		integer	$sno
	* return	array	$timesheet_details
	*/
	public function getTimeSheetInformation($sno) {

		$timesheet_details	= array();

		if (!empty($sno)) {

			$sel_tis_query	= "SELECT
							th.sdate, th.client, pt.username
						FROM
							par_timesheet pt
							LEFT JOIN timesheet_hours th ON pt.sno=th.parid
						WHERE
							pt.sno='".$sno."'
						GROUP BY
							th.rowid
						ORDER BY
							th.sdate ASC";

			$res_tis_query	= $this->mysqlobj->query($sel_tis_query,  $this->db);

			if (!$res_tis_query) {

				die('Could not connect: ' . mysql_error());
			}

			if (mysql_num_rows($res_tis_query) > 0) {

				while ($row_tis_query = $this->mysqlobj->fetch_object($res_tis_query)) {

					$timesheet_details[]	= $row_tis_query;
				}
			}
		}

		return $timesheet_details;
	}

	/*
	* This function gets total hours for rates from timesheet_hours table for the given parid & status
	*
	* param		integer	$parid
	* param		string	$status
	* return	array	$rates_total_hours
	*/
	public function getTotalHoursForRates($parid, $status = '', $condinvoices = '') {

		$and_clause	= '';
		$rates_total_hours	= array();

		if (!empty($status)) {

			$status	= explode(",", $status);
			$status	= implode("','", $status);

			$and_clause	= " AND th.status IN ('".$status."') ";
		}

		if (!empty($parid)) {

			$sel_rate_query	= "SELECT
							SUM(hours) AS rates_total, th.hourstype AS rate
						FROM
							timesheet_hours th
						WHERE
							th.parid = ".$parid."
							".$and_clause."
							".$condinvoices."
						GROUP BY 
							th.hourstype";

			$res_rate_query	= $this->mysqlobj->query($sel_rate_query,  $this->db);

			if (!$res_rate_query) {

				die('Could not connect: ' . mysql_error());
			}

			if (mysql_num_rows($res_rate_query) > 0) {

				while ($row_rate_query = $this->mysqlobj->fetch_object($res_rate_query)) {

					$rates_total_hours[]	= $row_rate_query;
				}
			}
		}

		return $rates_total_hours;
	}

	/*
	* This function gets details from timeintimeout table for the given parid
	*
	* param		integer	$parid
	* param		integer	$rowid
	* return	array	$timeintimeout
	*/
	public function getTimeInTimeOutDetails($parid, $rowid = '') {

		$timeintimeout	= array();
		$and_clause		= '';

		if (!empty($parid)) {

			if (!empty($rowid)) {

				$and_clause	= " AND th.rowid='".$rowid."' ";
			}

			$sel_tito_query	= "SELECT 
									ta.pre_in_time, ta.pre_out_time, ta.break_time, ta.post_in_time, ta.post_out_time
								FROM 
									timeintimeout ta, timesheet_hours th
								WHERE 
									th.sno = ta.hours_sno AND ta.status != 'Backup' AND th.parid='".$parid."'
									".$and_clause."
								GROUP BY
									th.rowid";

			$res_tito_query	= $this->mysqlobj->query($sel_tito_query,  $this->db);

			if (!$res_tito_query) {

				die('Could not connect: ' . mysql_error());
			}

			if (mysql_num_rows($res_tito_query) > 0) {

				while ($row_tito_query = $this->mysqlobj->fetch_object($res_tito_query)) {

					$timeintimeout[]	= $row_tito_query;
				}
			}
		}

		return $timeintimeout;
	}

	/*
	* This function gets attachment details from time_attach table for the given parid
	*
	* param		integer	$parid
	* return	array	$attachment_details
	*/
	public function getAttachmentDetails($parid) {

		$attachment_details	= array();

		if (!empty($parid)) {

			$sel_att_query	= "SELECT ta.sno, ta.name FROM time_attach ta WHERE ta.parid='".$parid."'";
			$res_att_query	= $this->mysqlobj->query($sel_att_query, $this->db);

			if (!$res_att_query) {

				die('Could not connect: ' . mysql_error());
			}

			if (mysql_num_rows($res_att_query) > 0) {

				while ($row_att_query = $this->mysqlobj->fetch_object($res_att_query)) {

					$attachment_details[]	= $row_att_query;
				}
			}
		}

		return $attachment_details;
	}

	/*
	* This function gets details from timesheet_hours table for the given parid
	*
	* param		integer	$parid
	* param		string	$ts_status
	* return	array	$timesheet_hours
	*/
	public function getDetailsFromTimeSheetHours($parid, $ts_status, $condinvoice = '', $conjoin = '') {

		$timesheet_hours	= array();

		$conjoin	= str_replace("hrcon_jobs.","hj.", $conjoin);

		// to handle multiple timesheet statuses 
		$ts_status	= explode(",",$ts_status);
		$ts_status	= implode("','",$ts_status);

		if (!empty($parid)) {

			$sel_thrs_query	= "SELECT 
							count(*),th.assid, th.client, sc.cname, GROUP_CONCAT(th.sno) as sno, hj.sno as asgnsno, 
							th.sdate, th.type, th.username, th.status, hj.project,".tzRetQueryStringDate('pt.sdate','Date','/')." AS pstartdate,
							DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, ".tzRetQueryStringDate('th.edate','Date','/')." AS enddate, 
							DATE_FORMAT( th.sdate, '%W' ) AS weekday,".tzRetQueryStringDate('th.sdate','Date','/')." AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y %H:%i:%s' ) AS starttimedate,
							GROUP_CONCAT( DISTINCT CONCAT( hourstype, '|', hours, '|', billable ) ) AS time_data, SUM( th.hours ) AS sumhours, th.classid,th.auser, ti.pre_in_time, ti.pre_out_time, ti.break_time, ti.post_in_time, ti.post_out_time, u.name,".tzRetQueryStringDTime('th.approvetime','DateTime24','/')." AS approvetime,pt.issues,pt.astatus,pt.pstatus,pt.atime,pt.ptime,pt.puser,pt.notes,u.type as utype,th.payroll,th.task,DATE_FORMAT( th.edate, '%W' ) AS eweekday, DATE_FORMAT( pt.stime, '%m/%d/%Y %H:%i:%s' ) as stimedate
						FROM 
							par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid 
							LEFT JOIN timeintimeout ti ON (th.sno = ti.hours_sno) 
							LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername 
							LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno 
							INNER JOIN emp_list el ON el.username = pt.username
							LEFT JOIN users u ON u.username = th.auser 
							".$conjoin."
						WHERE 
							th.parid = '".$parid."' AND th.status IN ('".$ts_status."') ".$condinvoice." AND th.username = pt.username
						GROUP BY
							th.rowid";
		}else{
			$sel_thrs_query	= "SELECT 
							count(*),th.assid, th.client, sc.cname, GROUP_CONCAT(th.sno) as sno, hj.sno as asgnsno, 
							th.sdate, th.type, th.username, th.status, hj.project,".tzRetQueryStringDate('pt.sdate','Date','/')." AS pstartdate,
							DATE_FORMAT( pt.edate, '%m/%d/%Y' ) AS penddate,pt.notes, pt.ts_multiple,el.name, ".tzRetQueryStringDate('th.edate','Date','/')." AS enddate, 
							DATE_FORMAT( th.sdate, '%W' ) AS weekday,".tzRetQueryStringDate('th.sdate','Date','/')." AS startdate, DATE_FORMAT( pt.stime, '%m/%d/%Y %H:%i:%s' ) AS starttimedate,
							GROUP_CONCAT( DISTINCT CONCAT( hourstype, '|', hours, '|', billable ) ) AS time_data, SUM( th.hours ) AS sumhours, th.classid,th.auser, ti.pre_in_time, ti.pre_out_time, ti.break_time, ti.post_in_time, ti.post_out_time, u.name,".tzRetQueryStringDTime('th.approvetime','DateTime24','/')." AS approvetime,pt.issues,pt.astatus,pt.pstatus,pt.atime,pt.ptime,pt.puser,pt.notes,u.type as utype,th.payroll,th.task,DATE_FORMAT( th.edate, '%W' ) AS eweekday, DATE_FORMAT( pt.stime, '%m/%d/%Y' ) as stimedate
						FROM 
							par_timesheet pt INNER JOIN timesheet_hours th ON pt.sno = th.parid 
							LEFT JOIN timeintimeout ti ON (th.sno = ti.hours_sno) 
							LEFT JOIN hrcon_jobs AS hj ON th.assid = hj.pusername 
							LEFT JOIN staffacc_cinfo sc ON th.client = sc.sno 
							INNER JOIN emp_list el ON el.username = pt.username
							LEFT JOIN users u ON u.username = th.auser 
							".$conjoin."
						WHERE 
							1=1  ".$condinvoice." AND th.username = pt.username
						GROUP BY
							th.rowid";
		}

			$res_thrs_query	= $this->mysqlobj->query($sel_thrs_query, $this->db);

			if (!$res_thrs_query) {

				die('Could not connect: ' . mysql_error());
			}

			if (mysql_num_rows($res_thrs_query) > 0) {

				while ($row_thrs_query = $this->mysqlobj->fetch_object($res_thrs_query)) {

					$timesheet_hours[]	= $row_thrs_query;
			}
		}		

		return $timesheet_hours;
	}

	/*
	* This function gets par_timesheet details for the given sno
	*
	* param		integer	$sno
	* return	array	$par_timesheet
	*/
	public function getParTimeSheetDetails($sno) {

		$par_timesheet	= array();

		if (!empty($sno)) {

			$sel_par_query	= 'SELECT '
									.tzRetQueryStringDate('pt.sdate', 'Date', '/')." AS sdate, ".tzRetQueryStringDate('pt.edate', 'Date', '/')." AS edate, "
									.tzRetQueryStringDTime('pt.stime', 'DateTime', '/')." AS stime, ".tzRetQueryStringDTime('pt.atime', 'DateTime', '/').", "
									.tzRetQueryStringDTime("STR_TO_DATE(pt.ptime, '%d/%m/%Y')", 'DateTime', '/').", pt.issues, pt.astatus, pt.pstatus, pt.puser, pt.auser, pt.notes, pt.ts_multiple, el.name, DATE_FORMAT( pt.stime, '%m/%d/%Y %H:%i:%s' ) as stimedate
								FROM
									par_timesheet pt
									LEFT JOIN emp_list el ON el.username = pt.username
								WHERE
									pt.sno='".$sno."'";

			$res_par_query	= $this->mysqlobj->query($sel_par_query,  $this->db);

			if (!$res_par_query) {

				die('Could not connect: ' . mysql_error());
			}

			if (mysql_num_rows($res_par_query) > 0) {

				while ($row_par_query = $this->mysqlobj->fetch_object($res_par_query)) {

					$par_timesheet[]	= $row_par_query;
				}
			}
		}

		return $par_timesheet;
	}

	/*
	* This function gets company name from company_info table
	*
	* return	string	company_name
	*/
	public function getCompanyName() {

		$sel_comp_query	= "SELECT company_name FROM company_info";
		$res_comp_query	= $this->mysqlobj->query($sel_comp_query, $this->db);

		if (!$res_comp_query) {

			die('Could not connect: ' . mysql_error());
		}

		if (mysql_num_rows($res_comp_query) > 0) {

			$row_comp_query	= $this->mysqlobj->fetch_object($res_comp_query);

			return $row_comp_query->company_name;
		}

		return '';
	}

	/*
	* This function builds HTML for timesheet
	*
	* param		int	$parid
	* param		string  $check
	* param		string  $timesheetstatus
	* param		string	$module
	* param		int	$status_id
	* param		string	$emp_name
	* param		string	$start_date
	* param		string	$end_date
	* return	string  $grids
	*/
	public function displayTimeInTimeOut($parid, $header_title, $check='', $timesheetstatus, $module, $status_id='', $emp_name, $start_date, $end_date, $condinvoices = '', $conjoin = '', $conbillable = '') {

		global $companyname, $IsPrint;

		$timesheetstatus	= ucfirst($timesheetstatus);

		$summary_info	= $this->getDetailsFromTimeSheetHours($parid, $timesheetstatus, $condinvoices, $conjoin);
		$rowsCount	= count($summary_info);

		// GETTING TOTAL HOURS BASED ON RATES

		$reg_total_hours	= 0.00;
		$ovt_total_hours	= 0.00;
		$dbt_total_hours	= 0.00;

		$rates_total_hours	= $this->getTotalHoursForRates($parid, $timesheetstatus, $conbillable);

		if (!empty($rates_total_hours)) {

			foreach ($rates_total_hours as $key => $object) {

				if ($object->rate == 'rate1') {

					$reg_total_hours	= $object->rates_total;
				}

				if ($object->rate == 'rate2') {

					$ovt_total_hours	= $object->rates_total;
				}

				if ($object->rate == 'rate3') {

					$dbt_total_hours	= $object->rates_total;
				}
			}
		}

		$header		= explode('|', $header_title);
		$numflds	= count($header);

		$grids	= "<input type='hidden' name='chkcount' value='$rowsCount'>
			<table cellpadding='5' cellspacing='0' border='0' width='100%'>";

		if ($numflds > 0 && !empty($header_title)) {

			$grids	.= "<tr style='background-color:#00B9F2'>";

			for ($i = 0; $i < $numflds; $i++) {

				if ($header[$i] == 'checkbox') {

					if ($module == 'MyProfile') {

						$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none">';

					} else {

						if ((stristr($timesheetstatus, 'Approved') != false) || $timesheetstatus == 'Exported' || $timesheetstatus == 'Deleted') {

							$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none;">';

						} else {

							$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()">';
						}
					}

					$grids	.= "<th valign='middle' align='left'><font class='afontstyle'>$chk_cond</font></th>";

				} else {

					$grids	.= "<th valign='middle' align='left'><font class='afontstyle'>$header[$i]</font></th>";
				}
			}

			$grids	.= '</tr><tr>';

			for ($i = 0; $i < $numflds; $i++) {

				if (strtolower($header[$i]) == 'regular' || strtolower($header[$i]) == 'overtime' || strtolower($header[$i]) == 'doubletime') {

					$grids	.= '<td valign="top" style="background-color:white"><font class="smalltextfont">Hours</font></td>';

				} else {

					$grids	.= '<td valign="top" style="background-color:white">&nbsp;</td>';
				}
			}

			$grids	.= '</tr>';
		}

		$i	= 1;
		$total	= 0;
		$Biltotal	= 0;
		$BillableSuc	= false;

		foreach ($summary_info as $key => $object) {

			$total_rate_hours = 0;
			$grids	.= '<tr>';

			if (!empty($object->client)) {

				$que	= 'SELECT cname, '.getEntityDispName('sno', 'cname', 1)." FROM staffacc_cinfo WHERE sno=".$object->client;
				$res	= $this->mysqlobj->query($que, $this->db);
				$row	= $this->mysqlobj->fetch_row($res);

				$companyname1	= $row[1];

			} else {

				$companyname1	= $companyname;
			}

			if ($object->type == 'EARN') {

				$cli	= $object->client." ( Benefits )";

			} else {

				if ($object->assid == 'AS') {

					$cli	= $companyname1." ( Administrative Staff )";

				} elseif ($object->assid == 'OB') {

					$cli	= $companyname1." ( On Bench )";

				} elseif ($object->assid == 'OV') {

					$cli	= $companyname1." ( On Vacation )";

				} else {

					$lque	= "SELECT cname, ".getEntityDispName('sno', 'cname', 1)." FROM staffacc_cinfo WHERE sno=".$object->client;
					$lres	= $this->mysqlobj->query($lque,$this->db);
					$lrow	= $this->mysqlobj->fetch_row($lres);

					if (empty($object->assid)) {

						$object->assid	= ' N/A ';
					}

					$cli	= " ( ".$object->assid." ) ".$lrow[1];
				}
			}

			$getProject	= "SELECT project FROM hrcon_jobs WHERE hrcon_jobs.username = '".$object->username."' AND pusername = '".$object->assid."' AND ustatus IN ('active','closed','cancel')";
			$resProject	= $this->mysqlobj->query($getProject, $this->db);
			$rowProject	= $this->mysqlobj->fetch_row($resProject);

			$project	= !empty($rowProject[0]) ? $rowProject[0] : '';
			$strbil		= !empty($object->billable) ? 'Yes' : 'No';
			$cli		= $cli.$project;

			$taskDetails	= "<br><b>Task Details:</b> ".htmlspecialchars($object->task);

			if (!empty($object->auser)) {

				$sql_user	= "SELECT name, type FROM users WHERE username=".$object->auser;
				$res_user	= $this->mysqlobj->query($sql_user, $this->db);
				$nameAndsource	= $this->mysqlobj->fetch_row($res_user);
			}

			if ($timesheetstatus == 'Deleted') {

				$disSource = $nameAndsource[0];

			} else {

				if ($nameAndsource[1] == 'cllacc' && !empty($object->auser)) {

					if ($object->status == 'Approved' || $object->status == 'Billed') {

						if ($object->status != 'Billed')
						$disSource	= "Self Svc (".$nameAndsource[0].")";
						else
						$disSource	= "Self Svc (".$nameAndsource[0].") (Billed)";
					}

					if ($object->status == 'Rejected')
					$disSource	= $nameAndsource[0];

					if ($object->status == 'Saved')
					$disSource	= "Saved (".$nameAndsource[0].")";

				} elseif ($nameAndsource[1] != "cllacc" && !empty($object->auser)) {

					if ($object->status== "Approved" || $object->status== "Billed") {

						if ($object->status != "Billed")
						$disSource	= "Accounting (".$nameAndsource[0].")";
						else
						$disSource	= "Accounting (".$nameAndsource[0].") (Billed)";
					}

					if ($object->status == 'Rejected'){
						$disSource	= $nameAndsource[0];
					}

					if ($object->status == 'Saved')
					$disSource	= "Saved (".$nameAndsource[0].")";

				} elseif ($object->status == 'Saved') {

						$disSource	= 'Saved';

				} elseif ($object->status == 'Backup') {

						$disSource	= 'Deleted';

				} else {

					$disSource	= 'Pending';
				}
			}

			if ($check != 'no') {

				if ($disSource == 'Pending')
				$checked	= " checked";
				elseif($timesheetstatus == 'Rejected')
				$checked	= " checked";
				else
				$checked	= " disabled";

				$grids	.= "<td width='2%'><input type=checkbox name=auids[] id='chk".$i."' value='".$object->sno."' $checked onClick=chk_clearTop_TimeSheet()></td>";
			}

			if (!empty($object->enddate) && $object->enddate != '00/00/0000') {

				$dateRangeArr	= explode(' ', $object->startdate);
				$dateRangeShow	= $dateRangeArr[0].' - '.$object->enddate;

			} else {

				$dateRangeShow	= ucwords($object->startdate)."  ".$object->weekday;
			}

			$grids	.= "<td align='left' width='16%'><font class='afontstyle'>&nbsp;".$dateRangeShow."</font></td>";
			$grids	.= "<td align='left' width='28%'><font class='afontstyle'>".$cli.$taskDetails."</font></td>";

			// For displaying classes
			if (MANAGE_CLASSES == 'Y') {
				$class = $this->getClasses(" AND sno = $object->classid");
				$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$class[0]['classname']."</font></td>";
			}

			// For displaying pretime in/out
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->pre_in_time."</font></td>";
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->pre_out_time."</font></td>";

			// For displaying lunch break
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->break_time."</font></td>";

			// For displaying posttime in/out 
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->post_in_time."</font></td>";
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->post_out_time."</font></td>";

			// For displaying Regular Hours and Overtime Hours Or Any Other Rate Type Hours
			$ratecount = 0;

			$time_data 	= explode(",",$object->time_data);
			$ratetypes 	= $this->getRateTypesForAllAsgnnames($this->assignments,true);

			$rate_data = array();
			 
			foreach ($time_data as $val) {
				$ratetimedata	= explode("|",$val);
				$rate_data[] 	= $ratetimedata[0];
			}
			
			foreach ($ratetypes as $val) {
				
				if (in_array($val, $rate_data)) {
					
					$rate_hours	= explode("|",$time_data[$ratecount]);
					$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$rate_hours[1]."</font></td>";
					$total_rate_hours	+= $rate_hours[1];
					$ratecount++;
					
				} else {
					$grids	.= "<td align='left' width='8%'><font class='afontstyle'></font></td>";
				}			
			}

			if ($status_id == "1" || $status_id == "2" || $status_id == "7" ) {

				if (empty($disSource))
				$disSource	= 'Pending';

				$grids	.= "<td align='left'><font class='afontstyle'>".$disSource."</font></td>";
				$grids	.= "<td align='left'><font class='afontstyle'>".$object->approvetime."</font></td>";

			} elseif ($status_id == "3" && $module!='Client') {

				$grids	.= "<td align='left'><font class='afontstyle'>".$disSource."</font></td>";
				$grids	.= "<td align='left'><font class='afontstyle'>".$object->approvetime."</font></td>";
			}

			if ($status_id != "1" && $status_id != "2" && $status_id != "3" && $status_id != "7" || ($module == "Client" && $status_id == '3')) {
				// For last column - Overall Total
				$grids	.= "<td align='left'><font class='afontstyle'></font></td>";
			}

			$grids	.= '</tr>';

			$i++;

			if (strtolower($strbil) == 'yes' && $IsPrint == 'yes') {

				$BillableSuc	= true;
				$Biltotal		= $Biltotal + $object->hours;
			}

			$total	= $total + $total_rate_hours;
		}

		if ($i == 1) {

			$grids	.= "<tr><td colspan=".($numflds+1)." align=center class=tr2bgcolor><font class='afontstyle'>No Time Sheets are available.</td></tr>\n";

		} else {

			if (MANAGE_CLASSES == 'Y') {

				$grids	.= "<tr>";

				$tot_label_colnum	= 7;

				if ($check != 'no') {

					$tot_label_colnum	= 8;
				}

				for ($i = 0; $i < $numflds; $i++) {

					if ($i == $tot_label_colnum) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>Total Hours</b></font></td>";

					} elseif ($i == $tot_label_colnum + 1) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($reg_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 2) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($ovt_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 3) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($dbt_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 4) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($total,2,'.','')."</b></font></td>";

					} else {

						$grids	.= "<td align='left'>&nbsp;</td>";
					}
				}

				$grids	.= '</tr>';

			} else {

				$grids	.= '<tr>';

				$tot_label_colnum	= 6;

				if ($check != 'no') {

					$tot_label_colnum	= 7;
				}

				for ($i = 0; $i < $numflds; $i++) {

					if ($i == $tot_label_colnum) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>Total Hours</b></font></td>";

					} elseif ($i == $tot_label_colnum + 1) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($reg_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 2) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($ovt_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 3) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($dbt_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 4) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($total,2,'.','')."</b></font></td>";

					} else {

						$grids	.= "<td align='left'>&nbsp;</td>";
					}
				}

				$grids	.= '</tr>';
			}
		}

		mysql_free_result($result);

		$grids	.= '</table>';

		return	$grids;
	}

	/*
	* This function checks for equal values for the passed parameters
	*
	* param		string	$a
	* param		string	$b
	* return	string
	*/
	public function sel($a, $b) {

		if ($a == $b) {

			return 'selected';
		}

		return '';
	}

	/* Function to get Timesheet Status based on Id */
	public function getTimesheetStatusName($status_id) {

		switch ($status_id) {

			case 1:	$timesheetstatus 	= "approved";
			case "approved": $timesheetstatus 	= "approved";
				break;

			case 2:	$timesheetstatus	= "deleted";
			case "deleted":	$timesheetstatus	= "deleted";
				break;

			case 3:	$timesheetstatus	= "rejected";
			case "rejected": $timesheetstatus	= "rejected";
				break;

			case 4:	$timesheetstatus	= "create";
			case "create":	$timesheetstatus	= "create";
				break;

			case 5: $timesheetstatus	= "edit";
			case "edit": $timesheetstatus	= "edit";
				break;

			case 6: $timesheetstatus	= "saved";	// Saved Timesheet
			case "saved": $timesheetstatus	= "saved";
				break;
			
			case 7: $timesheetstatus	= "approved";	// Exported Timesheet
			case "exported": $timesheetstatus	= "approved";
				break;

			default:$timesheetstatus	= "ER";		// Submitted Timesheet
				break;
		}

		return $timesheetstatus;
	}

	/* Function to get Timesheet Status based on Id */
	public function getTimesheetStatusBasedOnId($status_id) {

		switch ($status_id) {

			case 1:	$timesheetstatus 	= "approved,billed";
				break;

			case 2:	$timesheetstatus	= "deleted";
				break;

			case 3:	$timesheetstatus	= "rejected";
				break;

			case 4:	$timesheetstatus	= "create";
				break;

			case 5: $timesheetstatus	= "edit";
				break;

			case 6: $timesheetstatus	= "ER,saved";		// Saved Timesheet
				break;
			
			case 7:	$timesheetstatus 	= "approved,billed";	// Exported Timesheet
				break;

			default:$timesheetstatus	= "ER,saved";		// Submitted Timesheet
				break;
		}

		return $timesheetstatus;
	}

	/* Function to display timesheet details for both UI and print */
	public function showTimeInTimeOutDetails($tmstatus, $sno, $ename, $eid, $submitted_date, $start_date, $end_date, $userSelfServicePref = '', $module = '', $media = '') {

		// Here sno and addr1 as equivalent
		// Flag to enable and disable checkboxes
		$check	= "no";

		$whosetmdetails_text = "";

		// Check the type of timesheet - string format
		if(isset($tmstatus) && !is_numeric($tmstatus)){
			$tmstatus = strtolower($tmstatus);
			if(($tmstatus == "billed") || ($tmstatus == "approved")){
				$tmstatus = 1;
			}elseif($tmstatus == "deleted"){
				$tmstatus = 2;
			}elseif($tmstatus == "saved"){
				$tmstatus = 6;
			}elseif($tmstatus == "rejected"){
				$tmstatus = 3;
			}elseif($tmstatus == "exported"){
				$tmstatus = 7;
			}else{
				$tmstatus = 99;
			}
		}

		// Check the type of timesheet - numeric format	
		// Show submitted timesheets by default, otherwise show respective timesheets
		$status_id	= isset($tmstatus) ? $tmstatus : '99';

		// DISPLAYING CLASSES BASED ON USER PREFERENCES
		$manage_classes	= (MANAGE_CLASSES == 'Y') ? 'Class|' : '';

		if($module == 'MyProfile' || $module == 'Client'){
			$whosetmdetails_text 	= "<font class=afontstyle>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";
		}else{
			$whosetmdetails_text = "<font class='afontstyle' color='black'>&nbsp;&nbsp;Following are <b>".$ename."</b> Time Sheet details from <b>".$start_date."</b> to <b>".$end_date."</b>.</font>";
		}

		// Get rate types
		$ratetype_title = array();
		$this->getAssignmentsByEmp($eid, $start_date, $end_date);

		$ratetypes = $this->getRateTypesForAllAsgnnames($this->assignments,true);
		$ratetype	= $this->getRateTypes();

		foreach ($ratetype as $val) {

			if (in_array($val['rateid'], $ratetypes)) {
		
				array_push($ratetype_title, $val['name']);
			}
		}

		$rateheader = implode("|",$ratetype_title);
		
		if($status_id != "1" && $status_id != "2" && $status_id != "3" && $status_id != "7" || ($module == "Client" && $status_id == '3')){
			$total = "|&nbsp;"; // for showing total column title
		}
			
		switch ($status_id) {

			case 1:	$timesheetstatus 	= "approved,billed";
				$statval		= "statapproved";
				$timesheetcaption	= "&nbsp;&nbsp;Approved&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Approved&nbsp;By|Approved&nbsp;Time".$total;
				$check			= "no";
				$whosetmdetails_text 	= "<font class=afontstyle>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";

				if ($module == 'MyProfile') {

					$link_title	= explode("|","print.gif~Print|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."')|javascript:self.close()");

				} elseif ($module == 'Client') {

					$link_title	= explode("|","print.gif~Print|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."','sub')|javascript:self.close()");
					$check		= 'no';

				} else {
					
					$edit_check	= $this->checkTimesheetEditable($sno);					
					if(!$edit_check){
						$link_title	= explode("|","print.gif~Print|cancel.gif~Cancel");
						$link_js	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."','sub')|javascript:self.close()");
					}else{
						$link_title	= explode("|","edit.gif~Edit|print.gif~Print|cancel.gif~Cancel");
						$link_js	= explode("|","javascript:editTimeSheet('".$module."','".$status_id."','".$sno."')|javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."','sub')|javascript:self.close()");
					}
				}
				
				$heading		= "time.gif~Approved&nbsp;Timesheet";
				break;

			case 2:	$timesheetstatus	= "deleted";
				$timesheetcaption	= "&nbsp;&nbsp;Deleted&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Deleted&nbsp;By|Deleted&nbsp;Time".$total;
				$check			= "no";
				$whosetmdetails_text 	= "<font class=afontstyle>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";

				$link_title		= explode("|","print.gif~Print|cancel.gif~Cancel");
				$link_js		= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."','sub')|javascript:self.close()");
				
				if($module == 'Client') {
					$link_title	= explode("|","print.gif~Print|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."')|javascript:self.close()");
				}
				$heading		= "time.gif~Deleted&nbsp;Timesheets";
				break;

			case 3:	$timesheetstatus	= "rejected";
				$statval		= "statrejected";
				$timesheetcaption	= "&nbsp;&nbsp;Rejected&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Rejected&nbsp;By|Rejected&nbsp;Time".$total;
				$check			= "yes";
				$whosetmdetails_text 	= "<font class=afontstyle>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";

				if ($module == 'MyProfile') {

					$link_title	= explode("|","submit.gif~Submit|edit.gif~Edit|delete.gif~Delete|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:reSubmitTimesheet()|javascript:editTimeSheet('".$module."','".$status_id."','".$sno."')|javascript:deleteTimesheet()|javascript:self.close()");
					$check		= 'no';

				} elseif($module == 'Client') {

					$headtitle	= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader.$total;
					$link_title	= explode("|","print.gif~Print&nbsp;&nbsp;|".((strpos($userSelfServicePref[7],"+2+"))?"edit.gif~Edit":"")."|cancel.gif~Cancel");
					$link_js 	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."');|".((strpos($userSelfServicePref[7],"+2+"))?"javascript:editTimeSheet('".$module."','".$status_id."','".$sno."');":"")."|javascript:self.close();");

				} else {

					$link_title	= explode("|","update.gif~Update Status|edit.gif~Edit|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:doApproveTimeGridSrej()|javascript:editTimeSheet('".$module."','".$status_id."','".$sno."')|javascript:self.close()");
				}

				$heading	= "time.gif~Rejected&nbsp;Timesheet";
				break;

			case 4:	$timesheetstatus	= "create";
				if($module=='MyProfile'){
					$link_title	= explode("|","cancel.gif~Cancel");
					$link_js	= explode("|","javascript:self.close()");
				}
				elseif($module=='Client'){
					$link_title	= explode("|","add.gif~Add Row|delete.gif~Delete Row|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:void(0)|javascript:void(0)|javascript:self.close()");
				}else{
					$link_title	= explode("|","add.gif~Add Row|delete.gif~Delete Row|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:void(0)|javascript:void(0)|javascript:self.close()");
				}
				break;

			case 5: $timesheetstatus	= "edit";
				
				if($module=='MyProfile'){
					$link_title	= explode("|","add.gif~Add Row|delete.gif~Delete Row|save.gif~Save|submit.gif~Submit|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:void(0)|javascript:void(0)|javascript:onClick=validate('save');|javascript:onClick=validate('submit');|javascript:self.close()");
				}
				elseif($module=='Client'){
					$link_title	= explode("|","add.gif~Add Row|delete.gif~Delete Row|submit.gif~Submit|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:void(0)|javascript:void(0)|javascript:onClick=validate('submit');|javascript:self.close()");
				}else{
					$link_title	= explode("|","add.gif~Add Row|delete.gif~Delete Row|submit.gif~Submit|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:void(0)|javascript:void(0)|javascript:onClick=validate('submit');|javascript:self.close()");
				}
				
				break;

			case 6: $timesheetstatus	= "saved";
				$statval		= "statsubmitted";

				if ($module == 'MyProfile') {
					$timesheetcaption	= "&nbsp;&nbsp;Saved&nbsp;Timesheet";
					$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader.$total;
					$check			= "no";
					$link_title	= explode("|","submit.gif~Submit|edit.gif~Edit|delete.gif~Delete|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:reSubmitTimesheet()|javascript:editTimeSheet('".$module."','".$status_id."','".$sno."')|javascript:deleteTimesheet()|javascript:self.close()");
					$heading	= "time.gif~Saved&nbsp;Timesheet";

					$whosetmdetails_text = "<font class='afontstyle' color='black'>&nbsp;&nbsp;Following are <b>".$ename."</b> Time Sheet details from <b>".$start_date."</b> to <b>".$end_date."</b>.</font>";
				}
				break;
			
			case 7:	$timesheetstatus 	= "approved,billed";
				$statval		= "statapproved";
				$timesheetcaption	= "&nbsp;&nbsp;Exported&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Approved&nbsp;By|Approved&nbsp;Time".$total;
				$check			= "no";
				$whosetmdetails_text 	= "<font class=afontstyle>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";

				if ($module == 'MyProfile') {

					$link_title	= explode("|","print.gif~Print|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."')|javascript:self.close()");

				} elseif ($module == 'Client') {

					$link_title	= explode("|","print.gif~Print|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."','sub')|javascript:self.close()");
					$check		= 'no';

				} else {
					
					$edit_check	= $this->checkTimesheetEditable($sno);					
					if(!$edit_check){
						$link_title	= explode("|","print.gif~Print|cancel.gif~Cancel");
						$link_js	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."','sub')|javascript:self.close()");
					}else{
						$link_title	= explode("|","edit.gif~Edit|print.gif~Print|cancel.gif~Cancel");
						$link_js	= explode("|","javascript:editTimeSheet('".$module."','".$status_id."','".$sno."')|javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."','sub')|javascript:self.close()");
					}
				}

				$heading		= "time.gif~Exported&nbsp;Timesheet";
				break;

			default:$timesheetstatus	= "ER";
				$statval		= "statsubmitted";
				$timesheetcaption	= "&nbsp;&nbsp;Submitted&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader.$total;
				$check			= "yes";
				$whosetmdetails_text	= "<font class='afontstyle' color='black'>&nbsp;&nbsp;Following are <b>".$ename."</b> Time Sheet details from <b>".$start_date."</b> to <b>".$end_date."</b>.</font>";
				
				if ($module == 'MyProfile') {

					$link_title	= explode("|","print.gif~Print|cancel.gif~Cancel");
					$link_js	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."','sub');|javascript:self.close()");
					if($media == "print"){
						$whosetmdetails_text 	= "<font class=afontstyle>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";
						if($mode == 'approved')
							$check	= "no";
					}else{
						$check		= 'no';
					}

				} elseif ($module == 'Client') {

					$link_title	= explode("|","print.gif~Print&nbsp;&nbsp;|".((strpos($userSelfServicePref[7],"+2+"))?"update.gif~Update&nbsp;Status|edit.gif~Edit":"")."|cancel.gif~Cancel");
					$link_js 	= explode("|","javascript:printPDFTimesheet('".$module."','".$status_id."','".$sno."');|".((strpos($userSelfServicePref[7],"+2+"))?"javascript:doApproveSubTSS()|javascript:editTimeSheet('".$module."','".$status_id."','".$sno."');":"")."|javascript:self.close();");

				} else {

					$link_title	= explode("|","update.gif~Update Status|edit.gif~Edit|close.gif~Close");
					$link_js	= explode("|","javascript:doApproveTimeGridS()|javascript:editTimeSheet('".$module."','".$status_id."','".$sno."')|javascript:closeWindow()");
				}

				$heading	= "time.gif~Submitted&nbsp;Timesheet";
				break;
		}
		if($check == "yes"){
			$headtitle = "checkbox|".$headtitle;
		}
		$elements_all	= array("timesheetstatus" => $timesheetstatus,"statval"=>$statval,"timesheetcaption"=>$timesheetcaption,"headtitle"=>$headtitle,"check"=>$check,"link_title"=>$link_title,"link_js"=>$link_js,"whosetmdetails_text"=>$whosetmdetails_text,"heading"=>$heading, "status_id"=>$status_id);

		return $elements_all;
	}
	
	/* Function to display timesheet details for print */
	public function showTimeInTimeOutDetailsPrint($tmstatus, $ename, $eid, $submitted_date, $start_date, $end_date, $module = '') {

		// Here sno and addr1 as equivalent

		$whosetmdetails_text = "";

		// Check the type of timesheet - string format
		if(isset($tmstatus) && !is_numeric($tmstatus)){
			$tmstatus = strtolower($tmstatus);
			if(($tmstatus == "billed") || ($tmstatus == "approved")){
				$tmstatus = 1;
			}elseif($tmstatus == "deleted"){
				$tmstatus = 2;
			}elseif($tmstatus == "saved"){
				$tmstatus = 6;
			}elseif($tmstatus == "rejected"){
				$tmstatus = 3;
			}elseif($tmstatus == "exported"){
				$tmstatus = 7;
			}else{
				$tmstatus = 99;
			}
		}

		// Check the type of timesheet - numeric format	
		// Show submitted timesheets by default, otherwise show respective timesheets
		$status_id	= isset($tmstatus) ? $tmstatus : '99';

		// DISPLAYING CLASSES BASED ON USER PREFERENCES
		$manage_classes	= (MANAGE_CLASSES == 'Y') ? 'Class|' : '';

		$whosetmdetails_text 	= "<font class=afontstyle>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";
		
		//if($module == 'MyProfile' || $module == 'Client'){
		//	$whosetmdetails_text 	= "<font class=afontstyle>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";
		//}else{
		//	$whosetmdetails_text = "<font class='afontstyle' color='black'>&nbsp;&nbsp;Following are <b>".$ename."</b> Time Sheet details from <b>".$start_date."</b> to <b>".$end_date."</b>.</font>";
		//}

		// Get rate types
		$ratetype_title = array();
		$this->getAssignmentsByEmp($eid, $start_date, $end_date);

		$ratetypes = $this->getRateTypesForAllAsgnnames($this->assignments,true);
		$ratetype	= $this->getRateTypes();

		foreach ($ratetype as $val) {

			if (in_array($val['rateid'], $ratetypes)) {
		
				array_push($ratetype_title, $val['name']);
			}
		}

		$rateheader = implode("|",$ratetype_title);
		
		if($status_id != "1" && $status_id != "2" && $status_id != "3" && $status_id != "7" || ($module == "Client" && $status_id == '3')){
			$total = "|&nbsp;"; // for showing total column title
		}
		
		switch ($status_id) {

			case 1:	$timesheetstatus 	= "approved,billed";				
				$timesheetcaption	= "&nbsp;&nbsp;Approved&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Approved&nbsp;By|Approved&nbsp;Time".$total;
				$heading		= "time.gif~Approved&nbsp;Timesheet";
				break;

			case 2:	$timesheetstatus	= "deleted";
				$timesheetcaption	= "&nbsp;&nbsp;Deleted&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Deleted&nbsp;By|Deleted&nbsp;Time".$total;
				$heading		= "time.gif~Deleted&nbsp;Timesheets";
				break;

			case 3:	$timesheetstatus	= "rejected";				
				$timesheetcaption	= "&nbsp;&nbsp;Rejected&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Rejected&nbsp;By|Rejected&nbsp;Time".$total;					
				if($module == 'Client') {
					$headtitle	= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader.$total;				
				} 
				$heading	= "time.gif~Rejected&nbsp;Timesheet";
				break;

			case 4:	$timesheetstatus	= "create";
				break;

			case 5: $timesheetstatus	= "edit";
				break;

			case 6: $timesheetstatus	= "saved";				
				if ($module == 'MyProfile') {
					$timesheetcaption	= "&nbsp;&nbsp;Saved&nbsp;Timesheet";
					$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader.$total;					
					$heading	= "time.gif~Saved&nbsp;Timesheet";
				}
				break;
			
			case 7:	$timesheetstatus 	= "approved,billed";				
				$timesheetcaption	= "&nbsp;&nbsp;Exported&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Approved&nbsp;By|Approved&nbsp;Time".$total;
				$heading		= "time.gif~Exported&nbsp;Timesheet";
				break;

			default:$timesheetstatus	= "ER";
				$timesheetcaption	= "&nbsp;&nbsp;Submitted&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader.$total;					
				$heading		= "time.gif~Submitted&nbsp;Timesheet";
				break;
		}		
		$elements_all	= array("timesheetstatus" => $timesheetstatus, "timesheetcaption"=>$timesheetcaption,"headtitle"=>$headtitle,"whosetmdetails_text"=>$whosetmdetails_text,"heading"=>$heading,"status_id"=>$status_id);

		return $elements_all;
	}

	/* Function to set header for timesheet detailed notes */
	public function showTimeInTimeOutDetailedNotes($tmstatus, $sno, $module = '',$servicedate,$servicedateto,$empnames) {

		// Check the type of timesheet - string format
		if(isset($tmstatus) && !is_numeric($tmstatus)){
			$tmstatus = strtolower($tmstatus);
			if(($tmstatus == "billed") || ($tmstatus == "approved")){
				$tmstatus = 1;
			}elseif($tmstatus == "deleted"){
				$tmstatus = 2;
			}elseif($tmstatus == "saved"){
				$tmstatus = 6;
			}elseif($tmstatus == "rejected"){
				$tmstatus = 3;
			}elseif($tmstatus == "exported"){
				$tmstatus = 7;
			}else{
				$tmstatus = 99;
			}
		}

		// Check the type of timesheet - numeric format	
		// Show submitted timesheets by default, otherwise show respective timesheets
		$status_id	= isset($tmstatus) ? $tmstatus : '99';

		// DISPLAYING CLASSES BASED ON USER PREFERENCES
		$manage_classes	= (MANAGE_CLASSES == 'Y') ? 'Class|' : '';
		
		// Get rate types
		$ratetype_title = array();
		$this->getAssignmentsByEmp($empnames, $servicedate, $servicedateto);

		$ratetypes 	= $this->getRateTypesForAllAsgnnames($this->assignments,true);
		$ratetype	= $this->getRateTypes();

		foreach ($ratetype as $val) {

			if (in_array($val['rateid'], $ratetypes)) {
		
				array_push($ratetype_title, $val['name']);
			}
		}

		$rateheader = implode("|",$ratetype_title);

		$heading		= "time.gif~Timesheet";
		$timesheetcaption	= "&nbsp;&nbsp;Timesheet";
		$link_title		= explode("|","cancel.gif~Close|");
		$link_js		= explode("|","javascript:self.close()|");

		switch ($status_id) {

			case 1:	$timesheetstatus 	= "approved,billed";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|$rateheader";
				break;

			case 2:	$timesheetstatus	= "deleted";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|$rateheader";
				break;

			case 3:	$timesheetstatus	= "rejected";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|$rateheader";
				if($module == 'Client') {

					$headtitle	= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|$rateheader";
				}
				break;

			case 4:	$timesheetstatus	= "create";
				break;

			case 5: $timesheetstatus	= "edit";
				break;

			case 6: $timesheetstatus	= "saved";
				if ($module == 'MyProfile') {
					$headtitle	= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|$rateheader";					
				}
				break;
			
			case 7:	$timesheetstatus 	= "approved,billed";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|$rateheader";
				break;
			
			default:$timesheetstatus	= "ER";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|$rateheader";								
				break;
		}

		$elements_all = array("timesheetstatus" => $timesheetstatus,"timesheetcaption"=>$timesheetcaption,"headtitle"=>$headtitle,"link_title"=>$link_title,"link_js"=>$link_js,"heading"=>$heading, "status_id"=>$status_id);
		return $elements_all;
	}

	/* Function to display Remarks */
	public function showRemarks($remarks){

		$str	= 
			'<tr>
				<td style="height:25px;">
					<font class="afontstylee"><b>Remarks&nbsp;:&nbsp;</b>
						'.WrapText(htmlspecialchars(stripslashes($remarks)),60,'').'
					</font>
				</td>
			</tr>';
		return $str;
	}

	/* Function to display Notes */
	public function showNotes($notes){

		$str	= 
			'<tr>
				<td style="height:25px;">
					<font class="afontstylee"><b>Notes&nbsp;:&nbsp;</b>
						'.WrapText(htmlspecialchars(stripslashes($notes)),60,'').'
					</font>
				</td>
			</tr>';

		return $str;
	}

	/* Function to display Attached File */
	public function showAttachedFile($filename, $fileid, $media='') {

		if ($media == 'print') {

			$filelink	= $filename;

		} else {

			$filelink	= "<a href='downts.php?id=".$fileid."'>".$filename."</a>";
		}

		$str = '<tr>
				<td align="left" style="height:30px;">
					<table width="100%" cellspacing="0" cellpadding="0" border="0">
						<tr>
							<td>
								<font class="afontstyle"><b>Attached Time Sheets File :</b>&nbsp;&nbsp;'.$filelink.'</font>
							</tr>
						</tr>
					</table>
				</td>
			</tr>';

		return $str;
	}

	/* Function to read User Timesheet Log from DB*/
	public function getUserTimesheetLog($tmstatus, $addr1, $module) {

		$bakupquery	= "SELECT ".tzRetQueryStringDTime('approvetime','DateTimeSec','-').",auser,notes,DATE_FORMAT(approvetime,'%Y-%m-%d %H:%i:%s') FROM 
						timesheet_hours 
					WHERE 
						parid='".$addr1."' AND status='Backup' 
					GROUP BY 
						approvetime 
					ORDER BY 
						approvetime DESC";

		$backresult	= $this->mysqlobj->query($bakupquery,$this->db);

		$display	= '';

		while ($backupRow = $this->mysqlobj->fetch_row($backresult)) {

			$sql_user	= "SELECT name,type from users WHERE username='".$backupRow[1]."'";
			$res_user	= $this->mysqlobj->query($sql_user, $this->db);

			$nameAndsource	= $this->mysqlobj->fetch_row($res_user);
			$backupNotes	= htmlspecialchars($backupRow[2], ENT_QUOTES);

			$display	.= "<tr>
								<td>
									<font class='afontstyle'>
										<a href='#' onclick=\"javascript:viewNotes('$tmstatus', '$addr1', '$module','$backupRow[3]');\">$backupRow[0]</a>
									</font>
								</td>
								<td><font class='afontstyle'>$nameAndsource[0]</font></td>
								<td><font class='afontstyle'>{$backupNotes}</font></td>
						</tr>";
		}

		return $display;
	}

	/* Function to display User Timesheet Log */
	public function showUserTimesheetLog($display){
		$str = '';
		$str = '<tr>
				<td style="height:25px;">
					<table width="100%" cellpadding="0" cellspacing="0">
						<tr class="hthbgcolor">
							<th width="32%" align="left"><font class="afontstyle">Date Updated</font></th>
							<th width="32%" align="left"><font class="afontstyle">Updated By</font></th>
							<th width="36%" align="left"><font class="afontstyle">Notes</font></th>
						</tr>'.
						$display
					.'</table>
				</td>
			</tr>';

		return $str;
	}

	/* Function to display User Timesheet Detailed Notes */
	public function displayTimeInTimeOutDetailedNotes($parid, $status_id, $date, $header_title) {

		$printdata	= '';
		$header		= explode('|', $header_title);
		$numflds	= count($header);

		$printdata	= "<div class='grid_forms' style='white-space:nowrap' id='grid_form'>
					<table cellpadding='0' cellspacing='0' border='0' width='100%'>
					  <tr>
					   <td>";
		$printdata	.= "<table cellpadding='5' cellspacing='0' border='0' width='100%'>";

		if ($numflds > 0 && !empty($header_title)) {

			$printdata	.= "<tr style='background-color:#00B9F2'>";

			for ($i = 0; $i < $numflds; $i++) {

				if ($header[$i] == 'checkbox') {

					if ($module == 'MyProfile') {

						$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none">';

					} else {

						if ((stristr($timesheetstatus, 'Approved') != false) || $timesheetstatus == 'Exported' || $timesheetstatus == 'Deleted') {

							$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none;">';

						} else {

							$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()">';
						}
					}

					$printdata	.= "<th valign='middle' align='center'><font class='afontstyle'>$chk_cond</font></th>";

				} else {

					$printdata	.= "<th valign='middle' align='center'><font class='afontstyle'>$header[$i]</font></th>";
				}
			}

			$printdata	.= '</tr><tr>';

			for ($i = 0; $i < $numflds; $i++) {

				if (strtolower($header[$i]) == 'regular' || strtolower($header[$i]) == 'overtime' || strtolower($header[$i]) == 'doubletime') {

					$printdata	.= '<td valign="top" style="background-color:white" align="center"><font class="smalltextfont">Hours</font></td>';

				} else {

					$printdata	.= '<td valign="top" style="background-color:white">&nbsp;</td>';
				}
			}

			$printdata	.= '</tr>';
		}
		
		$condinvoices = " and th.status = 'Backup' and th.approvetime = '".$date."'";
		
		if(!empty($parid))
		{	
			$condinvoices .= " and th.parid = '".$parid."'";
		}
		
		$parid = 0;  

		$summary_info	= $this->getDetailsFromTimeSheetHours($parid, $status_id, $condinvoices);
		$rowsCount	= count($summary_info);
		
		$i	= 0;
		
		foreach ($summary_info as $row) {
			
			$printdata	.= '<tr class="tr1bgcolor">';

			$sel_usr_qry	= "SELECT name, type from users WHERE username='".$row->auser."'";
			$res_usr_qry	= $this->mysqlobj->query($sel_usr_qry, $this->db);
			$rec_usr_qry	= $this->mysqlobj->fetch_object($res_usr_qry);

			if ($rec_usr_qry->type == 'cllacc' && !empty($row->auser)) {

				$source	= 'Self Svc ('. $rec_usr_qry->name .')';

			} elseif ($rec_usr_qry->type != 'cllacc' && !empty($row->auser)) {

				$source	= 'Accounting ('. $rec_usr_qry->name .')';
			}
			
			if (!empty($row->enddate) && $row->enddate != '00/00/0000') {

				$dateRangeArr	= explode(' ', $row->startdate);
				$showDate	= $dateRangeArr[0].' - '.$row->enddate;

			} else {

				$showDate	= ucwords($row->startdate)."  ".$row->weekday;				
			}

			$printdata	.= "<td><font class='afontstyle'>".$showDate."</font>";
			$printdata	.= "<td valign=top><font class='afontstyle'>".getAssignmentDet($row->client, $row->type, $row->assid, $row->username)."<br>&nbsp;Task Details:&nbsp;".htmlspecialchars($row->task, ENT_QUOTES)."</font></td>";

			// For displaying classes
			if (MANAGE_CLASSES == 'Y') {
				$printdata	.= "<td><font class='afontstyle'>".getClassType($row->classid)."</font></td>";
			}

			$printdata	.= "<td align='center'><font class='afontstyle'>".$row->pre_in_time."</font></td>";
			$printdata	.= "<td align='center'><font class='afontstyle'>".$row->pre_out_time."</font></td>";
			$printdata	.= "<td align='center'><font class='afontstyle'>".$row->break_time."</font></td>";
			$printdata	.= "<td align='center'><font class='afontstyle'>".$row->post_in_time."</font></td>";
			$printdata	.= "<td align='center'><font class='afontstyle'>".$row->post_out_time."</font></td>";			
			
			// For displaying Regular Hours and Overtime Hours Or Any Other Rate Type Hours
			$ratecount = 0;
			
			$time_data 	= explode(",",$row->time_data);			
			$ratetypes 	= $this->getRateTypesForAllAsgnnames($this->assignments,true);
			
			$rate_data = array();
			
			foreach ($time_data as $val) {
				$ratetimedata	= explode("|",$val);
				$rate_data[] 	= $ratetimedata[0];
			}
			
			foreach ($ratetypes as $val) {
				
				if (in_array($val, $rate_data)) {
					
					$rate_hours	= explode("|",$time_data[$ratecount]);
					$printdata	.= "<td align='center' width='8%'><font class='afontstyle'>".$rate_hours[1]."</font></td>";
					//$total_rate_hours	+= $rate_hours[1];
					$ratecount++;
					
				} else {
					$printdata	.= "<td align='center' width='8%'><font class='afontstyle'></font></td>";
				}			
			}

			$printdata	.= "</tr>";

			$i++;
		}
		$printdata	.= "</table>";
		
		$printdata	.= "<table width='100%' cellspacing='0' cellpadding='2' border='0' class='hthbgcolor'>
					<tr><td></td></tr>
					<tr>
						<td>
							<font class='afontstyle'>Submitted Date&nbsp;:&nbsp;<b>".$row->stimedate."</b></font>
						</td>
					</tr>
				    </table>";
				    
		$printdata	.= "</td></tr></table></div>";
		
		$printdata	.= '<table width="100%" cellspacing="0" cellpadding="0" border="0">';		    
		/* DISPLAYING REMARKS */
		if (!empty($row->issues)) {
			$printdata	.= $this->showRemarks($row->issues);
		}

		/* DISPLAYING NOTES */
		if (!empty($row->notes)) {
			$printdata	.= $this->showNotes($row->notes);
		}
		$printdata	.= '</table>';
		return $printdata;
	}

	/* Function to display Office Use section */
	function showOfficeUseSection($media=''){
		$str = '';
		if($media == "print"){
			$str = '<tr>
					<td>
						<table width="100%" border="0">
							<tr class="hthbgcolor">
								<td colspan="2">
									<font class="afontstyle"><b>Office Use</b></font>
								</td>
							</tr>
							<tr height="25">
								<td width="54%" height="40">
									<font class="afontstyle">Employee Signature&nbsp;&nbsp; :________________________________</font>
								</td>
								<td width="46%">
									<font class="afontstyle">Date :___________</font>
								</td>
							</tr>
							<tr height="25">
								<td height="40">
									<font class="afontstyle">Supervisor Signature :________________________________</font>
								</td>
								<td>
									<font class="afontstyle">Date :___________</font>
								</td>
							</tr>
						</table>
					</td>
				</tr>';
		}
		return $str;
	}
	
	public function displayTimeInTimeOutEmail($parid, $header_title, $check='', $timesheetstatus, $module, $status_id='', $emp_name, $start_date, $end_date, $condinvoices = '', $conjoin = '', $conbillable = ''){

		global $companyname, $IsPrint;
		$timesheetstatus	= ucfirst($timesheetstatus);

		$summary_info	= $this->getDetailsFromTimeSheetHours($parid, $timesheetstatus,$condinvoices,$conjoin);
		$rowsCount	= count($summary_info);

		// GETTING TOTAL HOURS BASED ON RATES

		$reg_total_hours	= 0.00;
		$ovt_total_hours	= 0.00;
		$dbt_total_hours	= 0.00;

		$rates_total_hours	= $this->getTotalHoursForRates($parid, $timesheetstatus, $conbillable);

		if (!empty($rates_total_hours)) {

			foreach ($rates_total_hours as $key => $object) {

				if ($object->rate == 'rate1') {

					$reg_total_hours	= $object->rates_total;
				}

				if ($object->rate == 'rate2') {

					$ovt_total_hours	= $object->rates_total;
				}

				if ($object->rate == 'rate3') {

					$dbt_total_hours	= $object->rates_total;
				}
			}
		}

		$header		= explode('|', $header_title);
		$numflds	= count($header);

		$grids	= "<input type='hidden' name='chkcount' value='$rowsCount'>
			<table cellpadding='4' cellspacing='1' border='0' width='100%'>";

		if ($numflds > 0 && !empty($header_title)) {

			$grids	.= "<tr style='background-color:#00B9F2'>";

			for ($i = 0; $i < $numflds; $i++) {

				if ($header[$i] == 'checkbox') {

					if ($module == 'MyProfile') {

						$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none">';

					} else {

						if ((stristr($timesheetstatus, 'Approved') != false) || $timesheetstatus == 'Exported' || $timesheetstatus == 'Deleted') {

							$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()" style="display:none;">';

						} else {

							$chk_cond	= '<input type="checkbox" id="chk" class="chk" value="check all" checked="checked" onclick="mainChkBox_ProcessedRecords()">';
						}
					}

					$grids	.= "<th valign='middle' align='left'><font class='afontstyle'>$chk_cond</font></th>";

				} else {

					$grids	.= "<th valign='middle' align='center'><font class='afontstyle'>$header[$i]</font></th>";
				}
			}

			$grids	.= '</tr><tr>';

			for ($i = 0; $i < $numflds; $i++) {

				if (strtolower($header[$i]) == 'regular' || strtolower($header[$i]) == 'overtime' || strtolower($header[$i]) == 'doubletime') {

					$grids	.= '<td valign="top" style="background-color:white"><font class="smalltextfont">Hours</font></td>';

				} else {

					$grids	.= '<td valign="top" style="background-color:white">&nbsp;</td>';
				}
			}

			$grids	.= '</tr>';
		}

		$i	= 1;
		$total	= 0;
		$Biltotal	= 0;
		$BillableSuc	= false;

		foreach ($summary_info as $key => $object) {

			$total_rate_hours = 0;
			$grids	.= '<tr>';

			if (!empty($object->client)) {

				$que	= 'SELECT cname, '.getEntityDispName('sno', 'cname', 1)." FROM staffacc_cinfo WHERE sno=".$object->client;
				$res	= $this->mysqlobj->query($que, $this->db);
				$row	= $this->mysqlobj->fetch_row($res);

				$companyname1	= $row[1];

			} else {

				$companyname1	= $companyname;
			}

			if ($object->type == 'EARN') {

				$cli	= $object->client." ( Benefits )";

			} else {

				if ($object->assid == 'AS') {

					$cli	= $companyname1." ( Administrative Staff )";

				} elseif ($object->assid == 'OB') {

					$cli	= $companyname1." ( On Bench )";

				} elseif ($object->assid == 'OV') {

					$cli	= $companyname1." ( On Vacation )";

				} else {

					$lque	= "SELECT cname, ".getEntityDispName('sno', 'cname', 1)." FROM staffacc_cinfo WHERE sno=".$object->client;
					$lres	= $this->mysqlobj->query($lque,$this->db);
					$lrow	= $this->mysqlobj->fetch_row($lres);

					if (empty($object->assid)) {

						$object->assid	= ' N/A ';
					}

					$cli	= " ( ".$object->assid." ) ".$lrow[1];
				}
			}

			$getProject	= "SELECT project FROM hrcon_jobs WHERE hrcon_jobs.username = '".$object->username."' AND pusername = '".$object->assid."' AND ustatus IN ('active','closed','cancel')";
			$resProject	= $this->mysqlobj->query($getProject, $this->db);
			$rowProject	= $this->mysqlobj->fetch_row($resProject);

			$project	= !empty($rowProject[0]) ? $rowProject[0] : '';
			$strbil		= !empty($object->billable) ? 'Yes' : 'No';
			$cli		= $cli.$project;

			$taskDetails	= "<br><b>Task Details:</b> ".htmlspecialchars($object->task);

			if (!empty($object->auser)) {

				$sql_user	= "SELECT name, type FROM users WHERE username=".$object->auser;
				$res_user	= $this->mysqlobj->query($sql_user, $this->db);
				$nameAndsource	= $this->mysqlobj->fetch_row($res_user);
			}

			if ($timesheetstatus == 'Deleted') {

				$disSource = $nameAndsource[0];

			} else {

				if ($nameAndsource[1] == 'cllacc' && !empty($object->auser)) {

					if ($object->status == 'Approved' || $object->status == 'Billed') {

						if ($object->status != 'Billed')
						$disSource	= "Self Svc (".$nameAndsource[0].")";
						else
						$disSource	= "Self Svc (".$nameAndsource[0].") (Billed)";
					}

					if ($object->status == 'Rejected')
					$disSource	= $nameAndsource[0];

					if ($object->status == 'Saved')
					$disSource	= "Saved (".$nameAndsource[0].")";

				} elseif ($nameAndsource[1] != "cllacc" && !empty($object->auser)) {

					if ($object->status== "Approved" || $object->status== "Billed") {

						if ($object->status != "Billed")
						$disSource	= "Accounting (".$nameAndsource[0].")";
						else
						$disSource	= "Accounting (".$nameAndsource[0].") (Billed)";
					}

					if ($object->status == 'Rejected'){
						$disSource	= $nameAndsource[0];
					}

					if ($object->status == 'Saved')
					$disSource	= "Saved (".$nameAndsource[0].")";

				} elseif ($object->status == 'Saved') {

						$disSource	= 'Saved';

				} elseif ($object->status == 'Backup') {

						$disSource	= 'Deleted';

				} else {

					$disSource	= 'Pending';
				}
			}

			if ($check != 'no') {

				if ($disSource == 'Pending')
				$checked	= " checked";
				elseif($timesheetstatus == 'Rejected')
				$checked	= " checked";
				else
				$checked	= " disabled";

				$grids	.= "<td width='2%'><input type=checkbox name=auids[] id='chk".$i."' value='".$object->sno."' $checked onClick=chk_clearTop_TimeSheet()></td>";
			}

			if (!empty($object->enddate) && $object->enddate != '00/00/0000') {

				$dateRangeArr	= explode(' ', $object->startdate);
				$dateRangeShow	= $dateRangeArr[0].' - '.$object->enddate;

			} else {

				$dateRangeShow	= ucwords($object->startdate)."  ".$object->weekday;
			}

			$grids	.= "<td align='left' width='16%'><font class='afontstyle'>".$dateRangeShow."</font></td>";
			$grids	.= "<td align='left' width='28%'><font class='afontstyle'>".$cli.$taskDetails."</font></td>";

			// For displaying classes
			if (MANAGE_CLASSES == 'Y') {
				$class = $this->getClasses(" AND sno = $object->classid");
				$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$class[0]['classname']."</font></td>";
			}

			// For displaying pretime in/out
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->pre_in_time."</font></td>";
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->pre_out_time."</font></td>";

			// For displaying lunch break
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->break_time."</font></td>";

			// For displaying posttime in/out 
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->post_in_time."</font></td>";
			$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$object->post_out_time."</font></td>";

			// For displaying Regular Hours and Overtime Hours Or Any Other Rate Type Hours
			$ratecount = 0;

			$time_data 	= explode(",",$object->time_data);
			$ratetypes 	= $this->getRateTypesForAllAsgnnames($this->assignments,true);

			$rate_data = array();
			
			foreach ($time_data as $val) {
				$ratetimedata	= explode("|",$val);
				$rate_data[] 	= $ratetimedata[0];
			}
			
			foreach ($ratetypes as $val) {
				
				if (in_array($val, $rate_data)) {
					
					$rate_hours	= explode("|",$time_data[$ratecount]);
					$grids	.= "<td align='left' width='8%'><font class='afontstyle'>".$rate_hours[1]."</font></td>";
					$total_rate_hours	+= $rate_hours[1];
					$ratecount++;
					
				} else {
					$grids	.= "<td align='left' width='8%'><font class='afontstyle'></font></td>";
				}			
			}

			if ($status_id == "1" || $status_id == "2" || $status_id == "7" ) {

				if (empty($disSource))
				$disSource	= 'Pending';

				$grids	.= "<td align='left'><font class='afontstyle'>".$disSource."</font></td>";
				$grids	.= "<td align='left'><font class='afontstyle'>".$object->approvetime."</font></td>";

			} elseif ($status_id == "3" && $module!='Client') {

				$grids	.= "<td align='left'><font class='afontstyle'>".$disSource."</font></td>";
				$grids	.= "<td align='left'><font class='afontstyle'>".$object->approvetime."</font></td>";
			}

			if ($status_id != "1" && $status_id != "2" && $status_id != "3" && $status_id != "7" || ($module == "Client" && $status_id == '3')) {
				// For last column - Overall Total
				$grids	.= "<td align='left'><font class='afontstyle'></font></td>";
			}

			$grids	.= '</tr>';

			$i++;

			if (strtolower($strbil) == 'yes' && $IsPrint == 'yes') {

				$BillableSuc	= true;
				$Biltotal		= $Biltotal + $object->hours;
			}

			$total	= $total + $total_rate_hours;
		}

		if ($i == 1) {

			$grids	.= "<tr><td colspan=".($numflds+1)." align=center class=tr2bgcolor><font class='afontstyle'>No Time Sheets are available.</td></tr>\n";

		} else {

			if (MANAGE_CLASSES == 'Y') {

				$grids	.= "<tr>";

				$tot_label_colnum	= 7;

				if ($check != 'no') {

					$tot_label_colnum	= 8;
				}

				for ($i = 0; $i < $numflds; $i++) {

					if ($i == $tot_label_colnum) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>Total Hours</b></font></td>";

					} elseif ($i == $tot_label_colnum + 1) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($reg_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 2) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($ovt_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 3) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($dbt_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 4) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($total,2,'.','')."</b></font></td>";

					} else {

						$grids	.= "<td align='left'>&nbsp;</td>";
					}
				}

				$grids	.= '</tr>';

			} else {

				$grids	.= '<tr>';

				$tot_label_colnum	= 6;

				if ($check != 'no') {

					$tot_label_colnum	= 7;
				}

				for ($i = 0; $i < $numflds; $i++) {

					if ($i == $tot_label_colnum) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>Total Hours</b></font></td>";

					} elseif ($i == $tot_label_colnum + 1) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($reg_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 2) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($ovt_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 3) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($dbt_total_hours,2,'.','')."</b></font></td>";

					} elseif ($i == $tot_label_colnum + 4) {

						$grids	.= "<td align='left'><font class='afontstyle'><b>".number_format($total,2,'.','')."</b></font></td>";

					} else {

						$grids	.= "<td align='left'>&nbsp;</td>";
					}
				}

				$grids	.= '</tr>';
			}
		}

		mysql_free_result($result);

		$grids	.= '</table>';

		return	$grids;
	}
	
	/* Function to display timesheet details for both UI and print */
	public function showTimeInTimeOutDetailsEmail($tmstatus, $sno, $ename, $eid, $submitted_date, $start_date, $end_date, $userSelfServicePref = '', $module = '', $media = '') {

		// Here sno and addr1 as equivalent
		// Flag to enable and disable checkboxes
		$check	= "no";

		$whosetmdetails_text = "";

		// Check the type of timesheet - string format
		if(isset($tmstatus) && !is_numeric($tmstatus)){
			$tmstatus = strtolower($tmstatus);
			if(($tmstatus == "billed") || ($tmstatus == "approved")){
				$tmstatus = 1;
			}elseif($tmstatus == "deleted"){
				$tmstatus = 2;
			}elseif($tmstatus == "saved"){
				$tmstatus = 6;
			}elseif($tmstatus == "rejected"){
				$tmstatus = 3;
			}elseif($tmstatus == "exported"){
				$tmstatus = 7;
			}else{
				$tmstatus = 99;
			}
		}

		// Check the type of timesheet - numeric format	
		// Show submitted timesheets by default, otherwise show respective timesheets
		$status_id	= isset($tmstatus) ? $tmstatus : '99';

		// DISPLAYING CLASSES BASED ON USER PREFERENCES
		$manage_classes	= (MANAGE_CLASSES == 'Y') ? 'Class|' : '';

		
		$whosetmdetails_text = "<font class='afontstyle' color='black'>&nbsp;&nbsp;Following are <b>".$ename."</b> Time Sheet details from <b>".$start_date."</b> to <b>".$end_date."</b>.</font>";
		
		// Get rate types
		$ratetype_title = array();
		$this->getAssignmentsByEmp($eid, $start_date, $end_date);
		// echo $this->assignments;
		$ratetypes = $this->getRateTypesForAllAsgnnames($this->assignments,true);
		$ratetype	= $this->getRateTypes();

		foreach ($ratetype as $val) {

			if (in_array($val['rateid'], $ratetypes)) {				
				array_push($ratetype_title, $val['name']);			
			}
		}

		
		
		$rateheader = implode("|",$ratetype_title);
		
		switch ($status_id) {
			case 1:	$timesheetstatus 	= "approved,billed";
				$statval		= "statapproved";
				$timesheetcaption	= "&nbsp;&nbsp;Approved&nbsp;Timesheet";
				$headtitle		= "Date|Assignments|".$manage_classes."Time&nbsp;In|Time&nbsp;Out|Lunch/Break|Time&nbsp;In|Time&nbsp;Out|".$rateheader."|Approved&nbsp;By|Approved&nbsp;Time";
				$check			= "no";
				$whosetmdetails_text 	= "<font class=afontstylee>Time Sheet Submitted by <b>".$ename."</b> on <b>".$submitted_date."</b>.</font>";
				
				$heading		= "time.gif~Approved&nbsp;Timesheet";
				break;

		}
		// $head_arr = explode('|',$headtitle);
		// echo "<pre>";
			// print_r($head_arr);
		// echo "</pre>";
		
		$elements_all	= array("timesheetstatus" => $timesheetstatus,"statval"=>$statval,"timesheetcaption"=>$timesheetcaption,"headtitle"=>$headtitle,"check"=>$check,"whosetmdetails_text"=>$whosetmdetails_text,"heading"=>$heading, "status_id"=>$status_id);

		return $elements_all;
	}
	
	// checks whether timesheet is editable. Mainly used to handle billed and unbilled timesheets
	function checkTimesheetEditable($parid){
		global $db;
		
		$edit_check	= 	false;
		$chk_for_edit	=	"select a.status,a.payroll from timesheet_hours a LEFT JOIN par_timesheet ON par_timesheet.sno = a.parid WHERE  parid='".$parid."' AND par_timesheet.astatus IN ('Approved','Billed','ER')  AND a.status IN ('Approved','Billed')";
		$res_for_edit	=	mysql_query($chk_for_edit,$db);
		while($row_for_edit = mysql_fetch_row($res_for_edit))
		{
			if($row_for_edit[0] != 'Billed' && $row_for_edit[1] == '')
			{
				$edit_check = true;
				break;
			}
		}		
		return $edit_check;
	}
}
?>