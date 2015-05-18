<?php
require_once("class.HttpClient.php");
require_once("json_functions.inc");
require_once("OAuth.php");

$test_server = new TestOAuthServer(new MockOAuthDataStore());
$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
$plaintext_method = new OAuthSignatureMethod_PLAINTEXT();
$rsa_method = new TestOAuthSignatureMethod_RSA_SHA1();

$test_server->add_signature_method($hmac_method);
$test_server->add_signature_method($plaintext_method);
$test_server->add_signature_method($rsa_method);
$sig_methods = $test_server->get_signature_methods();
$sig_method = $sig_methods["HMAC-SHA1"];

class CallEmAll
{
	public $apihost = 'rest.call-em-all.com';
	public $apiport = '443';
	public $apipath = '/v1/';
	public $ckey = "3aabbcce-6260-4809-888c-ff9dc3b4fab5";
	public $csecret = "17ad879a-2f22-4333-aee2-4e09690f0003";
	public $apphost = 'https://app.call-em-all.com/sso.aspx?TemporaryKey=[[SSOTOKEN]]';
	public $endpoint = 'https://rest.call-em-all.com/v1/[[ENDPOINT]]';

	public $client;
	public $slid = "";
	public $module = "";
	public $ids = "";
	public $grptype = "";

	public function __construct()
	{
		global $companyuser;

		if($companyuser=="inforlinx")
		{
			$this->apihost = 'staging-rest.call-em-all.com';
			$this->apiport = '443';
			$this->apipath = '/v1/';
			$this->ckey = "4F55E938-BE32-4CD0-8001-EF8773F3AECA";
			$this->csecret = "6C160B2E-9A16-4D96-BFDE-CB744C0DD4BA";
			$this->apphost = 'https://staging-app.call-em-all.com/sso.aspx?TemporaryKey=[[SSOTOKEN]]';
			$this->endpoint = 'https://staging-rest.call-em-all.com/v1/[[ENDPOINT]]';
		}

		$this->client = new HttpClient($this->apihost, $this->apiport);
		//$this->client->setDebug(true);
	}

	public function createBroadCasts($data)
	{
		if($result = $this->client->post($this->apipath.'draftbroadcasts',$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	function getUserTZ()
	{
		global $db,$username;

		if(date("I",$timestamp)==1)
			$que="select timezone.daytime FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid='$username'";
		else
			$que="select timezone.stdtime FROM timezone LEFT JOIN orgsetup ON timezone.sno=orgsetup.timezone WHERE orgsetup.userid='$username'";
		$res=mysql_query($que,$db);
		$row=mysql_fetch_row($res);

		return $row[0];
	}

	function extractDateTime($data)
	{
		return date('m/d/Y h:i.s A O', strtotime($data));
	}

	function dbDateTime($data)
	{
		return date('Y-m-d H:i.s', strtotime($data));
	}

 	function getManageSno($name,$type)
 	{
 		global $db;

 		$sql="SELECT sno FROM manage WHERE name='".$name."' AND type='".$type."'";
 		$res=mysql_query($sql,$db);
 		$fetch=mysql_fetch_row($res);

 		return($fetch[0]);
 	}

	function getContactsList()
	{
		global $db,$username;

		$i=0;

		if($this->module=="crmgroups")
		{
			if($this->grptype=="Contact")
				$oque="SELECT staffoppr_contact.sno,staffoppr_contact.fname,staffoppr_contact.lname,staffoppr_contact.mobile,staffoppr_contact.hphone,staffoppr_contact.wphone FROM staffoppr_contact,users,crmgroup_details WHERE staffoppr_contact.dontcall='N' AND (staffoppr_contact.mobile!='' OR staffoppr_contact.hphone!='' OR staffoppr_contact.wphone!='') AND AND staffoppr_contact.status ='ER' AND crmgroup_details.groupid IN (".$this->ids.") AND crmgroup_details.refid=staffoppr_contact.sno AND staffoppr_contact.owner = users.username AND (FIND_IN_SET('$username',staffoppr_contact.accessto)>0 OR staffoppr_contact.owner='$username' OR staffoppr_contact.accessto='ALL') GROUP BY staffoppr_contact.sno";
			else
				$oque="SELECT candidate_list.sno,candidate_list.fname,candidate_list.lname,candidate_list.mobile,candidate_list.hphone,candidate_list.wphone FROM users, crmgroup_details, candidate_list WHERE candidate_list.dontcall='N' AND (candidate_list.mobile!='' OR candidate_list.hphone!='' OR candidate_list.wphone!='') AND candidate_list.status='ACTIVE' AND crmgroup_details.groupid IN (".$this->ids.") AND crmgroup_details.refid=candidate_list.sno AND (candidate_list.owner='$username' OR FIND_IN_SET('$username',candidate_list.accessto )>0 OR candidate_list.accessto='ALL') AND candidate_list.owner=users.username GROUP BY candidate_list.sno";
		}
		else if($this->module=="crmcontacts")
		{
			$oque="SELECT staffoppr_contact.sno,staffoppr_contact.fname,staffoppr_contact.lname,staffoppr_contact.mobile,staffoppr_contact.hphone,staffoppr_contact.wphone FROM staffoppr_contact,users WHERE staffoppr_contact.dontcall='N' AND (staffoppr_contact.mobile!='' OR staffoppr_contact.hphone!='' OR staffoppr_contact.wphone!='') AND staffoppr_contact.status ='ER' AND staffoppr_contact.sno IN (".$this->ids.") AND staffoppr_contact.owner = users.username AND (FIND_IN_SET('$username',staffoppr_contact.accessto)>0 OR staffoppr_contact.owner='$username' OR staffoppr_contact.accessto='ALL') GROUP BY staffoppr_contact.sno";
		}
		else if($this->module=="crmcandidates")
		{
			$oque="SELECT candidate_list.sno,candidate_list.fname,candidate_list.lname,candidate_list.mobile,candidate_list.hphone,candidate_list.wphone FROM users, candidate_list WHERE candidate_list.dontcall='N' AND (candidate_list.mobile!='' OR candidate_list.hphone!='' OR candidate_list.wphone!='') AND candidate_list.status='ACTIVE' AND candidate_list.sno IN (".$this->ids.") AND (candidate_list.owner='$username' OR FIND_IN_SET('$username',candidate_list.accessto )>0 OR candidate_list.accessto='ALL') AND candidate_list.owner=users.username GROUP BY candidate_list.sno";
		}
		else if($this->module=="crmsubmissions")
		{
			$oque="SELECT candidate_list.sno,candidate_list.fname,candidate_list.lname,candidate_list.mobile,candidate_list.hphone,candidate_list.wphone FROM users, candidate_list WHERE candidate_list.dontcall='N' AND (candidate_list.mobile!='' OR candidate_list.hphone!='' OR candidate_list.wphone!='') AND candidate_list.sno IN (".$this->ids.") AND (candidate_list.owner='$username' OR FIND_IN_SET('$username',candidate_list.accessto )>0 OR candidate_list.accessto='ALL') AND candidate_list.owner=users.username GROUP BY candidate_list.sno";
		}
		else if($this->module=="crmshortlists")
		{
			$oque="SELECT candidate_list.sno,candidate_list.fname,candidate_list.lname,candidate_list.mobile,candidate_list.hphone,candidate_list.wphone FROM users, candidate_list WHERE candidate_list.dontcall='N' AND (candidate_list.mobile!='' OR candidate_list.hphone!='' OR candidate_list.wphone!='') AND candidate_list.sno IN (".$this->ids.") AND (candidate_list.owner='$username' OR FIND_IN_SET('$username',candidate_list.accessto )>0 OR candidate_list.accessto='ALL') AND candidate_list.owner=users.username GROUP BY candidate_list.sno";
		}
		$ores=mysql_query($oque,$db);
		while($orow=mysql_fetch_assoc($ores))
		{
			$result['Contacts'][$i]['sno']=$orow['sno'];
			$result['Contacts'][$i]['FirstName']=$orow['fname'];
			$result['Contacts'][$i]['LastName']=$orow['lname'];
			$result['Contacts'][$i]['PrimaryPhone']=preg_replace("/[^0-9]/","",$orow['hphone']);
			$result['Contacts'][$i]['SecondaryPhone']=preg_replace("/[^0-9]/","",$orow['wphone']);
			$result['Contacts'][$i]['TertiaryPhone']=preg_replace("/[^0-9]/","",$orow['mobile']);
			$i++;
		}

		return $result;
	}

	function filterContacts($hsno,$tfname,$tlname,$tpphone,$tsphone,$tmobile,$cpphone,$csphone,$cmobile)
	{
		$j=0;
		for($i=0;$i<count($hsno);$i++)
		{
			if(in_array($hsno[$i],$cpphone) || in_array($hsno[$i],$csphone) || in_array($hsno[$i],$cmobile))
			{
				$result['Contacts'][$j]['FirstName']=$tfname[$i];
				$result['Contacts'][$j]['LastName']=$tlname[$i];

				if(in_array($hsno[$i],$cpphone))
					$result['Contacts'][$j]['PrimaryPhone']=preg_replace("/[^0-9]/","",$tpphone[$i]);
				if(in_array($hsno[$i],$csphone))
					$result['Contacts'][$j]['SecondaryPhone']=preg_replace("/[^0-9]/","",$tsphone[$i]);
				if(in_array($hsno[$i],$cmobile))
					$result['Contacts'][$j]['TertiaryPhone']=preg_replace("/[^0-9]/","",$tmobile[$i]);

				$result['Contacts'][$j]['Notes']=$j;
				$result['Contacts'][$j]['sno']=$hsno[$i];

				$j++;
			}
		}

		if($j==0)
			$result['Contacts'][] = array();

		return $result;
	}

	function validateContacts($result,$failed)
	{
		$slidStr="";

		if($this->slid!="")
			$slidStr="|S:".$this->slid;

		if(count($failed)==0)
		{
			for($i=0;$i<count($result['Contacts']);$i++)
			{
				if($this->grptype=="Contacts")
					$result['Contacts'][$i]['Notes']="O:".$result['Contacts'][$i]['sno'].$slidStr;
				else
					$result['Contacts'][$i]['Notes']="A:".$result['Contacts'][$i]['sno'].$slidStr;
			}
		}
		else
		{
			for($i=0;$i<count($failed);$i++)
			{
				$id=$failed[$i]['Contact']['Notes'];
				$errMes=explode(":",strtolower($failed[$i]['ErrorMessage']));
				$errMes=trim(str_replace(" ","",$errMes[1]));
	
				if($failed[$i]['ErrorCode']=="201")
				{
					if($errMes=="primaryphone")
						$result['Contacts'][$id]['PrimaryStatus']="Invalid";
					else if($errMes=="secondaryphone")
						$result['Contacts'][$id]['SecondaryStatus']="Invalid";
					else if($errMes=="tertiaryphone")
						$result['Contacts'][$id]['TertiaryStatus']="Invalid";
				}
				else if($failed[$i]['ErrorCode']=="203")
				{
					if($errMes==$result['Contacts'][$id]['PrimaryPhone'])
						$result['Contacts'][$id]['PrimaryStatus']="Duplicate";
					else if($errMes==$result['Contacts'][$id]['SecondaryPhone'])
						$result['Contacts'][$id]['SecondaryStatus']="Duplicate";
					else if($errMes==$result['Contacts'][$id]['TertiaryPhone'])
						$result['Contacts'][$id]['TertiaryStatus']="Duplicate";
				}
			}

			for($i=0;$i<count($result['Contacts']);$i++)
			{
				if($this->grptype=="Contacts")
					$result['Contacts'][$i]['Notes']="O:".$result['Contacts'][$i]['sno'].$slidStr;
				else
					$result['Contacts'][$i]['Notes']="A:".$result['Contacts'][$i]['sno'].$slidStr;
			}
		}

		return $result;
	}

	function finalContacts($tfname,$tlname,$tpphone,$tsphone,$tmobile,$tnotes)
	{
		for($i=0;$i<count($tfname);$i++)
		{
			$result['Contacts'][$i]['FirstName']=$tfname[$i];
			$result['Contacts'][$i]['LastName']=$tlname[$i];
			$result['Contacts'][$i]['PrimaryPhone']=preg_replace("/[^0-9]/","",$tpphone[$i]);
			$result['Contacts'][$i]['SecondaryPhone']=preg_replace("/[^0-9]/","",$tsphone[$i]);
			$result['Contacts'][$i]['TertiaryPhone']=preg_replace("/[^0-9]/","",$tmobile[$i]);
			$result['Contacts'][$i]['Notes']=$tnotes[$i];
		}

		if($i==0)
			$result['Contacts'][] = array();

		return $result;
	}

	public function getBroadCasts($data)
	{
		if($result = $this->client->get($this->apipath.'broadcasts',$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	public function getBroadCastInfo($uri,$data)
	{
		if($result = $this->client->get($this->apipath.$uri,$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	public function getBroadCastDetails($uri,$data)
	{
		if($result = $this->client->get($this->apipath.$uri,$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	public function saveBroadCast($broadCastInfo,$broadCastDetails)
	{
		global $username,$db;

		$bcid=str_replace("/broadcasts/","",$broadCastInfo['Uri']);

		if(strtolower($broadCastInfo['BroadcastStatus'])=="complete")
		{
			$evnid = $this->getManageSno("Call-Em-All Broadcast","events");

			$que="SELECT COUNT(1) FROM ceaBCInfo WHERE bcid='$bcid'";
			$res=mysql_query($que,$db);
			$row=mysql_fetch_row($res);

			if($row[0]==0)
			{
				$bname=addslashes($broadCastInfo['BroadcastName']);
				$btype=$broadCastInfo['BroadcastType'];
				$bstatus=addslashes($broadCastInfo['BroadcastStatus']);
				$bctime=$this->dbDateTime($broadCastInfo['CreatedDate']);
				$bstime=$this->dbDateTime($broadCastInfo['StartDate']);
	
				$ique="INSERT INTO ceaBCInfo (bcid,username,bname,btype,bstatus,ctime,stime) VALUES ('$bcid','$username','$bname','".$btype[0]."','$bstatus','$bctime','$bstime')";
				mysql_query($ique,$db);

				for($i=0;$i<count($broadCastDetails);$i++)
				{
					$conid=0;
					$candid=0;
					$slid=0;

					$phone=addslashes($broadCastDetails[$i]['PhoneNumber']);
					$noa=$broadCastDetails[$i]['NumberOfAttempts'];
					$fname=addslashes($broadCastDetails[$i]['FirstName']);
					$lname=addslashes($broadCastDetails[$i]['LastName']);
					$notes=addslashes($broadCastDetails[$i]['Notes']);
					$ctime=$this->dbDateTime($broadCastDetails[$i]['LastCallTime']);
					$sresult=addslashes($broadCastDetails[$i]['SurveyResult']);

					if($btype=="SMS")
					{
						$cresult=addslashes($broadCastDetails[$i]['TextResult']);
						$cstatus=addslashes($broadCastDetails[$i]['TextStatus']);
					}
					else
					{
						$cresult=addslashes($broadCastDetails[$i]['CallResult']);
						$cstatus=addslashes($broadCastDetails[$i]['CallStatus']);
					}

					$recid=$notes;

					$atxt="Broadcast Name : $bname\n";
					$atxt.="Broadcast Type : $btype\n";
					$atxt.="Broadcast Created On : $bctime\n";
					$atxt.="Broadcast Started On : $bstime\n";
					$atxt.="First Name : $fname\n";
					$atxt.="Last Name : $lname\n";
					$atxt.="Phone : $phone\n";
					$atxt.="Number Of Attempts : $noa\n";
					if($btype=="SMS")
					{
						$atxt.="Last Call Time : $ctime\n";
						$atxt.="Call Result : $cresult\n";
						$atxt.="Call Status : $cstatus\n";
					}
					else
					{
						$atxt.="Last Text Time : $ctime\n";
						$atxt.="Text Result : $cresult\n";
						$atxt.="Text Status : $cstatus\n";
					}
					$atxt.="Survey Result : $sresult";

					$srecid=explode("|",$recid);
					$ssrecid=explode(":",$srecid[0]);

					if($ssrecid[0]=="O")
						$conid=$ssrecid[1];
					else
						$candid=$ssrecid[1];

					if($srecid[1]!="")
					{
						$ssrecid=explode(":",$srecid[1]);
						$slid=$ssrecid[1];
					}

					$ique="INSERT INTO ceaBCDetails (bcid,phone,noa,ctime,fname,lname,notes,sresult,cresult,cstatus,candid,conid,slid) VALUES ('$bcid','$phone','$noa','$ctime','$fname','$lname','$notes','$sresult','$cresult','$cstatus','$candid','$conid','$slid')";
					mysql_query($ique,$db);
					$ceaid=mysql_insert_id($db);

					if($recid!="")
						$this->saveActivity($ceaid,$evnid,$recid,$bname,$atxt,$ctime);
				}
			}
		}

		return $bcid;
	}

	public function saveActivity($ceaid,$evnid,$recid,$bname,$atxt,$ctime)
	{
		global $username,$db;

		$srecid=explode("|",$recid);
		$ssrecid=explode(":",$srecid[0]);

		if($ssrecid[0]=="O")
			$con_id="oppr".$ssrecid[1];
		else
			$con_id="cand".$ssrecid[1];

		$etypename="Call-Em-All Broadcast";

		$que="INSERT INTO contact_event (con_id,username,etype,etitle,enotes,sdate) values ('".$con_id."','".$username."','".$evnid."','".addslashes($bname)."','".$atxt."','".$ctime."')";
		mysql_query($que,$db);
		$last_id=mysql_insert_id($db);

		$que="INSERT INTO cmngmt_pr (con_id,username,tysno,title,sdate,subject,lmuser,subtype) values ('".$con_id."','".$username."','".$last_id."','Event','".$ctime."','".addslashes($bname)."','".$username."','".addslashes($etypename)."')";
		mysql_query($que,$db);

		$que="UPDATE ceaBCDetails set processed='Y' WHERE sno='$ceaid'";
		mysql_query($que,$db);
	}

	public function getTextResponses($uri,$data)
	{
		if($result = $this->client->get($this->apipath.$uri,$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	public function getTextResponseID()
	{
		global $db,$ceaAcId;

		$trid=0;

		$sque="SELECT MAX(trid) FROM ceaBCResponses WHERE acid='$ceaAcId'";
		$sres=mysql_query($sque,$db);
		$srow=mysql_fetch_row($sres);
		if($srow[0]!="")
			$trid=$srow[0];

		return $trid;		
	}

	public function saveTextResponses($textResponses)
	{
		global $db,$ceaAcId;

		$evnid = $this->getManageSno("Call-Em-All Text Response","events");

		for($i=0;$i<count($textResponses);$i++)
		{
			$conid=0;
			$candid=0;
			$slid=0;

			$bcid=$textResponses[$i]['BroadcastId'];
			$trid=$textResponses[$i]['TextResponseId'];
			$phone=addslashes($textResponses[$i]['PhoneNumber']);
			$response=addslashes($textResponses[$i]['Body']);
			$status=addslashes($textResponses[$i]['Status']);
			$notes=addslashes($textResponses[$i]['Notes']);
			$rtime=$this->dbDateTime($textResponses[$i]['ReceivedAt']);
			$bcsubj=addslashes($textResponses[$i]['Subject']);

			$sque="SELECT COUNT(1) FROM ceaBCResponses WHERE trid='$trid'";
			$sres=mysql_query($sque,$db);
			$srow=mysql_fetch_row($sres);
			if($srow[0]==0)
			{
				$dque="SELECT sno FROM ceaBCDetails WHERE bcid='$bcid' AND phone='$phone'";
				$dres=mysql_query($dque,$db);
				$drow=mysql_fetch_row($dres);
				if($drow[0]>0)
				{
					$recid=$notes;
					$acid=$ceaAcId;
					$bdid=$drow[0];

					$srecid=explode("|",$recid);
					$ssrecid=explode(":",$srecid[0]);

					if($ssrecid[0]=="O")
						$conid=$ssrecid[1];
					else
						$candid=$ssrecid[1];

					if($srecid[1]!="")
					{
						$ssrecid=explode(":",$srecid[1]);
						$slid=$ssrecid[1];
					}

					$ique="INSERT INTO ceaBCResponses (trid,acid,bcid,bdid,phone,rtime,response,notes,status,candid,conid,slid) VALUES ('$trid','$acid','$bcid','$bdid','$phone','$rtime','$response','$notes','$status','$candid','$conid','$slid')";
					mysql_query($ique,$db);
					$ceaid=mysql_insert_id($db);

					if($recid!="")
						$this->respActivity($ceaid,$evnid,$recid,$bcsubj,$response,$rtime);

				}
			}
		}
	}

	public function respActivity($ceaid,$evnid,$recid,$bcsubj,$response,$rtime)
	{
		global $username,$db;

		$srecid=explode("|",$recid);
		$ssrecid=explode(":",$srecid[0]);

		if($ssrecid[0]=="O")
			$con_id="oppr".$ssrecid[1];
		else
			$con_id="cand".$ssrecid[1];

		$etypename="Call-Em-All Text Response";

		$que="INSERT INTO contact_event (con_id,username,etype,etitle,enotes,sdate) values ('".$con_id."','".$username."','".$evnid."','".$bcsubj."','".$response."','".$rtime."')";
		mysql_query($que,$db);
		$last_id=mysql_insert_id($db);

		$que="INSERT INTO cmngmt_pr (con_id,username,tysno,title,sdate,subject,lmuser,subtype) values ('".$con_id."','".$username."','".$last_id."','Event','".$rtime."','".$bcsubj."','".$username."','".addslashes($etypename)."')";
		mysql_query($que,$db);
	}
}
?>