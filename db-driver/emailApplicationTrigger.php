<?php
	//update the folder count and unread messages count 

	function update_efolder_operations($Fldstring)
	{
		global $username,$sysflist,$maildb,$db;

		//$Fldstring   format- (fldname|^**^|addTot|^**^|addUnr|^**^|totstatus^unrstatus)|^fldsep^|(fldname|^**^|addTot|^**^|addUnr|^**^|totstatus^unrstatus)|^fldsep^|...
		$UpfoldersDet=explode("|^fldsep^|",$Fldstring);

		if(count($UpfoldersDet)>0)
		{
			foreach($UpfoldersDet as $key=>$UpfoldersDet)
			{
				$UpFlddet=explode("|^**^|",$UpfoldersDet);
				$UpdCountStatus=explode("^",$UpFlddet[3]);
				$UpdTotStr=($UpdCountStatus[0]=='true')?"'".$UpFlddet[1]."'":"if((total+(".$UpFlddet[1]."))>0,(total+(".$UpFlddet[1].")),0)";
				$UpdUnrStr=($UpdCountStatus[1]=='true')?"'".$UpFlddet[2]."'":"if(((unread+(".$UpFlddet[2]."))>0 AND total>0),(unread+(".$UpFlddet[2].")),0)";
				if(in_array($UpFlddet[0],$sysflist))
					$uque="update e_folder set total=".$UpdTotStr." , unread=".$UpdUnrStr." where username='$username' AND foldername='$UpFlddet[0]' AND parent='system'";
				else
					$uque="update e_folder set total=".$UpdTotStr." , unread=".$UpdUnrStr." where fid='$UpFlddet[0]' AND username='$username'";
				mysql_query($uque,$db);
			}	
		}
	}

	function update_efolder()
	{
		global $username,$sysflist,$db;

		$UpdCntFolderIds=array();  
		$UpdCnt_sys_keys=array();  
		$UpdCnt_user_keys=array();  

		//array format  0=>foderid,1=>username,2=>readmsg,3=>unreadmsg.

		$que="SELECT count(1),username,folder,IF(status='CampaignIP' OR status='PostingIP','S',seen) AS seen1 FROM mail_headers WHERE username='$username' AND folder!='' AND status IN ('Active','CampaignIP','PostingIP','scheduled') GROUP BY username,folder,seen1 ORDER BY username,folder,seen1";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_row($res))
		{
			if(array_key_exists($row[2],$UpdCntFolderIds))
			{
				if($row[3]=="U")
					$UpdCntFolderIds[$row[2]][3]=($UpdCntFolderIds[$row[2]][3]+$row[0]);
				else 
					$UpdCntFolderIds[$row[2]][2]=($UpdCntFolderIds[$row[2]][2]+$row[0]);
			}
			else
			{
				if($row[3]=="U")
					$UpdCntFolderIds[$row[2]]=array($row[2],$row[1],0,$row[0]);
				else 
					$UpdCntFolderIds[$row[2]]=array($row[2],$row[1],$row[0],0);
			}
		}

		foreach($UpdCntFolderIds as $UpdFldId=>$UpdFldDet)
		{	
			if(in_array($UpdFldDet[0],$sysflist))
			{
				$ftype="system";
				array_push($UpdCnt_sys_keys,$UpdFldDet[0]);
			}
			else
			{
				$ftype="user";
				array_push($UpdCnt_user_keys,$UpdFldDet[0]);
			}

			if($ftype=="system")
				$uque="UPDATE e_folder SET unread='".$UpdFldDet[3]."',total='".($UpdFldDet[2]+$UpdFldDet[3])."' WHERE username='".$UpdFldDet[1]."' AND foldername='".$UpdFldDet[0]."' AND parent='system'";
			else
				$uque="UPDATE e_folder SET unread='".$UpdFldDet[3]."',total='".($UpdFldDet[2]+$UpdFldDet[3])."' WHERE fid='".$UpdFldDet[0]."'";
			mysql_query($uque,$db);
		}

		$UpdCnt_sys_keys_string="'".implode("','",$UpdCnt_sys_keys)."'";
		$UpdCnt_user_keys_string="'".implode("','",$UpdCnt_user_keys)."'";

		$UpdCnt_sys_keys_qry="UPDATE e_folder SET unread=0,total=0 WHERE (total!=0 or unread!=0) AND username='".$username."' AND FID!='' AND ((fid NOT IN(".$UpdCnt_user_keys_string.") AND parent!='system') OR (foldername NOT IN(".$UpdCnt_sys_keys_string.") AND parent='system'))";
		$UpdRows_sys=mysql_query($UpdCnt_sys_keys_qry,$db);
	}

	function update_efolder_all($userid)
	{
		global $sysflist,$db;

		$UpdCntFolderIds=array();

		if($userid=="all")
		{
			$eclause = "";
			$wclause = "";
		}
		else
		{
			$eclause = " AND username='$userid' ";
			$wclause = " AND m.username='$userid' ";
		}

		$que="SELECT count(1),e.fid,m.seen FROM mail_headers m LEFT JOIN e_folder e ON m.username=e.username LEFT JOIN users u ON e.username=u.username WHERE u.usertype!='' AND u.status!='DA' AND m.folder=e.foldername AND e.parent='system' AND m.status='Active' ".$wclause." GROUP BY m.username,m.folder,m.seen";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_row($res))
		{
			if(array_key_exists($row[1],$UpdCntFolderIds))
			{
				if($row[2]=="U")
					$UpdCntFolderIds[$row[1]][1]=($UpdCntFolderIds[$row[1]][1]+$row[0]);
				else 
					$UpdCntFolderIds[$row[1]][0]=($UpdCntFolderIds[$row[1]][0]+$row[0]);
			}
			else
			{
				if($row[2]=="U")
					$UpdCntFolderIds[$row[1]]=array(0,$row[0]);
				else 
					$UpdCntFolderIds[$row[1]]=array($row[0],0);
			}
		}

		$que="SELECT count(1),e.fid,m.seen FROM mail_headers m LEFT JOIN e_folder e ON m.username=e.username LEFT JOIN users u ON e.username=u.username WHERE u.usertype!='' AND u.status!='DA' AND m.folder=e.fid AND e.parent!='system' AND m.status='Active' ".$wclause." GROUP BY m.username,m.folder,m.seen";
		$res=mysql_query($que,$db);
		while($row=mysql_fetch_row($res))
		{
			if(array_key_exists($row[1],$UpdCntFolderIds))
			{
				if($row[2]=="U")
					$UpdCntFolderIds[$row[1]][1]=($UpdCntFolderIds[$row[1]][1]+$row[0]);
				else 
					$UpdCntFolderIds[$row[1]][0]=($UpdCntFolderIds[$row[1]][0]+$row[0]);
			}
			else
			{
				if($row[2]=="U")
					$UpdCntFolderIds[$row[1]]=array(0,$row[0]);
				else 
					$UpdCntFolderIds[$row[1]]=array($row[0],0);
			}
		}

		foreach($UpdCntFolderIds as $UpdFldId=>$UpdFldDet)
		{	
			$uque="UPDATE e_folder SET unread='".$UpdFldDet[1]."',total='".($UpdFldDet[0]+$UpdFldDet[1])."' WHERE fid='".$UpdFldId."'";
			mysql_query($uque,$db);
		}

		$uque="UPDATE e_folder SET unread=0,total=0 WHERE fid NOT IN (".implode(",",array_keys($UpdCntFolderIds)).")".$eclause;
		mysql_query($uque,$db);
	}
?>
