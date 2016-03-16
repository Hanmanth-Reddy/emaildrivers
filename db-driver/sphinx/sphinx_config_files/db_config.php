<?php
// Create connection
define('SPHINXHOST',"localhost");
define('SPHINXAPIPORT', '9312');
define('SPHINXPORT', '9306'); 

define('DBHOST', '192.168.2.41');
define('DBADMIN', 'educeit');
define('DBADMINPW', 'educeit');
define('DBPORT', '3306');

define('MAINDBHOST', '192.168.2.41');
define('MAINDBADMIN', 'educeit');
define('MAINDBADMINPW', 'educeit');
define('MAINDBNAME', 'iwnasp');
define('MAINDBPORT', '3306');

define('SPHINX_MAX_MATCHES', '100000');

function cFolder($company)
{
	if (!file_exists('/sphinx-data/'.$company)) {
		mkdir('/sphinx-data/'.$company, 0777, true);
		#chmod('/sphinx-data/'.$company, 0777);
		chown('/sphinx-data/'.$company, 'sphinx');
	}
	return $company;
}
/*
$con=mysql_connect(DBHOST,DBADMIN,DBADMINPW);
$db = @mysql_select_db(DBNAME,$con) or die( "Unable to select database");
*/
$con=mysql_connect(MAINDBHOST,MAINDBADMIN,MAINDBADMINPW)or die("Unable to connect database");
$maindb = @mysql_select_db(MAINDBNAME,$con) or die( "Unable to select database");
?>