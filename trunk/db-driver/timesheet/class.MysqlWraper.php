<?php
class MysqlWraper
{
    function query($sql, $db){
		global $db;
	    $query = mysql_query($sql,$db);
	    return $query;
    }

    function fetch_row($result){
	    $result = mysql_fetch_row($result);
	    return $result;
    }
    
    function fetch_array($result){
	    $result = mysql_fetch_array($result);
	    return $result;
    }
	
	function num_rows($result){
	    $result = mysql_num_rows($result);
	    return $result;
    }
}

?>