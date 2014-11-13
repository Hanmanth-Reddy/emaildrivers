<?php
	// CRM > Contacts -- Private / Shared / Public 
	$crm_contacts_que="SELECT sno FROM staffoppr_contact WHERE ".getActivitiesEmailCondition($frmemail,'staffoppr_contact','email','email_2','email_3')." AND status='ER' AND (FIND_IN_SET('$username',accessto)>0 OR owner='$username' OR accessto='ALL') AND staffoppr_contact.etrack='Y'";

	// CRM > Active Clients -- Private / Shared / Public 
	$crm_active_clients_que="SELECT staffacc_list.sno FROM hrcon_jobs, staffacc_cinfo, staffacc_list, staffacc_contact WHERE ".getActivitiesEmailCondition($frmemail,'staffacc_contact','email','email_2','email_3')." AND staffacc_cinfo.username = staffacc_list.username AND staffacc_list.username=staffacc_contact.username AND (hrcon_jobs.client = staffacc_cinfo.sno OR hrcon_jobs.endclient = staffacc_cinfo.sno OR hrcon_jobs.bill_address = staffacc_cinfo.sno) AND staffacc_list.status ='ACTIVE' AND hrcon_jobs.ustatus = 'active' AND staffacc_contact.etrack='Y' GROUP BY staffacc_cinfo.username";

	//CRM > Candidates --- Private / Shared / Public
	$crm_candidates_que="SELECT sno FROM candidate_list WHERE ".getActivitiesEmailCondition($frmemail,'candidate_list','email','alternate_email','other_email')." AND (candidate_list.cuser='$username' OR FIND_IN_SET('$username',accessto)>0 OR candidate_list.accessto='ALL') AND candidate_list.status = 'ACTIVE' AND candidate_list.etrack='Y'";

	// HRM > Applicant Tracking, Overseas Recruitment -- Public 
	$hrm_consultants_que="SELECT consultant_list.serial_no FROM consultant_list WHERE ".getActivitiesEmailCondition($frmemail,'consultant_list','email','alternate_email','other_email')." AND consultant_list.astatus!='backup' AND consultant_list.etrack='Y'";

	// HRM > Employees AND ACCOUNTING > Employees -- Public
	$hrm_employees_que="SELECT emp_list.sno FROM emp_list WHERE ".getActivitiesEmailCondition($frmemail,'emp_list','email','alternate_email','other_email')." AND emp_list.lstatus NOT IN ('DA','INACTIVE') AND emp_list.etrack='Y'";

	// ACCOUNTING > Customers -- Public
	$acc_companies_que="SELECT DISTINCT(staffacc_list.sno) FROM staffacc_list LEFT JOIN staffacc_contact ON staffacc_list.username=staffacc_contact.username WHERE ".getActivitiesEmailCondition($frmemail,'staffacc_contact','email','email_2','email_3')." AND staffacc_list.status='ACTIVE' AND staffacc_contact.etrack='Y'";
?>