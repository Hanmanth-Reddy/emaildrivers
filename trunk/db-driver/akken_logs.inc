<?php

/* function to maintain the company logs when this cron has initiated. */
function insertCronLog($companyuser,$fileName,$group_id,$logMsg)
{
    global $db;
    $insertQuery  = "INSERT INTO akken_logs.cron_logs
                                (
                                    `comp_id`,
                                    `filename`,
                                    `group_id`,
                                    `cdate`,
                                    `logs`
                                )
                                VALUES('".mysql_real_escape_string($companyuser)."',
                                '".mysql_real_escape_string($fileName)."',
                                '".mysql_real_escape_string($group_id)."',
                                NOW(),
                                '".mysql_real_escape_string($logMsg)."'
                                )";
    mysql_query($insertQuery, $db);
}

/* function to delete the cron logs which are maintained from more than 30days. */
function deleteCronlog($fileName, $noOfDays='')
{
        global $db;
        if(!empty($fileName))
        {
            if($noOfDays=="")
            {
                $noOfDays = "30";
            }
            $deleteQuery = "DELETE FROM akken_logs.cron_logs WHERE DATEDIFF(CURDATE(),cron_logs.cdate)>".$noOfDays." AND filename='".$fileName."'";
            mysql_query($insertQuery, $db);
        }
}
?>