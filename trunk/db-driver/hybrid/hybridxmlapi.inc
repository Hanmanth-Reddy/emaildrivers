<?php
$XMLTrees= array();
$xmltree_inc = true;
$xmltree_release = "991026";

function startElement($parser, $name, $attrs)
{
	global $XMLtrees;
	$XMLtrees[$parser]->start_elt($name, $attrs);
}

function endElement($parser, $name)
{
	global $XMLtrees;
	$XMLtrees[$parser]->pop();
}

function XMLString($parser, $text)
{
	global $XMLtrees;
	if ($text=="&")
		$text="&amp;";
	$XMLtrees[$parser]->addString($text);
}

class XMLtree
{
	var $nodes =array();
	var $ids =array();
	var $curpath = "";
	var $fp;
	var $mode;
	var $filename;
	var $name = "";

	function parseString($string)
	{
		global $XMLtrees;

		$this->nodes = array();
		$this->ids = array();

		$xml_parser = xml_parser_create();
		$XMLtrees[$xml_parser]= $this;

		xml_set_element_handler($xml_parser, "startElement", "endElement");
		xml_set_character_data_handler($xml_parser, XMLString);

		if (!xml_parse($xml_parser, $string, true))
			return sprintf("XML error: %s at line %d",xml_error_string(xml_get_error_code($xml_parser)),xml_get_current_line_number($xml_parser));

		$tree = $XMLtrees[$xml_parser];
		$this->nodes = $XMLtrees[$xml_parser]->nodes;
		$this->ids = $XMLtrees[$xml_parser]->attributes;
		$this->name = $XMLtrees[$xml_parser]->name;

		unset($XMLtrees[$xml_parser]);
		xml_parser_free($xml_parser);

		return false;
	}

	function parseData($data)
	{
		return $this->parseString($data);
	}

	function parse($filename, $mode="r")
	{
		$this->mode = $mode;
		$this->filename = $filename;
		if ($mode != "r")
		{
			$mode = "r+";
		}
		if (!($this->fp = fopen($filename, $mode)))
		{
			die("could not open XML input");
		}
		$data = fread($this->fp, filesize($filename));
		fclose($this->fp);
		return $this->parseString($data);
	}

	function start_elt($name, $attrs)
	{
		$name = strtolower($name);
		if ($this->name=="")
		{
			$this->name='/'.$name.'(1)';
		}
		$path = "$this->curpath/$name";
		$relpath = "$name(".(++ $this->ids[$path]).")";
		$fullpath = "$this->curpath/$relpath";
		$this->nodes[$fullpath]["attributes"] = $attrs;
		$this->nodes[$fullpath]["name"] = $name;
		$this->nodes[$fullpath]["text"] = "";
		$this->nodes[$elements]["elements"] = array();
		$this->nodes[$this->curpath]["elements"][] = $name;
		$this->nodes[$this->curpath]["text"] .= "<\$$relpath>";
		$this->curpath = $fullpath;
	}

	function pop ()
	{
		$this->curpath = substr($this->curpath, 0, strrpos($this->curpath, "/"));
	}

	function addString($text)
	{
		$this->nodes[$this->curpath]["text"] .= $text;
	}

	function showAllElements($XMLtree)
	{
		while ( list( $key, $val ) = each($this->nodes) )
		{
			while ( list( $key1, $val1 ) = each($val) )
			{
				if(!is_array($val1))
					$XMLtree->getEltByPath($key);
			}
		}
	}

	function toStringByPath($path, $self=false, $dep=0)
	{
		if (!is_array($this->nodes[$path]))
		{
			return false;
		}
		$txt = "";
		$tab="\n";

		for ($i=0; $i<$dep; $i++)
			$tab = "|";

		$inn = "";
		$pieces = explode("<$", $this->nodes[$path]["text"]);
		while ( list( $key, $val ) = each($pieces) )
		{
			if (preg_match("|^([^(]*)\(([^)]*)\)>(.*)$|m", $val, $matches))
			{
				$name = $matches[1];
				$id = $matches[2];
				$txtpiece = trim($matches[3]);
				$inn .= $this->toStringByPath("$path/$name($id)", true, $dep+1) . $txtpiece;
			}
			else
			{
				$inn.= trim($val);
			}
		}
		if ($self)
		{
			reset($this->nodes[$path]["attributes"]);
			while ( list( $key, $val ) = each($this->nodes[$path]["attributes"]) )
			{
				$quot = strpos($val, "\"") ? "'" : "\"" ;
				$txt .= "".strtolower($key)."=$quot$val$quot";
			}
			if ($inn)
			{
				$txt .=$inn.$tab;
			}
		}
		else
		{
			if ($inn)
			{
				$txt.="$inn";
			}
		}
		return $txt;
	}

	function toString($self=false, $dep=0)
	{
		return $this->toStringByPath($this->name, $self, $dep);
	}

	function getAttribute($path, $att)
	{
		return $this->nodes[$path]["attributes"][strtoupper($att)];
	}

	function getEltByPath($path)
	{
		$last_slash = strrpos($path, "/");
		switch ($path[$last_slash+1]) 
		{
			case "@":
				return $this->getAttribute(substr($path, 0, $last_slash), substr($path, $last_slash+2));
			case "*" :
				return $this->toStringByPath(substr($path, 0, $last_slash), true);
			default :
				return $this->toStringByPath($path);
		}
	}

	function setAttribute($path, $att, $val)
	{
		$this->nodes[$path]["attributes"][strtoupper($att)] = $val;
		return $this;
	}

	function setNode($path, $value)
	{
		reset($this->nodes);
		while ( list( $key, $val ) = each( $this->nodes ) )
		{
			if (strpos(" ".$key, $path) == 1)
			{
				unset ($this->nodes[$key]);
			}
		}
		reset($value->nodes);
		while ( list( $key, $val ) = each( $value->nodes ) )
		{
			if ($key)
			{
				if ($pos = strpos($key, "/", 1))
				{
					$rel_path = "/".substr($key, $pos+1);
				}
				else
				{
					$rel_path= "";
				}
				$this->nodes["$path"."$rel_path"] = $val;
			}
		}
		return $this;
	}

	function setEltByPath($path, $value)
	{
		$last_slash = strrpos($path, "/");
		switch ($path[$last_slash+1])
		{
			case "@":
				return $this->setAttribute(substr($path, 0, $last_slash), substr($path, $last_slash+2), $value);
			case "*" :
				return $this->setNode(substr($path, 0, $last_slash), $value);
			default :
				return $this->setNode($path, $value);
		}
	}

	function save()
	{
		$fxml = fopen($this->filename, "w");
		fwrite($fxml, "<?xml version=\"1.0\" encoding='ISO-8859-1'?>\n");
		fwrite($fxml, $this->toString(true));
		fclose($fxml);
	}

	function element_type($path)
	{
		if (strpos($path, "@"))
		{
			return "attribute";
		}
		else
		{
			return "element";
		}
	}

	function element_name($path)
	{
		$path = substr($path, strrpos($path, "/"));
		return substr($path, 1, strpos($path, "(")-1);
	}

	function showData($XMLtree,$doctitle)
	{
		$infodata=array();
		$arkey=array_keys($XMLtree->nodes);
		$tags=array_values($XMLtree->nodes);

		$totcand=1;

		for($i=1;$i<=$totcand;$i++)
		{
			$contpos="";
			$emppos="";
			$edpos="";
			$qualpos="";
			$refpos="";

			$contactcount=0;
			$emphiscount=0;
			$eduhiscount=0;
			$qualcount=0;
			$percount=0;
			$refcount=0;
			$ps1="";
			$ps2="";
			$ps3="";
			$ps4="";
			$ps5="";
			$pm="";
			$errono="";
			$errmsg="";

			for($ak=0;$ak<count($arkey);$ak++)
			{
				if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/personaldata(1)")
				{
					$contpos=$ak;
					if(in_array("personname",$tags[$contpos][elements]))
					{
						$contactcount=(count($tags[$contpos][elements]))-1;
						$percount=1;
					}
					else
					{
						$contactcount=(count($tags[$contpos][elements]));
						$percount=0;
					}
				}

				for($m=1;$m<=$contactcount;$m++)
				{
					if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$m.")/postaladdress(1)")
						$pm=$m;

					if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$m.")/business(1)")
						$ps2=$m;

					if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$m.")/mobile(1)")
						$ps3=$m;

					if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$m.")/telephone(1)")
						$ps1=$m;

					if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$m.")/internetemailaddress(1)")
					{
						if($tags[$ak]['name']=="internetemailaddress")
							$ps4=$m;
					}

					if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$m.")/internetwebaddress(1)")
					{
						if($tags[$ak]['name']=="internetwebaddress")
							$ps5=$m;
					}
				}

				if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/employmenthistory(1)")
					$emppos=$ak;

				if($arkey[$ak]=="/candidate(1)/candidateprofile(1)/educationhistory(1)")
					$edpos=$ak;

				if($arkey[$ak]=="/candidate(1)/userarea(1)")
					$qualpos=$ak;
			}

			// EXECUTIVE SUMMARY
			$executivesummary[$i]=$XMLtree->getExeSumm($XMLtree,$i);
			$infodata[$i]["executivesummary"]=$executivesummary[$i];

			// OBJECTIVE
			$obj[$i]=$XMLtree->getObjective($XMLtree,$i);
			$infodata[$i]["objective"]=$obj[$i];

			if($percount!="0")
				$personinfo[$i]=$XMLtree->getPerInfo($XMLtree,$i);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Postal Address
			if($pm!="")
				$postalinfo[$i]=$XMLtree->getPostInfo($XMLtree,$i,$pm);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Phone Numbers - Home
			if($ps1!="")
				$hphone[$i]=$XMLtree->getHphone($XMLtree,$i,$ps1);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Phone Numbers - Business
			if($ps2!="")
				$wphone[$i]=$XMLtree->getWphone($XMLtree,$i,$ps2);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Phone Numbers - Personal
			if($ps3!="")
				$mobile[$i]=$XMLtree->getMobile($XMLtree,$i,$ps3);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Email Address
			if($ps4!="")
				$hemail[$i]=$XMLtree->getEmail($XMLtree,$i,$ps4);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Web Address
			if($ps5!="")
				$hweb[$i]=$XMLtree->getWeb($XMLtree,$i,$ps5);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Employment History
			$emphist="";
			if($emppos!="")
				$emphiscount=(count($tags[$emppos][elements]));

			if($emphiscount!="0")
				$emphist[$i]=$XMLtree->getEmpHist($XMLtree,$emphiscount,$emphist,$i);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Education History
			$edhist="";
			if($edpos!="")
				$eduhiscount=(count($tags[$edpos][elements]));	
			if($eduhiscount!="0")
				$edhist[$i]=$XMLtree->getEduHist($XMLtree,$eduhiscount,$edhist,$i);

			/***** SUCCESS *****/
			// Humoun Resource Information -- Qualifications
			$qualifications="";
			if($qualpos!="")
			{
				for($k=0;$k<count($tags[$qualpos][elements]);$k++)
				{
					if($tags[$qualpos][elements][$k] == "competency")
						$qualifications[$i]=$XMLtree->getQual($XMLtree,$k-1,$qualifications,$i);
				}
			}

			/***** NOT CHECKED YET *****/
			// Humoun Resource Information -- References
            if($refpos!="")
				$refcount=(count($tags[$refpos][elements]));
			if($refcount!="0")
		      	$references[$i]=$XMLtree->getRef($XMLtree,$i);

			$infodata[$i]["personinfo"]=$personinfo[$i];
			$infodata[$i]["postalinfo"]=$postalinfo[$i];
			$infodata[$i]["hphone"]=$hphone[$i];
			$infodata[$i]["wphone"]=$wphone[$i];
			$infodata[$i]["mobile"]=$mobile[$i];
			$infodata[$i]["hemail"]=$hemail[$i];
			$infodata[$i]["hweb"]=$hweb[$i];
			$infodata[$i]["emphist"]=$emphist[$i];
			$infodata[$i]["edhist"]=$edhist[$i];
			$infodata[$i]["qualifications"]=$qualifications[$i];
		}

		return $infodata;
	}

	// FULLNAME | FIRSTNAME | MIDDLENAME | LASTNAME
	function getPerInfo($XMLtree,$i)
	{
		$personinfo[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/personname(1)/formattedname(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/personname(1)/givenname(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/personname(1)/middlename(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/personname(1)/familyname(1)");
		return $personinfo[$i];
	}

	// COUNTRY | ZIP | STATE | CITY | ADDRESS1
	function getPostInfo($XMLtree,$i,$pm)
	{
		$postalinfo[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$pm.")/postaladdress(1)/countrycode(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$pm.")/postaladdress(1)/postalcode(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$pm.")/postaladdress(1)/region(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$pm.")/postaladdress(1)/municipality(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$pm.")/postaladdress(1)/deliveryaddress(1)/addressline(1)");
		return $postalinfo[$i];
	}

	// 	HPHONE
	function getHphone($XMLtree,$i,$ps)
	{
		$hphone[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$ps.")/telephone(1)/formattednumber(1)");
		return $hphone[$i];	
	}

	// WPHONE
	function getWphone($XMLtree,$i,$ps)
	{
		$wphone[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$ps.")/business(1)/formattednumber(1)");
		return $wphone[$i];	
	}

	// MOBILE
	function getMobile($XMLtree,$i,$ps)
	{
		$mobile[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$ps.")/mobile(1)/formattednumber(1)");
		return $mobile[$i];
	}

	// EMAIL
	function getEmail($XMLtree,$i,$ps)
	{
		$hemail[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$ps.")/internetemailaddress(1)");
		return $hemail[$i];	
	}

	// WEB ADDRESS
	function getWeb($XMLtree,$i,$ps)
	{
		$hweb[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/personaldata(1)/contactmethod(".$ps.")/internetwebaddress(1)");				
		return $hweb[$i];	
	}

	// OBJECTIVE
	function getObjective($XMLtree,$i)
	{
		$objective[$i]=$XMLtree->getEltByPath("/candidate(1)/resume(1)/structuredxmlresume(1)/objective(1)");
		return $objective[$i];
	}

	// SUMMARY
	function getExeSumm($XMLtree,$i)
	{
		$exesummary[$i]=$XMLtree->getEltByPath("/candidate(1)/resume(1)/structuredxmlresume(1)/executivesummary(1)");
		return $exesummary[$i];
	}

	// 	SKILL NAME | | | YEARS
	function getQual($XMLtree,$qc,$qualifications,$i)
	{
		if($qualifications[$i]=="")
			$qualifications[$i]=$XMLtree->getEltByPath("/candidate(1)/userarea(1)/competency(".$qc.")/@name")."|||";
		else
			$qualifications[$i].="^".$XMLtree->getEltByPath("/candidate(1)/userarea(1)/competency(".$qc.")/@name")."|||";
		return $qualifications[$i];
	}


	// EMPNAME | TITLE | STATE | CITY | DESCRIPTION | STARTDATE | ENDDATE
	function getEmpHist($XMLtree,$emphiscount,$emphist,$i)
	{
		for($eh=1;$eh<=$emphiscount;$eh++)
		{
			$emp_hist_sdate="";
			$emp_hist_edate="";

			if($XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/startdate(1)/stringdate(1)"))
				$emp_hist_sdate=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/startdate(1)/stringdate(1)");
			else if($XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/startdate(1)/anydate(1)"))
				$emp_hist_sdate=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/startdate(1)/anydate(1)");
			else if($XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/startdate(1)/yearmonth(1)"))
				$emp_hist_sdate=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/startdate(1)/yearmonth(1)");
			else
				$emp_hist_sdate=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/startdate(1)");

			if($XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/enddate(1)/stringdate(1)"))
				$emp_hist_edate=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/enddate(1)/stringdate(1)");
			else if($XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/enddate(1)/anydate(1)"))
				$emp_hist_edate=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/enddate(1)/anydate(1)");
			else if($XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/enddate(1)/yearmonth(1)"))
				$emp_hist_edate=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/enddate(1)/yearmonth(1)");
			else
				$emp_hist_edate=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/enddate(1)");

			if($emphist=="")
				$emphist[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/employerorgname(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/title(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/employercontactinfo(1)/locationsummary(1)/region(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/employercontactinfo(1)/locationsummary(1)/municipality(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/description(1)")."|".$emp_hist_sdate."|".$emp_hist_edate;
			else
				$emphist[$i].="^".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/employerorgname(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/title(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/employercontactinfo(1)/locationsummary(1)/region(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/employercontactinfo(1)/locationsummary(1)/municipality(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/employmenthistory(1)/employerorg(".$eh.")/positionhistory(1)/description(1)")."|".$emp_hist_sdate."|".$emp_hist_edate;
		}	
		return $emphist[$i];
	}	

	// SCHOOLNAME | DEGREENAME | DEGREEMAJOR | DATE
	function getEduHist($XMLtree,$eduhiscount,$edhist,$i)
	{
		for($ed=1;$ed<=$eduhiscount;$ed++)
		{
			if(TRIM($XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/schoolname(1)")) != "")
			{
				if($edhist=="")
					$edhist[$i]=$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/schoolname(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/degree(1)/degreename(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/major(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/enddate(1)/year(1)");
				else
					$edhist[$i].="^".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/schoolname(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/degree(1)/degreename(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/major(1)")."|".$XMLtree->getEltByPath("/candidate(1)/candidateprofile(1)/educationhistory(1)/schoolorinstitution(".$ed.")/enddate(1)/year(1)");
			}
		}
		return $edhist[$i];
	}
}
?>