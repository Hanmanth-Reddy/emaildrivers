<?php
function extractResume($res_id)
{
	global $db, $WDOCUMENT_ROOT;

	$resume_contents = "";

	$erque="select filecontent,username,filetype,markadd,sno from con_resumes where sno=".$res_id;
	$erres=mysql_query($erque,$db);
	$errow=mysql_fetch_row($erres);

	if($errow[0]!="" && $errow[3]!="" && mysql_num_rows($erres)>0)
	{
		$path_parts = pathinfo($errow[3]);

		$filename_name=$errow[3];
		$filename_name=md5(time());

		$file = fopen($WDOCUMENT_ROOT."/".$filename_name, "w");
		fwrite($file, $errow[0]);
		fclose($file);

		$file_type=mime_content_type($WDOCUMENT_ROOT."/".$filename_name);

		if($file_type=="text/plain" && (strtolower($path_parts['extension'])=="html" || strtolower($path_parts['extension'])=="htm"))
			$file_type="text/html";

		if($file_type=="application/octet-stream" || $file_type=="application/vnd.openxmlformats-officedocument.wordprocessing" || $file_type=="message/rfc822")
		{
			if(strtolower($path_parts['extension'])=="doc")
				$file_type="application/msword";
			else if(strtolower($path_parts['extension'])=="rtf")
				$file_type="text/rtf";
			else if(strtolower($path_parts['extension'])=="html" || strtolower($path_parts['extension'])=="htm")
				$file_type="text/html";
			else if(strtolower($path_parts['extension'])=="pdf")
				$file_type="application/pdf";
			else if(strtolower($path_parts['extension'])=="docx")
				$file_type="application/x-zip";
			else
				$file_type="application/msword";
		}

		if($file_type=="text/plain")
		{
			$resume_contents = $errow[0];
		}
		else if($file_type=="text/html" || $file_type=="application/HTM")
		{
			$cmd = "html2text -o ".$WDOCUMENT_ROOT."/htmlfile.txt  -ascii -nobs ".$WDOCUMENT_ROOT."/".$filename_name;
			system($cmd);

			$conv_file=$WDOCUMENT_ROOT."/htmlfile.txt";
			$file = fopen($conv_file, "r");
			$resume_contents = fread($file, filesize($conv_file));
			fclose($file);

			unlink($conv_file);
		}
		else if($file_type=="application/pdf")
		{
			$cmd = "pdftotext -layout ".$WDOCUMENT_ROOT."/".$filename_name."  ".$WDOCUMENT_ROOT."/pdffile.txt";
			system($cmd);

			$conv_file=$WDOCUMENT_ROOT."/pdffile.txt";
			$file = fopen($conv_file, "r");
			$resume_contents = fread($file, filesize($conv_file));
			fclose($file);

			unlink($conv_file);
		}
		else if($file_type=="application/msword" || $file_type=="application/DOC")
		{
			$cmd = "antiword ".$WDOCUMENT_ROOT."/".$filename_name." > ".$WDOCUMENT_ROOT."/wordfile.txt";
			system($cmd);

			$conv_file=$WDOCUMENT_ROOT."/wordfile.txt";
			$file = fopen($conv_file, "r");
			$resume_contents = fread($file, filesize($conv_file));
			fclose($file);

			unlink($conv_file);
		}
		else if($file_type=="text/rtf" || $file_type=="text/richtext" || $file_type=="text/rtf" || $file_type=="application/x-rtf" || $file_type=="application/RTF")
		{
			$cmd = "rtf2text ".$WDOCUMENT_ROOT."/".$filename_name." > ".$WDOCUMENT_ROOT."/rtffile.txt";
			system($cmd);

			$conv_file=$WDOCUMENT_ROOT."/rtffile.txt";
			$file = fopen($conv_file, "r");
			$resume_contents = fread($file, filesize($conv_file));
			fclose($file);

			unlink($conv_file);
		}
		else if($file_type=="application/x-zip")
		{
			$cmd = "/usr/local/bin/docx2txt.pl ".$WDOCUMENT_ROOT."/".$filename_name."  ".$WDOCUMENT_ROOT."/docxfile.txt";
			system($cmd);

			$conv_file=$WDOCUMENT_ROOT."/docxfile.txt";
			$file = fopen($conv_file, "r");
			$resume_contents = fread($file, filesize($conv_file));
			fclose($file);

			unlink($conv_file);
		}

		unlink($WDOCUMENT_ROOT."/".$filename_name);
	}
	else
	{
		if($errow[0]!="" && $errow[2]=="" && $errow[3]=="" && mysql_num_rows($erres)>0)
			$resume_contents = $errow[0];
	}

	return $resume_contents;
}
?>