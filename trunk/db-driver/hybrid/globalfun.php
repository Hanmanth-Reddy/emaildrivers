<?php
/* Functions written for the purpose of Expereince Sorting throughout the Application by RamesH.T.One function is used for Marketing,Collaboration and Activities and another function is used for the HRM module */
/*
	Created Date: May 13, 2009
	Created By  : Sundar.k
	Purpose     : create webservice call for Automated Import of XML Resumes from Data Frenzy
	Task Id     : 4272
	
	Modified By: Praveen.P
	Modified Date: May 28th, 09.
	Purpose: Added "phoneFormating" function, this function will separate the phone extension number based on given separator.
	Task Id: 
*/	
	//to get the manage id 
function getmanagename($cid,$status)
{
	global $db;
	if($cid)
	{
		$addqry=((trim($status)!="")?" and type='".$status."'":"");			
		$con_que="select name from manage where sno=$cid ".$addqry;
		$res_que=mysql_query($con_que,$db);
		$res_data=mysql_fetch_row($res_que);
	}
	return $res_data[0];
}

//to get the country name
function getCountryName($cid)
{
	global $db;
	if($cid)
	{
		$con_que="select country from countries where sno='$cid'";
		$res_que=mysql_query($con_que,$db);
		$res_data=mysql_fetch_row($res_que);
	}
	return $res_data[0];
}	

function getCountryCode($cname)
{
	global $db;
	if($cname)
	{
		$con_que="select sno from countries where country='$cname' OR country_abbr='$cname'";
		$res_que=mysql_query($con_que,$db);
		$res_data=mysql_fetch_row($res_que);
		$num=mysql_num_rows($res_que);
	}

	if($num==0)
		return 0;
	else
		return $res_data[0];
}

//Function for replacing spl chars like *, /, \ with null in the file name.
function convertSplCharsFileName($fileName)
{
	$patterns[0] = '/\*/';
	$patterns[1] = '/</';
	$patterns[2] = '/>/';
	$patterns[3] = '/\//';
	$patterns[4] = '/\\\\/';
	$patterns[5] = '/\?/';
	$patterns[6] = '/:/';
	$patterns[7] = '/"/';
	$fileNameConverted = preg_replace($patterns, '', $fileName);
	return $fileNameConverted;
}

function Experiencesrt($expstr)
{
	$Sort_Pre=array();
	$Sort_Emp=array();
	$Sort_Rem=array();
	$Sort_RemFin=array();
	$Exp_Sort1=array();	
	$atest1=array();
	$Exp_Sort=explode("^",$expstr);
	$Exp_Count=count($Exp_Sort);
	$j=0;
	$k=0;
	$m=0;
	for($i=0;$i <  $Exp_Count;$i++)
	{
		$Exp_Sort1[$i]=$Exp_Sort[$i];
		$Exp_Sort[$i]=explode("|",$Exp_Sort[$i]);
		//if it is Present or if no data is entered	for Enddate	
		if((trim($Exp_Sort[$i][4]) =='Present') || (trim($Exp_Sort[$i][4]) =='Present-0')||(trim($Exp_Sort[$i][4]) =='-'))
		{	
			$Sort_Pre[$j]=$Exp_Sort1[$i];
			$j++;
		}
		else if( (trim($Exp_Sort[$i][4]) =='') || (trim($Exp_Sort[$i][4]) =="0-0"))
		{
			$Sort_Emp[$k]=$Exp_Sort1[$i];
			$k++;
		}	
		else//if either year or month is entered
		{
			$Sort_Rem[$i]=$Exp_Sort1[$i];
			$atest=explode("-",$Exp_Sort[$i][4]);	
			if((trim($atest[0])=="") || (trim($atest[0])=="0"))
			{
				$atest1[$i]=$atest[1]."13";		

			}
			else
			{
				$h="";
				switch(strtolower($atest[0]))
				{
					case "january":$h="01";
					break;
					case "february":$h="02";
					break;
					case "march":$h="03";
					break;
					case "april":$h="04";
					break;
					case "may":$h="05";
					break;
					case "june":$h="06";
					break;
					case "july":$h="07";
					break;
					case "august":$h="08";
					break;
					case "september":$h="09";
					break;
					case "october":$h="10";
					break;
					case "november":$h="11";
					break;
					case "december":$h="12";
					break;
					default :$h="";
				}
				$atest1[$i]=$atest[1].$h;	
			}		
		}	
	}
	arsort($atest1);
	foreach ($atest1 as $key => $val) 
	{    		
		$Sort_RemFin[$key]=$Sort_Rem[$key];			
	}

	$Sort_RemFinT=array_merge($Sort_Pre,$Sort_Emp,$Sort_RemFin);
	$Sort_RemFinTot=implode("^",$Sort_RemFinT);
	return $Sort_RemFinT;
}	

function phoneFormating($phonenumber)
{
	$res[0] = '';
	$res[1] = '';
	if(trim($phonenumber) != '')
	{
		if (strpos(strtolower($phonenumber),"x")===true)
		{
			$temp = explode("x",$phonenumber);
			$res[0] = CheckSpecailCharacters($temp[0]);	
			$res[1] = CheckSpecailCharacters($temp[1]);			
		}
		else
		{
			$res[0] = CheckSpecailCharacters($phonenumber);	
		}
	}
	
	return $res;
}

function RemoveSpecailCharacters( $value )
{
 	$value = str_replace('^','',$value);
	$value = str_replace('|','',$value);
	return $value; 
      
}
?>