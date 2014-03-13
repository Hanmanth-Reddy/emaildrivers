<?php
	$setQry = "SET SESSION group_concat_max_len=1073740800";
	mysql_query($setQry,$db);

	$cdb_uidls=array_intersect($server_uidls,$db_uidls);
	$ddb_uidls=array_diff($db_uidls,$server_uidls);

	$iuidls="'".implode("','",$cdb_uidls)."'";
	$duidls="'".implode("','",$ddb_uidls)."'";

	$que1="SELECT GROUP_CONCAT(messageid) FROM mail_headers WHERE status='Active' AND folder='trash' AND extid='$extsno'";
	$res1=mysql_query($que1,$db);
	$row1=mysql_fetch_row($res1);
	if($row1[0]!="")
	{
		$at_uidls=explode(",",$row1[0]);
		$st_uidls = array_intersect($at_uidls,$db_uidls);
		if(count($st_uidls)>0)
		{
			foreach($st_uidls as $key => $val)
			{
				$msgno=$pop3->imap_search_uid($val);
				if(trim($msgno)!="" && $msgno>0)
					$pop3->imap_move_messages($msgno, $pop3->TRASH_BOX);
			}
		}
	}

	$que1="SELECT MIN(messageid) FROM mail_headers WHERE status='Active' AND folder!='sentmessages' AND extid='$extsno'";
	$res1=mysql_query($que1,$db);
	$row1=mysql_fetch_row($res1);
	$am_uidl = $row1[0];

	$que1="SELECT GROUP_CONCAT(mailid) FROM mail_headers WHERE status='Active' AND folder NOT IN ('sentmessages','trash') AND extid='$extsno' AND messageid NOT IN ('".implode("','",$db_uidls)."')";
	$res1=mysql_query($que1,$db);
	$row1=mysql_fetch_row($res1);
	if($row1[0]!="")
	{
		$que1="UPDATE mail_headers SET folder='trash' WHERE mailid IN (".$row1[0].")";
		mysql_query($que1,$db);
	}

	if($duidls!="''" && $am_uidl!="" && $am_uidl>0)
	{
		$que1="UPDATE mail_headers SET folder='trash' WHERE folder!='sentmessages' AND status='Active' AND extid='$extsno' AND messageid>'$am_uidl' AND messageid IN (".$duidls.")";
		mysql_query($que1,$db);
	}

	if(count($uidl_flags["seen"])>0)
	{
		$usque="UPDATE mail_headers SET seen='S' WHERE seen='U' AND status='Active' AND folder!='sentmessages' AND extid=$extsno AND messageid IN ('".implode("','",$uidl_flags["seen"])."')";
		mysql_query($usque,$db);
	}

	if(count($uidl_flags["flagged"])>0)
	{
		$usque="UPDATE mail_headers SET flag='RF' WHERE flag='N' AND status='Active' AND folder!='sentmessages' AND extid=$extsno AND messageid IN ('".implode("','",$uidl_flags["flagged"])."')";
		mysql_query($usque,$db);
	}

	if(count($uidl_flags["answered"])>0)
	{
		$usque="UPDATE mail_headers SET reply='A' WHERE reply='N' AND status='Active' AND folder!='sentmessages' AND extid=$extsno AND messageid IN ('".implode("','",$uidl_flags["answered"])."')";
		mysql_query($usque,$db);
	}

	if($iuidls!="''")
	{
		$que1="SELECT GROUP_CONCAT(messageid) FROM mail_headers WHERE seen='S' AND status='Active' AND folder!='sentmessages' AND extid='$extsno' AND messageid IN (".$iuidls.")";
		$res1=mysql_query($que1,$db);
		$row1=mysql_fetch_row($res1);
		if($row1[0]!="")
		{
			$as_uidls = explode(",",$row1[0]);

			if(is_array($uidl_flags["seen"]))
				$fs_uidls = array_diff($as_uidls,$uidl_flags["seen"]);
			else
				$fs_uidls = $as_uidls;

			foreach($fs_uidls as $key => $val)
			{
				$msgno=$pop3->imap_search_uid($val);
				$pop3->imap_seen_message($msgno);
			}
		}

		$que1="SELECT GROUP_CONCAT(messageid) FROM mail_headers WHERE flag!='N' AND status='Active' AND folder!='sentmessages' AND extid='$extsno' AND messageid IN (".$iuidls.")";
		$res1=mysql_query($que1,$db);
		$row1=mysql_fetch_row($res1);
		if($row1[0]!="")
		{
			$as_uidls = explode(",",$row1[0]);

			if(is_array($uidl_flags["flagged"]))
				$fs_uidls = array_diff($as_uidls,$uidl_flags["flagged"]);
			else
				$fs_uidls = $as_uidls;

			foreach($fs_uidls as $key => $val)
			{
				$msgno=$pop3->imap_search_uid($val);
				$pop3->imap_flagged_message($msgno);
			}
		}

		$que1="SELECT GROUP_CONCAT(messageid) FROM mail_headers WHERE reply!='N' AND status='Active' AND folder!='sentmessages' AND extid='$extsno' AND messageid IN (".$iuidls.")";
		$res1=mysql_query($que1,$db);
		$row1=mysql_fetch_row($res1);
		if($row1[0]!="")
		{
			$as_uidls = explode(",",$row1[0]);

			if(is_array($uidl_flags["answered"]))
				$fs_uidls = array_diff($as_uidls,$uidl_flags["answered"]);
			else
				$fs_uidls = $as_uidls;

			foreach($fs_uidls as $key => $val)
			{
				$msgno=$pop3->imap_search_uid($val);
				$pop3->imap_answered_message($msgno);
			}
		}
	}
?>