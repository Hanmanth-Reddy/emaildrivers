<?php
	function doPostRequest($url, $data, $optional_headers = null) 
	{
        $params = array(
            'http' => array(
                'method' => 'POST',
                'content' => $data
            ));
        if ($optional_headers !== null)
            $params['http']['header'] = $optional_headers;
 
        $ctx = stream_context_create($params);
        $fp = fopen($url, 'rb', false, $ctx);
        if (!$fp)
            throw new Exception("Problem with $url, $php_errmsg");
 
        $response = @stream_get_contents($fp);
        if ($response === false)
            return false;
        return $response;
    }
 
    function pwCall( $action, $data = array() ) {
        $url = 'https://cp.pushwoosh.com/json/1.3/' . $action;
        $json = json_encode( array( 'request' => $data ) );
        $res = doPostRequest( $url, $json, 'Content-Type: application/json' );
        //print_r( @json_decode( $res, true ) );
		return @json_decode($res, true);
    }
	function getUserSTZOffset()
	{
		global $maildb,$db,$username;

		$utz = "+0000";

		$que="select REPLACE(timezone.stdtime,':','') FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid='$username'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);
		if($row[0]!="")
			$utz = $row[0];

		$sign = substr($utz, 0, 1);
		$hours = substr($utz, 1, 2);
		$mins = substr($utz, 3, 2);
		$secs = ((int)$hours * 3600) + ((int)$mins * 60);

		if ($sign == '-') 
			$secs = 0 - $secs;

		return $secs;
	}
 ?>