<?php
	// CRM > Contacts -- Private / Shared / Public 
	$crm_contacts_que="SELECT DISTINCT(sno) FROM staffoppr_contact WHERE ".getActivitiesEmailCondition($frmemail,'staffoppr_contact','email','email_2','email_3')." AND status='ER' AND (FIND_IN_SET('$username',accessto)>0 OR approveuser='$username' OR accessto='ALL') AND staffoppr_contact.etrack='Y'";

	// CRM > Active Clients -- Private / Shared / Public 
	$crm_active_clients_que="SELECT DISTINCT(staffacc_list.sno) FROM staffacc_list LEFT JOIN staffacc_contact ON staffacc_list.username=staffacc_contact.username LEFT JOIN hrcon_jobs ON (hrcon_jobs.client=staffacc_list.username OR hrcon_jobs.endclient=staffacc_list.username) WHERE ".getActivitiesEmailCondition($frmemail,'staffacc_contact','email','email_2','email_3')." AND staffacc_list.status in ('ACTIVE','INACTIVE') AND ((hrcon_jobs.ustatus='active' AND hrcon_jobs.sagent='$username') OR (FIND_IN_SET('$username',staffacc_contact.assignto)>0)) AND staffacc_contact.etrack='Y'";

	//CRM > Candidates > Candidate Profiles -- Private / Shared / Public
	$crm_candidates_que="SELECT DISTINCT(sno) FROM (SELECT sno FROM candidate_list WHERE (owner='$username' OR accessto='ALL' OR FIND_IN_SET('$username',accessto)) AND status = 'ACTIVE' AND email='$frmemail' AND etrack='Y' UNION SELECT sno FROM candidate_list WHERE (owner='$username' OR accessto='ALL' OR FIND_IN_SET('$username',accessto)) AND status = 'ACTIVE' AND alternate_email='$frmemail' AND etrack='Y' UNION SELECT sno FROM candidate_list WHERE (owner='$username' OR accessto='ALL' OR FIND_IN_SET('$username',accessto)) AND status = 'ACTIVE' AND other_email='$frmemail' AND etrack='Y') AS candsno";

	// HRM > Consultants -- PUBLIC
	$hrm_consultants_que="SELECT DISTINCT(consultant_list.serial_no) FROM consultant_list WHERE ".getActivitiesEmailCondition($frmemail,'consultant_list','email','alternate_email','other_email')." AND consultant_list.astatus!='backup' AND consultant_list.etrack='Y'";

	// HRM > Employees -- PUBLIC
	$hrm_employees_que="SELECT DISTINCT(emp_list.sno) FROM emp_list WHERE ".getActivitiesEmailCondition($frmemail,'emp_list','email','alternate_email','other_email')." AND emp_list.lstatus NOT IN ('DA','INACTIVE') AND emp_list.etrack='Y'";

	// Accounting > Companies (Clients / Vendors) -- PUBLIC
	$acc_companies_que="SELECT DISTINCT(staffacc_list.sno) FROM staffacc_list LEFT JOIN staffacc_contact ON staffacc_list.username=staffacc_contact.username WHERE ".getActivitiesEmailCondition($frmemail,'staffacc_contact','email','email_2','email_3')." AND staffacc_list.status in ('ACTIVE','INACTIVE') AND staffacc_contact.etrack='Y'";
?>