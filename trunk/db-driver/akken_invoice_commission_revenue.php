<?php
    $include_path   = dirname(__FILE__);
    ini_set("include_path",$include_path);

    require("global.inc");
    
    $dque   = "SELECT
                    capp_info.comp_id
                FROM
                    company_info
                LEFT JOIN
                    capp_info ON (capp_info.sno = company_info.sno)
                WHERE
                    company_info.status='ER'
                    ".$version_clause;
    $dres   = mysql_query($dque, $maindb);

    if(mysql_num_rows($dres) > 0)
    {
        while($drow = mysql_fetch_row($dres))
        {
            $companyuser    = strtolower($drow[0]);
            require("database.inc");

            $invoiceFrDate  = ""; //variable for getting current month starting date.
            $invoiceToDate  = ""; //variable for getting today date.

            //Query to fetch today date and current month starting date
            $getCurDate     = "SELECT
                                    DISTINCT(DATE_FORMAT(STR_TO_DATE(invoice_date,'%m/%d/%Y'),'%Y-%m-01')) AS 'fdate', 
                                    LAST_DAY(DATE_FORMAT(STR_TO_DATE(invoice_date,'%m/%d/%Y'),'%Y-%m-01')) AS 'tdate'
                                FROM invoice
                                WHERE DATE_FORMAT(stime,'%Y-%m-%d') = DATE_ADD(CURDATE(), INTERVAL -1 DAY) 
                                ORDER BY STR_TO_DATE(invoice_date,'%m/%d/%Y')";
            $getCurDateRes  = mysql_query($getCurDate, $db);
            
            while($getCurDateRow  = mysql_fetch_array($getCurDateRes))
            {
                $invoiceFrDate  = $getCurDateRow['fdate'];
                $invoiceToDate  = $getCurDateRow['tdate'];
    
                //Calling function to create temp tables and functions for Invoice Commission Revenue 
                getCommRevenueData($invoiceFrDate, $invoiceToDate);
            }
        }
    }

    /* Function used to create temp tables and functions for Invoice Commission Revenue */
    function getCommRevenueData($invoiceFrDate, $invoiceToDate)
    {
            global $db;

            /* Calling below function to truncate temp tables created and exists */
            truncatetemptables();

            /*
             * Creating the Temporary Tables Start
             * Step 1 Start
             * Temp - 1
             */
            $tmpTableCreateSql_1 = "CREATE TABLE IF NOT EXISTS rpt_tmp_InvComm_cmsn_details_individual (
                                        rowid int(15) unsigned NOT NULL AUTO_INCREMENT,
                                        sno int(15) NOT NULL DEFAULT 0,
                                        amount double (10,2),
                                        co_type varchar(15) NOT NULL DEFAULT '',
                                        comm_calc varchar(15) NOT NULL DEFAULT '',
                                        type varchar(10) NOT NULL DEFAULT '',
                                        cempname varchar(255) NOT NULL DEFAULT '',
                                        person varchar(15) NOT NULL DEFAULT '',
                                        assignid int(15) NOT NULL DEFAULT 0,
                                        roletitle varchar(255) NOT NULL DEFAULT '',
                                        employee_sno int(15) NOT NULL DEFAULT 0,
                                        manage_comm_sno int(15) NOT NULL DEFAULT 0,
                                        manage_comm_period_sno int(15) NOT NULL DEFAULT 0,
                                        commlevelsno int(15) NOT NULL DEFAULT 0,
                                        commission_level varchar(255) NOT NULL DEFAULT '',
                                        startdate date DEFAULT '0000-00-00',
                                        enddate date DEFAULT '0000-00-00', 
                                        PRIMARY KEY (rowid),
                                        KEY sno (sno),
                                        KEY person (person),
                                        KEY assignid (assignid),
                                        KEY type (type),
                                        KEY employee_sno (employee_sno),
                                        KEY manage_comm_sno (manage_comm_sno),
                                        KEY manage_comm_period_sno (manage_comm_period_sno),
                                        KEY commlevelsno (commlevelsno),
                                        KEY startdate (startdate),
                                        KEY enddate (enddate)
                                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 PACK_KEYS=1 CHECKSUM=1";
	    mysql_query($tmpTableCreateSql_1, $db);

            //Temp - 1.1 - Insertion
            $tmpTableInsertSql_1_1 = "INSERT INTO rpt_tmp_InvComm_cmsn_details_individual
                                        (sno, amount, co_type, comm_calc, type, cempname, person, assignid, roletitle, employee_sno,
                                        manage_comm_sno, manage_comm_period_sno, commlevelsno, commission_level, startdate, enddate) 
                                        SELECT
                                            a.sno, 
                                            a.amount, 
                                            co_type, 
                                            comm_calc, 
                                            a.type, 
                                            el.name AS cempname, 
                                            person, 
                                            a.assignid, 
                                            cc.roletitle, 
                                            el.sno 'employee_sno',
                                            mc.sno 'manage_comm_sno',
                                            mcp.sno 'manage_comm_period_sno',
                                            cl.sno 'commlevelsno',
                                            cl.commission_level,
                                            mcp.startdate,
                                            mcp.enddate 
                                        FROM
                                            assign_commission a, 
                                            emp_list el, 
                                            manage_commission mc 
                                        LEFT JOIN
                                            manage_commission_periods mcp ON (mcp.manage_commission_id = mc.sno) 
                                        LEFT JOIN
                                            commission_levels cl ON (cl.sno = mcp.commission_level_sno), company_commission cc 
                                        WHERE
                                            a.assigntype = 'H' AND 
                                            a.type='E' AND 
                                            el.username = a.person AND 
                                            el.sno = mc.employee_id AND 
                                            cc.sno = a.roleid";
            mysql_query($tmpTableInsertSql_1_1, $db);

            //Temp - 1.2 - Insertion
            $tmpTableInsertSql_1_2 = "INSERT INTO rpt_tmp_InvComm_cmsn_details_individual
                                        (sno, amount, co_type, comm_calc, type, cempname, person, assignid, roletitle, employee_sno,
					manage_comm_sno, manage_comm_period_sno, commlevelsno, commission_level, startdate, enddate) 
					SELECT
                                            a.sno, 
                                            a.amount, 
                                            co_type, 
                                            comm_calc, 
                                            a.type, 
                                            el.name AS cempname, 
                                            person, 
                                            a.assignid, 
                                            cc.roletitle, 
                                            el.sno 'employee_sno',
                                            mc.sno 'manage_comm_sno', 
                                            mcs.sno 'manage_comm_split_sno', 
                                            cl.sno 'commlevelsno', 
                                            cl.commission_level, 
                                            mcs.startdate, 
                                            mcs.enddate 
					FROM
                                            assign_commission a, 
					    emp_list el 
					LEFT JOIN
                                            manage_commission_splits mcs ON (el.sno = mcs.employee_id)
					LEFT JOIN
                                            manage_commission mc ON (mcs.manage_commission_id = mc.sno)
					LEFT JOIN
                                            commission_levels cl ON (cl.sno = mcs.commission_level_sno), company_commission cc 
					WHERE
                                            a.assigntype = 'H' AND 
                                            a.type='E' AND 
                                            el.username = a.person AND 
                                            cc.sno = a.roleid AND 
                                            mcs.employee_id NOT IN (SELECT DISTINCT employee_id FROM manage_commission)";
            mysql_query($tmpTableInsertSql_1_2, $db);

            //Temp - 2
            $tmpTableCreateSql_2 = "CREATE TABLE IF NOT EXISTS rpt_tmp_InvComm_invactual_amount (
                                        rowid int(15) unsigned NOT NULL AUTO_INCREMENT,
                                        invoiceActualAmt double (10,2),
                                        invoice_number varchar(50) NOT NULL DEFAULT '',
                                        sno int(15) NOT NULL DEFAULT 0,
                                        PRIMARY KEY (rowid),
                                        KEY invoiceActualAmt (invoiceActualAmt),
                                        KEY invoice_number (invoice_number),
                                        KEY sno (sno)
                                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 PACK_KEYS=1 CHECKSUM=1";
            mysql_query($tmpTableCreateSql_2, $db);

            //Temp - 2.1 - Insertion
            $tmpTableInsertSql_2_1 = "INSERT INTO rpt_tmp_InvComm_invactual_amount
					(invoiceActualAmt, invoice_number, sno) 
					SELECT
                                            SUM((-1 * acctrans.amount)) AS invoiceActualAmt, 
                                            inv.invoice_number, 
                                            inv.sno 
					FROM
                                            acc_transaction acctrans, 
					    invoice inv 
					WHERE
                                            acctrans.txnId = inv.sno AND 
                                            acctrans.txnLineType NOT IN ('Expense','Charge','Credit','CompanyTax','CompanyDiscount','CustomerTax','Discount','Deposit','Custom1','Custom2','Custom3','Total') AND 
                                            acctrans.entityRefType = 'EMP' AND 
                                            acctrans.status = 'ACTIVE' 
					GROUP BY
                                            acctrans.txnId";
            mysql_query($tmpTableInsertSql_2_1, $db);

            //Temp - 3
            $tmpTableCreateSql_3    = "CREATE TABLE IF NOT EXISTS rpt_tmp_InvComm_all_cmsn_details (
                                            rowid int(15) unsigned NOT NULL AUTO_INCREMENT,
                                            invoiceNo varchar(50) NOT NULL DEFAULT '',
                                            invoice_date date DEFAULT '0000-00-00',
                                            cust_sno int(15) NOT NULL DEFAULT 0,
                                            customer varchar(255) NOT NULL DEFAULT '',
                                            assgn_name varchar(30) DEFAULT NULL,
                                            emp_sno int(15) NOT NULL DEFAULT 0,
                                            emp_uname varchar(30) DEFAULT NULL,
                                            emp_name varchar(255) DEFAULT NULL,
                                            LineType TEXT,
                                            Quantity double DEFAULT NULL,
                                            Cost varchar(62) DEFAULT NULL,
                                            payrate varchar(9) DEFAULT NULL,
                                            billrate varchar(9) DEFAULT NULL,
					    regular_payrate varchar(9) DEFAULT NULL,
					    regular_billrate varchar(9) DEFAULT NULL,
                                            burden double(8,2) DEFAULT NULL,
                                            bill_burden double(8,2) DEFAULT NULL,
                                            hrcon_sno int(15),
                                            markup double(8,2) DEFAULT NULL,
                                            margin double(8,2) DEFAULT NULL,
                                            placement_fee double(10,2) DEFAULT NULL,
                                            AssignmentStartDate varchar(10) DEFAULT NULL,
                                            Amount decimal(31,2) DEFAULT NULL,
                                            Taxable varchar(3) NOT NULL DEFAULT '',
                                            InvoiceTotal double(10,2) DEFAULT NULL,
                                            AppliedCredits double(19,2) DEFAULT NULL,
                                            feid varchar(25) NOT NULL DEFAULT '',
                                            location varchar(357) NOT NULL DEFAULT '',
                                            deptname varchar(45) DEFAULT NULL,
                                            Jotype varchar(255) DEFAULT NULL,
                                            ServiceDate varchar(23) DEFAULT NULL,
                                            HoursType varchar(255) DEFAULT NULL,
                                            AmountReceived double(19,2) DEFAULT NULL,
                                            cmsnSno int(15) NOT NULL DEFAULT 0,
                                            cmsnAmt double(8,2) DEFAULT NULL,
                                            cmsnAmtType varchar(50) DEFAULT NULL,
                                            cmsnBsdOn varchar(9) NOT NULL DEFAULT '',
                                            cmsnType varchar(1) DEFAULT NULL,
                                            CommissionPerson varchar(45) DEFAULT NULL,
                                            Roletitle varchar(255) DEFAULT NULL,
                                            cmsnLevelSno int(11) NOT NULL DEFAULT 0,
                                            commissionTier varchar(40) DEFAULT NULL,
                                            commissionTierStartDate date DEFAULT '0000-00-00',
                                            commissionTierEndDate date DEFAULT '0000-00-00',
                                            actualInvoiceAmt double(10,2) DEFAULT NULL, 
                                            pay_amount double(10,2) DEFAULT NULL, 
                                            bill_amount double(10,2) DEFAULT NULL, 
                                            pay_burden_amount double(10,2) DEFAULT NULL, 
                                            convMarginPrecToAmount double(10,2) DEFAULT NULL,
                                            quantityMarginTotal double(10,2) DEFAULT NULL, 
                                            quanMarginTotalCmsn double(10,2) DEFAULT NULL,
                                            total_pay_burden_perc double(10,2),
                                            total_pay_burden_flat double (10,2),
                                            total_bill_burden_perc double (10,2),
                                            total_bill_burden_flat double (10,2),
					    payrate_calcburden_on varchar(30) DEFAULT NULL,
					    billrate_calcburden_on varchar(30) DEFAULT NULL,
					    payburden_threshold_amt double (10,2), 
                                            PRIMARY KEY (rowid),
                                            KEY invoiceNo (invoiceNo),
                                            KEY cust_sno (cust_sno),
                                            KEY emp_sno (emp_sno),
                                            KEY assgn_name (assgn_name),
                                            KEY cmsnSno (cmsnSno),
                                            KEY cmsnLevelSno (cmsnLevelSno)
                                        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 PACK_KEYS=1 CHECKSUM=1";
            mysql_query($tmpTableCreateSql_3, $db);

            //Temp - 3.1 - Insertion
            $tmpTableCreateSql_3_1  = "INSERT INTO rpt_tmp_InvComm_all_cmsn_details 
                                            (invoiceNo, invoice_date, cust_sno, customer, assgn_name, emp_sno, emp_uname, emp_name, LineType, Quantity, Cost, payrate, billrate,regular_payrate, regular_billrate, burden, 
                                            bill_burden,hrcon_sno, markup, margin, placement_fee, AssignmentStartDate, Amount, Taxable, InvoiceTotal, AppliedCredits, feid, 
                                            location, deptname, Jotype, ServiceDate, HoursType, AmountReceived, cmsnSno, cmsnAmt, cmsnAmtType, cmsnBsdOn, cmsnType, 
                                            CommissionPerson, Roletitle, cmsnLevelSno, commissionTier, commissionTierStartDate, commissionTierEndDate, actualInvoiceAmt, 
						pay_amount, bill_amount, total_pay_burden_perc, total_pay_burden_flat, total_bill_burden_perc, total_bill_burden_flat, payrate_calcburden_on, billrate_calcburden_on,  payburden_threshold_amt)
                                        SELECT
                                            invoice.invoice_number AS invoiceNo,
                                            STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y') AS invoice_date,
                                            staffacc_cinfo.sno AS cust_sno,
                                            staffacc_cinfo.cname AS customer,
                                            timesheet.assid AS assgn_name,
                                            hgTime.sno AS emp_sno,
                                            hgTime.username AS emp_uname, 
                                            CONCAT(hgTime.fname,' ',hgTime.lname) AS emp_name,
                                            'Timesheet' AS LineType,
                                            ROUND(SUM(timesheet.hours),2) AS Quantity,
                                            CONCAT(invoice_multiplerates.rate,'(',invoice_multiplerates.period,')') AS Cost,
					    IF(hrcon_jobs.mdate > invoice.stime, invComm_getAsgnRateValue(hrcon_jobs.pusername,invoice.stime,multiplerates_assignment.ratemasterid,multiplerates_assignment.ratetype), multiplerates_assignment.rate) AS payrate,
					    IF(hrcon_jobs.mdate > invoice.stime, invComm_getAsgnRateValue(hrcon_jobs.pusername,invoice.stime,mulbill.ratemasterid,mulbill.ratetype), mulbill.rate) AS billrate,
					    IF(hrcon_jobs.mdate > invoice.stime, invComm_getAsgnRateValue(hrcon_jobs.pusername,invoice.stime,'rate1','payrate'), hrcon_jobs.pamount) AS regular_payrate,
					    IF(hrcon_jobs.mdate > invoice.stime, invComm_getAsgnRateValue(hrcon_jobs.pusername,invoice.stime,'rate1','billrate'), hrcon_jobs.bamount) AS regular_billrate,
                                            hrcon_jobs.burden,
                                            hrcon_jobs.bill_burden AS bill_burden,
                                            hrcon_jobs.sno,
                                            hrcon_jobs.markup AS markup,
                                            hrcon_jobs.margin,
                                            hrcon_jobs.placement_fee AS placement_fee,
                                            hrcon_jobs.s_date AS AssignmentStartDate,
                                            (-1 * acc_transaction.amount) AS Amount,
                                            IF(timesheet.tax =  'yes',  'Y',  'N') AS Taxable,
                                            inv.total AS InvoiceTotal,
                                            CreditUsed.Credit AS AppliedCredits,
                                            contact_manage.feid AS feid,
                                            CONCAT(contact_manage.heading,',',contact_manage.city,',',contact_manage.state) AS location,
                                            dept.deptname AS deptname,
                                            manage.name AS Jotype,
                                            CONCAT_WS(' - ',DATE_FORMAT(par_timesheet.sdate,'%m/%d/%Y'),DATE_FORMAT(par_timesheet.edate,'%m/%d/%Y')) AS ServiceDate,
                                            multiplerates_master.name AS HoursType,
                                            (SELECT SUM(ar.amount) FROM acc_reg ar WHERE ar.inv_bill_lineid = invoice.sno AND ar.type = 'PMT' AND ar.status = 'ER') AS AmountReceived, 
                                            cmsn.sno AS cmsnSno,
                                            IF(cmsn.amount!='',cmsn.amount,0.0) AS  cmsnAmt,
                                            cmsn.co_type AS cmsnAmtType,
                                            IF(cmsn.comm_calc='P','Placement',IF(cmsn.comm_calc='PR','Pay Rate',IF(cmsn.comm_calc='BR','Bill Rate',IF(cmsn.comm_calc='MN','Margin',IF(cmsn.comm_calc='MP','Markup',''))))) AS 'cmsnBsdOn',
                                            cmsn.type AS cmsnType,
                                            cmsn.cempname  AS CommissionPerson,
                                            cmsn.roletitle AS  Roletitle,
                                            cmsn.commlevelsno AS cmsnLevelSno, 
                                            cmsn.commission_level AS commissionTier,
                                            cmsn.startdate AS commissionTierStartDate,
                                            cmsn.enddate AS commissionTierEndDate, 
                                            tinvact.invoiceActualAmt AS actualInvoiceAmt, 
                                            IF(hrcon_jobs.mdate > invoice.stime, (SUM(timesheet.hours) * invComm_getAsgnRateValue(hrcon_jobs.pusername,invoice.stime,multiplerates_assignment.ratemasterid,multiplerates_assignment.ratetype)), (SUM(timesheet.hours) * multiplerates_assignment.rate)) AS pay_amount, 
                                            IF(hrcon_jobs.mdate > invoice.stime, (SUM(timesheet.hours) * invComm_getAsgnRateValue(hrcon_jobs.pusername,invoice.stime,mulbill.ratemasterid,mulbill.ratetype)), (SUM(timesheet.hours) * mulbill.rate)) AS bill_amount,
                                            invComm_getPayBurdenPerc(hrcon_jobs.sno,multiplerates_assignment.ratemasterid),
                                            invComm_getPayBurdenFlat(hrcon_jobs.sno,multiplerates_assignment.ratemasterid),
                                            invComm_getBillBurdenPerc(hrcon_jobs.sno,mulbill.ratemasterid),
					    invComm_getBillBurdenFlat(hrcon_jobs.sno,mulbill.ratemasterid),
					    invComm_getPercCalcBurdenOn(hrcon_jobs.sno,'payrate'),
					    invComm_getPercCalcBurdenOn(hrcon_jobs.sno,'billrate'),
					    invComm_getPayBurdenThreshold(hrcon_jobs.sno,mulbill.ratemasterid) 
                                        FROM
                                            acc_transaction 
                                        JOIN
                                            timesheet_hours timesheet ON (timesheet.parid = acc_transaction.txnLineId AND timesheet.hourstype = acc_transaction.txnLineType AND timesheet.assid = acc_transaction.refNumber) 
                                        JOIN
                                            invoice ON (invoice.sno = acc_transaction.txnId AND timesheet.billable = invoice.sno) 
                                        JOIN
                                            staffacc_cinfo ON (invoice.client_id = staffacc_cinfo.sno AND staffacc_cinfo.type IN('CUST','BOTH'))
                                        JOIN
                                            Client_Accounts ON (Client_Accounts.typeid = staffacc_cinfo.sno AND Client_Accounts.clienttype =  'CUST' AND Client_Accounts.status =  'active') 
                                        LEFT JOIN
                                            department AS dept ON (dept.sno = Client_Accounts.deptid)
                                        JOIN
                                            contact_manage ON (contact_manage.serial_no = Client_Accounts.loc_id)
                                        JOIN
                                            hrcon_jobs ON (hrcon_jobs.pusername = timesheet.assid AND hrcon_jobs.ustatus IN ('active','cancel','closed'))
                                        LEFT JOIN
                                            (SELECT ROUND( SUM( IFNULL( credit_memo_trans.used_amount,  '0' ) ) , 2 ) AS Credit, inv_bill_sno,TYPE FROM credit_memo_trans GROUP BY inv_bill_sno,TYPE) CreditUsed ON ( CreditUsed.inv_bill_sno = invoice.sno AND CreditUsed.type =  'invoice' )
                                        LEFT JOIN
                                            invoice inv ON (inv.sno = invoice.sno) 
                                        LEFT JOIN
                                            rpt_tmp_InvComm_invactual_amount tinvact ON (tinvact.sno = invoice.sno) 
                                        LEFT JOIN
                                            multiplerates_assignment ON (multiplerates_assignment.asgnid = hrcon_jobs.sno AND multiplerates_assignment.asgn_mode = 'hrcon' AND multiplerates_assignment.status = 'ACTIVE' AND multiplerates_assignment.ratemasterid = timesheet.hourstype AND multiplerates_assignment.ratetype='payrate')
                                        LEFT JOIN
                                            multiplerates_master ON multiplerates_master.rateid = multiplerates_assignment.ratemasterid
                                        LEFT JOIN
                                            multiplerates_assignment mulbill ON (mulbill.asgnid = hrcon_jobs.sno AND mulbill.asgn_mode = 'hrcon' AND multiplerates_assignment.status = 'ACTIVE' AND mulbill.ratemasterid = timesheet.hourstype AND mulbill.ratetype='billrate') 
                                        LEFT JOIN
                                            invoice_multiplerates ON (invoice_multiplerates.pusername = timesheet.assid AND timesheet.billable = invoice_multiplerates.invid AND invoice_multiplerates.rateid = timesheet.hourstype)
                                        LEFT JOIN
                                            par_timesheet ON( par_timesheet.sno = timesheet.parid)
                                        LEFT JOIN
                                            hrcon_general hgTime ON (timesheet.username = hgTime.username AND hgTime.ustatus = 'active') 
                                        LEFT JOIN
                                            manage ON hrcon_jobs.jotype = manage.sno 
                                        LEFT JOIN
                                            rpt_tmp_InvComm_cmsn_details_individual cmsn ON (cmsn.assignid = hrcon_jobs.sno AND (IF(cmsn.enddate = '0000-00-00' AND cmsn.startdate != '0000-00-00', DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') BETWEEN cmsn.startdate AND CURDATE(), DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') BETWEEN cmsn.startdate AND cmsn.enddate)))
                                        WHERE
                                            acc_transaction.txnType = 'Invoice'
                                            AND acc_transaction.status = 'ACTIVE' 
                                            AND acc_transaction.txnLineType NOT IN ('Expense','Charge','Credit','CompanyTax','CompanyDiscount','CustomerTax','Discount','Deposit','Custom1','Custom2','Custom3','Total')
                                            AND timesheet.type != 'EARN'  
                                            AND IF(IFNULL(invoice.invoice_date,'')!='',(DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') >= '".$invoiceFrDate."'),1)
                                            AND IF(IFNULL(invoice.invoice_date,'')!='',(DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') <= '".$invoiceToDate."'),1) 
                                        GROUP BY
                                            timesheet.username,
                                            timesheet.parid,
                                            timesheet.assid,
                                            cmsn.sno,
                                            timesheet.hourstype 
                                    UNION ALL
                                        SELECT
                                            invoice.invoice_number AS invoiceNo,
                                            STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y') AS invoice_date,
                                            staffacc_cinfo.sno AS cust_sno,
                                            staffacc_cinfo.cname AS customer,
                                            timesheet.assid AS assgn_name,
                                            hgTime.sno AS emp_sno,
                                            hgTime.username AS emp_uname,
                                            CONCAT(hgTime.fname,' ',hgTime.lname) AS emp_name,
                                            'Per Diem' AS LineType,
                                            IF(timesheet.edate='0000-00-00',COUNT(DISTINCT(timesheet.sdate)),DATEDIFF(timesheet.edate,timesheet.sdate)+1) AS Quantity,
                                            CONCAT(invoice_multiplerates.rate,'(',invoice_multiplerates.period,')') AS Cost,
                                            hrcon_jobs.diem_total AS payrate,
                                            IF(hrcon_jobs.diem_billable='Y',hrcon_jobs.diem_total,'') AS billrate,
					    hrcon_jobs.diem_total AS regular_payrate,
					    IF(hrcon_jobs.diem_billable='Y',hrcon_jobs.diem_total,'') AS regular_billrate,
                                            hrcon_jobs.burden,
                                            hrcon_jobs.bill_burden,
                                            hrcon_jobs.sno,
                                            hrcon_jobs.markup,
                                            hrcon_jobs.margin,
                                            hrcon_jobs.placement_fee,
                                            hrcon_jobs.s_date AS AssignmentStartDate,
                                            (IF(timesheet.edate='0000-00-00',COUNT(DISTINCT(timesheet.sdate)),DATEDIFF(timesheet.edate,timesheet.sdate)+1) * IF(invoice_multiplerates.period='YEAR',ROUND((CAST(invoice_multiplerates.rate AS DECIMAL(10,6))/(8*261)),2),IF(invoice_multiplerates.period='MONTH',ROUND((CAST(invoice_multiplerates.rate AS DECIMAL(10,6))/(8*(261/12))),2),IF(invoice_multiplerates.period='WEEK', ROUND((CAST(invoice_multiplerates.rate AS DECIMAL(10,6))/(8*5)),2),IF(invoice_multiplerates.period='HOUR',ROUND((CAST(invoice_multiplerates.rate AS DECIMAL(10,6))* 8),2),ROUND(CAST(invoice_multiplerates.rate AS DECIMAL(10,6)),2)))))) AS Amount,
                                            IF(timesheet.tax =  'yes',  'Y',  'N') AS Taxable,
                                            inv.total InvoiceTotal,
                                            CreditUsed.Credit AS AppliedCredits,
                                            contact_manage.feid,
                                            CONCAT(contact_manage.heading,',',contact_manage.city,',',contact_manage.state) AS location,
                                            dept.deptname,
                                            manage.name AS Jotype,
                                            CONCAT_WS(' - ',DATE_FORMAT(par_timesheet.sdate,'%m/%d/%Y'),DATE_FORMAT(par_timesheet.edate,'%m/%d/%Y')) AS ServiceDate,
                                            'PerDiem' AS HoursType,
                                            (SELECT SUM(ar.amount) FROM acc_reg ar WHERE ar.inv_bill_lineid = invoice.sno AND ar.type = 'PMT' AND ar.status = 'ER') AS AmountReceived, 
                                            cmsn.sno cmsnSno,
                                            IF(cmsn.amount!='',cmsn.amount,0.0) cmsnAmt,
                                            cmsn.co_type cmsnAmtType,
                                            IF(cmsn.comm_calc='P','Placement',IF(cmsn.comm_calc='PR','Pay Rate',IF(cmsn.comm_calc='BR','Bill Rate',IF(cmsn.comm_calc='MN','Margin',IF(cmsn.comm_calc='MP','Markup',''))))) AS 'cmsnBsdOn',
                                            cmsn.type cmsnType,
                                            cmsn.cempname CommissionPerson,
                                            cmsn.roletitle AS  Roletitle,
                                            cmsn.commlevelsno AS cmsnLevelSno, 
                                            cmsn.commission_level AS commissionTier,
                                            cmsn.startdate AS commissionTierStartDate,
                                            cmsn.enddate AS commissionTierEndDate, 
                                            tinvact.invoiceActualAmt AS actualInvoiceAmt,
                                            (IF(timesheet.edate='0000-00-00',COUNT(DISTINCT(timesheet.sdate)),DATEDIFF(timesheet.edate,timesheet.sdate)+1) * hrcon_jobs.diem_total) AS pay_amount,
                                            (IF(timesheet.edate='0000-00-00',COUNT(DISTINCT(timesheet.sdate)),DATEDIFF(timesheet.edate,timesheet.sdate)+1) * (IF(hrcon_jobs.diem_billable='Y',hrcon_jobs.diem_total,0))) AS bill_amount ,
                                            invComm_getPayBurdenPerc(hrcon_jobs.sno,''),
                                            invComm_getPayBurdenFlat(hrcon_jobs.sno,''),
                                            invComm_getBillBurdenPerc(hrcon_jobs.sno,''),
					    invComm_getBillBurdenFlat(hrcon_jobs.sno,''),
					    invComm_getPercCalcBurdenOn(hrcon_jobs.sno,'payrate'),
					    invComm_getPercCalcBurdenOn(hrcon_jobs.sno,'billrate'),
					    invComm_getPayBurdenThreshold(hrcon_jobs.sno,'')
                                        FROM
                                            acc_transaction 
                                        JOIN
                                            timesheet_hours timesheet ON (timesheet.parid = acc_transaction.txnLineId AND timesheet.hourstype = acc_transaction.txnLineType AND timesheet.assid = acc_transaction.refNumber) 
                                        JOIN
                                            invoice ON (invoice.sno = acc_transaction.txnId AND timesheet.billable = invoice.sno) 
                                        JOIN
                                            staffacc_cinfo ON (invoice.client_id = staffacc_cinfo.sno AND staffacc_cinfo.type IN('CUST','BOTH'))
                                        JOIN
                                            Client_Accounts ON (Client_Accounts.typeid = staffacc_cinfo.sno AND Client_Accounts.clienttype =  'CUST' AND Client_Accounts.status =  'active') 
                                        LEFT JOIN
                                            department AS dept ON (dept.sno = Client_Accounts.deptid) 
                                        JOIN
                                            contact_manage ON (contact_manage.serial_no = Client_Accounts.loc_id) 
                                        JOIN
                                            hrcon_jobs ON (hrcon_jobs.pusername = timesheet.assid AND hrcon_jobs.ustatus IN ('active','cancel','closed')) 
                                        LEFT JOIN
                                            (SELECT ROUND( SUM( IFNULL( credit_memo_trans.used_amount,  '0' ) ) , 2 ) AS Credit, inv_bill_sno,TYPE FROM credit_memo_trans GROUP BY inv_bill_sno,TYPE) CreditUsed ON ( CreditUsed.inv_bill_sno = invoice.sno AND CreditUsed.type =  'invoice' )
                                        LEFT JOIN
                                            invoice inv ON (inv.sno = invoice.sno) 
                                        LEFT JOIN
                                            rpt_tmp_InvComm_invactual_amount tinvact ON (tinvact.sno = invoice.sno) 
                                        LEFT JOIN
                                            invoice_multiplerates ON (invoice_multiplerates.pusername = timesheet.assid AND timesheet.billable = invoice_multiplerates.invid AND invoice_multiplerates.rateid = 'rate4')
                                        LEFT JOIN
                                            par_timesheet ON (par_timesheet.sno = timesheet.parid)
                                        LEFT JOIN
                                            hrcon_general hgTime ON (timesheet.username = hgTime.username AND hgTime.ustatus = 'active')  
                                        LEFT JOIN
                                            manage ON hrcon_jobs.jotype = manage.sno
                                        LEFT JOIN
                                            rpt_tmp_InvComm_cmsn_details_individual cmsn ON (cmsn.assignid = hrcon_jobs.sno AND (IF(cmsn.enddate = '0000-00-00' AND cmsn.startdate != '0000-00-00', DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') BETWEEN cmsn.startdate AND CURDATE(), DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') BETWEEN cmsn.startdate AND cmsn.enddate)))
                                        WHERE
                                            acc_transaction.txnType = 'Invoice'
                                            AND acc_transaction.status = 'ACTIVE'
                                            AND acc_transaction.txnLineType NOT IN ('Expense','Charge','Credit','CompanyTax','CompanyDiscount','CustomerTax','Discount','Deposit','Custom1','Custom2','Custom3','Total')
                                            AND timesheet.billable != ''
                                            AND timesheet.type != 'EARN'
                                            AND invoice.inv_tmp_selected  = 'NEW' 
                                            AND IF(IFNULL(invoice.invoice_date,'')!='',(DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') >= '".$invoiceFrDate."'),1)
                                            AND IF(IFNULL(invoice.invoice_date,'')!='',(DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') <= '".$invoiceToDate."'),1) 
                                        GROUP BY
                                            timesheet.username,
                                            timesheet.parid,
                                            timesheet.assid,
                                            cmsn.sno
                                        HAVING
                                            Amount > 0
                                    UNION ALL 
                                        SELECT
                                            invoice.invoice_number AS invoiceNo,
                                            STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y') AS invoice_date,
                                            staffacc_cinfo.sno AS cust_sno,
                                            staffacc_cinfo.cname AS customer,
                                            IF(acc_transaction.txnLineType = 'Expense', expense.assid,hj.pusername) AS assgn_name,
                                            IF(acc_transaction.txnLineType = 'Expense',hgExpense.sno,hgTime.sno) AS emp_sno,
                                            IF(acc_transaction.txnLineType = 'Expense',hgExpense.username,hgTime.username) AS emp_uname,
                                            IFNULL(IF(acc_transaction.txnLineType = 'Expense',CONCAT(hgExpense.fname,' ',hgExpense.lname),CONCAT(hgTime.fname,' ',hgTime.lname)),acc_transaction.memo) AS emp_name,
                                            IF(acc_transaction.txnLineType IN ('Charge','Credit'), CONCAT(credit_charge.employee_name,'(',acc_transaction.txnLineType,')'), IF(acc_transaction.txnLineType ='CompanyTax', CONCAT(invoice_taxes.taxtype,'(',acc_transaction.txnLineType,')'), IF(acc_transaction.txnLineType ='CompanyDiscount', CONCAT(invoice_discounts.discname,'(',acc_transaction.txnLineType,')'), acc_transaction.txnLineType))) AS LineType,
                                            IF(acc_transaction.txnLineType = 'Expense', expense.quantity, 0.00) AS Quantity,
                                            IF(acc_transaction.txnLineType = 'Expense',expense.unitcost ,'----') AS Cost,
                                            '' payrate,
                                            '' billrate,
					    '' regular_payrate,
					    '' regular_billrate,
                                            hj.burden,
                                            hj.bill_burden,
                                            hj.sno,
                                            hj.markup,
                                            hj.margin,
                                            hj.placement_fee,
                                            hj.s_date AS AssignmentStartDate,
                                            (-1 * acc_transaction.amount) AS Amount,
                                            IF(acc_transaction.txnLineType = 'Expense', IF(expense.tax = 'yes', 'Y', 'N'), IF(acc_transaction.txnLineType IN ('Charge','Credit'), IF(credit_charge.tax = 'yes', 'Y', 'N'), IF(acc_transaction.txnLineType ='CompanyTax', IF(invoice_taxes.tax = 'yes', 'Y', 'N'), IF(acc_transaction.txnLineType ='CompanyDiscount', IF(invoice_discounts.taxmode = 'bt', 'Y', 'N'),'---')))) AS Taxable,
                                            inv.total InvoiceTotal,
                                            CreditUsed.Credit AS AppliedCredits,
                                            contact_manage.feid,
                                            CONCAT(contact_manage.heading,',',contact_manage.city,',',contact_manage.state) AS location,
                                            dept.deptname,
                                            manage.name AS Jotype,
                                            IF(acc_transaction.txnLineType = 'Expense', DATE_FORMAT(expense.edate,'%m/%d/%Y'), IF(acc_transaction.txnLineType IN ('Charge','Credit'), IFNULL((DATE_FORMAT(STR_TO_DATE(credit_charge.ser_date,'%m/%d/%Y'),'%m/%d/%Y')),(DATE_FORMAT(STR_TO_DATE(credit_charge.ser_date,'%Y/%m/%d'),'%m/%d/%Y'))), DATE_FORMAT(acc_transaction.txnDate,'%m/%d/%Y'))) AS ServiceDate,
                                            et.title AS HoursType,
                                            (SELECT SUM(ar.amount) FROM acc_reg ar WHERE ar.inv_bill_lineid = invoice.sno AND ar.type = 'PMT' AND ar.status = 'ER') AS AmountReceived, 
                                            cmsn.sno cmsnSno,
                                            IF(cmsn.amount!='',cmsn.amount,0.0) cmsnAmt,
                                            cmsn.co_type cmsnAmtType,
                                            IF(cmsn.comm_calc='P','Placement',IF(cmsn.comm_calc='PR','Pay Rate',IF(cmsn.comm_calc='BR','Bill Rate',IF(cmsn.comm_calc='MN','Margin',IF(cmsn.comm_calc='MP','Markup',''))))) AS 'cmsnBsdOn',
                                            cmsn.type cmsnType,
                                            cmsn.cempname CommissionPerson,
                                            cmsn.roletitle AS  Roletitle,
                                            cmsn.commlevelsno AS cmsnLevelSno, 
                                            cmsn.commission_level AS commissionTier,
                                            cmsn.startdate AS commissionTierStartDate,
                                            cmsn.enddate AS commissionTierEndDate, 
                                            tinvact.invoiceActualAmt AS actualInvoiceAmt, 
                                            ((IF(acc_transaction.txnLineType = 'Expense', expense.quantity, 0.00)) * 0) AS pay_amount, 
                                            IF(acc_transaction.txnLineType = 'Expense', (expense.quantity * 0), hj.placement_fee) AS bill_amount,
                                            invComm_getPayBurdenPerc(hj.sno,''),
                                            invComm_getPayBurdenFlat(hj.sno,''),
                                            invComm_getBillBurdenPerc(hj.sno,''),
                                            invComm_getBillBurdenFlat(hj.sno,''),
					    invComm_getPercCalcBurdenOn(hj.sno,'payrate'),
					    invComm_getPercCalcBurdenOn(hj.sno,'billrate'),
					    invComm_getPayBurdenThreshold(hj.sno,'') 
					FROM
                                            staffacc_cinfo, Client_Accounts
					LEFT JOIN
                                            department AS dept ON (dept.sno = Client_Accounts.deptid) , contact_manage, invoice
					LEFT JOIN
                                            hrcon_jobs hj ON (hj.assg_status = invoice.sno)
					LEFT JOIN
                                            manage ON hj.jotype = manage.sno
					LEFT JOIN
                                            hrcon_general hgTime ON (hgTime.username=hj.username AND hgTime.ustatus = 'active')
					LEFT JOIN
                                            rpt_tmp_InvComm_cmsn_details_individual cmsn ON (cmsn.assignid = hj.sno AND (IF(cmsn.enddate = '0000-00-00' AND cmsn.startdate != '0000-00-00', DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') BETWEEN cmsn.startdate AND CURDATE(), DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') BETWEEN cmsn.startdate AND cmsn.enddate)))
					LEFT JOIN
                                            (SELECT ROUND( SUM( IFNULL( credit_memo_trans.used_amount,  '0' ) ) , 2 ) AS Credit, inv_bill_sno,TYPE FROM credit_memo_trans GROUP BY inv_bill_sno,TYPE) CreditUsed ON ( CreditUsed.inv_bill_sno = invoice.sno AND CreditUsed.type =  'invoice' )
					LEFT JOIN
                                            invoice inv ON (inv.sno = invoice.sno) 
					LEFT JOIN
                                            rpt_tmp_InvComm_invactual_amount tinvact ON (tinvact.sno = invoice.sno),
                                            acc_transaction
					LEFT JOIN
                                            expense ON (expense.sno = acc_transaction.txnLineId AND acc_transaction.txnLineType = 'Expense')
					LEFT JOIN
                                            exp_type et ON et.sno = expense.expid
					LEFT JOIN
                                            par_expense ON (expense.parid = par_expense.sno)
					LEFT JOIN
                                            hrcon_general hgExpense ON (par_expense.username = hgExpense.username AND hgExpense.ustatus = 'active')
					LEFT JOIN
                                            credit_charge ON (credit_charge.sno = acc_transaction.txnLineId AND acc_transaction.txnLineType IN ('Charge','Credit'))
					LEFT JOIN
                                            invoice_taxes ON (invoice_taxes.sno = acc_transaction.txnLineId AND acc_transaction.txnLineType = 'CompanyTax')
					LEFT JOIN
                                            invoice_discounts ON (invoice_discounts.sno = acc_transaction.txnLineId AND acc_transaction.txnLineType = 'CompanyDiscount') 
					WHERE
                                            acc_transaction.txnType = 'Invoice'
                                            AND acc_transaction.txnLineType IN ('Expense','Charge','Credit','CompanyTax','CompanyDiscount','CustomerTax','Discount','Deposit','Custom1','Custom2','Custom3')
                                            AND acc_transaction.txnLineType != 'Total'
                                            AND acc_transaction.status = 'ACTIVE'
                                            AND invoice.sno = acc_transaction.txnId
                                            AND invoice.client_id = staffacc_cinfo.sno
                                            AND contact_manage.serial_no = Client_Accounts.loc_id
                                            AND Client_Accounts.typeid = staffacc_cinfo.sno
                                            AND Client_Accounts.clienttype =  'CUST' AND staffacc_cinfo.type IN('CUST','BOTH')
                                            AND Client_Accounts.status =  'active' 
                                            AND IF(IFNULL(invoice.invoice_date,'')!='',(DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') >= '".$invoiceFrDate."'),1)
                                            AND IF(IFNULL(invoice.invoice_date,'')!='',(DATE_FORMAT(STR_TO_DATE(invoice.invoice_date,'%m/%d/%Y'),'%Y-%m-%d') <= '".$invoiceToDate."'),1)";
            mysql_query($tmpTableCreateSql_3_1, $db);

            //Temp - 3.2 - Updating
            $tmpTableCreateSql_3_2  = "UPDATE rpt_tmp_InvComm_all_cmsn_details SET 
						pay_burden_amount = IF(HoursType != 'PerDiem', ROUND((((total_pay_burden_perc/100) * IF(payrate_calcburden_on='Regular',regular_payrate,payrate) * Quantity) + (total_pay_burden_flat * Quantity)), 2),'0.00'),
						quantityMarginTotal = invComm_getQuanMarginTotalAmount(Quantity, IF(payrate_calcburden_on='Regular',regular_payrate,payrate), IF(billrate_calcburden_on='Regular',regular_billrate,billrate), total_pay_burden_perc, total_bill_burden_perc, total_pay_burden_flat, total_bill_burden_flat)";
            mysql_query($tmpTableCreateSql_3_2, $db);

            //Step 1 End

            /*
             * Step - 2 Start
             * Temp - 4
             */
            $tmpTableCreateSql_4 = "CREATE TABLE IF NOT EXISTS rpt_tmp_InvComm_gp_cmsntiers_calc (
                                        rowid int(15) unsigned NOT NULL AUTO_INCREMENT,
                                        deptname varchar(255) NOT NULL DEFAULT '',
                                        assgn_number varchar(255) NOT NULL DEFAULT '',
                                        invoiceNo varchar(50) NOT NULL DEFAULT '',
                                        invoice_date date DEFAULT '0000-00-00',
                                        InvoiceTotal double (10,2), 
                                        customer varchar(255) NOT NULL DEFAULT '', 
                                        emp_name varchar(255) NOT NULL DEFAULT '',
                                        Quantity int(15) NOT NULL DEFAULT 0, 
                                        payrate int(15) NOT NULL DEFAULT 0, 
                                        billrate int(15) NOT NULL DEFAULT 0, 
                                        pay_amount double (10,2), 
                                        bill_amount double (10,2), 
                                        burden double (10,2), 
                                        pay_burden_amount double (10,2), 
                                        non_billed_paid_expense double (10,2),  
                                        cmsnAmt double (10,2), 
                                        CommissionPerson varchar(255) NOT NULL DEFAULT '',  
                                        Roletitle varchar(255) NOT NULL DEFAULT '',  
                                        commissionTier varchar(255) NOT NULL DEFAULT '',  
                                        cmsnLevelSno int(15) NOT NULL DEFAULT 0,
                                        quantityMarginTotal double (10,2),
                                        cmsnSno int(15) NOT NULL DEFAULT 0,
                                        PRIMARY KEY (rowid),
                                        KEY assgn_number (assgn_number),
                                        KEY invoiceNo (invoiceNo),
                                        KEY invoice_date (invoice_date),
                                        KEY cmsnLevelSno (cmsnLevelSno),
                                        KEY cmsnSno (cmsnSno),
                                        KEY commissionTier (commissionTier) 
                                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 PACK_KEYS=1 CHECKSUM=1";
            mysql_query($tmpTableCreateSql_4, $db);

            //Temp - 4.1 - Insertion
            $tmpTableInsertSql_4_1 = "INSERT INTO rpt_tmp_InvComm_gp_cmsntiers_calc 
                                        (deptname, assgn_number, invoiceNo, invoice_date, InvoiceTotal, customer, 
					emp_name, Quantity, payrate, billrate, pay_amount, bill_amount, burden, 
					pay_burden_amount, non_billed_paid_expense, cmsnAmt, CommissionPerson, 
					Roletitle, commissionTier, cmsnLevelSno, quantityMarginTotal, cmsnSno) 
                                        SELECT
                                            deptname, 
                                            GROUP_CONCAT(DISTINCT assgn_name) AS assgn_number, 
                                            invoiceNo, 
                                            invoice_date, 
                                            InvoiceTotal, 
                                            customer, 
                                            emp_name, 
                                            Quantity, 
					    IF(payrate_calcburden_on='Regular',regular_payrate,payrate) AS payrate, 
					    IF(billrate_calcburden_on='Regular',regular_billrate,billrate) AS billrate, 
                                            SUM(pay_amount) AS pay_amount, 
                                            SUM(bill_amount) AS bill_amount, 
                                            burden, 
						IF(MAX(payburden_threshold_amt) != '0.00', (IF((SUM(pay_burden_amount) > MAX(payburden_threshold_amt)),MAX(payburden_threshold_amt),SUM(pay_burden_amount))), SUM(pay_burden_amount)) AS pay_burden_amount,
					    invComm_getPayAdjExpense(cust_sno,emp_uname,
					    DATE_FORMAT(STR_TO_DATE(SUBSTRING_INDEX(ServiceDate,'-',1), '%m/%d/%Y'), '%Y-%m-%d'),
					    DATE_FORMAT(STR_TO_DATE(SUBSTRING_INDEX(ServiceDate,'-',-1), '%m/%d/%Y'), '%Y-%m-%d'),'') AS 'non_billed_paid_expense', 
                                            cmsnAmt, 
                                            CommissionPerson, 
                                            Roletitle, 
                                            commissionTier, 
                                            cmsnLevelSno,
                                            quantityMarginTotal,
                                            cmsnSno 
                                        FROM
					    rpt_tmp_InvComm_all_cmsn_details 
					WHERE
                                            IF(IFNULL(invoice_date,'')!='',(invoice_date >= '".$invoiceFrDate."'),1)
                                            AND IF(IFNULL(invoice_date,'')!='',(invoice_date <= '".$invoiceToDate."'),1) 
					GROUP BY
                                            deptname, 
                                            cust_sno, 
                                            emp_sno, 
                                            invoiceNo, 
                                            cmsnSno";
            mysql_query($tmpTableInsertSql_4_1, $db);
            
            //Temp Delete - 1
            $tmpTableDelSql_1   = "DELETE FROM tmp_InvComm_Revenue_trans_details WHERE DATE_FORMAT(invoice_date, '%Y-%m-%d') >= '".$invoiceFrDate."' AND DATE_FORMAT(invoice_date, '%Y-%m-%d') <= '".$invoiceToDate."'";
            mysql_query($tmpTableDelSql_1, $db);

            //Temp Select - 1
            $tmpTableSelSql_1   = "SELECT
                                        tct.deptname AS 'dept_name',
                                        tct.CommissionPerson AS 'comm_person',
                                        tct.Roletitle AS 'role_title',
                                        tct.emp_name AS 'emp_name',
                                        tct.assgn_number AS 'assignment_id',
                                        tct.customer AS 'customer_name',
                                        tct.invoice_date AS 'invoice_date',
                                        tct.invoiceNo AS 'invoice_number',
                                        tct.InvoiceTotal AS 'invoice_total',
                                        ROUND((tct.bill_amount-tct.pay_amount), 2) AS 'before_burden_gp_amount',
                                        ROUND((tct.bill_amount-tct.pay_amount-tct.pay_burden_amount-tct.non_billed_paid_expense), 2) AS 'gp_amount',
                                        tct.cmsnAmt AS 'perc_apply',
                                        ROUND(((tct.bill_amount-tct.pay_amount) * (tct.cmsnAmt / 100)), 2) AS 'before_burden_gp_available',
                                        ROUND(((tct.bill_amount-tct.pay_amount-tct.pay_burden_amount-tct.non_billed_paid_expense) * (tct.cmsnAmt / 100)), 2) AS 'gp_avail_comm',
                                        ROUND(((tct.bill_amount-tct.pay_amount-tct.pay_burden_amount-tct.non_billed_paid_expense) * (tct.cmsnAmt / 100)), 2) AS 'accumulated_gp_avail_comm',
                                        ct.commission_level_sno 
                                    FROM
                                        rpt_tmp_InvComm_gp_cmsntiers_calc tct, 
                                        commission_tiers ct 
                                    WHERE
                                        tct.cmsnLevelSno = ct.commission_level_sno 
                                    GROUP BY
                                        tct.deptname, 
                                        tct.customer, 
                                        tct.emp_name, 
                                        tct.invoiceNo,
                                        tct.cmsnSno 
                                    ORDER BY
                                        tct.CommissionPerson,
                                        tct.invoice_date,
                                        tct.invoiceNo";
            $tmpTableSelRs_1    = mysql_query($tmpTableSelSql_1, $db);

            if(mysql_num_rows($tmpTableSelRs_1) > 0)
            {
                $gpAccumTotalArray  = array();
    
                while($tmpTableSelRw_1 = mysql_fetch_array($tmpTableSelRs_1))
                {
                    $gpAccumKey = $tmpTableSelRw_1["comm_person"];
    
                    if(isset($gpAccumTotalArray[$gpAccumKey]))
                    {
                        $gpAccumTotalArray[$gpAccumKey] = $gpAccumTotalArray[$gpAccumKey] + $tmpTableSelRw_1["gp_avail_comm"];
                    }
                    else
                    {
                        $gpAccumTotalArray[$gpAccumKey] = $tmpTableSelRw_1["gp_avail_comm"];
                    }
    
                    //Temp Select - 2
                    $minMaxSelSql_2	= "SELECT ct.commission AS 'commVal', cl.amount_mode AS 'Type'
                                            FROM
                                                commission_tiers ct 
                                            LEFT JOIN
                                                commission_levels cl ON (cl.sno = ct.commission_level_sno AND cl.status = 'active')  
                                            WHERE
                                                ct.commission_level_sno = '".$tmpTableSelRw_1['commission_level_sno']."'
                                                AND IF(ct.maximum = 0 AND ct.minimum != 0, (".$gpAccumTotalArray[$gpAccumKey]." BETWEEN ct.minimum AND '9999999.99'), (".$gpAccumTotalArray[$gpAccumKey]." BETWEEN ct.minimum AND ct.maximum))";
                    $minMaxSelRs_2	= mysql_query($minMaxSelSql_2, $db);
                    $minMaxSelRw_2	= mysql_fetch_array($minMaxSelRs_2);
    
                    if($minMaxSelRw_2['Type'] == 'PER')
                    {			
                        $gpAccuCommAmount   = round((($tmpTableSelRw_1["gp_avail_comm"] * $minMaxSelRw_2['commVal']) / 100), 2);
                        $gpCommPerFlat      = $minMaxSelRw_2['commVal'];
                    }
                    else
                    {
                        $gpAccuCommAmount   = $minMaxSelRw_2['commVal'];
                        $gpCommPerFlat      = round((($minMaxSelRw_2['commVal'] / $gpAccumTotalArray[$gpAccumKey]) * 100), 2);
                    }
    
                    //Temp - 5.1 - Insertion
                    $tmpTableInsSql_5_1  = "INSERT INTO tmp_InvComm_Revenue_trans_details
                                                (dept_name,
                                                comm_person,
                                                role_title,
                                                emp_name,
                                                assignment_id,
                                                customer_name,
                                                invoice_date,
                                                invoice_number,
                                                invoice_total,
                                                before_burden_gp_amount,
                                                gp_amount,
                                                perc_apply,
                                                before_burden_gp_available,
                                                gp_avail_comm,
                                                accumulated_gp_avail_comm,
                                                comm_amount,
                                                comm_percentage)
                                                VALUES('".mysql_real_escape_string($tmpTableSelRw_1["dept_name"])."',
                                                '".mysql_real_escape_string($tmpTableSelRw_1["comm_person"])."',
                                                '".mysql_real_escape_string($tmpTableSelRw_1["role_title"])."',
                                                '".mysql_real_escape_string($tmpTableSelRw_1["emp_name"])."',
                                                '".mysql_real_escape_string($tmpTableSelRw_1["assignment_id"])."',
                                                '".mysql_real_escape_string($tmpTableSelRw_1["customer_name"])."',
                                                '".$tmpTableSelRw_1["invoice_date"]."',
                                                '".mysql_real_escape_string($tmpTableSelRw_1["invoice_number"])."',
                                                '".$tmpTableSelRw_1["invoice_total"]."',
                                                '".$tmpTableSelRw_1["before_burden_gp_amount"]."',
                                                '".$tmpTableSelRw_1["gp_amount"]."',
                                                '".$tmpTableSelRw_1["perc_apply"]."',
                                                '".$tmpTableSelRw_1["before_burden_gp_available"]."',
                                                '".$tmpTableSelRw_1["gp_avail_comm"]."',
                                                '".$gpAccumTotalArray[$gpAccumKey]."',
                                                '".$gpAccuCommAmount."',
                                                '".$gpCommPerFlat."')";
                    mysql_query($tmpTableInsSql_5_1, $db);
                }
            }

            /*
             * Step - 2 End
             * Creating the Temporary Tables End
             */

            /* Calling below function to truncate temp tables created and exists */
            truncatetemptables();
    }

    /* Function to Truncate temp tables created for Invoice Commission Revenue */
    function truncatetemptables()
    {
            global $db;

            //Dropping Temporary Tables Start

            $tmpTable1DropSql_1 = "TRUNCATE TABLE rpt_tmp_InvComm_cmsn_details_individual";
            mysql_query($tmpTable1DropSql_1, $db);

            $tmpTable1DropSql_2 = "TRUNCATE TABLE rpt_tmp_InvComm_invactual_amount";
            mysql_query($tmpTable1DropSql_2, $db);

            $tmpTable1DropSql_3 = "TRUNCATE TABLE rpt_tmp_InvComm_all_cmsn_details";
            mysql_query($tmpTable1DropSql_3, $db);

            $tmpTable1DropSql_4 = "TRUNCATE TABLE rpt_tmp_InvComm_gp_cmsntiers_calc";
            mysql_query($tmpTable1DropSql_4, $db);
    
            //Dropping Temporary Tables End
    }
?>