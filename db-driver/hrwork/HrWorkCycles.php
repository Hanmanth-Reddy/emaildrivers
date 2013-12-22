<?php
	class HRWorkCycles
	{
		var $DEBUG = false;

		var $api_sslurl = "ssl://secure.usverify.com";
		var $api_url = "https://secure.usverify.com/xmlserver/xmlserver";
		var $api_hrurl = "https://secure.usverify.com/hrmgr/hrmgr";

		var $api_rescode = "40382";
		var $api_rusername = "akken_admin";
		var $api_rpassword = "Akken4237";
		var $document_root = "/var/www/fs";

		var $api_empcode = "";
		var $api_username = "";
		var $api_password = "";

		var $token_code = "";
		var $login_status = "";
		var $resp_status = "";

		var $emp_ssn = 0;
		var $doc_id = 0;

		function getConvStatus($status)
		{
			$cstatus = array("EMP_NEW" => "In Progress", "EMP_UPD" => "EMP Updated", "EMP_REV" => "EMP Reverified", "EMP_REH" => "EMP Rehired", "EMP_AETERM" => "EMP Terminated", "NEW_I9" => "NEW I9 Created", "DHS_UPD", "DHS Updated", "DHS_CLS" => "DHS Closed");
			return $cstatus($status);
		}

		function xmlRequest($plobParam,$xmlurl)
		{
			$xml_obj = Array2XML::createXML('xml_request', $plobParam);
			$xml_con = $xml_obj->saveXML();

			if($this->DEBUG)
			{
				print "<pre>";
				print "<b>XML Request : </b><br><br>";
				echo htmlspecialchars($xml_con);
				print "</pre>";
			}

			$fp = fsockopen($this->api_sslurl,443,$errno,$errstr,5);

			if($fp)
			{
				fclose($fp);

				$ch = curl_init($xmlurl);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_con);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$xml_resp = curl_exec($ch);
				curl_close($ch);

				if($this->DEBUG)
				{
					print "<pre>";
					print "<b>XML Response : </b><br><br>";
					echo htmlspecialchars($xml_resp);
					print "</pre>";
				}

				try
				{ 
					$response = new SimpleXMLElement($xml_resp);
					if($this->DEBUG)
					{
						print "<pre>";
						print_r($response);
						print "</pre>";
					}
	
					return $response;

				}
				catch (Exception $e) 
				{
					$this->resp_status="HRWork Cycles is DOWN. Please try again later.";
				}
			}
			else
			{
				$this->resp_status="HRWork Cycles is DOWN. Please try again later.";
			}
		}

		function ssoResponse()
		{
			$plobParam = array();
			$requesttime = date("Y-m-d",time())."T".date("H:i:sO",time());

			$plobParam = array('@attributes' => array('version' => '1.0','server' => 'USVXML1','app_server_id' => ''));
			$plobParam['authentication']['employer_code'] = $this->api_empcode;
			$plobParam['authentication']['username'] = $this->api_username;
			$plobParam['authentication']['password'] = $this->api_password;
			$plobParam['authentication']['request_time'] = $requesttime;
			$plobParam['authentication']['requesting_app_id'] = 1;
			$plobParam['signon_user'][0]['@attributes'] = array('request_id' => time());

			return $this->xmlRequest($plobParam,$this->api_url);
		}

		function isEmptyResp()
		{
			if(trim($this->resp_status)=="")
				return true;
			else
				return false;
		}


		function ssoParse($response)
		{
			if($this->isEmptyResp())
			{
				if(trim($response->signon_user->token)!="")
				{
					$this->login_status="SUCCESS";
					$this->token_code=$response->signon_user->token;
					return true;
				}
				else
				{
					$this->login_status=$response->signon_user->error_msg;
					return false;
				}
			}
			else
			{
				$this->login_status=$this->resp_status;
				return false;
			}
		}

		function dbResponse()
		{
			$plobParam = array();
			$requesttime = date("Y-m-d",time())."T".date("H:i:sO",time());
	
			$plobParam = array('@attributes' => array('version' => '1.0','server' => 'USVXML1','app_server_id' => ''));
			$plobParam['authentication']['employer_code'] = $this->api_empcode;
			$plobParam['authentication']['username'] = $this->api_username;
			$plobParam['authentication']['password'] = $this->api_password;
			$plobParam['authentication']['request_time'] = $requesttime;
			$plobParam['authentication']['requesting_app_id'] = 1;
			$plobParam['get_dashboard_statistics'][0]['@attributes'] = array('request_id' => time(), 'application_id' => '1');

			return $this->xmlRequest($plobParam,$this->api_url);
		}

		function dbParse($response)
		{
			if($this->isEmptyResp())
			{
				$attr = array();
				$cfields = $response->get_dashboard_statistics->attributes();
	
				foreach($cfields as $key => $val)
				{
					$val = (string) $val;
					if(trim($key)!="" && trim($val)!="")
						$attr[$key]=$val;
				}
	
				if($attr['result_msg']=="SUCCESS")
				{
					return true;
				}
				else
				{
					$this->resp_status=$response->get_dashboard_statistics->error_msg;
					return false;
				}
			}
			else
			{
				return false;
			}
		}

		function dbTitles($response)
		{
			$titles = array();

			foreach($response->get_dashboard_statistics->dashboard_statistics as $element)
			{
				foreach($element as $key => $val)
				{
					$val = (string) $val;
					$val = ($val=="N/A") ? 0 : $val;
					if(trim($key)!="" && trim($val)!="")
						$titles[$key]=$val;
				}
			}

			return $titles;
		}

		function dbLinks($response)
		{
			$links = array();

			foreach($response->get_dashboard_statistics->dashboard_statistics->link_codes as $element)
			{
				foreach($element as $key => $val)
				{
					$val = (string) $val;
					if(trim($key)!="" && trim($val)!="")
						$links[$key]=$val;
				}
			}

			return $links;
		}

		function transResponse()
		{
			$plobParam = array();
			$requesttime = date("Y-m-d",time())."T".date("H:i:sO",time());

			$plobParam = array('@attributes' => array('version' => '1.0','server' => 'USVXML1','app_server_id' => ''));
			$plobParam['authentication']['employer_code'] = $this->api_rescode;
			$plobParam['authentication']['username'] = $this->api_rusername;
			$plobParam['authentication']['password'] = $this->api_rpassword;
			$plobParam['authentication']['request_time'] = $requesttime;
			$plobParam['authentication']['requesting_app_id'] = 1;
			$plobParam['et_get_next_trans'][0]['@attributes'] = array('request_id' => time(), 'brand_id' => $this->api_rescode);

			return $this->xmlRequest($plobParam,$this->api_url);
		}

		function transParse($response)
		{
			if($this->isEmptyResp())
			{
				$attr = array();
				$cfields = $response->et_get_next_trans->attributes();

				foreach($cfields as $key => $val)
				{
					$val = (string) $val;
					if(trim($key)!="" && trim($val)!="")
						$attr[$key]=$val;
				}

				if($attr['result_msg']=="SUCCESS")
				{
					return true;
				}
				else if($attr['result_msg']=="FAILURE")
				{
					$this->resp_status=$response->et_get_next_trans->error_msg;
					return false;
				}
				else
				{
					$this->resp_status=$attr['result_msg'];
					return false;
				}
			}
			else
			{
				return false;
			}
		}

		function transEmpInfo($response)
		{
			$empInfo = array();

			$cfields = $response->et_get_next_trans->usv_transmission->attributes();
			foreach($cfields as $key => $val)
			{
				$val = (string) $val;
				if(trim($key)!="" && trim($val)!="")
					$empInfo[$key]=addslashes($val);
			}

			$cfields = $response->et_get_next_trans->usv_transmission->employee_record->attributes();
			foreach($cfields as $key => $val)
			{
				$val = (string) $val;
				if(trim($key)!="" && trim($val)!="")
					$empInfo[$key]=addslashes($val);
			}

			foreach($response->et_get_next_trans->usv_transmission->employee_record->emp_info as $element)
			{
				foreach($element as $key => $val)
				{
					$val = (string) $val;
					if(trim($key)!="" && trim($val)!="")
						$empInfo[$key]=addslashes($val);
				}
			}

			foreach($response->et_get_next_trans->usv_transmission->employee_record->emp_info->address as $element)
			{
				foreach($element as $key => $val)
				{
					$val = (string) $val;
					if(trim($key)!="" && trim($val)!="")
						$empInfo[$key]=addslashes($val);
				}
			}

			foreach($response->et_get_next_trans->usv_transmission->employee_record->dhs as $element)
			{
				foreach($element as $key => $val)
				{
					$val = (string) $val;
					if(trim($key)!="" && trim($val)!="")
						$empInfo[$key]=addslashes($val);
				}
			}

			return $empInfo;
		}

		function transFormNames($response)
		{
			$formNames = array();

			foreach($response->et_get_next_trans->usv_transmission->employee_record->forms as $element)
			{
				$i=0;
				foreach($element as $key => $val)
				{
					$cfields = $response->et_get_next_trans->usv_transmission->employee_record->forms->form[$i]->attributes();
					foreach($cfields as $key1 => $val1)
					{
						$val1 = (string) $val1;
						if(trim($key1)!="" && trim($val1)!="")
							$formNames[$i][$key1]=addslashes($val1);
					}
					$i++;
				}
			}

			return $formNames;
		}

		function transFormFields($response,$i)
		{
			$temp="";
			$formFields = array();

			foreach($response->et_get_next_trans->usv_transmission->employee_record->forms->form[$i]->field as $element)
			{
				foreach($element as $key => $val)
				{
					$val = (string) $val;

					if($key=="name")
					{
						$temp=addslashes($val);
						$formFields[$temp]="";
					}
					else
					{
						$formFields[$temp]=addslashes($val);
					}
				}
			}

			return $formFields;
		}

		function transACK($trans_id)
		{
			$plobParam = array();
			$requesttime = date("Y-m-d",time())."T".date("H:i:sO",time());

			$plobParam = array('@attributes' => array('version' => '1.0','server' => 'USVXML1','app_server_id' => ''));
			$plobParam['authentication']['employer_code'] = $this->api_rescode;
			$plobParam['authentication']['username'] = $this->api_rusername;
			$plobParam['authentication']['password'] = $this->api_rpassword;
			$plobParam['authentication']['request_time'] = $requesttime;
			$plobParam['authentication']['requesting_app_id'] = 1;
			$plobParam['et_ack_trans'][0]['@attributes'] = array('request_id' => time(), 'brand_id' => $this->api_rescode);
			$plobParam['et_ack_trans'][0]['trans_id'] = $trans_id;
			$plobParam['et_ack_trans'][0]['status'] = 'PASS';

			return $this->xmlRequest($plobParam,$this->api_url);
		}

		function getSSN($str)
		{
			preg_match_all('/\d+/', $str, $matches);
			return implode("",$matches[0]);
		}

		function docsResponse()
		{
			$plobParam = array();
			$requesttime = date("Y-m-d",time())."T".date("H:i:sO",time());

			$plobParam = array('@attributes' => array('version' => '1.0','server' => 'USVXML1','app_server_id' => ''));
			$plobParam['authentication']['employer_code'] = $this->api_rescode;
			$plobParam['authentication']['username'] = $this->api_rusername;
			$plobParam['authentication']['password'] = $this->api_rpassword;
			$plobParam['authentication']['request_time'] = $requesttime;
			$plobParam['authentication']['requesting_app_id'] = 1;
			$plobParam['get_available_docs_emp'][0]['@attributes'] = array('request_id' => time(), 'application_id' => '1', 'actas_empid' => $this->api_empcode, 'ee_ssn' => $this->emp_ssn);

			return $this->xmlRequest($plobParam,$this->api_url);
		}

		function docsParse($response)
		{
			if($this->isEmptyResp())
			{
				$attr = array();
				$cfields = $response->get_available_docs_emp->attributes();

				foreach($cfields as $key => $val)
				{
					$val = (string) $val;
					if(trim($key)!="" && trim($val)!="")
						$attr[$key]=$val;
				}

				if($attr['result_msg']=="SUCCESS")
				{
					return true;
				}
				else if($attr['result_msg']=="FAILURE")
				{
					$this->resp_status=$response->get_available_docs_emp->error_msg;
					return false;
				}
				else
				{
					$this->resp_status=$attr['result_msg'];
					return false;
				}
			}
			else
			{
				return false;
			}
		}

		function docsIDs($response)
		{
			$docsInfo = array();

			foreach($response->get_available_docs_emp->documents as $element)
			{
				$i=0;
				foreach($element as $key => $val)
				{
					$cfields = $response->get_available_docs_emp->documents->document[$i]->attributes();
					foreach($cfields as $key1 => $val1)
					{
						$val1 = (string) $val1;
						if(trim($key1)!="" && trim($val1)!="")
							$docsInfo[$i][$key1]=addslashes($val1);
					}
					$i++;
				}
			}

			return $docsInfo;
		}

		function docResponse()
		{
			$plobParam = array();
			$requesttime = date("Y-m-d",time())."T".date("H:i:sO",time());

			$plobParam = array('@attributes' => array('version' => '1.0','server' => 'USVXML1','app_server_id' => ''));
			$plobParam['authentication']['employer_code'] = $this->api_rescode;
			$plobParam['authentication']['username'] = $this->api_rusername;
			$plobParam['authentication']['password'] = $this->api_rpassword;
			$plobParam['authentication']['request_time'] = $requesttime;
			$plobParam['authentication']['requesting_app_id'] = 1;
			$plobParam['get_employee_doc'][0]['@attributes'] = array('request_id' => time(), 'application_id' => '1', 'actas_empid' => $this->api_empcode, 'doc_id' => $this->doc_id);

			return $this->xmlRequest($plobParam,$this->api_url);
		}

		function docParse($response)
		{
			if($this->isEmptyResp())
			{
				$attr = array();

				if($response->get_employee_doc)
					$cfields = $response->get_employee_doc->attributes();
				else
					$cfields = $response->handleGetEmployeeDoc->attributes();

				foreach($cfields as $key => $val)
				{
					$val = (string) $val;
					if(trim($key)!="" && trim($val)!="")
						$attr[$key]=$val;
				}

				if($attr['result_msg']=="SUCCESS")
				{
					return true;
				}
				else if($attr['result_msg']=="FAILURE")
				{
					if(trim($response->get_employee_doc->error_msg)!="")
						$this->resp_status=$response->get_employee_doc->error_msg;
					else if(trim($response->handleGetEmployeeDoc->error_msg)!="")
						$this->resp_status=$response->handleGetEmployeeDoc->error_msg;

					return false;
				}
				else
				{
					$this->resp_status=$attr['result_msg'];
					return false;
				}
			}
			else
			{
				return false;
			}
		}

		function docContent($response)
		{
			$zipfolder = $this->document_root."/".md5(time());
			mkdir($zipfolder,0777);

			$content = base64_decode($response->get_employee_doc->base64);

			$zipfile = $zipfolder."/".md5(time()).".zip";
			$fp=fopen($zipfile,"w");
			fwrite($fp,$content);
			fclose($fp);

			$cmd = "unzip -j $zipfile -d $zipfolder";
			exec($cmd);

			chdir($zipfolder);
			$d = dir($zipfolder);
			while($entry = $d->read())
			{
				if($entry!="." && $entry!="..")
				{
					$pdfcon=file_get_contents($entry);
					unlink($entry);
				}
				else if($entry==$zipfile)
				{
					unlink($entry);
				}
			}

			chdir($this->document_root);
			rmdir($zipfolder);

			return $pdfcon;
		}
	}
?>
