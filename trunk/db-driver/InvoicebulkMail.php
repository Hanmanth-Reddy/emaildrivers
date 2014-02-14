<?php
	// Set include folder
	$include_path=dirname(__FILE__);
	ini_set('memory_limit',-1);
	ini_set("max_execution_time", -1);
	ini_set("include_path",$include_path);
	require("global.inc");	
	require("smtp.inc");
	require("html2text.inc");
	require("saveemails.inc");
	require("emailApplicationTrigger.php");
	require('phptoPDF/fpdf.php');	
	
	require_once('timesheet/class.Timesheet.php');
	$timesheetObj = new AkkenTimesheet($db, '33');
	
	$path_pdf = 'mpdfnew/mpdf.php'; 
	require_once($path_pdf);
	
	$Mysql_DtFormat = setMySQLDateFormat();	
	$user_timezone = getTimezone();	
	function stripslashes_deep($value){
    		$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
		    return $value;
	}
	// Instance SMTP
	$smtp=new smtp_class;
	$smtp->saveCopy=false;
	
	$date=date("YmdHis", time()) - $utzos;
	$zipfolder = 'INV_'.$date;
	$path = $WDOCUMENT_ROOT;
	$folder_name = $path.'/'.$zipfolder;
	if (is_dir($folder_name)) {
		rrmdir($folder_name);
	}
	if(!is_dir($folder_name))
	{
	    mkdir($folder_name, 0777);
	}
	
	//$pdf=new FPDF();
   // $pdf->AddPage();
   /***************
   1) IF THE FILE IS RELEASED TO DEV ROOT THEN
   $dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where capp_info.comp_id='naveend' ".$version_clause; 
   2) IF THE FILE IS RELEASED TO ALPHA ROOT THEN
   $dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where capp_info.comp_id='alphaasd' ".$version_clause;
   3) IF THE FILE IS RELEASED TO PRODUCTION/BETA ROOT THEN
    $dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
   *******************/
	$dque="select capp_info.comp_id from company_info LEFT JOIN capp_info ON capp_info.sno=company_info.sno where company_info.status='ER' ".$version_clause;
	$dres=mysql_query($dque,$maindb);	
	if(mysql_num_rows($dres)>0){
		$inc=0;
		$incW = 0;
		// Checking the count of Companyies
		while($drow=mysql_fetch_row($dres)){			
			// Fetch the Companyies one by one
			$companyuser=strtolower($drow[0]);
			require("maildatabase.inc");
			require("database.inc");
			$ubque="SELECT DISTINCT users.username FROM users LEFT JOIN email_invoice ON users.username=email_invoice.created_by WHERE users.type NOT IN ('con','cllacc') AND users.status!='DA' AND email_invoice.to_email!='' AND (email_invoice.status='0' or email_invoice.status='1') ";
			
			$ubres=mysql_query($ubque,$db);
			if(mysql_num_rows($ubres)>0){
				//Checking the Mails count
				while($ubrow=mysql_fetch_row($ubres)){					
					$username=$ubrow[0];
					$mailidList="";
					$queid="select id,status from email_invoice where  status ='0' and created_by='$username'";
					$resid=mysql_query($queid,$db);
					while($rsid=mysql_fetch_row($resid)){
						$mailidList.=$rsid[0].",";
					}// end of the while
					$mailidList=substr($mailidList,0,(strlen($mailidList)-1));				
					if($mailidList!=""){
						$que="update email_invoice set status='1' where id IN (".$mailidList.")";
						mysql_query($que,$db);
					}// end of the if loop
                    $sel_q = "select id from email_invoice where status='1' and created_by='$username'";
                    $resid_q =mysql_query($sel_q,$db);
					$emailids = array();
                    while($rsid_q = mysql_fetch_row($resid_q)){
						
                          $emailids[] = $rsid_q[0];
						  $mailidList	= implode(',',$emailids);
                          //$mailidList = substr($mailidList,0,(strlen($mailidList)-1));
					}
					
					 	 $que="select a.bcc,a.from,a.inv_subject,a.id,a.attach,a.to_email,a.status,a.charset,a.body,a.inv_sno,a.inv_number,a.timesheet_attach as timesheet_attach,a.cc,a.filename as inv_filename,staffacc_contact.sno as contactId  from email_invoice a 
LEFT JOIN  invoice ON a.inv_sno=invoice.sno LEFT JOIN staffacc_cinfo ON staffacc_cinfo.sno = invoice.client_name 
LEFT JOIN staffacc_contact ON staffacc_cinfo.bill_contact = staffacc_contact.sno
 where a.id IN (".$mailidList.") and invoice.deliver = 'Yes' AND invoice.status = 'ACTIVE' AND invoice.client_name=staffacc_cinfo.sno ";
						 $res=mysql_query($que,$db);
					  	 if(mysql_num_rows($res)>0){
						   $inti=0;
							//Checking the Mails count							
							while($row=mysql_fetch_array($res)){
								
								$emailCount[] = $row;
								$file_name=array();
								$file_size=array();
								$file_type=array();
								$tempfile=array();
								$arrTotal=array();
								$hfattachments="";
								$flag="";
								$attach="N";
								$mailid=$row['id'];								
								if($row['to_email'] != '' && $row['bcc'] != '' &&  $row['cc'] != '')
									$bcc = $row['bcc'].",".$row['to_email'].",".$row['cc'];
								else if($row['to_email'] != '' && $row['bcc'] == '' && $row['cc'] == '')
									$bcc = $row['to_email'];
								else if($row['to_email'] == '' && $row['bcc'] != '' && $row['cc'] == '')
									$bcc = $row['bcc'];
								else if($row['to_email'] == '' && $row['bcc'] == '' && $row['cc'] != '')
									$bcc = $row['cc'];
									else if($row['to_email'] != '' && $row['bcc'] != '' && $row['cc'] == '')
									$bcc = $row['to_email'].",".$row['bcc'];
								else if($row['to_email'] == '' && $row['bcc'] != '' && $row['cc'] != '')
									$bcc = $row['bcc'].",".$row['cc'];
								else if($row['to_email'] != '' && $row['bcc'] == '' && $row['cc'] != '')
									$bcc = $row['to_email'].",".$row['cc'];
	
								$from=$row['from'];
								$subject=$row['inv_subject'];
								$inv_filename = $row['inv_filename'];
								$filesubject = stripslashes(stripslashes(str_replace("'","",urldecode($inv_filename)))); 
								//$filesubject = str_replace($filesubject);
								$matter=stripslashes($row['body']);
								$mail_attach=$row['attach'];
								$emailopt = $row['timesheet_attach'];
								$statusmail=$row['status'];
								$contactId = $row['contactId'];
	
								$msgs=$mailid;
								$inv_id=$row['inv_number'];
								$inv_sno=$row['inv_sno'];
								$content_body=stripslashes($row['body']);
								$CharSet_mail=AssignEmailCharset($row['charset']);
								$sentsubject=encodedMailsubject($CharSet_mail,$subject,'B');
								$sentsubject=stripslashes_deep($sentsubject);
								$efrom=$from;

								require("setSMTP.php");	
								
								$i=0;
								$flag=0;
								$attach_body="";
								$attach_body="";
								$inlineattach_body="";
								$mailheaders=array();
								$attach_folder="";
								$attach_folder=mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"));
								$attach_folder.=$mailid;//Added in case two mails in outbox are processed with in a sec
								// code for timesheets update
								$displayFrom = "InvoiceHistory_Print";
								
								$pdf=new FPDF();
                                $pdf->AddPage();
								$temp_name = "";
								 $hque="SELECT sno,created_by,client_name,client_id,invoice_date,due_date,tsdate,tsdate1,cli_mes,tax,discount,deposit, total,invoice_number,inv_tmp_selected, time_spacing, expense_spacing, charge_spacing FROM invoice WHERE invoice_number='".$inv_id."' and sno='".$inv_sno."'";
								$hres=mysql_query($hque,$db);
								$hrow=mysql_fetch_row($hres);
								$inv_tmp_selected = $hrow[14];
								$innonewone = $hrow[13];
								$client = $hrow[2];
								//if(!isset($tax))
								$tax=$hrow[9];
								//if(!isset($discount))
								$discount=$hrow[10];
								//if(!isset($deptotal))
								$deptotal=$hrow[11];
								$taxPerc =$tax;
								$ique="select COUNT(*) from credit_charge where in_no='".$hrow[0]."'";
								$ires=mysql_query($ique,$db);
								$irow=mysql_fetch_row($ires);
								if(!isset($rows))
									$rows=$irow[0];
								if($thisday==""){
									if($val==""){
										$indate=$hrow[4];
										$duedate=$hrow[5];
										if($hrow[6]!=""){
											$tsdate=$hrow[6];
											$tsdate1=$hrow[7];
										}else{
											$tsdate=$hrow[4];
											$tsdate1=$hrow[5];
										}// end of if
									}// end of if
								}else{
									$duedate=date("m/d/Y",$thisday);
								}
								$sintdate=explode("/",$tsdate);
								$sintdate1=explode("/",$tsdate1);
							
								$ftdate=$sintdate[2]."-".$sintdate[0]."-".$sintdate[1];
								$ftdate1=$sintdate1[2]."-".$sintdate1[0]."-".$sintdate1[1];
								$reqclient=$hrow[2];
								$clique="select cname from staffacc_cinfo where sno=".$hrow[2];
								$clires=mysql_query($clique,$db);
								$clirow=mysql_fetch_row($clires);
								$client_real=$clirow[0];
								if($inv_tmp_selected == "NEW")
									$SQL="SELECT timesheet.parid,MIN(timesheet.sdate),MAX(timesheet.sdate) FROM timesheet_hours AS timesheet LEFT JOIN par_timesheet ON(timesheet.parid=par_timesheet.sno)LEFT JOIN invoice ON(invoice.sno=timesheet.billable) WHERE timesheet.client='".$hrow[2]."' AND timesheet.status='Billed' AND timesheet.billable='".$hrow[0]."' GROUP BY invoice.sno";
								else
									$SQL="select timesheet.parid,MIN(timesheet.sdate),MAX(timesheet.sdate) from timesheet left join par_timesheet on (timesheet.parid=par_timesheet.sno)left join invoice on (invoice.sno=timesheet.billable) where timesheet.client='".$hrow[2]."' and timesheet.status='Billed' and timesheet.billable='".$hrow[0]."' group by invoice.sno"; 
								$RSQL=mysql_query($SQL,$db);
								$dd=mysql_fetch_row($RSQL); 
								$Sql="select expense.parid,MIN(expense.edate),MAX(expense.edate) from expense left join par_expense on (expense.parid=par_expense.sno)left join invoice on (invoice.sno=expense.billable) where expense.client='".$hrow[2]."' and  expense.billable='".$hrow[0]."' group by expense.client";
								$Rsql=mysql_query($Sql,$db); 
								$dd1=mysql_fetch_row($Rsql);
								if(($dd[1]!=NULL) && ($dd1[1]!=NULL)){ 
								  if($dd[1]<$dd1[1]){
										$fdate=$dd[1];
								  }else{
										$fdate=$dd1[1];
								  }
								  if($dd[2]>$dd1[2]){
										$todate=$dd[2];
								  }else{
										$todate=$dd1[2];
								  }
								}else if($dd[1] != NULL){
									$fdate=$dd[1];
									$todate=$dd[2];
								}else if($dd1[1] != NULL){
									$fdate=$dd1[1];
									$todate=$dd1[2];
								}else{
									$thisday=mktime(date("H"),date("i"),date("s"),date("m"),date("d")-30,date("Y"));
									$fdate=date("Y-m-d",$thisday);
									$thisday4=mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"));
									$todate=date("Y-m-d",$thisday4);
								}	
								$serfdate=explode("-",$fdate);
								$sertodate=	explode("-",$todate);
								$serfdate1=$serfdate[1]."/".$serfdate[2]."/".$serfdate[0];
								$sertodate1=$sertodate[1]."/".$sertodate[2]."/".$sertodate[0];
								
								if($inv_tmp_selected == "OLD"){
								//For Timesheet Details
									$eque="SELECT emp_list.username, emp_list.name, timesheet.assid, SUM( timesheet.thours ) , timesheet.parid, ".tzRetQueryStringDate(' par_timesheet.sdate','Date', '/' )." , invoice_rates.bamount, ".tzRetQueryStringDate(' par_timesheet.edate','Date', '/' )." , '', SUM( timesheet.othours ) , invoice_rates.bperiod, invoice_rates.otrate, invoice_rates.ot_period, sum( SUBSTRING( timesheet.thours, 1, (INSTR( timesheet.thours, '.' ) -1 ) ) ), sum( SUBSTRING( timesheet.thours, (INSTR( timesheet.thours, '.' ) +1 ) ) ), sum( SUBSTRING( timesheet.othours, 1, (INSTR( timesheet.othours, '.' ) -1 ) ) ), sum( SUBSTRING( timesheet.othours, (INSTR( timesheet.othours, '.' ) +1 ) ) ), invoice_rates.double_rate, invoice_rates.double_period, sum( SUBSTRING( timesheet.double_hours, 1, (INSTR( timesheet.double_hours, '.' ) -1 ) ) ), sum( SUBSTRING( timesheet.double_hours, (INSTR( timesheet.double_hours, '.' ) +1 ) ) ), timesheet.tax,SUM(timesheet.double_hours), invoice_rates.diem_total, invoice_rates.diem_billable, invoice_rates.diem_period, SUM(round((ROUND(CAST(timesheet.thours AS DECIMAL(12,2)),2) * IF(invoice_rates.bperiod='YEAR',ROUND((CAST(invoice_rates.bamount AS DECIMAL(12,2))/(8*261)),2),IF(invoice_rates.bperiod='MONTH',ROUND((CAST(invoice_rates.bamount AS DECIMAL(12,2))/(8*(261/12))),2),IF(invoice_rates.bperiod='WEEK',ROUND((CAST(invoice_rates.bamount AS DECIMAL(12,2))/(8*5)),2),IF(invoice_rates.bperiod='DAY',ROUND((CAST(invoice_rates.bamount AS DECIMAL(12,2))/8),2),ROUND(CAST(invoice_rates.bamount AS DECIMAL(12,2)),2)))))),2)), SUM(round((ROUND(CAST(timesheet.othours AS DECIMAL(12,2)),2) * IF(invoice_rates.ot_period='YEAR',ROUND((CAST(invoice_rates.otrate AS DECIMAL(12,2))/(8*261)),2),IF(invoice_rates.ot_period='MONTH',ROUND((CAST(invoice_rates.otrate AS DECIMAL(12,2))/(8*(261/12))),2),IF(invoice_rates.ot_period='WEEK',ROUND((CAST(invoice_rates.otrate AS DECIMAL(12,2))/(8*5)),2),IF(invoice_rates.ot_period='DAY',ROUND((CAST(invoice_rates.otrate AS DECIMAL(12,2))/8),2),ROUND(CAST(invoice_rates.otrate AS DECIMAL(12,2)),2)))))),2)), SUM(round((ROUND(CAST(timesheet.double_hours AS DECIMAL(12,2)),2) * IF(invoice_rates.double_period='YEAR',ROUND((CAST(invoice_rates.double_rate AS DECIMAL(12,2))/(8*261)),2),IF(invoice_rates.double_period='MONTH',ROUND((CAST(invoice_rates.double_rate AS DECIMAL(12,2))/(8*(261/12))),2),IF(invoice_rates.double_period='WEEK',ROUND((CAST(invoice_rates.double_rate AS DECIMAL(12,2))/(8*5)),2),IF(invoice_rates.double_period='DAY',ROUND((CAST(invoice_rates.double_rate AS DECIMAL(12,2))/8),2),ROUND(CAST(invoice_rates.double_rate AS DECIMAL(12,2)),2)))))),2)),project,refcode FROM timesheet LEFT JOIN emp_list ON timesheet.username = emp_list.username LEFT JOIN invoice_rates ON (invoice_rates.pusername = timesheet.assid AND timesheet.billable = invoice_rates.invid) LEFT JOIN par_timesheet ON ( par_timesheet.sno = timesheet.parid ) WHERE timesheet.billable ='".$hrow[0]."' AND timesheet.billable != '' AND timesheet.type != 'EARN' AND invoice_rates.invid ='".$hrow[0]."' GROUP BY timesheet.username,timesheet.parid,timesheet.assid ORDER BY emp_list.name, timesheet.sdate";
		
								$template_Time_Values = getTimesheet_Details($eque,$db);
							}else{
								$eque = "SELECT emp_list.username,emp_list.name,CONCAT_WS(' - ',".tzRetQueryStringDate('par_timesheet.sdate','Date','/').",".tzRetQueryStringDate('par_timesheet.edate','Date','/')."),invoice_multiplerates.pusername, invoice_multiplerates.project,invoice_multiplerates.refcode,invoice_multiplerates.ponumber, class_setup.classname, invoice_lines.custom1,invoice_lines.custom2,invoice_multiplerates.ratetype,SUM(timesheet.hours),invoice_multiplerates.rate, invoice_multiplerates.period,ROUND(SUM(( ROUND(CAST(timesheet.hours AS DECIMAL(12,2)),2) * IF(invoice_multiplerates.period='YEAR',ROUND((CAST( invoice_multiplerates.rate AS DECIMAL(12,2))/(8*261)),2), IF(invoice_multiplerates.period = 'MONTH',ROUND((CAST( invoice_multiplerates.rate AS DECIMAL(12,2))/(8*(261/12))),2),IF(invoice_multiplerates.period='WEEK',ROUND(( CAST(invoice_multiplerates.rate AS DECIMAL(12,2))/(8*5)),2),IF(invoice_multiplerates.period='DAY',ROUND((CAST(invoice_multiplerates.rate AS DECIMAL(12,2))/8),2), ROUND(CAST(invoice_multiplerates.rate AS DECIMAL(12,2)),2))))))),2),timesheet.tax,timesheet.parid,PerDiem.rate,IF(timesheet.perdiem_billed='billable','Y','N'),PerDiem.period FROM timesheet_hours AS timesheet LEFT JOIN emp_list ON timesheet.username = emp_list.username LEFT JOIN invoice_multiplerates ON (invoice_multiplerates.pusername = timesheet.assid AND timesheet.billable = invoice_multiplerates.invid AND invoice_multiplerates.rateid = timesheet.hourstype) LEFT JOIN (SELECT rate,period,pusername,invid FROM invoice_multiplerates WHERE rateid='rate4') PerDiem ON (PerDiem.pusername = timesheet.assid AND timesheet.billable = PerDiem.invid) LEFT JOIN par_timesheet ON(par_timesheet.sno = timesheet.parid) LEFT JOIN invoice_lines ON(invoice_lines.lineid = timesheet.parid AND invoice_lines.ratetype = invoice_multiplerates.rateid AND invoice_lines.pusername = invoice_multiplerates.pusername AND invoice_lines.invid = '".$hrow[0]."' AND invoice_lines.linetype = 'TS')  LEFT JOIN class_setup ON (invoice_lines.classid = class_setup.sno) WHERE timesheet.billable ='".$hrow[0]."' AND timesheet.billable != '' AND timesheet.type != 'EARN' AND invoice_multiplerates.invid ='".$hrow[0]."' GROUP BY timesheet.username,timesheet.parid,timesheet.assid ORDER BY emp_list.name,timesheet.sdate";
			
								$template_Time_Values = getTimesheetHours_Details($eque,$db,$hrow[0]);
							}	
							//For Expense Details
							/*$eque="select emp_list.username,emp_list.name,".tzRetQueryStringDate('expense.edate','Date','/').",IF(expense.billable!='',expense.expense_billrate,ROUND(expense.quantity * expense.unitcost,2)),par_expense.advance,expense.quantity,expense.unitcost,expense.sno,expense.tax,exp_type.title,expense.parid from par_expense LEFT JOIN emp_list ON par_expense.username=emp_list.username LEFT JOIN expense ON expense.parid=par_expense.sno LEFT JOIN exp_type ON exp_type.sno=expense.expid where expense.billable='".$hrow[0]."' and expense.client='".$reqclient."' group by emp_list.username,expense.sno order by emp_list.name,expense.parid,expense.edate";
							$template_Expense_Values = getExpense_Details($eque,$db);
								if(count($template_Expense_Values) > 0){
									for($cnt=0;$cnt<count($template_Expense_Values);$cnt++){
										$sqry = "SELECT classid,custom1,custom2 FROM invoice_lines WHERE linetype='EX' AND invid = '".$hrow[0]."' AND lineid = '".$template_Expense_Values[$cnt]['expenseId']."'";
										$srs  = mysql_query($sqry,$db);
										$srow = mysql_fetch_row($srs);
										$template_Expense_Values[$cnt]['Class'] = $srow[0];
										$template_Expense_Values[$cnt]['Custom1'] = $srow[1];
										$template_Expense_Values[$cnt]['Custom2'] = $srow[2];
					
									}// for loop ends
								}// if loop ends
								*/
								//For Expense Details
				//$eque="select emp_list.username,emp_list.name,".tzRetQueryStringDate('expense.edate','Date','/').",IF(expense.billable!='',expense.expense_billrate,ROUND(expense.quantity * expense.unitcost,2)),par_expense.advance,expense.quantity,expense.unitcost,expense.sno,expense.tax,exp_type.title,expense.parid from par_expense LEFT JOIN emp_list ON par_expense.username=emp_list.username LEFT JOIN expense ON expense.parid=par_expense.sno LEFT JOIN exp_type ON exp_type.sno=expense.expid where expense.billable='".$hrow[0]."' and expense.client='".$reqclient."' group by emp_list.username,expense.sno order by emp_list.name,expense.parid,expense.edate";

								// To Get Expense Details
								$expense_values	= getExpenseDetails($hrow[0], $reqclient);

								// To Get Email Invoice Attachments
								$email_attachments	= getEmailInvoiceAttachments($inv_id, $reqclient);

								$expensepdf	= new FPDF();

								$expensepdf->AddPage();
									
								if (($emailopt == 1 || $emailopt == 2 || $emailopt == 3) &&  count($expense_values) > 0) {
									$bill_invoice = $inv_sno;
									$companyLogo = getCompanyLogo($bill_invoice, $folder_name);
									if($companyLogo != '')
									{
										$expensepdf->SetFont('Arial','B',11);
										$expensepdf->Cell(300,10,"{$expensepdf->Image($companyLogo, $expensepdf->GetX(), $expensepdf->GetY(), 44, 14)}");
										$expensepdf->Ln();
									}
									foreach ($expense_values as $assignmentid) {

										$expensepdf->Ln();

										$expensepdf->SetFont('Arial', 'B', 11);

										$expensepdf->Cell(100, 5, 'Expenses Submitted By ' . $assignmentid['username']);

										$expensepdf->Ln(10);

										$expensepdf->SetFont('Arial', 'B', 8);

										$expensepdf->Cell(20, 6, 'Date');
										$expensepdf->Cell(25, 6, 'Assignments');
										$expensepdf->Cell(30, 6, 'Expense Type');
										$expensepdf->Cell(15, 6, 'Quantity');
										$expensepdf->Cell(20, 6, 'Unit Cost');
										$expensepdf->Cell(20, 6, 'Amount');
										$expensepdf->Cell(40, 6, 'Approved By');
										$expensepdf->Cell(20, 6, 'Approved Date');

										$expensepdf->Ln();

										$billrate	= 0;

										for ($cnt = 0; $cnt < count($assignmentid); $cnt++) {

											$expensepdf->SetFont('Arial','',7);

											$expensepdf->Cell(20, 6, $assignmentid[$cnt]['date']);
											$expensepdf->Cell(25, 6, $assignmentid[$cnt]['assignment']);
											$expensepdf->Cell(30, 6, $assignmentid[$cnt]['expensetype']);
											$expensepdf->Cell(15, 6, $assignmentid[$cnt]['quantity']);
											$expensepdf->Cell(20, 6, $assignmentid[$cnt]['unitcost']);
											$expensepdf->Cell(20, 6, $assignmentid[$cnt]['billrate']);
											$expensepdf->Cell(40, 6, $assignmentid[$cnt]['approvedby']);
											$expensepdf->Cell(20, 6, $assignmentid[$cnt]['dateapproved']);

											$expensepdf->Ln();

											$billrate	= $billrate + $assignmentid[$cnt]['billrate'];
										}

										$billrate	= number_format($billrate, 2, '.', '');

										$expensepdf->SetFont('Arial', '', 9);

										$expensepdf->Cell(20, 7, 'Total');
										$expensepdf->Cell(30, 7, "{$billrate}");

										$expensepdf->Ln();
										$expensepdf->Ln();

										$expensepdf->Cell(100, 8, 'Office Use');

										$expensepdf->Ln();
										$expensepdf->Ln();

										$expensepdf->Cell(130, 8, 'Signature :');
										$expensepdf->Cell(130, 8, 'Signature :');

										$expensepdf->Ln();

										$expensepdf->Cell(130, 8, 'Date :');
										$expensepdf->Cell(130, 8, 'Date :');

										$expensepdf->Ln();
										$expensepdf->Ln();
									}
								}


								if(($emailopt == 1 || $emailopt == 2 || $emailopt == 3) &&  count(
								$template_Time_Values) > 0){	
									$bill_invoice = $inv_sno;
									//added by naveen
									
									$companyLogo = getCompanyLogo($bill_invoice, $folder_name);
									if($companyLogo != '')
									{
										$pdf->SetFont('Arial','B',11);
										$pdf->Cell(300,6,"{$pdf->Image($companyLogo, $pdf->GetX(), $pdf->GetY(), 30, 10)}");
										$pdf->Ln();
										$pdf->Ln();
									}
									$template_Time_Values = remove_duplicateKeys("timeParId",$template_Time_Values);
									
									$rec_cnt = count($template_Time_Values);
									for($rr=0; $rr<$rec_cnt; $rr++){
											 $total = 0;
										$addr1 = $template_Time_Values[$rr]['timeParId'];
										
										$qu="select ".tzRetQueryStringDate('par_timesheet.sdate','ShMonth','/').", ".tzRetQueryStringDate('par_timesheet.edate','ShMonth','/').", DATE_FORMAT( par_timesheet.stime, '%m/%d/%Y %H:%i:%s' ), par_timesheet.issues, par_timesheet.astatus, par_timesheet.pstatus, emp_list.name, ".tzRetQueryStringDTime('par_timesheet.atime','DateTime24','/').", ".tzRetQueryStringDTime('par_timesheet.ptime','DateTime24','/').", par_timesheet.puser, par_timesheet.auser, par_timesheet.notes from par_timesheet LEFT JOIN emp_list ON emp_list.username=par_timesheet.username where par_timesheet.sno='".$addr1."'";
										
										$result=mysql_query($qu,$db);
										$myrow=mysql_fetch_row($result);
										mysql_free_result($result);
										$que2="select name from time_attach where parid='".$addr1."'";
										$res2=mysql_query($que2,$db);
										$row2=mysql_fetch_row($res2);
										$filename=$row2[0];
										$CompInfoQry="select company_name from company_info";
										$CompInfoRes=mysql_query($CompInfoQry,$db);
										$CompInfoRow=mysql_fetch_row($CompInfoRes);
										$EmpCompanyName=$CompInfoRow[0];
										$logopath = getCompanyLogo($companyuser, $WDOCUMENT_ROOT);
										
										$timesheet .= "
											<style>@page {
												margin: 0px, 35px, 35px, 35px;
											}</style>
										";
										$timesheet .= '<table width="99%" border="0" cellspacing="0" cellpadding="0">
													  <tr>
														<td width="50%" align="left" valign="top"><div></div></td>
														<td width="50%" align="right">&nbsp;</td>
													  </tr>
													   <tr>
														<td width="50%" align="left" valign="top"><div><img src="'.$logopath.'" border=0 height=48 width=165></div></td>
														<td width="50%" align="right">&nbsp;</td>
													  </tr>
													  <tr>
														<td><font class=afontstylee>Company Name : <b>'.stripslashes($EmpCompanyName).'</b></font></td>
														<td align="right"><font class=afontstylee>Time Sheet Submitted by <b>'.$myrow[6].'</b> on <b>'.$myrow[2].'</b></font></td>
													  </tr>
												 </table>
												 </td>
											</tr>    	
											</table>
										';
										if(bill_invoice != '')
											$chk_condi_bill = "AND billable='".$bill_invoice."'";
										else
											$chk_condi_bill = "AND billable='Yes'";
										$timesheet .= $timesheetObj->displayTimesheetDetailsEmail($addr1, "approved",$chk_condi_bill);
										
										 if($Biltotal>"0")
										{
											$timesheet .=  '<tr>
								<td colspan=1><font class=afontstyle>&nbsp;</font></td><td colspan="1">&nbsp;</td><td align=right><font class=hfontstyle >Billable Hours: &nbsp;&nbsp;</font></td><td><font class=hfontstyle>'.number_format($Biltotal,2,"."," ").'</font></td><td>&nbsp;</td><td>&nbsp;</td>
								</tr>';
										}
										$timesheet .=  '<tr>
								<td colspan="8">
									<table width="99%" border="0">
									<tr>
										<td colspan="2">&nbsp;</td>
										</tr>
										<tr class=hthbgcolor>
										<td colspan="2"><font class=afontstylee><b>Office Use</b></font></td>
									</tr>
										
									<tr height="25">
											<td width="54%" height="40"><font class=afontstylee>Employee Signature&nbsp;&nbsp; :________________________________</font></td>
											<td width="46%"><font class=afontstylee>Date :___________</font></td>
									</tr>
									<tr height="25">
											<td height="40"><font class=afontstylee>Supervisor Signature :________________________________</font></td>
											<td><font class=afontstylee>Date :___________</font></td>
									</tr>
									</table></td>
								</tr>  </table></table>';
								if ($rr==$rec_cnt-1){
									$timesheet .=  "";
								}else{
									$timesheet .=   "delimiterforakken<pagebreak />";
								}
								
								
								
							
					} //End for()--loop of records	
						$html_arr = explode('delimiterforakken',$timesheet);
						if($emailopt == 3){
							$mpdf=new mPDF('utf-8');
							
							$mpdf->SetDisplayMode('fullpage');
							$stylesheet = file_get_contents('/usr/bin/db-driver/timesheet/educeit.css');
							$mpdf->WriteHTML($stylesheet,1);
							
							$stylesheet = file_get_contents('/usr/bin/db-driver/timesheet/tab.css');
							$mpdf->WriteHTML($stylesheet,1);
							
							$stylesheet = file_get_contents('/usr/bin/db-driver/timesheet/timesheet.css');
							$mpdf->WriteHTML($stylesheet,1);

							$mpdf->list_indent_first_level = 0;	
								
							for($n=0; $n<count($html_arr); $n++){
								$mpdf->WriteHTML($html_arr[$n]);
							}
							$timesheet = '';
						}
						elseif($emailopt == 1 || $emailopt == 2){
							$mpdf=new mPDF('utf-8', 'A4-L');
							$mpdf->SetDisplayMode('fullpage');
						
							$stylesheet = file_get_contents('/usr/bin/db-driver/timesheet/educeit.css');
							$mpdf->WriteHTML($stylesheet,1);
							
							$stylesheet = file_get_contents('/usr/bin/db-driver/timesheet/tab.css');
							$mpdf->WriteHTML($stylesheet,1);
							
							$stylesheet = file_get_contents('/usr/bin/db-driver/timesheet/timesheet.css');
							$mpdf->WriteHTML($stylesheet,1);

							$mpdf->list_indent_first_level = 0;	
								
							for($n=0; $n<count($html_arr); $n++){
								$mpdf->WriteHTML($html_arr[$n]);
								
							}
							$timesheet = '';
						}
						
				
				}//if(;;) 
							
							// Sending attachments code
							if($mail_attach=="A"){
								 $aque="select invoice_number as filename,size as filesize,type as filetype,data as filecontent from invoicehistory_attach where invoice_number='$inv_id' and invoiceid='$inv_sno'";
								$ares=mysql_query($aque,$db);								
								while($arow=mysql_fetch_array($ares)){
										$replace_sub =str_replace(':','_',$filesubject).".pdf";
										$file_name[$i]=$replace_sub;
										$file_type[$i]=$arow['filetype'];
										$tfile=$replace_sub;
										$isDirEx=$WDOCUMENT_ROOT."/".$attach_folder;
										if(!is_dir($isDirEx))
											mkdir($isDirEx,0777);
	
										$file=$isDirEx."/".$tfile;
										$fp = fopen($file,"w");
										fwrite($fp, $arow['filecontent']);
										
										fclose($fp);
										if($emailopt != 3){
											$file_size[$i]=filesize($file);
											$tempfile[$i]=$tfile."|-".stripslashes($replace_sub);
										}
										
										$i++;
										$flag++;
										$file1 = $file;
									}									
									if($emailopt == 1 &&  count($template_Time_Values) > 0){									
										$pdf->Ln();
										$replace_timesheet =str_replace(' : INV','_Timesheet',$filesubject).".pdf";
										//$pdf->Cell(1000,6,"{$timesheet}");
										$file_name[$i]=$replace_timesheet;
										$file_type[$i]="application/pdf";
										$tfile=$replace_timesheet;
										$isDirEx=$WDOCUMENT_ROOT."/".$attach_folder;
										if(!is_dir($isDirEx))
											mkdir($isDirEx,0777);
	
										$file= $isDirEx."/".$tfile;
										$fp1 = fopen($file,"w");
										fwrite($fp1, '');
										//$output = $pdf->Output($file,'F');
										$output = $mpdf->Output($file,'F');
										fclose($fp1);
										//$file2 = $file;
										if($emailopt != 3){
											$file_size[$i]=filesize($file);
											$tempfile[$i]=$tfile."|-".stripslashes($replace_timesheet);
										}
										$i++;
										$flag++;
									}
									
									if($emailopt == 2 &&  count($template_Time_Values) > 0 || $emailopt == 3) {											
									
										$pdf->Ln();
										//$pdf->Cell(1000,6,"{$timesheet}");
										$replace_timesheet =str_replace(' : INV','_Timesheet',$filesubject).".pdf";
										$file_name[$i]=$replace_timesheet;
										$file_type[$i]="application/pdf";
										$tfile=$replace_timesheet;
										$isDirEx=$WDOCUMENT_ROOT."/".$attach_folder;
										if(!is_dir($isDirEx))
											mkdir($isDirEx,0777);
	
										$file= $isDirEx."/".$tfile;
										$fp1 = fopen($file,"w");
										fwrite($fp1, '');
										$output = $mpdf->Output($file,'F');
										fclose($fp1);
										$file3 = $file;
										
										if($emailopt != 3){
											$file_size[$i]=filesize($file);
											$tempfile[$i]=$tfile."|-".stripslashes($replace_timesheet);
										}
										
										
										$i++;
										$flag++;
									
										$timesheet_parid = array();

										for($k=0;$k<count($template_Time_Values);$k++)
										{
											$parid	= $template_Time_Values[$k]['timeParId'];
											
											$que2="select name,type,size,data from time_attach where parid='$parid'";
											$res2=mysql_query($que2,$db);

											while($row2=mysql_fetch_array($res2)) {

												$file_name[$i]=$row2['name'];
												$file_type[$i]=$row2['type'];
												$tfile=$row2['name'];
												$isDirEx=$WDOCUMENT_ROOT."/".$attach_folder;

												if(!is_dir($isDirEx))
												mkdir($isDirEx,0777);

												$file=$isDirEx."/".$tfile;
												$fp = fopen($file,"w");
												fwrite($fp,$row2['data']);										
												fclose($fp);	
												
												if($emailopt != 3){
													$file_size[$i]=filesize($file);
													$tempfile[$i]=$tfile."|-".stripslashes($row2['name']);
												}
												$file4 = $file;

												if (!in_array($parid, $timesheet_parid)) {

													array_push($timesheet_parid, $parid);

													$i++;
													$flag++;
												}
											}
										}
									}

									if(count($expense_values) > 0) {

										if ($emailopt == 1) {

											$expensepdf->Ln();

											$replace_expense	= str_replace(' : INV', '_Expense', $filesubject) . '.pdf';
											$file_name[$i]		= $replace_expense;
											$file_type[$i]		= 'application/pdf';

											$expense_file	= $replace_expense;
											$folder_path	= $WDOCUMENT_ROOT . '/' . $attach_folder;

											if (!is_dir($folder_path)) {
												mkdir($folder_path, 0777);
											}

											$file	= $folder_path . '/' . $expense_file;
											$handle	= fopen($file, 'w');
											fwrite($handle, '');

											$output	= $expensepdf->Output($file, 'F');

											fclose($handle);
											//$file5 = $file;

											if($emailopt != 3){
												$file_size[$i]	= filesize($file);
												$tempfile[$i]	= $expense_file .'|-'. stripslashes($replace_expense);
											}
											
											$i++;
											$flag++;

										} elseif ($emailopt == 2 || $emailopt == 3) {

											$expensepdf->Ln();

											$replace_expense	= str_replace(' : INV', '_Expense', $filesubject) . '.pdf';
											$file_name[$i]		= $replace_expense;
											$file_type[$i]		= 'application/pdf';

											$expense_file	= $replace_expense;
											$folder_path	= $WDOCUMENT_ROOT . '/' . $attach_folder;

											if (!is_dir($folder_path))
											mkdir($folder_path, 0777);

											$file	= $folder_path . '/' . $expense_file;
											$handle	= fopen($file, 'w');

											fwrite($handle, '');

											$output	= $expensepdf->Output($file, 'F');

											fclose($handle);
											$file6 = $file;

											if($emailopt != 3){
												$file_size[$i]	= filesize($file);
												$tempfile[$i]	= $expense_file .'|-'. stripslashes($replace_expense);
											}
											

											$i++;
											$flag++;

											$expense_parid	= array();

											$k	= 0;

											foreach ($expense_values as $assignments) {

												$assignmentid	= $assignments[$k]['assignment'];

												if (!in_array($assignmentid, $assignment_ids)) {

													array_push($assignment_ids, $assignmentid);

													$parid	= $assignments[$k]['parid'];

													if (!in_array($parid, $expense_parid)) {

														array_push($expense_parid, $parid);

														$i++;
														$flag++;
													}

													if (isset($parid) && !empty($parid)) {

														$exp_query	= "SELECT name, type, size, data FROM exp_attach WHERE parid='$parid'";
														$exp_result	= mysql_query($exp_query, $db);

														$row	= mysql_fetch_array($exp_result);

														$file_type[$i]	= $row['type'];
														$file_name[$i]	= $row['name'];

														$folder_path	= $WDOCUMENT_ROOT .'/'. $attach_folder;

														if (!is_dir($folder_path))
														mkdir($folder_path,0777);

														$file	= $folder_path .'/'. $row['name'];
														$fp	= fopen($file, 'w');

														fwrite($fp, $row['data']);
														fclose($fp);

														if($emailopt != 3){
															$file_size[$i]	= filesize($file);
															$tempfile[$i]	= $row['name'] .'|-'. stripslashes($row['name']);
														}
														
													}
												}
											}
											
										}
										
									}
								
								//if(($emailopt == 1 &&  count($template_Time_Values) > 0 ) || $emailopt == 3 || ($emailopt == 2 &&  count($template_Time_Values) > 0 )){
									
								if($emailopt == 3){	
								
										$path_pdf = 'mpdfnew/mpdf.php'; 
										//$path_pdf = '/var/www/companies/naveend/include/mpdfnew/mpdf.php'; 
										require_once($path_pdf);
										$mpdf = new mPDF('utf-8', 'A4', '8', '', 10, 10, 7, 7, 10, 10);
										$mpdf->SetImportUse();
										
										$pagecount = $mpdf->SetSourceFile($file1);
										for ($i=1;$i<=$pagecount;$i++) {
											$mpdf->AddPage();
											$tplId = $mpdf->ImportPage($i);
											$mpdf->UseTemplate($tplId);
											$mpdf->WriteHTML();
										}
										
										if($file2){
											$pagecount = $mpdf->SetSourceFile($file2);
											for ($i=1;$i<=$pagecount;$i++) {
												$mpdf->AddPage();
												$tplId = $mpdf->ImportPage($i);
												$mpdf->UseTemplate($tplId);
												$mpdf->WriteHTML();
											}
										}

										if($file3){
											$pagecount = $mpdf->SetSourceFile($file3);
											for ($i=1;$i<=$pagecount;$i++) {
												$mpdf->AddPage();
												$tplId = $mpdf->ImportPage($i);
												$mpdf->UseTemplate($tplId);
												$mpdf->WriteHTML();
											}
										}
										
										// if($file4){
											// $pagecount = $mpdf->SetSourceFile($file4);
											// for ($i=1;$i<=$pagecount;$i++) {
												// $mpdf->AddPage();
												// $tplId = $mpdf->ImportPage($i);
												// $mpdf->UseTemplate($tplId);
												// $mpdf->WriteHTML();
											// }
										// }
										
										if($file5){
											$pagecount = $mpdf->SetSourceFile($file5);
											for ($i=1;$i<=$pagecount;$i++) {
												$mpdf->AddPage();
												$tplId = $mpdf->ImportPage($i);
												$mpdf->UseTemplate($tplId);
												$mpdf->WriteHTML();
											}
										}
										
										if($file6){
											$pagecount = $mpdf->SetSourceFile($file6);
											for ($i=1;$i<=$pagecount;$i++) {
												$mpdf->AddPage();
												$tplId = $mpdf->ImportPage($i);
												$mpdf->UseTemplate($tplId);
												$mpdf->WriteHTML();
											}
										}
										
										//$single_pdf = 'Invoice and Timesheet.pdf';
										$single_pdf  = str_replace("/","",str_replace(' : INV',' _INV',$filesubject)).".pdf";
										$folder_path	= $WDOCUMENT_ROOT .'/'. $attach_folder;
										if (!is_dir($folder_path))
											mkdir($folder_path,0777);
										
										$file7 = $folder_path.'/'.$single_pdf;
										$file_name[$i] = $single_pdf;
										$file_type[$i] = 'application/pdf';
										chmod($file7, 0777);
										$fp5 = fopen($file7,"w");
										fwrite($fp5, '');
										$output = $mpdf->Output($file7,'F');
										fclose($fp5);

										$file_size[$i]=filesize($file7);
										$tempfile[$i]=$single_pdf.'|-'. stripslashes($single_pdf);
										$i++;
										$flag++;
								}
								
								
							}// end of if loop ($mail_attach=="A")

							if (count($email_attachments) > 0) {

								for ($k = 0; $k < count($email_attachments); $k++) {

									$file_type[$i]	= $email_attachments[$k]['filetype'];
									$file_name[$i]	= $email_attachments[$k]['filename'];
									$file_data[$i]	= $email_attachments[$k]['filecontent'];

									$folder_path	= $WDOCUMENT_ROOT .'/'. $attach_folder;

									if (!is_dir($folder_path))
									mkdir($folder_path, 0777);

									$file	= $folder_path .'/'. $file_name[$i];
									$fp	= fopen($file, 'w');

									fwrite($fp, $file_data[$i]);
									fclose($fp);

									$file_size[$i]	= filesize($file);
									$tempfile[$i]	= $file_name[$i] .'|-'. stripslashes($file_name[$i]);

									$i++;
									$flag++;
								}
							}

							$attachments=implode(",",$file_name);
							$hfattachments=implode("|^",$file_name);
							$hfAttach=$hfattachments;
							$attachType=implode(",",$file_type);
							$asize=implode("|^",$file_size);
							$sesstr=implode("|^",$tempfile);
							$flag=prepareAttachsNEW($file_name,$file_size,$file_type,$file_con,$attach_body);
							$attach = $flag=="0" ? "NA" : "A";

							//Checking the Mails count
						    $ChkStatus="total";
							$tsucsent = 0;                                                       
							$detres = explode(",",$bcc);
							$All_eml_Det=array();
                                                       
							$To_Array=array();
							$SentArray=array();										
						    $email_cnt = count($emailids);						
							
							if(count($detres)>0){								
									for($m=0;$m<count($detres);$m++){
											$To_Array=array();
											$mailheaders=array("Date: $curtime_header","From: $from","To: $detres[$m]","Subject: $sentsubject","MIME-Version: 1.0");
							                $msg_body=prepareBody($matter,$mailheaders,"text/html");								
										 	$sel = " select fname,mname,lname from staffacc_contact where sno = '".$contactId."'";
											$result = mysql_query($sel,$db);
											$result_re=mysql_fetch_row($result);
											
											$email_query  = "select to_email from email_invoice where status = '1'";
											$email_result = mysql_query($email_query,$db);
											$email_data   = mysql_fetch_row($email_result);

																						
											$Mail_Arry=array();
											$Mail_status_array['false']=array();
											$Mail_status_array['true']=array();
											
											array_push($To_Array,$detres[$m]);

											$Rpl_msg_body="";
											$Rpl_msg_body=$msg_body;
											$Rpl_msg_body=preg_replace("/&lt;Firstname&gt;|<Firstname>|&amp;lt;Firstname&amp;gt;+$/",$result_re[0],$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/&lt;Middlename&gt;|<Middlename>|&amp;lt;Middlename&amp;gt;+$/",$result_re[1],$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/&lt;Lastname&gt;|<Lastname>|&amp;lt;Lastname&amp;gt;+$/",$result_re[2],$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/&lt;Salutation&gt;|<Salutation>|&amp;lt;Salutation&amp;gt;+$/",'',$Rpl_msg_body);
											$Rpl_msg_body=preg_replace("/&lt;Suffix&gt;|<Suffix>|&amp;lt;Suffix&amp;gt;+$/",'',$Rpl_msg_body);
											$Rpl_msg_body=$Rpl_msg_body.$attach_body;

											$Rpl_matter=$matter;
											$Rpl_matter=preg_replace("/&lt;Firstname&gt;|<Firstname>|&amp;lt;Firstname&amp;gt;+$/",$result_re[0],$Rpl_matter);
											$Rpl_matter=preg_replace("/&lt;Middlename&gt;|<Middlename>|&amp;lt;Middlename&amp;gt;+$/",$result_re[1],$Rpl_matter);
											$Rpl_matter=preg_replace("/&lt;Lastname&gt;|<Lastname>|&amp;lt;Lastname&amp;gt;+$/",$result_re[2],$Rpl_matter);
											$Rpl_matter=preg_replace("/&lt;Suffix&gt;|<Suffix>|&amp;lt;Suffix&amp;gt;+$/",'',$Rpl_matter);
											$Rpl_matter=preg_replace("/&lt;Salutation&gt;|<Salutation>|&amp;lt;Salutation&amp;gt;+$/",'',$Rpl_matter);
										
											if($detres[$m]) {
											    	
												$suc=$smtp->SendMessage($from,$To_Array,$mailheaders,$Rpl_msg_body);
													if($suc){
														$log_que = " UPDATE log_Activity SET ActivityStatus='Sent' WHERE cuser='".$username."' AND   inv_num='".$inv_sno."' and  inv_email_id = '".$mailid."' AND ActivityType='EMAIL'";
														$log_res = mysql_query($log_que);
														 $que="update email_invoice set status='2' where id  = '".$mailid."'";
														mysql_query($que,$db);
													}else{

													   // should write the code here
														$log_que = " UPDATE log_Activity SET ActivityStatus='Failed' WHERE cuser='".$username."' AND   inv_num='".$inv_sno."' and  inv_email_id = '".$mailid."' AND ActivityType='EMAIL'";
														$log_res = mysql_query($log_que);
														$que="update email_invoice set status='2' where id  = '".$mailid."'";
														mysql_query($que,$db);
													}											
											//array_push($SentArray,$mailid);
											//array_pop($To_Array,$detres[$m]);
											}//if loop [$detres[$m]] ends
									}  // for loop
								} // if loop ends (count($detres))
						   $inc++;
		  				}// while loop ends
          
					}// if loops ends	
			}// if loop ends here
		  }// if loop ends here
		}// while loop ends
	}// if loop
	// Function to get time values
	function getTimesheet_Details($eque,$db)
	{
		global $delparids,$delassignids;
		$eres=mysql_query($eque,$db);
		$inc=0;
		$tamount=array();
		$timeexpval = "";		
		$Time_Values = array();
		
		while($erow=mysql_fetch_row($eres))
		{
			//Regular rate
			$erow[3] = number_format($erow[3],2,'.','');
			//Overtime rate
			$erow[9] = number_format($erow[9],2,'.','');
			//Double time hours
			$doublehours = number_format($erow[22],2,'.','');
		
			$thours_time[$inc]=$erow[3];
			
			if($trate[$inc]=="")
				$trate[$inc]=0;
			
			$totmins[$inc] = $erow[3];
			$rhours[$inc] = $erow[3] + $erow[9] + $doublehours; //Total Hours
		
			$tothours = 8;
			// Converting salary in Hourly
			if($erow[10]=="YEAR")
				$trate[$inc] = number_format(($erow[6]/($tothours*261)),2,'.','');
			else if($erow[10]=="MONTH")
				$trate[$inc] = number_format(($erow[6]/($tothours*(261/12))),2,'.','');
			else if($erow[10]=="WEEK")
				$trate[$inc] = number_format(($erow[6]/($tothours*5)),2,'.','');
			else if($erow[10]=="DAY")
				$trate[$inc] = number_format(($erow[6]/$tothours),2,'.','');
			else
				$trate[$inc] = number_format($erow[6],2,'.','');
			
			// Converting over time salary in Hourly
			if($erow[12]=="YEAR")
				$otrate[$inc] = number_format(($erow[11]/($tothours*261)),2,'.','');
			else if($erow[12]=="MONTH")
				$otrate[$inc] = number_format(($erow[11]/($tothours*(261/12))),2,'.','');
			else if($erow[12]=="WEEK")
				$otrate[$inc] = number_format(($erow[11]/($tothours*5)),2,'.','');
			else if($erow[12]=="DAY")
				$otrate[$inc] = number_format(($erow[11]/$tothours),2,'.','');
			else
				$otrate[$inc] = number_format($erow[11],2,'.','');
			
			// Converting Double time salary in to Hourly
			if($erow[18]=="YEAR")
				$dbrate[$inc] = number_format(($erow[17]/($tothours*261)),2,'.','');
			else if($erow[18]=="MONTH")
				$dbrate[$inc] = number_format(($erow[17]/($tothours*(261/12))),2,'.','');
			else if($erow[18]=="WEEK")
				$dbrate[$inc] = number_format(($erow[17]/($tothours*5)),2,'.','');
			else if($erow[18]=="DAY")
				$dbrate[$inc] = number_format(($erow[17]/$tothours),2,'.','');
			else
				$dbrate[$inc] = number_format($erow[17],2,'.','');
			// Coversion ends
		
			if($erow[10]=="FLATFEE")
				$regrate = number_format($erow[6],2,'.','');
			else
				$regrate = number_format($erow[26],2,'.','');//number_format(($erow[3] * $trate[$inc]),2,'.','');
			
			if($erow[12]=="FLATFEE")
				$overate = number_format($erow[11],2,'.','');
			else
				$overate = number_format($erow[27],2,'.','');//number_format(($erow[9] * $otrate[$inc]),2,'.','');
			
			if($erow[18]=="FLATFEE")
				$doublerate = number_format($erow[17],2,'.','');
			else
				$doublerate = number_format($erow[28],2,'.','');//number_format(($doublehours * $dbrate[$inc]),2,'.','');	
		
			$tamount[$inc] = $regrate + $overate  + $doublerate; //Total Amount
			
			$timeexpval = $erow[4]."|".$erow[5]."|".$erow[7]."|".$erow[0];
			
			if($erow[10]=="FLATFEE") 
				$regRate =  "FLATFEE"; 
			else  
				$regRate =  number_format($trate[$inc],2,'.','')."/hr";
				
			if($erow[12]=="FLATFEE") 
				$OveRate =  "FLATFEE"; 
			else  
				$OveRate =  number_format($otrate[$inc],2,'.','')."/hr";
				
			if($erow[18]=="FLATFEE") 
				$dbRate =  "FLATFEE"; 
			else  
				$dbRate =  number_format($dbrate[$inc],2,'.','')."/hr";
			
			$Time_Values[$inc] = array();
			
			$Time_Values[$inc]['ServiceDate'] = $erow[5]." - ".$erow[7];
			$Time_Values[$inc]['Assignment'] = $erow[2];
			$Time_Values[$inc]['AsgnName'] = $erow[29];
			$Time_Values[$inc]['AsgnRefCode'] = $erow[30];
			$Time_Values[$inc]['RegularHours'] = number_format(($erow[3]),2,'.','')."<font class='newhrfont_tmp'>(".$regRate.")</font>";
			$Time_Values[$inc]['RegularHoursCharge'] = number_format($regrate,2,'.','');
			$Time_Values[$inc]['OvertimeHours'] = number_format(($erow[9]),2,'.','')."<font class='newhrfont_tmp'>(".$OveRate.")</font>";
			$Time_Values[$inc]['OvertimeHoursCharge'] = number_format($overate,2,'.','');
			$Time_Values[$inc]['DoubletimeHours'] = number_format(($doublehours),2,'.','')."<font class='newhrfont_tmp'>(".$dbRate.")</font>";
			$Time_Values[$inc]['DoubletimeHoursCharge'] = number_format($doublerate,2,'.','');
			$Time_Values[$inc]['TotalHours'] = number_format($rhours[$inc],2,'.','');
			$Time_Values[$inc]['Amount'] = number_format($tamount[$inc],2,'.','');
			$Time_Values[$inc]['Tax'] = $erow[21];
			$Time_Values[$inc]['PerDiem'] = $erow[23]."|".$erow[24]."|".$erow[25];
			$Time_Values[$inc]['timeSno'] = $erow[0];
			$Time_Values[$inc]['employeeName'] = htmlspecialchars(stripslashes($erow[1]),ENT_QUOTES);
			$Time_Values[$inc]['timeParId'] = $erow[4];
			if($delparids!="" && $delassignids!="")
			{
				$expDelParids=explode(",",stripslashes($delparids));
				$expDelAssignIds=explode(",",stripslashes($delassignids));
				$countDelids=count($expDelAssignIds);
				$countdelInc = 0;
				for($delid=0;$delid<$countDelids;$delid++)
				{
					if($Time_Values[$inc]['timeParId'] == $expDelParids[$delid] && $Time_Values[$inc]['Assignment'] == $expDelAssignIds[$delid])
						$countdelInc++ ;
				}
			}
			if($countdelInc > 0 )
				$Time_Values[$inc]['delParId'] = $Time_Values[$inc]['timeParId'];
			else
				$Time_Values[$inc]['delParId'] ="";
				
			$inc++;
		}
		return $Time_Values;
	}
	
	// Function To Get expense details
	function getExpense_Details($query,$db)
	{
		global $aryinvHrconSnos,$template_Expense;
		$count=0;
    	$res_query=mysql_query($query,$db);
		$eamount = array();
		while($row_query=mysql_fetch_row($res_query))
		{
			$template_Expense_Details[$count]['ServiceDate'] = $row_query[2];
			$template_Expense_Details[$count]['Name'] = htmlspecialchars(stripslashes($row_query[1]),ENT_QUOTES);
			$template_Expense_Details[$count]['Description'] = htmlspecialchars(stripslashes($row_query[9]),ENT_QUOTES);
			$template_Expense_Details[$count]['Class'] = $row_query[11];
			$template_Expense_Details[$count]['Custom1'] = '';
			$template_Expense_Details[$count]['Custom2'] = '';
			$template_Expense_Details[$count]['Quantity'] = number_format($row_query[5],2,'.','');
			$template_Expense_Details[$count]['Cost'] = number_format($row_query[6],2,'.','');
			$template_Expense_Details[$count]['Amount'] = number_format($row_query[3],2,'.','');;
			$template_Expense_Details[$count]['Tax'] = $row_query[8];
			$template_Expense_Details[$count]['expenseId'] = $row_query[7];
			$template_Expense_Details[$count]['expenseSno'] = $row_query[0];
			$template_Expense_Details[$count]['HrconSno'] = $row_query[12];
			
			if($template_Expense['Expense'][0] == "Y")
				$aryinvHrconSnos[] = $row_query[12];
			$count++;
		}
		return $template_Expense_Details;
	}	
	
	function getTimesheetHours_Details($eque,$db,$invid)
	{
		$eres=mysql_query($eque,$db) or die(mysql_error());
		$inc=0;
		$tamount=array();
		$timeexpval = "";		
		$Time_Values = array();
		$parIdAsgnIdArr = array();
		while($erow=mysql_fetch_row($eres))
		{	
			$parIdAsgnId = $erow[2]."-".$erow[3];
			if($erow[13] == "FLATFEE")
			{
				if($erow[11] > 0)
					$trate = $erow[12];
				else
					$trate = 0.00;
			}
			else
			{
				$trate = $erow[14];
			}
			
			$Time_Values[$inc] = array();
			
			$Time_Values[$inc]['ServiceDate'] = $erow[2];
			$Time_Values[$inc]['Assignment'] = $erow[3];
			$Time_Values[$inc]['AsgnName'] = $erow[4];
			$Time_Values[$inc]['AsgnRefCode'] = $erow[5];
			$Time_Values[$inc]['RegularHours'] = $erow[6];
			$Time_Values[$inc]['Class'] = $erow[7];
			$Time_Values[$inc]['RegularHoursCharge'] = $erow[8];
			$Time_Values[$inc]['OvertimeHours'] = $erow[9];
			$Time_Values[$inc]['OvertimeHoursCharge'] = $erow[10];
			$Time_Values[$inc]['DoubletimeHours'] = $erow[11];
			$Time_Values[$inc]['DoubletimeHoursCharge'] = number_format($erow[12],2,'.','');
			$Time_Values[$inc]['TotalHours'] = "<font class='newhrfont_tmp'>".$erow[13]."</font>";
			$Time_Values[$inc]['Amount'] = number_format($trate,2,'.','');
			$Time_Values[$inc]['Tax'] = $erow[15];
			
			if(array_key_exists($parIdAsgnId,$parIdAsgnIdArr))
			{		
				$Time_Values[$inc]['PerDiem'] = $Time_Values[$parIdAsgnIdArr[$parIdAsgnId]]['PerDiem'];
				$Time_Values[$parIdAsgnIdArr[$parIdAsgnId]]['PerDiem'] = "0.00|N|DAY|0";
				$parIdAsgnIdArr[$parIdAsgnId] = $inc;
			}
			else
			{
				$sqry = "SELECT IF(timesheet_hours.edate='0000-00-00',COUNT(DISTINCT(timesheet_hours.sdate)),DATEDIFF(timesheet_hours.edate,timesheet_hours.sdate)+1) FROM timesheet_hours WHERE timesheet_hours.parid='".$erow[16]."' AND timesheet_hours.assid='".$erow[3]."' AND timesheet_hours.billable='".$invid."' GROUP BY timesheet_hours.assid";				
				$srs  = mysql_query($sqry,$db);
				$srow = mysql_fetch_row($srs);
				
				$Time_Values[$inc]['PerDiem'] = $erow[17]."|".$erow[18]."|".$erow[19]."|".$srow[0];
				$parIdAsgnIdArr[$parIdAsgnId] = $inc;
			}
			
			$Time_Values[$inc]['timeSno'] = $erow[0];
			$Time_Values[$inc]['employeeName'] = htmlspecialchars(stripslashes($erow[1]),ENT_QUOTES);
			$Time_Values[$inc]['timeParId'] = $erow[16];
			$Time_Values[$inc]['delParId'] = "";
			$Time_Values[$inc]['HoursType'] = "";
			$inc++;
		}
		return $Time_Values;
	}
	
	function getEntityDispName($disId, $disName, $disType = 1)
	{
		$returnValue = '';		
		
		switch($disType)
		{
			case '1':
			    $returnValue = "CONCAT_WS(' - ',".$disId.",".$disName.")";			
			break;
			case '2':
				$returnValue = "CONCAT('(',".$disId.",') ',".$disName.")";
			break;
			case '3':
				$returnValue = "CONCAT(".$disName.",' (',".$disId.",')')";
			break;			
		}
		
		return $returnValue;
	}
	
	function getHoursType($rateId)
{
	global $maildb,$db;
	$queryRate = "SELECT multiplerates_master.name FROM multiplerates_master WHERE multiplerates_master.status = 'ACTIVE' AND multiplerates_master.rateid = '".$rateId."'";
	$sqlRate = mysql_query($queryRate, $db);
	$rowRate = mysql_fetch_row($sqlRate);
	return $rowRate[0];
}
function tzRetQueryStringDTime($tbl_col_name,$dt_type,$separator)
	{
		/*
		This function is used only when input(i.e $tbl_col_name) should evaluate to ex :"yyyy-mm-dd hh:ii:ss"
		*/
		global $user_timezone;
		$TzConvert="CONVERT_TZ(".$tbl_col_name.",'SYSTEM','".$user_timezone[1]."')";
		return  "DATE_FORMAT(".$TzConvert.",'".getMySQLDateFormat($dt_type,$separator)."')";
		
	}
	
	function tzRetQueryStringDate($tbl_col_name,$dt_type,$separator)
	{
		/*
		 This function is used only when input(i.e $tbl_col_name) should evaluate to ex :"yyyy-mm-dd"
		*/
		global $user_timezone;

		 return "DATE_FORMAT(".$tbl_col_name.",'".getMySQLDateFormat($dt_type,$separator)."')";
	}
		function tzRetQueryStringSTRTODate($tbl_col_name,$cur_format,$dt_type,$separator)
	{
		/*
         This function is used only when input(i.e $tbl_col_name) should evaluate to ex :"yyyy-mm-dd"
        */
        global $user_timezone;
		return  "DATE_FORMAT(STR_TO_DATE(IF(".$tbl_col_name."='0-0-0','00-00-0000',".$tbl_col_name."),'".$cur_format."'),'".getMySQLDateFormat($dt_type,$separator)."')";
	}
	
	function getMySQLDateFormat($dt_type,$separator='/')
	{
		global $Mysql_DtFormat;
		$array_DateFormats["Date"] = array("YMDDate","MonYear","Date","WeekDay","DMYDate","MDYWeek","ShMonth","Day","CEYIntDate");
		$array_DateFormats["DateTime"] = array("DateTime","DateTimeSec");
		$array_DateFormats["DateTime24"] = array("DateTime24","DateTime24Sec","DateTime24Day");
		$array_DateFormats["YMDDateTime"] = array("YMDDateTime24Sec","YMDDateTimeSec");
		
		
		if(in_array($dt_type,$array_DateFormats["DateTime"]))
		 	$retFrmt=$Mysql_DtFormat["Date"][$separator]." ".($dt_type=="DateTimeSec"?$Mysql_DtFormat["Timesec"]["M"]:$Mysql_DtFormat["Time"]["M"]);
		else if(in_array($dt_type,$array_DateFormats["DateTime24"]))
		 	$retFrmt=$Mysql_DtFormat["Date"][$separator]." ".($dt_type=="DateTime24Sec"?$Mysql_DtFormat["Timesec"]["24"]:$Mysql_DtFormat["Time"]["24"])." ".($dt_type=="DateTime24Day"?$Mysql_DtFormat["DateDay"][$separator]:"");
		else if($dt_type=="DateDay")
			$retFrmt=$Mysql_DtFormat["Date"][$separator]." ".$Mysql_DtFormat["DateDay"][$separator];
		else if(in_array($dt_type,$array_DateFormats["YMDDateTime"]))
			$retFrmt=$Mysql_DtFormat["YMDDate"][$separator]." ".($dt_type=="YMDDateTime24Sec"?$Mysql_DtFormat["Timesec"]["24"]:$Mysql_DtFormat["Timesec"]["M"]);
		else if(in_array($dt_type,$array_DateFormats["Date"]))
		  	$retFrmt=$Mysql_DtFormat[$dt_type][$separator]; 
		return $retFrmt;
	}
	function setMySQLDateFormat()
	{
		$array_mysql_dtformats = array (
		"Date"  => array("-" => "%m-%d-%Y","/" => "%m/%d/%Y"),
		"ShYear" => array("-" => "%m-%d-%y","/" => "%m/%d/%y"),
		"Time"  => array("M" => "%h:%i %p","24" => "%H:%i"),
		"Timesec"  => array("M" => "%h:%i:%s %p","24" => "%H:%i:%s"),
		"ShMonth" => array("-" => "%b %d %Y","/" => "%b %d %Y"),
		"YMDDate" => array("-" => "%Y-%m-%d","/" => "%Y/%m/%d"),
		"FMonth" => "%M %D, %Y",
		"MonYear" => array("-" => "%m-%Y","/" => "%m/%Y"),
		"WeekDay" => array("-" => "%u","/" => "%u"),
		"DMYDate" => array("-" => "%d-%m-%Y","/" => "%d/%m/%Y"),
		"MDYWeek" => array("-" => "%m-%d-%Y (%W)","/" => "%m/%d/%Y (%W)"),
		"DateDay" => array("-" => "%W","/" => "%W"),
		"CEYIntDate"  => array("-" => "%c-%e-%Y","/" => "%c/%e/%Y"),
		"Day" => array("-" => " %W ","/" => " %W ")
									   );
		return $array_mysql_dtformats;
	}
	
	function getTimezone()
	{
		global $maildb,$db,$username;

		$que = "SELECT timezone FROM orgsetup WHERE userid = '".$username."'";
		$res = mysql_query($que,$db);
		if(mysql_num_rows($res)>0)
		{
			$TimezoneSno = mysql_fetch_row($res);
			$TZque = "SELECT phpvar,time FROM timezone WHERE sno = '".$TimezoneSno[0]."'";
			$TZres = mysql_query($TZque,$db);
			if(mysql_num_rows($TZres)>0)
			{
				$TimezoneDet= mysql_fetch_row($TZres);
				$user_timezone=array($TimezoneDet[0],$TimezoneDet[0]);
			}
			else
			{
				$user_timezone=array("EST5EDT","EST5EDT");
			}
		}
		else 
		{
			$user_timezone=array("EST5EDT","EST5EDT");
		}
		return $user_timezone;		
	}

	// Function To Get Expense Details
	function getExpenseDetails($invoiceid, $clientid) {

		global $db;

		$count	= 0;

		$expense_details	= array();
		$assignment_ids		= array();

		$query_expenses	=	'SELECT
						users.name, users.type, expense.sno, expense.assid, expense.parid, expense.expid, expense.client,
						expense.quantity, expense.unitcost, (expense.quantity * expense.unitcost) AS amount, expense.advance,
						expense.billable, expense.expnotes, expense.status, expense.approveuser, expense.expense_billrate AS billrate,
						expense.classid, expense.payable, exp_type.title, emp_list.name AS employee, staffacc_cinfo.cname, ' .
						tzRetQueryStringDate('expense.edate', 'Date', '/') . ' AS createdon,' .
						tzRetQueryStringDTime('expense.approvetime', 'DateTime', '/') . " AS approvedon
					FROM
						expense
						LEFT JOIN exp_type ON exp_type.sno=expense.expid
						LEFT JOIN par_expense ON expense.parid = par_expense.sno
						LEFT JOIN emp_list ON emp_list.username = par_expense.username
						LEFT JOIN staffacc_cinfo ON staffacc_cinfo.sno = expense.client
						LEFT JOIN users ON users.username = expense.approveuser
					WHERE
						expense.billable='$invoiceid' AND expense.client='$clientid'
						AND par_expense.astatus IN ('Approved','Billed','ER')
						AND expense.status IN ('Approved','Billed')
					ORDER BY
						expense.edate";

		$result_expenses	= mysql_query($query_expenses, $db);

		while ($row_query = mysql_fetch_object($result_expenses)) {

			$companyname	= $row_query->cname;
			$assignmentid	= $row_query->assid;

			if (!in_array($assignmentid, $assignment_ids)) {

				array_push($assignment_ids, $assignmentid);

				$count	= 0;
			}

			if ($row_query->type == 'cllacc' && !empty($row_query->approveuser)) {

				if ($row_query->status == 'Approved' || $row_query->status == 'Billed') {

					$approvedby	= 'Self Svc ('.$row_query->name.')';

				} elseif ($row_query->status == 'Rejected') {

					$approvedby	= 'Rejected ('.$row_query->name.')';
				}

			} elseif ($row_query->type != 'cllacc' && !empty($row_query->approveuser)) {

				if ($row_query->status == 'Approved' || $row_query->status == 'Billed') {

					$approvedby	= 'Accounting ('.$row_query->name.')';

				} elseif ($row_query->status == 'Rejected') {

					$approvedby	= 'Rejected ('.$row_query->name.')';
				}

			} else {

				$approvedby	= 'Pending';
			}

			$payable	= ($row_query->payable == 'pay') ? 'Yes' : 'No';
			$billable	= (isset($row_query->billable) && !empty($row_query->billable)) ? 'Yes' : 'No';

			$expense_details[$assignmentid]['username']	= $row_query->employee;

			$expense_details[$assignmentid][$count]['payable']	= $payable;
			$expense_details[$assignmentid][$count]['billable']	= $billable;
			$expense_details[$assignmentid][$count]['approvedby']	= $approvedby;
			$expense_details[$assignmentid][$count]['company']	= $companyname;
			$expense_details[$assignmentid][$count]['parid']	= $row_query->parid;
			$expense_details[$assignmentid][$count]['assignment']	= $row_query->assid;
			$expense_details[$assignmentid][$count]['expensetype']	= $row_query->title;
			$expense_details[$assignmentid][$count]['notes']	= $row_query->expnotes;
			$expense_details[$assignmentid][$count]['billrate']	= $row_query->billrate;
			$expense_details[$assignmentid][$count]['date']		= $row_query->createdon;
			$expense_details[$assignmentid][$count]['dateapproved']	= $row_query->approvedon;
			$expense_details[$assignmentid][$count]['class']	= getClassType($row_query->classid);
			$expense_details[$assignmentid][$count]['quantity']	= number_format($row_query->quantity, 2, '.', '');
			$expense_details[$assignmentid][$count]['unitcost']	= number_format($row_query->unitcost, 2, '.', '');
			$expense_details[$assignmentid][$count]['amount']	= number_format($row_query->amount, 2, '.', '');
			$expense_details[$assignmentid][$count]['advance']	= number_format($row_query->advance, 2, '.', '');

			$count++;
		}

		return $expense_details;
	}

	// Function To Get Class Type
	function getClassType($sno) {

		global $db;

		$query_class	= "SELECT classname FROM class_setup WHERE status='ACTIVE' AND sno='$sno'";
		$result_class	= mysql_query($query_class, $db);
		$row_class		= mysql_fetch_object($result_class);

		return $row_class->classname;
	}

	// Function To Get Email Invoice Attachments
	function getEmailInvoiceAttachments($invoiceid, $clientid) {

		global $db;

		$count	= 0;

		$email_attachments	= array();

		$query_attachments	= "SELECT
							ema.filename, ema.filetype, ema.filecontent
						FROM
							email_manage_attachments ema
						WHERE
							ema.invoiceid = $invoiceid AND ema.clientid = $clientid";

		$result_attachments	= mysql_query($query_attachments, $db);

		while ($row_query = mysql_fetch_object($result_attachments)) {

			$email_attachments[$count]['filename']		= stripslashes($row_query->filename);
			$email_attachments[$count]['filetype']		= stripslashes($row_query->filetype);
			$email_attachments[$count]['filecontent']	= $row_query->filecontent;

			$count ++;
		}

		return $email_attachments;
	}

	function remove_duplicateKeys($key,$data){

		$_data = array();

		foreach ($data as $v) {
		if (isset($_data[$v[$key]])) {
		// found duplicate
		continue;
		}
		// remember unique item
		$_data[$v[$key]] = $v;
		}
		// if you need a zero-based array, otheriwse work with $_data
		$data = array_values($_data);
		return $data;
	}
	
	//function getCompanyLogo($addr, $path)
	//{		
	//	global $db;
	//	$attach_folder=mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"));
	//	$attach_folder_temp="";
	//	$isDirEx=$path."/".$attach_folder;
	//	
	//	while(is_dir($isDirEx))
	//	{
	//		$attach_folder_temp=rand(0,100);
	//		$isDirEx=$path."/".$attach_folder.$attach_folder_temp;
	//	}
	//	
	//	$attach_folder=$attach_folder.$attach_folder_temp;
	//	$isDirEx=$path."/".$attach_folder;
	//	mkdir($isDirEx,0777);
	//	
	//	$que="select image_type,image_data from company_logo";
	//	$res=mysql_query($que,$db);
	//	$row=mysql_fetch_row($res);
	//	$ext = split("/",$row[0]);		
	//	$content = $row[1]; 
	//	$ext[1] = (strtolower($ext[1]) == 'pjpeg') ? 'jpeg' : $ext[1];
	//	$fp = fopen($isDirEx."/".$addr.".".$ext[1],"wb");
	//	$head = $addr.".".$ext[1]; 
	//	fwrite($fp,$content); 
	//	fclose($fp);
	//	
	//	$img = $isDirEx."/".$head;
	//	chmod($isDirEx, 0777);
	//	chmod($img, 0777);
	//	
	//	if($content != '')
	//	{
	//		return $img;
	//	}
	//	else
	//	{
	//		return '';
	//	}
	//	
	//}
	
	function getCompanyLogo($addr, $isDirEx)
	{
		global $db;
		$attach_temp_name=rand(0,100);
		chmod($isDirEx, 0777);
		
		$que="select image_type,image_data from company_logo";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
		$ext = split("/",$row[0]);		
		$content = $row[1]; 
		$ext[1] = (strtolower($ext[1]) == 'jpeg') ? 'jpeg' : 'jpg';
		$fp = fopen($isDirEx."/".$attach_temp_name.$addr.".".$ext[1],"wb");
		$head = $attach_temp_name.$addr.".".$ext[1]; 
		fwrite($fp,$content); 
		fclose($fp);
		
		$img = $isDirEx."/".$head;
		
		if($content != '')
		{
			return $img;
		}
		else
		{
			return '';
		}
	}
	
function rrmdir($dir) 
{
    foreach(glob($dir . '/*') as $file) {
        if(is_dir($file))
            rrmdir($file);
        else
            unlink($file);
    }
    rmdir($dir);
}
?>