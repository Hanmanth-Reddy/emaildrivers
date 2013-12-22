<?php
	/*
		TS Task ID		:	4307
		Created By		:	Ramesh
		Creation Date	:	02nd May, 2009 
		Merged By		:	Rajkumar M.
		Main Purpose	:	To Map Mails sent from Imap server to Akken
	*/

	// CRM > Contacts -- Private / Shared / Public 
	$crm_contacts_que="SELECT DISTINCT(sno) 
	FROM staffoppr_contact WHERE TRIM(email) IN (".$frmemail.") 
	AND status='ER' AND (FIND_IN_SET('$username',accessto)>0 OR approveuser='$username' OR accessto='ALL')";

	// CRM > Active Clients -- Private / Shared / Public 
	$crm_active_clients_que="SELECT DISTINCT(staffacc_list.sno) 
	FROM staffacc_list 
	LEFT JOIN staffacc_contact ON staffacc_list.username=staffacc_contact.username 
	LEFT JOIN hrcon_jobs ON (hrcon_jobs.client=staffacc_list.username OR hrcon_jobs.endclient=staffacc_list.username) 
	WHERE TRIM(staffacc_contact.email) IN (".$frmemail.")  
	AND staffacc_list.status in ('ACTIVE','INACTIVE') 
	AND ((hrcon_jobs.ustatus='active' AND hrcon_jobs.sagent='$username') OR (FIND_IN_SET('$username',staffacc_contact.assignto)>0))";

	//CRM > Candidates > Candidate Profiles -- Private / Shared / Public
    $crm_candidates_que="SELECT DISTINCT(sno) 
	FROM candidate_list 
	WHERE TRIM(email) IN (".$frmemail.") 
	AND status = 'ACTIVE' 
    AND ((cuser='$username') OR (FIND_IN_SET('$username',accessto)>0) OR (accessto='ALL'))";

	// CRM > Candidates > Employees -- Private / Shared / Public 
	$crm_employees_que="SELECT DISTINCT(emp_list.sno) 
	FROM emp_list 
	LEFT JOIN empcon_general ON emp_list.username=empcon_general.username 
	WHERE TRIM(empcon_general.email) IN (".$frmemail.") 
	AND emp_list.lstatus!='DA'";

	// HRM > Consultants -- PUBLIC
	$hrm_consultants_que="SELECT DISTINCT(consultant_list.serial_no) 
	FROM consultant_list 
	LEFT JOIN consultant_general ON consultant_list.username=consultant_general.username 
	WHERE TRIM(consultant_general.email) IN (".$frmemail.") 
	AND (ISNULL(consultant_list.astatus) OR consultant_list.astatus!='backup')";

	// HRM > Employees -- PUBLIC
	$hrm_employees_que="SELECT DISTINCT(emp_list.sno) 
	FROM emp_list 
	LEFT JOIN hrcon_general ON emp_list.username=hrcon_general.username 
	WHERE TRIM(hrcon_general.email) IN (".$frmemail.") 
	AND hrcon_general.ustatus='active' 
	AND emp_list.lstatus!='DA'";

	// Accounting > Companies (Clients / Vendors) -- PUBLIC
	$acc_companies_que="SELECT DISTINCT(staffacc_list.sno) 
	FROM staffacc_list 
	LEFT JOIN staffacc_contact ON staffacc_list.username=staffacc_contact.username 
	WHERE TRIM(staffacc_contact.email) IN (".$frmemail.") 
	AND staffacc_list.status in ('ACTIVE','INACTIVE')";
?>
