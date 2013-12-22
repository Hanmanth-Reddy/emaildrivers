<?php
	// Batch script that will check for the old Temp / Session Folder files on appservers when the users doesn't use Logout.
	// This script needs to be executed as apache user
	
	$val=86400;
	$homeday=mktime(0,0,0,date("m"),date("d"),date("Y"));	
	$userdir = array("/var/www/fs");

	for($j=0;$j<count($userdir);$j++)
	{
		chdir($userdir[$j]);
		$files = scandir($userdir[$j]);

		for($i=0;$i<count($files);$i++)
		{
			if(is_dir($files[$i]) && $files[$i]!="." && $files[$i]!="..")
			{
				$ftime=filectime($files[$i]);
				$rem=$homeday-$ftime;
				if($rem>$val)
					delFolder($files[$i]);
			}
		}
	}
	
	Function delFolder($par_fol)
	{
		if(is_dir($par_fol))
		{
			$d=dir($par_fol);
			while($entry=$d->read())
			{
				if( ($entry!=".") and ($entry!="..") )
				{
					if(is_dir($par_fol."/".$entry))
						delFolder($par_fol."/".$entry);
					else
						unlink($par_fol."/".$entry);
				}
			}
			rmdir($par_fol);
		}
	}
?>
