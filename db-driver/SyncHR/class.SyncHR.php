<?php
require("class.RestHttpClient.php");
require("json_functions.inc");

class SyncHR
{
	private $apihost = 'stage-clients.synchr.com';
	private $apiport = '443';
	private $apipath = '/synchr/api/1.0/';
	private $rpwdUrl = '/synchr/password/resetRequest';

	public $client;
	public $debug = false;
	public $apiKey = "";
	public $token = "";
	public $bcode = "";

	function __construct()
	{
		global $pri_production;

		if($pri_production)
			$this->apihost = 'clients.synchr.com';

		$this->client = new HttpClient($this->apihost, $this->apiport);
	}

	function debug() 
	{
		if($this->debug)
			print $this->client->debug_string;

		return $this->client->debug_string;
	}

	function clear_debug() 
	{
		$this->client->debug_string="";
		$this->client->res_error="";
	}

	function msgDebug($msg) 
	{
		print $msg."\n";
	}

	function doAuth($data)
	{
		if($result = $this->client->post($this->apipath.'authentication/createToken',$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	function shListAPI($endpoint,$data)
	{
		if($result = $this->client->get($this->apipath.$endpoint.'/list',$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	function shCreateAPI($endpoint,$data)
	{
		if($result = $this->client->post($this->apipath.$endpoint.'/create',$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	function shUpdateAPI($endpoint,$data)
	{
		if($result = $this->client->put($this->apipath.$endpoint.'/update',$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	function shDeleteAPI($endpoint,$data)
	{
		if($result = $this->client->delete($this->apipath.$endpoint.'/delete',$data))
			return json_decode($this->client->getContent());
		return $result;
	}

	function isPreviousDate($date1,$date2)
	{
		if(strtotime($date1)<strtotime($date2))
			return true;
		else
			return false;
	}

	function dataCheck($aCloudData,$syncHrArray,$cfields,$shElement)
	{
		$chkFlag=false;
		$syncHrData=$syncHrArray[$shElement];

		foreach($cfields as $key => $val)
			if(trim($aCloudData[$key])!="")
				$chkFlag=true;

		if($chkFlag)
		{
			foreach($cfields as $key => $val)
			{
				if(is_float($syncHrData[$val]) || is_float($aCloudData[$key]))
				{
					if(floatval($syncHrData[$val])!=floatval($aCloudData[$key]))
						return false;
				}
				else 
				{
					//print "dataCheck :: $shElement :: $val :: ".$syncHrData[$val]." :: $key :: ".$aCloudData[$key]." :: \n==============\n";
					if($syncHrData[$val]!=$aCloudData[$key])
						return false;
				}
			}
		}

		return $chkFlag;
	}

	function checkElement($shData,$key,$val)
	{
		$retval=-1;

		for($i=0;$i<count($shData);$i++)
		{
			if($shData[$i][$key]==$val)
			{
				$retval=$i;
				break;
			}
		}

		//print "checkElement :: $key :: ".$shData[$i][$key]." :: $val :: $retval\n==============\n";

		return $retval;
	}

	//******** PERSON FUNCTIONS **********//

	function getPersonsData($locid)
	{
		global $db;

		$personsData=array();

		$pque="SELECT sno,username,acStatus,empNo,effectiveDate,emplHireDate,endDate,payThroughDate,fName,mName,lName,DBA,locationCode,benefitStatus,emplStatus,emplPermanency,employmentType,emplClass,emplFulltimePercent,emplServiceDate,emplSenorityDate,emplBenefithireDate,streetAddress,streetAddress2,countryCode,city,stateProvinceCode,postalCode,phoneno,emailAddress,birthDate,genderCode,netId,SSN,emplEvent FROM syncHR_personData WHERE SSN!='' AND locid='$locid' AND process='N' ORDER BY cdate";
		$pres=mysql_query($pque,$db);
		while($prow=mysql_fetch_assoc($pres))
			$personsData[]=$prow;

		return $personsData;
	}

	function checkPersonIdentity($eID,$eType="SSN")
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$data['identityType'][0] = "eq";
		$data['identityType'][1] = $eType;

		$data['identity'][0] = "eq";
		$data['identity'][1] = $eID;

		$filter = array("filter" => json_encode($data));

		return $this->shListAPI("personIdentity",$filter);
	}

	function updateSyncHRCheck($acempNo)
	{
		global $db;

		$uque="UPDATE emp_list SET syncHRcheck='Y' WHERE syncHRempNo='$acempNo'";
		mysql_query($uque,$db);
	}

	function updateSyncHREmpNo($shempNo,$acempNo)
	{
		global $db;

		if(trim($shempNo)=="" || trim($acempNo)=="")
		{
			return false;
		}
		else
		{
			if($shempNo!=$acempNo)
			{
				$uque="UPDATE emp_list SET syncHRempNo='$shempNo', syncHRcheck='Y' WHERE lstatus!='DA' AND syncHRempNo='$acempNo'";
				mysql_query($uque,$db);

				$uque="UPDATE syncHR_personData SET empNo='$shempNo' WHERE empNo='$acempNo'";
				mysql_query($uque,$db);

				$uque="UPDATE syncHR_positionData SET empNo='$shempNo' WHERE empNo='$acempNo'";
				mysql_query($uque,$db);

				$uque="UPDATE syncHR_payData SET empNo='$shempNo' WHERE empNo='$acempNo'";
				mysql_query($uque,$db);
			}
			else
			{
				$uque="UPDATE emp_list SET syncHRcheck='Y' WHERE syncHRempNo='$acempNo'";
				mysql_query($uque,$db);
			}

			return true;
		}
	}

	function createNetID($empNo,$fname,$lname,$cnt)
	{
		if($cnt==0)
			$temp_netId=strtolower(substr(trim($fname),0,2).trim($lname))."@qgtemp";
		else
			$temp_netId=strtolower(substr(trim($fname),0,2).trim($lname)).$cnt."@qgtemp";

		$netId=$this->checkPersonIdentity($temp_netId,"netId");
		if(trim($netId['personIdentity'][0]['identity'])=="")
		{
			$personData = array("empNo" => $empNo, "identityType" => "netId", "identity" => $temp_netId);
			return $this->shCreateAPI("personIdentity",$personData);
		}
		else
		{
			$cnt++;
			$this->createNetID($empNo,$fname,$lname,$cnt);
		}
	}

	function resetPassword($email)
	{
		$data['email']=$email;

		$client = new HttpClient($this->apihost, $this->apiport);
		$result = $client->get($this->rpwdUrl,$data);
	}

	function setNetID_PWD($personData)
	{
		$shNetId=$this->createNetID($personData['empNo'],$personData['fName'],$personData['lName'],0);
		if(trim($personData['emailAddress'])!="")
			$shPwd=$this->resetPassword($personData['emailAddress']);
	}

	function createPersonEmpNO($SSN,$empNo)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$personData = array("SSN" => $SSN, "identityType" => "empNo", "identity" => $empNo);

		return $this->shCreateAPI("personIdentity",$personData);
	}

	function createPersonIdentity($empNo,$eID,$eType="SSN")
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$personData = array("empNo" => $empNo, "identityType" => $eType, "identity" => $eID);

		return $this->shCreateAPI("personIdentity",$personData);
	}

	function checkPersonStatus($empNo,$endpoint)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$data['empNo'][0] = "eq";
		$data['empNo'][1] = $empNo;
		$filter = array("filter" => json_encode($data));

		return $this->shListAPI($endpoint,$filter);
	}

	function createPersonData($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$data['nametype'] = "Legal";

		$fields = array("empNo","effectiveDate","emplHireDate","fName","mName","lName","locationCode","emplStatus","emplPermanency","employmentType","emplClass","emplFulltimePercent","emplServiceDate","emplSenorityDate","emplBenefithireDate","streetAddress","streetAddress2","countryCode","city","stateProvinceCode","postalCode","phoneno","emailAddress","emplEvent");

		foreach($fields as $key => $val)
			$personData[$val]=$data[$val];

		$personData['phoneContactType']="Home";
		$personData['netContactType']="HomeEmail";

		return $this->shCreateAPI("personData",$personData);
	}

	function createPersonDBA($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$data['nametype'] = "DBA";

		$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","DBA" => "name","nametype" => "nametype","emplEvent" => "emplEvent");

		$personName['fName'] = "";
		$personName['lName'] = $data['DBA'];

		foreach($fields as $key => $val)
			$personName[$val]=$data[$key];

		return $this->shCreateAPI("personName",$personName);
	}

	function insertPersonData($personData)
	{
		if($this->isPreviousDate($personData['emplHireDate'],$personData['effectiveDate']))
			$personData['effectiveDate']=$personData['emplHireDate'];

		$shPersonData=$this->createPersonData($personData);
		if($shPersonData)
		{
			if(trim($personData['DBA'])!="" && $personData['emplStatus']=="C")
				$this->createPersonDBA($personData);

			$shVitals=$this->createPersonVitals($personData['empNo'],$personData['birthDate'],$personData['genderCode'],$personData['effectiveDate']);
			$shSSN=$this->createPersonIdentity($personData['empNo'],$personData['SSN'],"SSN");

			$this->setNetID_PWD($personData);

			$this->updatePersonStatus($personData,"Y");

			return true;
		}
		return false;
	}

	function createPersonVitals($empNo,$birthDate,$genderCode,$effectiveDate)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$personData = array("effectiveDate" => $effectiveDate, "empNo" => $empNo, "birthDate" => $birthDate, "genderCode" => $genderCode);

		return $this->shCreateAPI("personVitals",$personData);
	}

	function createPersonPayroll($effectiveDate,$empNo,$payUnitCode)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$personPayroll = array("effectiveDate" => $effectiveDate, "empNo" => $empNo, "payUnitCode" => $payUnitCode, "payrollevent" => "Enrol", "payrolleventDescription" => "Enrollment", "payunitrelationship" => "M", "payunitrelationshipDescription" => "Member", "payrollstatus" => "A", "payrolltype" => "Y");

		return $this->shCreateAPI("personPayroll",$personPayroll);
	}

	function updatePersonDBA($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personName"))
		{
			$data['nametype'] = "DBA";

			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","DBA" => "name","nametype" => "nametype");

			$personName['nameevent']="NameChg";
			$personName['fname'] = "";
			$personName['lname'] = $data['DBA'];

			foreach($fields as $key => $val)
				$personName[$val]=$data[$key];

			if(count($shData['personName'])>0)
			{
				$shElement=$this->checkElement($shData['personName'],"nametype",$data['nametype']);
				if($shElement>=0)
				{
					$cfields = array("DBA" => "fname","DBA" => "name","nametype" => "nametype");
					if(!$this->dataCheck($data,$shData['personName'],$cfields,$shElement))
						return $this->shUpdateAPI("personName",$personName);
					else
						return true;
				}
				else
				{
					return $this->shCreateAPI("personName",$personName);
				}
			}
			else
			{
				return $this->shCreateAPI("personName",$personName);
			}
		}

		return false;
	}

	function updatePersonName($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personName"))
		{
			$data['nametype'] = "Legal";

			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","fName" => "fname","mName" => "mname","lName" => "lname","nametype" => "nametype");

			$personName['nameevent']="NameChg";
			foreach($fields as $key => $val)
				$personName[$val]=$data[$key];

			if(count($shData['personName'])>0)
			{
				$shElement=$this->checkElement($shData['personName'],"nametype",$data['nametype']);
				if($shElement>=0)
				{
					$cfields = array("fName" => "fname","mName" => "mname","lName" => "lname","nametype" => "nametype");
					if(!$this->dataCheck($data,$shData['personName'],$cfields,$shElement))
						return $this->shUpdateAPI("personName",$personName);
					else
						return true;
				}
				else
				{
					return $this->shCreateAPI("personName",$personName);
				}
			}
			else
			{
				return $this->shCreateAPI("personName",$personName);
			}
		}

		return false;
	}

	function updatePersonAddress($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personAddress"))
		{
			$data['addressType']="Res";

			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","streetAddress" => "streetAddress","streetAddress2" => "streetAddress2","countryCode" => "countryCode","city" => "city","stateProvinceCode" => "stateProvinceCode","postalCode" => "postalCode","addressType" => "addressType");

			$personAddress['emplEvent']="Status";
			foreach($fields as $key => $val)
				$personAddress[$val]=$data[$key];

			if(count($shData['personAddress'])>0)
			{
				$shElement=$this->checkElement($shData['personAddress'],"addressType",$data['addressType']);
				if($shElement>=0)
				{
					$cfields = array("streetAddress" => "streetAddress","streetAddress2" => "streetAddress2","countryCode" => "countryCode","city" => "city","stateProvinceCode" => "stateProvinceCode","postalCode" => "postalCode","addressType" => "addressType");
					if(!$this->dataCheck($data,$shData['personAddress'],$cfields,$shElement))
						return $this->shUpdateAPI("personAddress",$personAddress);
					else
						return true;
				}
				else
				{
					return $this->shCreateAPI("personAddress",$personAddress);
				}
			}
			else
			{
				return $this->shCreateAPI("personAddress",$personAddress);
			}
		}

		return false;
	}

	function updatePersonPhone($data)
	{
		$data['phoneno']=preg_replace("/[^0-9]/","",$data['phoneno']);

		if(trim($data['phoneno'])!="")
		{
			$this->client->setAuthorization($this->apiKey, $this->token);
	
			if($shData=$this->checkPersonStatus($data['empNo'],"personPhone"))
			{
				$data['phoneContactType']="Home";

				$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","phoneno" => "phoneNo","phoneContactType" => "phoneContactType");

				foreach($fields as $key => $val)
					$personPhone[$val]=$data[$key];

				if(count($shData['personPhone'])>0)
				{
					$shElement=$this->checkElement($shData['personPhone'],"phoneContactType",$data['phoneContactType']);
					if($shElement>=0)
					{
						$cfields = array("phoneno" => "phoneNo","phoneContactType" => "phoneContactType");
						if(!$this->dataCheck($data,$shData['personPhone'],$cfields,$shElement))
							return $this->shUpdateAPI("personPhone",$personPhone);
						else
							return true;
					}
					else
					{
						return $this->shCreateAPI("personPhone",$personPhone);
					}
				}
				else
				{
					return $this->shCreateAPI("personPhone",$personPhone);
				}
			}
		}

		return false;
	}

	function updatePersonEmail($data)
	{
		if(trim($data['emailAddress'])!="")
		{
			$this->client->setAuthorization($this->apiKey, $this->token);

			if($shData=$this->checkPersonStatus($data['empNo'],"personEmail"))
			{
				$data['netContactType']="HomeEmail";

				$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","emailAddress" => "url","netContactType" => "netContactType");

				foreach($fields as $key => $val)
					$personEmail[$val]=$data[$key];

				if(count($shData['personEmail'])>0)
				{
					$shElement=$this->checkElement($shData['personEmail'],"netContactType",$data['netContactType']);
					if($shElement>=0)
					{
						$cfields = array("emailAddress" => "url","netContactType" => "netContactType");
						if(!$this->dataCheck($data,$shData['personEmail'],$cfields,$shElement))
							return $this->shUpdateAPI("personEmail",$personEmail);
						else
							return true;
					}
					else
					{
						return $this->shCreateAPI("personEmail",$personEmail);
					}
				}
				else
				{
					return $this->shCreateAPI("personEmail",$personEmail);
				}
			}
		}

		return false;
	}

	function checkPersonEmploymentStatus($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personEmployment"))
		{
			if(count($shData['personEmployment'])>0)
			{
				$syncHrData=$shData['personEmployment'][count($shData['personEmployment'])-1];
				if(($syncHrData['emplStatus']=="T" || $syncHrData['benefitStatus']=="T") && $data['acStatus']=="A")
					return true;
			}
		}

		return false;
	}

	function updatePersonEmployment($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personEmployment"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","emplStatus" => "emplStatus","emplClass" => "emplClass","emplHireDate" => "emplHireDate","emplServiceDate" => "emplServiceDate","emplSenorityDate" => "emplSenorityDate","emplBenefithireDate" => "emplBenefithireDate");
	
			if($data['acStatus']=="T")
			{
				$personEmployment['emplEvent']="InvTerm";
				$personEmployment['emplEventDETCode']="EA";
			}
			else if($data['acStatus']=="R")
			{
				$personEmployment['emplEvent']="Rehire";
				$personEmployment['emplEventDETCode']="RH";
			}
			else
			{
				$personEmployment['emplEvent']="Status";
				$personEmployment['emplEventDETCode']="ST";
			}

			foreach($fields as $key => $val)
				$personEmployment[$val]=$data[$key];

			if(count($shData['personEmployment'])>0)
			{
				$cfields = array("emplStatus" => "emplStatus","emplClass" => "emplClass","emplHireDate" => "emplHireDate","emplServiceDate" => "emplServiceDate","emplSenorityDate" => "emplSenorityDate","emplBenefithireDate" => "emplBenefithireDate");
				if(!$this->dataCheck($data,$shData['personEmployment'],$cfields,count($shData['personEmployment'])-1))
					return $this->shUpdateAPI("personEmployment",$personEmployment);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personEmployment",$personEmployment);
			}
		}

		return false;
	}

	function terminateEmployment($data)
	{
		$data['emplEvent']="InvTerm";
		$data['emplEventdetCode']="EA";

		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personEmployment"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","emplStatus" => "emplStatus","emplClass" => "emplClass","emplHireDate" => "emplHireDate","emplServiceDate" => "emplServiceDate","emplSenorityDate" => "emplSenorityDate","emplBenefithireDate" => "emplBenefithireDate","benefitStatus" => "benefitStatus","payThroughDate" => "payThroughDate","emplEvent" => "emplEvent","emplEventdetCode" => "emplEventdetCode");
	
			foreach($fields as $key => $val)
				$personEmployment[$val]=$data[$key];
	
			if(count($shData['personEmployment'])>0)
			{
				$cfields = array("emplStatus" => "emplStatus","emplHireDate" => "emplHireDate","benefitStatus" => "benefitStatus","payThroughDate" => "payThroughDate","emplEvent" => "emplEvent","emplEventdetCode" => "emplEventdetCode");
				if(!$this->dataCheck($data,$shData['personEmployment'],$cfields,count($shData['personEmployment'])-1))
					return $this->shUpdateAPI("personEmployment",$personEmployment);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personEmployment",$personEmployment);
			}
		}

		return false;
	}

	function terminatePersonPosition($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personPosition"))
		{
			if(count($shData['personPosition'])>0)
			{
				$fields = array("empNo" => "empNo", "effectiveDate" => "effectiveDate", "endDate" => "endDate");
				$personPosition['persPosEvent']="EndAssgmnt";
				foreach($fields as $key => $val)
					$personPosition[$val]=$data[$key];

				return $this->shUpdateAPI("personPosition",$personPosition);
			}
		}

		return true;
	}

	function rehireEmployment($data)
	{
		$data['emplEvent']="Rehire";
		$data['emplEventdetCode']="RH";

		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personEmployment"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","emplStatus" => "emplStatus","emplClass" => "emplClass","emplHireDate" => "emplHireDate","emplServiceDate" => "emplServiceDate","emplSenorityDate" => "emplSenorityDate","emplBenefithireDate" => "emplBenefithireDate","benefitStatus" => "benefitStatus","emplEvent" => "emplEvent","emplEventdetCode" => "emplEventdetCode");
	
			foreach($fields as $key => $val)
				$personEmployment[$val]=$data[$key];
	
			if(count($shData['personEmployment'])>0)
			{
				$cfields = array("emplStatus" => "emplStatus","emplClass" => "emplClass","emplHireDate" => "emplHireDate","emplServiceDate" => "emplServiceDate","emplSenorityDate" => "emplSenorityDate","emplBenefithireDate" => "emplBenefithireDate","benefitStatus" => "benefitStatus","emplEvent" => "emplEvent","emplEventdetCode" => "emplEventdetCode");
				if(!$this->dataCheck($data,$shData['personEmployment'],$cfields,count($shData['personEmployment'])-1))
					return $this->shUpdateAPI("personEmployment",$personEmployment);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personEmployment",$personEmployment);
			}
		}

		return false;
	}

	function updatePersonVitals($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personVitals"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","birthDate" => "birthDate", "genderCode" => "genderCode");

			$personVitals['pbirthEvent']="Hire";
			foreach($fields as $key => $val)
				$personVitals[$val]=$data[$key];

			if(count($shData['personVitals'])>0)
			{
				$cfields = array("birthDate" => "birthDate", "genderCode" => "genderCode");
				if(!$this->dataCheck($data,$shData['personVitals'],$cfields,count($shData['personVitals'])-1))
					return $this->shUpdateAPI("personVitals",$personVitals);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personVitals",$personVitals);
			}
		}

		return false;
	}

	function updatePersonLocation($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personLocation"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","locationCode" => "locationCode");

			$personLocation['locationEvent']="Chnge";
			foreach($fields as $key => $val)
				$personLocation[$val]=$data[$key];

			if(count($shData['personLocation'])>0)
			{
				$cfields = array("locationCode" => "locationCode");
				if(!$this->dataCheck($data,$shData['personLocation'],$cfields,count($shData['personLocation'])-1))
					return $this->shUpdateAPI("personLocation",$personLocation);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personLocation",$personLocation);
			}
		}

		return false;
	}

	function updatePersonPayroll($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personPayroll"))
		{
			$data['payrollevent']="Enrol";
			$data['payrolleventDescription']="Enrollment";
			$data['payrollstatus']="A";
			$data['payrolltype']="Y";
			$data['payunitrelationship']="M";
			$data['payunitrelationshipDescription']="Member";

			$fields = array("effectiveDate" => "effectiveDate", "empNo" => "empNo", "payUnitCode" => "payUnitCode", "payrollevent" => "payrollevent", "payrolleventDescription" => "payrolleventDescription", "payrollstatus" => "payrollstatus", "payrolltype" => "payrolltype", "payunitrelationship" => "payunitrelationship", "payunitrelationshipDescription" => "payunitrelationshipDescription");

			foreach($fields as $key => $val)
				$personPayroll[$val]=$data[$key];

			if(count($shData['personPayroll'])>0)
			{
				$cfields = array("payUnitCode" => "payUnitCode","payrollstatus" => "payrollstatus");
				if(!$this->dataCheck($data,$shData['personPayroll'],$cfields,count($shData['personPayroll'])-1))
					return $this->shUpdateAPI("personPayroll",$personPayroll);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personPayroll",$personPayroll);
			}
		}

		return false;
	}

	function updatePersonStatus($personData,$status)
	{
		global $db;

		if($status=="Y")
			$this->msgDebug($personData['empNo']." :: ".$personData['fName']." ".$personData['lName']." :: Pushed Successfully");
		else if($status=="U")
			$this->msgDebug($personData['empNo']." :: ".$personData['fName']." ".$personData['lName']." :: Updated Successfully");
		else if($status=="F")
			$this->msgDebug($personData['empNo']." :: ".$personData['fName']." ".$personData['lName']." :: Push Failed");

		$uque="UPDATE syncHR_personData SET process='$status', pdate=NOW(),shError='".addslashes($this->client->res_error)."',bcode='".addslashes($this->bcode)."' WHERE sno='".$personData['sno']."'";
		mysql_query($uque,$db);

		$ique="INSERT INTO syncHR_Log (type,parid,log,ltime) VALUES ('E','".$personData['sno']."','".addslashes($this->debug())."',NOW())";
		mysql_query($ique,$db);

		$this->clear_debug();
	}

	function updatePersonLog($personData)
	{
		global $db;

		if(trim($this->debug())!="")
		{
			$uque="UPDATE syncHR_Log SET log=CONCAT(log,'".addslashes($this->debug())."'),shError=CONCAT(shError,'".addslashes($this->client->res_error)."') WHERE type='E' AND parid='".$personData['sno']."'";
			mysql_query($uque,$db);
		}

		$this->clear_debug();
	}

	function updatePerson($personData)
	{
		$this->updatePersonName($personData);
		$this->updatePersonAddress($personData);
		$this->updatePersonPhone($personData);
		$this->updatePersonEmail($personData);

		if(trim($personData['DBA'])!="" && $personData['emplStatus']=="C")
			$this->updatePersonDBA($personData);

		$this->updatePersonVitals($personData);
		$this->updatePersonLocation($personData);

		if($personData['acStatus']=="T")
		{
			$this->terminateEmployment($personData);
			$this->terminatePersonPosition($personData);
		}
		else if($personData['acStatus']=="R")
		{
			$this->rehireEmployment($personData);
		}
		else
		{
			$this->updatePersonEmployment($personData);
		}

		$this->updatePersonStatus($personData,"U");
	}

	//******** POSITION FUNCTIONS **********//

	function getPersonPositionData($personData)
	{
		global $db;

		$positionData=array();

		$pque="SELECT sno,username,empNo,positionCode,positionTitle,positionEvent,effectiveDate,sDate,scheduleFrequency,scheduledHours,partialPercent,persPosEvent,managingPosition,hrOrganization as orgCode,IF(eeoCode=0,50,eeoCode),companyOfficer,flsaProfile,flsaCode,mgmtClass,grade,workersCompProfile,workersCompCode,orgCode,budgetOrgCode,posOrgPercent,earningsCode,frequencyCode,currencyCode,compEvent,compAmount,compLimit,increaseAmount,compLimitCode,shiftCode,payOvertime,payUnitCode,SSN FROM syncHR_positionData WHERE process='N' AND username='".$personData['username']."' AND parid='".$personData['sno']."'";
		$pres=mysql_query($pque,$db);
		$prow=mysql_fetch_assoc($pres);
		if(mysql_num_rows($pres)>0)
			$positionData=$prow;
		else
			$positionData=false;

		return $positionData;
	}

	function checkPositionStatus($positionCode,$endpoint)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$data['positionCode'][0] = "eq";
		$data['positionCode'][1] = $positionCode;

		$filter = array("filter" => json_encode($data));

		return $this->shListAPI($endpoint,$filter);
	}

	function insertPositionData($personData)
	{
		if($positionData=$this->getPersonPositionData($personData))
		{
			if($this->isPreviousDate($positionData['sDate'],$personData['emplHireDate']))
				$positionData['sDate']=$personData['emplHireDate'];

			if($this->isPreviousDate($personData['emplHireDate'],$positionData['effectiveDate']))
			{
				$positionData['effectiveDate']=$personData['emplHireDate'];
				$positionData['sDate']=$personData['emplHireDate'];
			}

			$shPositionData=$this->createPositionData($positionData);
			if($shPositionData)
			{
				$this->createPersonCompensation($positionData['sDate'],$positionData);
				$this->createPersonPostion($positionData['sDate'],$positionData);
				$this->createPersonPayroll($positionData['sDate'],$positionData['empNo'],$positionData['payUnitCode']);
				$this->createPositionBudgetOrganization($positionData);
				$this->updatePositionStatus($positionData,"Y");
			}
			else
			{
				$this->updatePositionStatus($positionData,"F");
			}
		}
	}

	function insertNewPositionData($positionData)
	{
		$shPositionData=$this->createPositionData($positionData);
		if($shPositionData)
		{
			$this->updatePersonCompensation($positionData);
			$this->createPersonPostion($positionData['effectiveDate'],$positionData);
			$this->updatePersonPayroll($positionData['effectiveDate'],$positionData);
			$this->createPositionBudgetOrganization($positionData);
			$this->updatePositionStatus($positionData,"Y");
		}
		else
		{
			$this->updatePositionStatus($positionData,"F");
		}
	}

	function createPositionData($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$fields = array("positionCode","positionTitle","positionEvent","effectiveDate","sDate","orgCode","eeoCode","companyOfficer","flsaProfile","flsaCode","mgmtClass","grade","workersCompProfile","workersCompCode","shiftCode","payOvertime");

		foreach($fields as $key => $val)
			$positionData[$val]=$data[$val];

		return $this->shCreateAPI("positionData",$positionData);
	}

	function createPersonCompensation($effectiveDate,$data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$personCompensation['effectiveDate']=$effectiveDate;

		$fields = array("compEvent","empNo","positionCode","earningsCode","frequencyCode","currencyCode","compAmount","compLimit","increaseAmount","compLimitCode");

		foreach($fields as $key => $val)
			$personCompensation[$val]=$data[$val];

		if($personCompensation['earningsCode']=="1099H")
			$personCompensation['earningsCodeDescription']="1099H:1099 Hours";
		else
			$personCompensation['earningsCodeDescription']="RegHrly:Regular Hourly";

		return $this->shCreateAPI("personCompensation",$personCompensation);
	}

	function createPersonPostion($effectiveDate,$data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$personPosition['effectiveDate']=$effectiveDate;

		$fields = array("persPosEvent","empNo","positionCode","scheduleFrequency","scheduledHours","partialPercent");

		foreach($fields as $key => $val)
			$personPosition[$val]=$data[$val];

		return $this->shCreateAPI("personPosition",$personPosition);
	}

	function createPositionBudgetOrganization($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$fields = array("effectiveDate","positionCode","budgetOrgCode","posOrgPercent");

		$positionBudgetOrganization['posOrgRelEvent'] = "NewPos";
		foreach($fields as $key => $val)
			$positionBudgetOrganization[$val]=$data[$val];

		return $this->shCreateAPI("positionBudgetOrganization",$positionBudgetOrganization);
	}

	function updatePositionData($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPositionStatus($data['positionCode'],"positionData"))
		{
			$fields = array("positionCode" => "positionCode","effectiveDate" => "effectiveDate","positionTitle" => "positionTitle","flsaProfile" => "flsaProfile","workersCompProfile" => "workersCompProfile","workersCompCode" => "workersCompCode");

			$positionData['positionEvent']="Title";
			foreach($fields as $key => $val)
				$positionData[$val]=$data[$key];

			if(count($shData['positionData'])>0)
			{
				$cfields = array("positionTitle" => "positionTitle","flsaProfile" => "flsaProfile","workersCompProfile" => "workersCompProfile","workersCompCode" => "workersCompCode");
				if(!$this->dataCheck($data,$shData['positionData'],$cfields,count($shData['positionData'])-1))
					return $this->shUpdateAPI("positionData",$positionData);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("positionData",$positionData);
			}
		}

		return false;
	}

	function updatePersonCompensation($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personCompensation"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","earningsCode" => "earningsCode","frequencyCode" => "frequencyCode","currencyCode" => "currencyCode","compAmount" => "compAmount");

			$personCompensation['compEvent']="Temp";
			foreach($fields as $key => $val)
				$personCompensation[$val]=$data[$key];
	
			if($personCompensation['earningsCode']=="1099H")
				$personCompensation['earningsCodeDescription']="1099H:1099 Hours";
			else
				$personCompensation['earningsCodeDescription']="RegHrly:Regular Hourly";

			if(count($shData['personCompensation'])>0)
			{
				$shElement=$this->checkElement($shData['personCompensation'],"earningsCode",$data['earningsCode']);
				if($shElement>=0)
				{
					$cfields = array("frequencyCode" => "frequencyCode","currencyCode" => "currencyCode","compAmount" => "compAmount","earningsCode" => "earningsCode");
					if(!$this->dataCheck($data,$shData['personCompensation'],$cfields,$shElement))
						return $this->shUpdateAPI("personCompensation",$personCompensation);
					else
						return true;
				}
				else
				{
					return $this->shCreateAPI("personCompensation",$personCompensation);
				}
			}
			else
			{
				return $this->shCreateAPI("personCompensation",$personCompensation);
			}
		}

		return false;
	}

	function checkPersonPositionStatus($data,$posCode)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personPosition"))
		{
			if(count($shData['personPosition'])>0)
			{
				$syncHrData=$shData['personPosition'][count($shData['personPosition'])-1];
				if($syncHrData['positionCode']!=$posCode)
					return $syncHrData['positionCode'];
			}
		}

		return "";
	}

	function updatePersonPosition($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personPosition"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","positionCode" => "positionCode","scheduleFrequency" => "scheduleFrequency","scheduledHours" => "scheduledHours","partialPercent" => "partialPercent");

			$personPosition['persPosEvent']="NewAssgmnt";
			foreach($fields as $key => $val)
				$personPosition[$val]=$data[$key];

			if(count($shData['personPosition'])>0)
			{
				$cfields = array("positionCode" => "positionCode","scheduleFrequency" => "scheduleFrequency","scheduledHours" => "scheduledHours","partialPercent" => "partialPercent");
				if(!$this->dataCheck($data,$shData['personPosition'],$cfields,count($shData['personPosition'])-1))
					return $this->shUpdateAPI("personPosition",$personPosition);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personPosition",$personPosition);
			}
		}

		return false;
	}

	function updatePositionBudgetOrganization($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPositionStatus($data['positionCode'],"positionBudgetOrganization"))
		{
			$fields = array("effectiveDate" => "effectiveDate","positionCode" => "positionCode","budgetOrgCode" => "budgetOrgCode","posOrgPercent" => "posOrgPercent");

			$positionBudgetOrganization['posOrgRelEvent']="NewPos";
			foreach($fields as $key => $val)
				$positionBudgetOrganization[$val]=$data[$key];

			if(count($shData['positionBudgetOrganization'])>0)
			{
				$cfields = array("budgetOrgCode" => "budgetOrgCode");
				if(!$this->dataCheck($data,$shData['positionBudgetOrganization'],$cfields,count($shData['positionBudgetOrganization'])-1))
					return $this->shUpdateAPI("positionBudgetOrganization",$positionBudgetOrganization);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("positionBudgetOrganization",$positionBudgetOrganization);
			}
		}

		return false;
	}

	function updatePositionStatus($positionData,$status)
	{
		global $db;

		if($status=="Y")
			$this->msgDebug($positionData['positionCode']." :: ".$positionData['positionTitle']." :: Pushed Successfully");
		else if($status=="U")
			$this->msgDebug($positionData['positionCode']." :: ".$positionData['positionTitle']." :: Updated Successfully");
		else if($status=="F")
			$this->msgDebug($positionData['positionCode']." :: ".$positionData['positionTitle']." :: Push Failed");

		$uque="UPDATE syncHR_positionData SET process='$status', pdate=NOW(),shError='".addslashes($this->client->res_error)."',bcode='".addslashes($this->bcode)."' WHERE sno='".$positionData['sno']."'";
		mysql_query($uque,$db);

		$ique="INSERT INTO syncHR_Log (type,parid,log,ltime) VALUES ('A','".$positionData['sno']."','".addslashes($this->debug())."',NOW())";
		mysql_query($ique,$db);

		$this->clear_debug();
	}

	function updatePositionLog($positionData)
	{
		global $db;

		if(trim($this->debug())!="")
		{
			$uque="UPDATE syncHR_Log SET log=CONCAT(log,'".addslashes($this->debug())."'),shError=CONCAT(shError,'".addslashes($this->client->res_error)."') WHERE type='A' AND parid='".$positionData['sno']."'";
			mysql_query($uque,$db);
		}

		$this->clear_debug();
	}

	function updatePosition($posData,$positionCode)
	{
		if($positionCode=="")
		{
			$this->updatePositionData($posData);
			$this->updatePersonCompensation($posData);
			$this->updatePersonPosition($posData);
			$this->updatePersonPayroll($posData);
			$this->updatePositionBudgetOrganization($posData);

			$this->updatePositionStatus($posData,"U");
		}
		else
		{
			if($shData=$this->checkPositionStatus($posData['positionCode'],"positionData"))
			{
				if(count($shData['positionData'])>0)
				{
					$this->updatePosition($posData,"");
				}
				else
				{
					$sedate = explode("-",$posData['sDate']);
					$yesday = mktime(0,0,0,$sedate[1],$sedate[2]-1,$sedate[0]);
					$endDate = date("Y-m-d",$yesday);

					$posData['endDate']=$endDate;
					$this->terminatePersonPosition($posData);

					$posData['effectiveDate']=$posData['sDate'];
					$this->insertNewPositionData($posData);
				}
			}
		}
	}

	function updatePersonPositionStatus($personsData,$status)
	{
		$this->updatePersonStatus($personsData,$status);

		if($personPositionData=$this->getPersonPositionData($personsData))
			$this->updatePositionStatus($personPositionData,$status);
	}

	//******** TIME FUNCTIONS **********//

	function getTimeControlBatchData($locid)
	{
		global $db;

		$timeControlBatchData=array();

		$tcque="SELECT sno,locid,payunit as payUnitCode,payedate as periodEndDate,payprocess as payProcessType,paybname as batchName, IF(payprocess='R','Regular','Supplement') as payProcessTypeDescription, SUBSTRING(MD5(UNIX_TIMESTAMP()),1,8) as payTimeControlBatchCode FROM syncHR_payUnitBatch WHERE locid='$locid' AND process='N'";
		$tcres=mysql_query($tcque,$db);
		while($tcrow=mysql_fetch_assoc($tcres))
			$timeControlBatchData[]=$tcrow;

		return $timeControlBatchData;
	}

	function checkTimeControlBatchStatus($payUnitCode,$endpoint)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($payUnitCode!="")
		{
			$data['payUnitCode'][0] = "eq";
			$data['payUnitCode'][1] = $payUnitCode;
		}

		$filter = array("filter" => json_encode($data));

		return $this->shListAPI($endpoint,$filter);
	}

	function createPayTimeControlBatch($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$fields = array("batchName","payUnitCode","periodEndDate","payProcessType","payProcessTypeDescription","payTimeControlBatchCode");
		foreach($fields as $key => $val)
			$payTimeControlBatch[0][$val]=$data[$val];

		return $this->shCreateAPI("payTimeControlBatch",$payTimeControlBatch);
	}

	function deletePayTimeControlBatch($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$fields = array("payUnitCode","payTimeControlBatchCode");
		foreach($fields as $key => $val)
			$payTimeControlBatch[$val]=$data[$val];

		return $this->shDeleteAPI("payTimeControlBatch",$payTimeControlBatch);
	}

	function updatePayTimeControlBatchStatus($timeControlBatchData,$status)
	{
		global $db;

		if($status=="Y")
			$this->msgDebug($timeControlBatchData['payUnitCode']." :: ".$timeControlBatchData['payTimeControlBatchCode']." :: Pushed Successfully");
		else if($status=="F")
			$this->msgDebug($timeControlBatchData['payUnitCode']." :: ".$timeControlBatchData['payTimeControlBatchCode']." :: Push Failed");

		$uque="UPDATE syncHR_payUnitBatch SET paybcode='".$timeControlBatchData['payTimeControlBatchCode']."',process='$status', pdate=NOW(),shError='".addslashes($this->client->res_error)."' WHERE sno='".$timeControlBatchData['sno']."'";
		mysql_query($uque,$db);

		$ique="INSERT INTO syncHR_Log (type,parid,log,ltime) VALUES ('B','".$timeControlBatchData['sno']."','".addslashes($this->debug())."',NOW())";
		mysql_query($ique,$db);

		$this->clear_debug();
	}

	function getPersonTimeData($parid,$payUnitCode,$payTimeControlBatchCode,$periodEndDate,$payProcessType)
	{
		global $db;

		$personTimeData=array();

		$ptque="SELECT sno as sno,CONCAT('AC-',sno) as personTimeCode,'$payTimeControlBatchCode' as payTimeControlBatchCode,'$payUnitCode' as payUnitCode,'$periodEndDate' as periodEndDate,'$payProcessType' as payProcessType,empNo as empNo,orgCode,edtCode,workStartDate,hours,locationCode,payRate,payAmount,activityCode,deductionClass,workersCompCode,checkTag,budgetOrgCode,RefID,RefType FROM syncHR_payData WHERE parid='".$parid."'";
		$ptres=mysql_query($ptque,$db);
		while($ptrow=mysql_fetch_assoc($ptres))
			$personTimeData[]=$ptrow;

		return $personTimeData;
	}

	function checkPersonTimeStatus($payTimeControlBatchCode,$empNo,$endpoint)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$data['payTimeControlBatchCode'][0] = "eq";
		$data['payTimeControlBatchCode'][1] = $payTimeControlBatchCode;

		$data['empNo'][0] = "eq";
		$data['empNo'][1] = $empNo;

		$filter = array("filter" => json_encode($data));

		return $this->shListAPI($endpoint,$filter);
	}

	function createPersonTime($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$fields = array("personTimeCode","empNo","payTimeControlBatchCode","payUnitCode","workStartDate","periodEndDate","payProcessType","edtCode","hours","payRate","payAmount","activityCode","deductionClass");
		foreach($fields as $key => $val)
			$personTime[$val]=$data[$val];

		return $this->shCreateAPI("personTime",$personTime);
	}

	function updatePersonTimeStatus($personTimeData,$payTimeControlBatchCode,$status)
	{
		global $db;

		if($status=="Y")
		{
			$this->msgDebug($personTimeData['empNo']." :: ".$personTimeData['workStartDate']." :: Pushed Successfully");
			$synchr_flag="Y";
		}
		else if($status=="F")
		{
			$this->msgDebug($personTimeData['empNo']." :: ".$personTimeData['workStartDate']." :: Push Failed");
			$synchr_flag="N";
		}

		if($personTimeData['RefType']=="Time")
		{
			$tuque="UPDATE timesheet_hours SET synchr='$synchr_flag',synchr_time=NOW() WHERE sno='".$personTimeData['RefID']."'";
			mysql_query($tuque,$db);
		}

		if($personTimeData['RefType']=="Expense")
		{
			$euque="UPDATE expense, SET synchr='$synchr_flag',synchr_time=NOW() WHERE sno='".$personTimeData['RefID']."'";
			mysql_query($euque,$db);
		}

		$uque="UPDATE syncHR_payData SET paybcode='$payTimeControlBatchCode',process='$status', pdate=NOW(), shError='".addslashes($this->client->res_error)."' WHERE sno='".$personTimeData['sno']."'";
		mysql_query($uque,$db);

		$ique="INSERT INTO syncHR_Log (type,parid,log,ltime) VALUES ('T','".$personTimeData['sno']."','".addslashes($this->debug())."',NOW())";
		mysql_query($ique,$db);

		$this->clear_debug();
	}

	function notifyBatchStatus()
	{
		global $pri_production,$WDOCUMENT_ROOT,$db;

		if($this->generateLogFiles()>0)
		{
			$bfolder=$WDOCUMENT_ROOT.$this->bcode;
			$zipfile=$bfolder."/".$this->bcode.".zip";
	
			$bfolder=$WDOCUMENT_ROOT.$this->bcode."/".$this->bcode;
			$csvfile=$bfolder."/".$this->bcode.".csv";

			if($pri_production)
				$to="ssorrells@qgsearch.com, s.anderson@qgsearch.com, bhofstadter@quesths.com, ijohnson2@qgsearch.com, czugel@qgsearch.com, rvempati@akkencloud.com";
			else
				$to="rvempati@akkencloud.com";
	
			$from="donot-reply@akken.com";	
			$subject=$this->bcode." :: Status of SyncHR Person / Position Data Push";
	
			$mime_boundary="==Message-Boundary-".md5(time());
	
			$headers="From: $from \r\n";
			$headers.="MIME-Version: 1.0 \r\n";			
			$headers.="Content-type: multipart/mixed; boundary="."\"".$mime_boundary."\""."\r\n\r\n";
	
			$message="--".$mime_boundary."\r\n";
			$message.="Content-Type: text/html; charset=iso-8859-1"."\r\n\r\n";
			$message.=wordwrap("<br><p>We have pushed the data to SyncHR. However, few records errored out. Please find the CSV file and correct them as needed.</p>",75,"\n")."\r\n\r\n";
	
			if(file_exists($csvfile))
			{
				$message.="--".$mime_boundary."\r\n";
				$message.="Content-Type: text/csv; name="."\"".$this->bcode.".csv"."\""."\r\n";
	
				$fp=@fopen($csvfile, 'rb');
				$file_size=filesize($csvfile);
				$data=@fread($fp,$file_size);
				$data=chunk_split(base64_encode($data));
				unlink($csvfile);
	
				$message.="Content-Disposition: attachment; filename="."\"".$this->bcode.".csv"."\""."; size="."\"".$file_size."\"".";"."\r\n";
				$message.="Content-Transfer-Encoding: base64"."\r\n\r\n";
				$message.=$data;
				$message.="\r\n\r\n";
			}
	
			if(file_exists($zipfile))
			{
				$message.="--".$mime_boundary."\r\n";
				$message.="Content-Type: application/x-zip; name="."\"".$this->bcode.".zip"."\""."\r\n";
	
				$fp=@fopen($zipfile, 'rb');
				$file_size=filesize($zipfile);
				$data=@fread($fp,$file_size);
				$data=chunk_split(base64_encode($data));
				unlink($zipfile);
	
				$message.="Content-Disposition: attachment; filename="."\"".$this->bcode.".zip"."\""."; size="."\"".$file_size."\"".";"."\r\n";
				$message.="Content-Transfer-Encoding: base64"."\r\n\r\n";
				$message.=$data;
				$message.="\r\n\r\n";
			}

			$message.="--".$mime_boundary."--";
	
			mail($to,$subject,$message,$headers);
	
			if(is_dir($WDOCUMENT_ROOT.$this->bcode))
				exec("rm -fr ".$WDOCUMENT_ROOT.$this->bcode);
		}
	}

	function generateLogFiles()
	{
		global $WDOCUMENT_ROOT,$db;

		$bque="SELECT e.process as Processed,e.SSN as SSN,e.acStatus as acStatus,e.sno as esno,a.sno as asno,e.empNo,e.effectiveDate,e.emplHireDate,e.emplLastHireDate,e.payThroughDate,e.fName,e.mName,e.lName,e.locationCode,e.emplStatus,e.emplPermanency,e.employmentType,e.emplClass,e.emplFulltimePercent,e.emplServiceDate,e.emplSenorityDate,e.emplBenefithireDate,e.streetAddress,e.streetAddress2,e.countryCode,e.city,e.stateProvinceCode,e.postalCode,e.phoneno,e.emailAddress,e.shError as eshError,a.positionCode,a.positionTitle,a.hrOrganization,a.eeoCode,a.companyOfficer,a.flsaProfile,a.flsaCode,a.mgmtClass,a.grade,a.workersCompProfile,a.workersCompCode,a.shiftCode,a.payOvertime,a.orgCode,a.shError as ashError FROM syncHR_personData e LEFT JOIN syncHR_positionData a ON e.sno=a.parid WHERE e.acStatus!='T' AND e.process!='N' AND a.process!='N' AND e.bcode='".$this->bcode."' AND a.bcode='".$this->bcode."' AND (e.shError!='' OR a.shError!='') UNION SELECT e.process as Processed,e.SSN as SSN,e.acStatus as acStatus,e.sno as esno,'' as asno,e.empNo,e.effectiveDate,e.emplHireDate,e.emplLastHireDate,e.payThroughDate,e.fName,e.mName,e.lName,e.locationCode,e.emplStatus,e.emplPermanency,e.employmentType,e.emplClass,e.emplFulltimePercent,e.emplServiceDate,e.emplSenorityDate,e.emplBenefithireDate,e.streetAddress,e.streetAddress2,e.countryCode,e.city,e.stateProvinceCode,e.postalCode,e.phoneno,e.emailAddress,e.shError as eshError,'' as positionCode,'' as positionTitle,'' as hrOrganization,'' as eeoCode,'' as companyOfficer,'' as flsaProfile,'' as flsaCode,'' as mgmtClass,'' as grade,'' as workersCompProfile,'' as workersCompCode,'' as shiftCode,'' as payOvertime,'' as orgCode,'' as ashError FROM syncHR_personData e WHERE e.acStatus='T' AND e.process!='N' AND e.bcode='".$this->bcode."' AND e.shError!=''";
		$bres=mysql_query($bque,$db);
		$bcount=mysql_num_rows($bres);
		if($bcount>0)
		{
			$bfolder=$WDOCUMENT_ROOT.$this->bcode;
			mkdir($bfolder,0777);

			$bfolder=$WDOCUMENT_ROOT.$this->bcode."/".$this->bcode;
			mkdir($bfolder,0777);

			$fields = array("empNo","effectiveDate","emplHireDate","emplLastHireDate","payThroughDate","SSN","fName","mName","lName","locationCode","emplStatus","emplPermanency","employmentType","emplClass","emplFulltimePercent","emplServiceDate","emplSenorityDate","emplBenefithireDate","streetAddress","streetAddress2","countryCode","city","stateProvinceCode","postalCode","phoneno","emailAddress","positionCode","positionTitle","hrOrganization","eeoCode","companyOfficer","flsaProfile","flsaCode","mgmtClass","grade","workersCompProfile","workersCompCode","shiftCode","payOvertime","orgCode","Processed","eshError","ashError");
			$csv_content='"'.implode('","',$fields).'"'."\n";

			while($brow=mysql_fetch_assoc($bres))
			{
				foreach($fields as $key => $val)
				{
					$nrow[$val]=stripslashes(str_replace("\r\n","",str_replace("\n","",$brow[$val])));
					$contents=$this->generateCsv($nrow);
				}
				$csv_content.=$contents;
	
				$csvfile=$bfolder."/".$this->bcode.".csv";
				$fp=fopen($csvfile,"w");
				fwrite($fp, $csv_content);
				fclose($fp);
	
				$eque="SELECT log FROM syncHR_Log WHERE type='E' AND parid='".$brow['esno']."'";
				$eres=mysql_query($eque,$db);
				$erow=mysql_fetch_assoc($eres);

				if($brow['acStatus']!="T")
				{
					$aque="SELECT log FROM syncHR_Log WHERE type='A' AND parid='".$brow['asno']."'";
					$ares=mysql_query($aque,$db);
					$arow=mysql_fetch_assoc($ares);

					$content=$erow['log']."<BR><BR>".$arow['log'];
					$logfile=$bfolder."/".$brow['esno']."_".$brow['empNo']."_".$brow['positionCode'].".html";
				}
				else
				{
					$content=$erow['log'];
					$logfile=$bfolder."/".$brow['esno']."_".$brow['empNo'].".html";
				}
	
				$fp=fopen($logfile,"w");
				fwrite($fp, $content);
				fclose($fp);
			}
	
			chdir($WDOCUMENT_ROOT.$this->bcode);
			exec("zip -r ".$this->bcode.".zip ".$this->bcode);
			sleep(5);
		}

		return $bcount;
	}

	function notifyTimeBatchStatus($batchId)
	{
		global $pri_production,$WDOCUMENT_ROOT,$db;

		$bque="SELECT l.heading,b.payunit,b.paysdate,b.payedate,b.paybname,b.paybcode,b.payprocess,b.shError,b.process FROM syncHR_payUnitBatch b, contact_manage l WHERE b.locid=l.serial_no AND b.sno='$batchId'";
		$bres=mysql_query($bque,$db);
		$brow=mysql_fetch_assoc($bres);

		if($brow['process']=="F")
		{
			$status_details="Batch was failed with the error : ".$brow['shError'];
			$status_subj=$brow['paybcode']." :: FAILED";
		}
		else
		{
			$pque="SELECT COUNT(1) as cnt FROM syncHR_payData WHERE process='F' AND parid='$batchId'";
			$pres=mysql_query($pque,$db);
			$prow=mysql_fetch_assoc($pres);
			if($prow['cnt']==0)
			{
				$status_details="Batch was created successfully with out errors.";
				$status_subj=$brow['paybcode']." :: SUCCESS";
			}
			else
			{
				$status_details="Batch was created succeffully. However, few pay roll items are errored.";
				$status_subj=$brow['paybcode']." :: PARTIALLY SUCCESS";
			}
		}

		if($pri_production)
			$to="ssorrells@qgsearch.com, s.anderson@qgsearch.com, bhofstadter@quesths.com, ijohnson2@qgsearch.com, czugel@qgsearch.com, rvempati@akkencloud.com";
		else
			$to="rvempati@akkencloud.com";

		$from="donot-reply@akken.com";	
		$subject=$status_subj." :: Status of SyncHR Payroll Data Push";

		$orig_message="<br><p>\n";
		$orig_message.="<b>SyncHR Company File : </b>".$brow['heading']."<br>\n";
		$orig_message.="<b>Pay Unit Code : </b>".$brow['payunit']."<br>\n";
		$orig_message.="<b>Pay Process : </b>".$brow['payprocess']."<br>\n";
		$orig_message.="<b>Pay Period Start Date : </b>".$brow['paysdate']."<br>\n";
		$orig_message.="<b>Pay Period End Date : </b>".$brow['payedate']."<br>\n";
		$orig_message.="<b>Batch Name : </b>".$brow['paybname']."<br>\n";
		$orig_message.="<b>Batch Code : </b>".$brow['paybcode']."<br>\n";
		$orig_message.="<br><br>\n";
		$orig_message.="<b>Status : </b>".$status_details."<br>\n";
		$orig_message.="</p>\n";

		$this->bcode=$brow['paybcode'];
		if($this->generateTimeLogFile($batchId))
		{
			$bfolder=$WDOCUMENT_ROOT.$this->bcode;
			$csvfile=$bfolder."/".$this->bcode.".csv";

			$mime_boundary="==Message-Boundary-".md5(time());
		
			$headers="From: $from \r\n";
			$headers.="MIME-Version: 1.0 \r\n";			
			$headers.="Content-type: multipart/mixed; boundary="."\"".$mime_boundary."\""."\r\n\r\n";
		
			$message="--".$mime_boundary."\r\n";
			$message.="Content-Type: text/html; charset=iso-8859-1"."\r\n\r\n";
			$message.=wordwrap($orig_message,75,"\n")."\r\n\r\n";
		
			if(file_exists($csvfile))
			{
				$message.="--".$mime_boundary."\r\n";
				$message.="Content-Type: text/csv; name="."\"".$this->bcode.".csv"."\""."\r\n";
		
				$fp=@fopen($csvfile, 'rb');
				$file_size=filesize($csvfile);
				$data=@fread($fp,$file_size);
				$data=chunk_split(base64_encode($data));
				unlink($csvfile);
		
				$message.="Content-Disposition: attachment; filename="."\"".$this->bcode.".csv"."\""."; size="."\"".$file_size."\"".";"."\r\n";
				$message.="Content-Transfer-Encoding: base64"."\r\n\r\n";
				$message.=$data;
				$message.="\r\n\r\n";
			}
		
			$message.="--".$mime_boundary."--";
			mail($to,$subject,$message,$headers);
		
			if(is_dir($WDOCUMENT_ROOT.$this->bcode))
				exec("rm -fr ".$WDOCUMENT_ROOT.$this->bcode);
		}
	}

	function generateTimeLogFile($batchId)
	{
		global $WDOCUMENT_ROOT,$db;

		$bfolder=$WDOCUMENT_ROOT.$this->bcode;
		mkdir($bfolder,0777);

		$fields = array("empNo","FirstName","LastName","AssignmentID","AssignmentName","CustomerName","orgCode","edtCode","workStartDate","locationCode","hours","payRate","payAmount","activityCode","deductionClass","workersCompCode","budgetOrgCode","budgetOrgDesc","RefID","RefType","shError");
		$csv_content='"'.implode('","',$fields).'"'."\n";

		$bque="SELECT empNo,FirstName,LastName,AssignmentID,AssignmentName,CustomerName,orgCode,edtCode,workStartDate,locationCode,hours,payRate,payAmount,activityCode,deductionClass,workersCompCode,budgetOrgCode,budgetOrgDesc,shError,RefID,RefType FROM syncHR_payData WHERE parid='$batchId'";
		$bres=mysql_query($bque,$db);
		while($brow=mysql_fetch_assoc($bres))
		{
			foreach($fields as $key => $val)
			{
				$nrow[$val]=stripslashes(str_replace("\r\n","",str_replace("\n","",$brow[$val])));
				$contents=$this->generateCsv($nrow);
			}
			$csv_content.=$contents;
		}

		$csvfile=$bfolder."/".$this->bcode.".csv";
		$fp=fopen($csvfile,"w");
		fwrite($fp, $csv_content);
		fclose($fp);

		return true;
	}

	function generateCsv($data,$delimiter=',',$enclosure='"')
	{
		$contents="";
		$handle=fopen('php://temp', 'r+');
		fputcsv($handle,$data,$delimiter,$enclosure);
		rewind($handle);

		while(!feof($handle))
			$contents.=fread($handle, 8192);

		fclose($handle);

		return $contents;
	}
}
?>