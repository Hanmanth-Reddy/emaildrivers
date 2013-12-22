<?php
	// Set Error / Warning display YES (1) or NO (0)
	ini_set("display_errors","0");

	require("config.inc");
	require("setServers.inc");
	require("getServers.inc");
	require("cdatabase.inc");
	require("class.popper.inc");


	// To set boundaries for Mixed/Related/Alternate parts in sending mails
	//$cur_time=time();
	$boun_mixed_part=md5(uniqid(microtime()));
	$boun_related_part=md5(uniqid(microtime()));
	$boun_alternative_part=md5(uniqid(microtime()));
	$cur_time=$boun_mixed_part;

	//date used in mail header while sending a mail 
	$curtime_header=date("D, j M Y H:i:s O");

	// Function to parse double quotes and split Email ids like '"Ramesh , Vempati" <rameshcv@educeit.com>,mythili@akkentech.com'
	$sysflist=array("inbox","outbox","sentmessages","drafts","trash","unsubscribe","failed","spam");

	Function quotesplit($line,$split=",",$temp="^")
	{
		$text=explode("\"",$line);
		for($i=0;$i<count($text);$i++)
		{
			if($j==1)
			{
				$text[$i]=str_replace($split,$temp,$text[$i]);
				$j=0;
			}
			else
			{
				$j++;
			}
			$string.=$text[$i];
			if($i<(count($text)-1))
				$string.="\"";
		}

		$cont=explode(",",$string);
		for($i=0;$i<count($cont);$i++)
		{
			$cont[$i]=str_replace($temp,$split,$cont[$i]);
		}
		return $cont;
	}
?>
