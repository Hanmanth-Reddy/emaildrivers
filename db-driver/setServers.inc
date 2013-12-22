<?php
function getActiveDatabase($int_ip)
{
	global $db_user,$db_pass,$admin_mail,$admin_title,$support_mail,$url_redirect,$pri_driver,$DB_HEALTH,$sysdb;

	$i=0;

	$dbcon=mysql_connect($int_ip[$i],$db_user,$db_pass);
	if($sysdb=="")
	{
		$sysdb=$dbcon;
		require("sysDBsysdb.inc");
	}

	if(!$dbcon)
	{
		if(!$pri_driver)
			Header("Location:$url_redirect");
		exit();
	}

	return $dbcon;
}

function getLocDBIPS($type,$sysdb,$status="Master")
{
	$i=0;
	$ips=array();

	$que="select int_ip from servers where type='$type' and status='$status' order by priority";
	$res=mysql_query($que,$sysdb);	
	while($row=mysql_fetch_row($res))
	{
		$ips[$i]=$row[0];
		$i++;
	}
	return $ips;
}

function getGroupDBIPS($akken_group_id,$sysdb,$status="Master")
{
	$i=0;
	$ips=array();

	$que="select int_ip from servers where group_id='$akken_group_id' AND status='$status' order by priority";
	$res=mysql_query($que,$sysdb);	
	while($row=mysql_fetch_row($res))
	{
		$ips[$i]=$row[0];
		$i++;
	}
	return $ips;
}

function getLocIPS($type,$sysdb)
{
	$i=0;
	$ips=array();

	$que="select int_ip from servers where type='$type' order by priority";
	$res=mysql_query($que,$sysdb);	
	while($row=mysql_fetch_row($res))
	{
		$ips[$i]=$row[0];
		$i++;
	}
	return $ips;
}

function getExtIPS($type,$sysdb)
{
	$i=0;
	$ips=array();
	$que="select ext_ip from servers where type='$type' order by priority";
	$res=mysql_query($que,$sysdb);
	while($row=mysql_fetch_row($res))
	{
		$sinip=explode(",",$row[0]);
		for($j=0;$j<count($sinip);$j++)
		{
			$ips[$i]=$sinip[$j];
			$i++;
		}
	}
	return $ips;
}

function swapOrder($type,$sysdb,$int_ip)
{
	$sque="select priority from servers where type='$type' and int_ip='$int_ip'";
	$sres=mysql_query($sque,$sysdb);
	$srow=mysql_fetch_row($sres);
	if($srow[0]>1)
	{
		$que="update servers set priority=priority+1 where type='$type'";
		mysql_query($que,$sysdb);

		$que="update servers set priority=1 where type='$type' and int_ip='$int_ip'";
		mysql_query($que,$sysdb);

		$i=1;
		$que="select id from servers where type='$type' order by priority";
		$res=mysql_query($que,$sysdb);
		while($row=mysql_fetch_row($res))
		{
			$que="update servers set priority=$i where id='$row[0]'";
			mysql_query($que,$sysdb);
			$i++;
		}
	}
}

function findServer($max,$type)
{
	global $egdomain,$failed,$sysdb,$url_redirect,$PLUGIN_PATH;

	$rand=rand(1,$max);
	if(!in_array($rand,$failed))
		$host="appserver".$rand;
	else
		return findServer($max,$type);

	$que="select count(*) from servers where type='$type' and host='$host'";
	$res=mysql_query($que,$sysdb);
	$row=mysql_fetch_row($res);

	$HTTP_HEALTH="$PLUGIN_PATH/check_http -H ";
	$vhost="$host.$egdomain";
	system("$HTTP_HEALTH $vhost > /dev/null",$ret);

	if($row[0]>0 && $ret<2)
	{
		return $host;
	}
	else
	{
		if(!in_array($rand,$failed))
			$failed[count($failed)+1]=$rand;

		if(count($failed)<$max)
		{
			return findServer($max,$type);
		}
		else
		{
			Header("Location:$url_redirect");
			exit();
		}
	}
}

function totalServers($sysdb,$type)
{
	$que="select count(*) from servers where type='$type'";
	$res=mysql_query($que,$sysdb);
	$row=mysql_fetch_row($res);
	return $row[0];
}
?>
