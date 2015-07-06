<?php
require("class.RestHttpClient.php");
require("json_functions.inc");

class SyncHR
{
	private $client;

	private $apihost = 'stage-clients.synchr.com';
	private $apiport = '443';
	private $apipath = '/synchr/api/1.0/';

	public $debug = false;
	public $apiKey = "";
	public $token = "";

	function __construct()
	{
		global $pri_production;

		$this->client = new HttpClient($this->apihost, $this->apiport);

		if($pri_production)
			$this->apihost = 'clients.synchr.com';
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

	function dataCheck($aCloudData,$syncHrArray,$cfields)
	{
		$chkFlag=false;

		$syncHrData=$syncHrArray[count($syncHrArray)-1];

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
					{
						//print "FLOAT ::".floatval($syncHrData[$val])." :: ".floatval($aCloudData[$key])."<BR>";
						return false;
					}
				}
				else 
				{
					if($syncHrData[$val]!=$aCloudData[$key])
					{
						//print $syncHrData[$val]." :: ".$aCloudData[$key]."<BR>";
						return false;
					}
				}
			}
		}

		return $chkFlag;
	}

	//******** PERSON FUNCTIONS **********//

	function getPersonsData($locid)
	{
		global $db;

		$personsData=array();

		$pque="SELECT sno,username,acStatus,empNo,effectiveDate,emplHireDate,endDate,payThroughDate,fName,mName,lName,locationCode,benefitStatus,emplStatus,emplPermanency,employmentType,emplClass,emplFulltimePercent,emplServiceDate,emplSenorityDate,emplBenefithireDate,streetAddress,streetAddress2,countryCode,city,stateProvinceCode,postalCode,phoneno,emailAddress,birthDate,genderCode,netId,SSN,emplEvent FROM syncHR_personData WHERE locid='$locid' AND process='N' ORDER BY cdate";
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

	function updateEmpNo($shempNo,$acempNo)
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
				$uque="UPDATE emp_list SET syncHRempNo='$shempNo' WHERE syncHRempNo='$acempNo'";
				mysql_query($uque,$db);

				$uque="UPDATE syncHR_personData SET empNo='$shempNo' WHERE empNo='$acempNo'";
				mysql_query($uque,$db);

				$uque="UPDATE syncHR_positionData SET empNo='$shempNo' WHERE empNo='$acempNo'";
				mysql_query($uque,$db);
			}

			return true;
		}
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

		$fields = array("empNo","effectiveDate","emplHireDate","fName","mName","lName","locationCode","emplStatus","emplPermanency","employmentType","emplClass","emplFulltimePercent","emplServiceDate","emplSenorityDate","emplBenefithireDate","streetAddress","streetAddress2","countryCode","city","stateProvinceCode","postalCode","phoneno","emailAddress","emplEvent");
		foreach($fields as $key => $val)
			$personData[$val]=$data[$val];

		return $this->shCreateAPI("personData",$personData);
	}

	function insertPersonData($personData)
	{
		$shPersonData=$this->createPersonData($personData);
		if($shPersonData)
		{
			$shVitals=$this->createPersonVitals($personData['empNo'],$personData['birthDate'],$personData['genderCode'],$personData['effectiveDate']);
			$shSSN=$this->createPersonIdentity($personData['empNo'],$personData['SSN'],"SSN");

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

	function updatePersonName($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personName"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","fName" => "fname","mName" => "mname","lName" => "lname");

			$personName['nameevent']="NameChg";
			foreach($fields as $key => $val)
				$personName[$val]=$data[$key];

			if(count($shData['personName'])>0)
			{
				$cfields = array("fName" => "fname","mName" => "mname","lName" => "lname");
				if(!$this->dataCheck($data,$shData['personName'],$cfields))
					return $this->shUpdateAPI("personName",$personName);
				else
					return true;
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
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","streetAddress" => "streetAddress","streetAddress2" => "streetAddress2","countryCode" => "countryCode","city" => "city","stateProvinceCode" => "stateProvinceCode","postalCode" => "postalCode");

			$personAddress['emplEvent']="Status";
			foreach($fields as $key => $val)
				$personAddress[$val]=$data[$key];

			if(count($shData['personAddress'])>0)
			{
				$cfields = array("streetAddress" => "streetAddress","streetAddress2" => "streetAddress2","countryCode" => "countryCode","city" => "city","stateProvinceCode" => "stateProvinceCode","postalCode" => "postalCode");
				if(!$this->dataCheck($data,$shData['personAddress'],$cfields))
					return $this->shUpdateAPI("personAddress",$personAddress);
				else
					return true;
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
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personPhone"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","phoneno" => "phoneNo");

			foreach($fields as $key => $val)
				$personPhone[$val]=$data[$key];
	
			if(count($shData['personPhone'])>0)
			{
				$cfields = array("phoneno" => "phoneNo");
				if(!$this->dataCheck($data,$shData['personPhone'],$cfields))
					return $this->shUpdateAPI("personPhone",$personPhone);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personPhone",$personPhone);
			}
		}

		return false;
	}

	function updatePersonEmail($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personEmail"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","emailAddress" => "url");

			foreach($fields as $key => $val)
				$personEmail[$val]=$data[$key];
	
			if(count($shData['personEmail'])>0)
			{
				$cfields = array("emailAddress" => "url");
				if(!$this->dataCheck($data,$shData['personEmail'],$cfields))
					return $this->shUpdateAPI("personEmail",$personEmail);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personEmail",$personEmail);
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
				$personEmployment['emplEvent']="InvTerm";
			else if($data['acStatus']=="R")
				$personEmployment['emplEvent']="Rehire";
			else
				$personEmployment['emplEvent']="Status";

			foreach($fields as $key => $val)
				$personEmployment[$val]=$data[$key];

			if(count($shData['personEmployment'])>0)
			{
				$cfields = array("emplStatus" => "emplStatus","emplClass" => "emplClass","emplHireDate" => "emplHireDate","emplServiceDate" => "emplServiceDate","emplSenorityDate" => "emplSenorityDate","emplBenefithireDate" => "emplBenefithireDate");
				if(!$this->dataCheck($data,$shData['personEmployment'],$cfields))
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
		$data['emplEventdetCode']="H";

		$this->client->setAuthorization($this->apiKey, $this->token);

		if($shData=$this->checkPersonStatus($data['empNo'],"personEmployment"))
		{
			$fields = array("empNo" => "empNo","effectiveDate" => "effectiveDate","emplStatus" => "emplStatus","emplClass" => "emplClass","emplHireDate" => "emplHireDate","emplServiceDate" => "emplServiceDate","emplSenorityDate" => "emplSenorityDate","emplBenefithireDate" => "emplBenefithireDate","benefitStatus" => "benefitStatus","payThroughDate" => "payThroughDate","emplEvent" => "emplEvent","emplEventdetCode" => "emplEventdetCode");
	
			foreach($fields as $key => $val)
				$personEmployment[$val]=$data[$key];
	
			if(count($shData['personEmployment'])>0)
			{
				$cfields = array("emplStatus" => "emplStatus","emplHireDate" => "emplHireDate","benefitStatus" => "benefitStatus","payThroughDate" => "payThroughDate","emplEvent" => "emplEvent","emplEventdetCode" => "emplEventdetCode");
				if(!$this->dataCheck($data,$shData['personEmployment'],$cfields))
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
				if(!$this->dataCheck($data,$shData['personEmployment'],$cfields))
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
				if(!$this->dataCheck($data,$shData['personVitals'],$cfields))
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
				if(!$this->dataCheck($data,$shData['personLocation'],$cfields))
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

	function updatePersonStatus($personData,$status)
	{
		global $db;

		if($status=="Y")
			$this->msgDebug($personData['empNo']." :: ".$personData['fName']." ".$personData['lName']." :: Pushed Successfully");
		else if($status=="U")
			$this->msgDebug($personData['empNo']." :: ".$personData['fName']." ".$personData['lName']." :: Updated Successfully");
		else if($status=="F")
			$this->msgDebug($personData['empNo']." :: ".$personData['fName']." ".$personData['lName']." :: Push Failed");

		$uque="UPDATE syncHR_personData SET process='$status', pdate=NOW() WHERE sno='".$personData['sno']."'";
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
			$uque="UPDATE syncHR_Log SET log=CONCAT(log,'".addslashes($this->debug())."') WHERE type='E' AND parid='".$personData['sno']."'";
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

		$pque="SELECT sno,username,empNo,positionCode,positionTitle,positionEvent,effectiveDate,scheduleFrequency,scheduledHours,partialPercent,persPosEvent,managingPosition,hrOrganization,eeoCode,companyOfficer,flsaProfile,flsaCode,mgmtClass,grade,workersCompProfile,workersCompCode,orgCode,posOrgPercent,earningsCode,frequencyCode,currencyCode,compEvent,compAmount,compLimit,increaseAmount,compLimitCode,shiftCode,payOvertime,SSN FROM syncHR_positionData WHERE process='N' AND username='".$personData['username']."' AND parid='".$personData['sno']."'";
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
			$shPositionData=$this->createPositionData($positionData);
			if($shPositionData)
			{
				$this->createPersonCompensation($positionData);
				$this->createPersonPostion($positionData);
				$this->updatePositionStatus($positionData,"Y");
			}
			else
			{
				$this->updatePositionStatus($positionData,"F");
			}
		}
	}

	function createPositionData($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$fields = array("positionCode","positionTitle","positionEvent","effectiveDate","hrOrganization","eeoCode","companyOfficer","flsaProfile","flsaCode","mgmtClass","grade","workersCompProfile","workersCompCode","shiftCode","payOvertime","orgCode");

		foreach($fields as $key => $val)
			$positionData[$val]=$data[$val];

		return $this->shCreateAPI("positionData",$positionData);
	}

	function createPersonCompensation($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$fields = array("effectiveDate","compEvent","empNo","positionCode","earningsCode","frequencyCode","currencyCode","compAmount","compLimit","increaseAmount","compLimitCode");

		foreach($fields as $key => $val)
			$personCompensation[$val]=$data[$val];

		return $this->shCreateAPI("personCompensation",$personCompensation);
	}

	function createPersonPostion($data)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$fields = array("effectiveDate","persPosEvent","empNo","positionCode","scheduleFrequency","scheduledHours","partialPercent");

		foreach($fields as $key => $val)
			$personPosition[$val]=$data[$val];

		return $this->shCreateAPI("personPosition",$personPosition);
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
				if(!$this->dataCheck($data,$shData['positionData'],$cfields))
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

			if(count($shData['personCompensation'])>0)
			{
				$cfields = array("earningsCode" => "earningsCode","frequencyCode" => "frequencyCode","currencyCode" => "currencyCode","compAmount" => "compAmount");
				if(!$this->dataCheck($data,$shData['personCompensation'],$cfields))
					return $this->shUpdateAPI("personCompensation",$personCompensation);
				else
					return true;
			}
			else
			{
				return $this->shCreateAPI("personCompensation",$personCompensation);
			}
		}

		return false;
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
				if(!$this->dataCheck($data,$shData['personPosition'],$cfields))
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

	function updatePositionStatus($positionData,$status)
	{
		global $db;

		if($status=="Y")
			$this->msgDebug($positionData['positionCode']." :: ".$positionData['positionTitle']." :: Pushed Successfully");
		else if($status=="U")
			$this->msgDebug($positionData['positionCode']." :: ".$positionData['positionTitle']." :: Updated Successfully");
		else if($status=="F")
			$this->msgDebug($positionData['positionCode']." :: ".$positionData['positionTitle']." :: Push Failed");

		$uque="UPDATE syncHR_positionData SET process='$status', pdate=NOW() WHERE sno='".$positionData['sno']."'";
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
			$uque="UPDATE syncHR_Log SET log=CONCAT(log,'".addslashes($this->debug())."') WHERE type='A' AND parid='".$positionData['sno']."'";
			mysql_query($uque,$db);
		}

		$this->clear_debug();
	}

	function updatePosition($positionData)
	{
		$this->updatePositionData($positionData);
		$this->updatePersonCompensation($positionData);
		$this->updatePersonPosition($positionData);
		$this->updatePositionStatus($positionData,"U");
	}

	// Time Functions

	function checkTimeControlBatchStatus($payUnitCode,$endpoint)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$data['payUnitCode'][0] = "eq";
		$data['payUnitCode'][1] = $payUnitCode;
		$filter = array("filter" => json_encode($data));

		return $this->shListAPI($endpoint,$filter);
	}

	function checkTimeStatus($empNo,$endpoint)
	{
		$this->client->setAuthorization($this->apiKey, $this->token);

		$data['empNo'][0] = "eq";
		$data['empNo'][1] = $empNo;
		$filter = array("filter" => json_encode($data));

		return $this->shListAPI($endpoint,$filter);
	}
}
?>