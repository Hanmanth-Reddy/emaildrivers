<?php
	// CRM > Contacts -- Private / Shared / Public 
	$crm_contacts_que="SELECT DISTINCT(sno) 
	FROM staffoppr_contact WHERE ".getActivitiesEmailCondition($frmemail,'staffoppr_contact','email','email_2','email_3')." AND status='ER' AND (FIND_IN_SET('$username',accessto)>0 OR approveuser='$username' OR accessto='ALL')";

	// CRM > Active Clients -- Private / Shared / Public 
	$crm_active_clients_que="SELECT DISTINCT(staffacc_list.sno) 
	FROM staffacc_list 
	LEFT JOIN staffacc_contact ON staffacc_list.username=staffacc_contact.username 
	LEFT JOIN hrcon_jobs ON (hrcon_jobs.client=staffacc_list.username OR hrcon_jobs.endclient=staffacc_list.username) 
	WHERE ".getActivitiesEmailCondition($frmemail,'staffacc_contact','email','email_2','email_3')."
	AND staffacc_list.status in ('ACTIVE','INACTIVE') 
	AND ((hrcon_jobs.ustatus='active' AND hrcon_jobs.sagent='$username') OR (FIND_IN_SET('$username',staffacc_contact.assignto)>0))";

	//CRM > Candidates > Candidate Profiles -- Private / Shared / Public

	/*
	$crm_candidates_que="SELECT DISTINCT(sno) 
	FROM candidate_list WHERE ".getActivitiesEmailCondition($frmemail,'candidate_list','email','alternate_email','other_email')." 
	AND ((cuser='$username') OR (FIND_IN_SET('$username',accessto)>0) OR (accessto='ALL')) 
	AND status = 'ACTIVE'";
	*/

	$crm_candidates_que="SELECT DISTINCT(sno) FROM (SELECT sno FROM candidate_list WHERE (owner='$username' OR accessto='ALL' OR FIND_IN_SET('$username',accessto)) AND status = 'ACTIVE' AND email='$frmemail' UNION SELECT sno FROM candidate_list WHERE (owner='$username' OR accessto='ALL' OR FIND_IN_SET('$username',accessto)) AND status = 'ACTIVE' AND alternate_email='$frmemail' UNION SELECT sno FROM candidate_list WHERE (owner='$username' OR accessto='ALL' OR FIND_IN_SET('$username',accessto)) AND status = 'ACTIVE' AND other_email='$frmemail') AS candsno";

	// CRM > Candidates > Employees -- Private / Shared / Public 
	$crm_employees_que="SELECT DISTINCT(emp_list.sno) 
	FROM emp_list 
	LEFT JOIN empcon_general ON emp_list.username=empcon_general.username 
	WHERE TRIM(empcon_general.email)='".addslashes($frmemail)."' 
	AND empcon_general.email!='' 
	AND emp_list.lstatus!='DA'";

	// HRM > Consultants -- PUBLIC
	$hrm_consultants_que="SELECT DISTINCT(consultant_list.serial_no) 
	FROM consultant_list 
	LEFT JOIN consultant_general ON consultant_list.username=consultant_general.username 
	WHERE ".getActivitiesEmailCondition($frmemail,'consultant_general','email','alternate_email','other_email')."
	AND (ISNULL(consultant_list.astatus) OR consultant_list.astatus!='backup')";

	// HRM > Employees -- PUBLIC
	$hrm_employees_que="SELECT DISTINCT(emp_list.sno) 
	FROM emp_list 
	LEFT JOIN hrcon_general ON emp_list.username=hrcon_general.username 
	WHERE ".getActivitiesEmailCondition($frmemail,'hrcon_general','email','alternate_email','other_email')."
	AND hrcon_general.ustatus='active' 
	AND emp_list.lstatus!='DA'";

	// Accounting > Companies (Clients / Vendors) -- PUBLIC
	$acc_companies_que="SELECT DISTINCT(staffacc_list.sno) 
	FROM staffacc_list 
	LEFT JOIN staffacc_contact ON staffacc_list.username=staffacc_contact.username 
	WHERE ".getActivitiesEmailCondition($frmemail,'staffacc_contact','email','email_2','email_3')."
	AND staffacc_list.status in ('ACTIVE','INACTIVE')";
?>