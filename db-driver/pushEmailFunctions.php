<?php
	function checkMobilePush($username, $db) 
	{
	    $returnflag = 0;
			$que = "SELECT u.username AS username, u.userid AS userid, t.phpvar AS usertz, a.c_value AS Platform_Type_id, s.collaboration, s.plugin_outlook, s.plugin_mobi
					FROM  mobi_customProperties AS a,
					  QB_LoginStatus AS b,    
					  users AS u,   
					  orgsetup AS o,
					  timezone AS t,
					  sysuser AS s
					WHERE b.wsname='AkkenForMobi' 
					AND b.LoginStatus=1 
					AND a.c_active=1 
					AND a.c_name = 'Platform_Type' 
					AND u.usertype!='' 
					AND u.status!='DA' 
					AND a.LoginSession=b.LoginSession
					AND b.username = u.username 
					AND u.username=o.userid
					AND s.username=u.username 
					AND o.timezone=t.sno 
					AND b.username = '".$username."'
					GROUP BY u.username 
					ORDER BY username";
			$res = mysql_query($que,$db);
			if(mysql_num_rows($res)>0)
			{
				$row = mysql_fetch_array($res);
				if($row['plugin_mobi']=='Y'){
						$collaborationpref = $row['collaboration'];
						$plugin_outlook = $row['plugin_outlook'];
						if($collaborationpref != '' && $collaborationpref != 'NO' && $plugin_outlook=="N")
						{
							if(strpos($collaborationpref,'+10+')>0 && strpos($collaborationpref,'+1+')>0)
							{
								$returnflag = 1;
							}else
							{
								$returnflag = 0;
							}
						}else
						{
							$returnflag = 0;
						}
				}else
				{
					$returnflag = 0;
				}
			}else
				{
					$returnflag = 0;
				}
		
		return $returnflag;
    }
	
function pushMailMessage($total_emails, $company, $user)
{
	global $maildb,$maindb,$db;
	if($total_emails!=0 && $company!='' && $user!=''){
		$usertag = strtolower($company.'@'.$user);
		$push_status = 0;
		$sub = "You have new email.";
		$select_LogQuery = "SELECT sno,username,comp_sno,ServerName FROM MB_LoginStatus WHERE username='".$user."' AND comp_id='".$company."' AND wsname='AkkenForMobi' ";
		$select_LogQueryRes = mysql_query($select_LogQuery,$maindb);
		$rowRes=mysql_fetch_row($select_LogQueryRes);
		$host = $rowRes[3];
		$ServerName = ($host != '') ? $host : 'appserver1';
	
		$push_link = "https://".$ServerName.".".AKKEN_MOBI_URL."/Collaboration-Folder-view.php";
		$output_send = pwCall( 'createMessage', array(
													'application' => PW_APPLICATION,
													'auth' => PW_AUTH,
													'notifications' => array(
														array(
															'send_date' => 'now',
															'content' => $sub,
															'data' => array( 'isNotficationGrouped' => 'true' ),
															'minimize_link' => 0,
															'link' => $push_link,
															'platforms' => array(1,2,3,5),
															'conditions' => array( array('User','EQ',$usertag))
														)
													)
												)
										);
		if($output_send!='')
		{
			$Messageid = $output_send['response']['Messages'][0];
			$insert_log = "INSERT INTO pushwoosh_logs (user_id,log_status,log_msg,company_id,log_pw_id,log_type) VALUES ('".$user."','".$output_send['status_code']."','".$output_send['status_message']."','".$company."','".$Messageid."','E') ";
			$insert_logRes = mysql_query($insert_log,$db);
		}else
		{
			$push_status = 0;
		}
	}
}
	
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
	
	function json_encode($a=false)
	{
		if (is_null($a)) return 'null';
		if ($a === false) return 'false';
		if ($a === true) return 'true';
		if (is_scalar($a))
		{
		  if (is_float($a))
		  {
			// Always use "." for floats.
			return floatval(str_replace(",", ".", strval($a)));
		  }
	 
		  if (is_string($a))
		  {
			static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
			return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
		  }
		  else
			return $a;
		}
		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a))
		{
		  if (key($a) !== $i)
		  {
			$isList = false;
			break;
		  }
		}
		$result = array();
		if ($isList)
		{
		  foreach ($a as $v) $result[] = json_encode($v);
		  return '[' . join(',', $result) . ']';
		}
		else
		{
		  foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
		  return '{' . join(',', $result) . '}';
		}
	}
  
	function json_decode($json)
	{
		$comment = false;
		$out = '$x=';
		for ($i=0; $i<strlen($json); $i++)
		{
			if (!$comment)
			{
				if (($json[$i] == '{') || ($json[$i] == '['))
					$out .= ' array(';
				else if (($json[$i] == '}') || ($json[$i] == ']'))
					$out .= ')';
				else if ($json[$i] == ':')
					$out .= '=>';
				else
					$out .= $json[$i];
			}
			else
				$out .= $json[$i];
			if ($json[$i] == '"' && $json[($i-1)]!="\\")
				$comment = !$comment;
		}
		eval($out . ';');
		return $x;
	}
	
 ?>
