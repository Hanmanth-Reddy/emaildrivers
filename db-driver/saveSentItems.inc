<?php
/*
	Modifed Date: May 13, 2009.
	Modified By:Swapna.
	Purpose: Merged changes from Branch 23 to 25.
	TS Task Id: 4307.
	
	TS Task ID		:	4307
	Created By		:	Ramesh
	Creation Date	:	02nd May, 2009 
	Merged By		:	Rajkumar M.
	Main Purpose	:	To Map Mails sent from Imap server to Akken
*/
	function seveSentItems($extsno,$mailContent)
	{
		global $maildb,$db,$curtime_header;

		require_once("class.pop3.inc");

		$sentdate = strtotime($curtime_header);
		$def_pop_suidl = "|^|";

		$ique="select imaddress,import,account,passwd,imsslchk,sentfolder,ogaddress,host_exchange from external_mail where sno=$extsno";
		$ires=mysql_query($ique,$db);
		$irow=mysql_fetch_row($ires);

		$imaddress=$irow[0];
		$im_port=$irow[1];
		$account=$irow[2];
		$passwd=$irow[3];
		$imsslchk=$irow[4];
		$imap_sentfolder=$irow[5];
		$omaddress=$irow[6];
		$host_exchange=$irow[7];

		if($imap_sentfolder!="")
		{
			$pop3 = new pop3($imaddress,$im_port);
			if($imsslchk=="Yes")
				$pop3->TLS=1;

			$pop3->MAIL_BOX=$imap_sentfolder;
			$count = $pop3->imap_login($account,$passwd);
			if ($count !== false)
			{
				$pop3->imap_close_mailbox();
				$pop3->SENT_BOX=$imap_sentfolder;

				if(strpos($omaddress,"akken.com"))
				{
					if($pop3->imap_append_mail($mailContent))
					{
						$pop3->imap_select_mailbox();
						$sentcount=$pop3->imap_get_list("MESSAGES");
						if($host_exchange=="N")
							$msguid=$pop3->imap_msguid($pop3->imap_fetch_mail("$sentcount","UID"));
						else
							$msguid=strtotime(eregi_replace("[\\\"]", "", $pop3->imap_msguid($pop3->imap_fetch_mail("$sentcount","INTERNALDATE"))));
					}
				}
				else
				{
					$pop3->imap_select_mailbox();
					$sentcount=$pop3->imap_get_list("MESSAGES");
					if($host_exchange=="N")
					{
						$msguid=$pop3->imap_msguid($pop3->imap_fetch_mail("$sentcount","UID"));
						$tmpuid=$pop3->imap_msguid($pop3->imap_fetch_mail("$sentcount","UID"));
						$maildate=strtotime($pop3->imap_sent_date($sentcount));
						if($maildate==$sentdate)
							$msguid=$tmpuid;
						else
							$msguid=$tmpuid+1;
					}
					else
					{
						$msguid=strtotime(eregi_replace("[\\\"]", "", $pop3->imap_msguid($pop3->imap_fetch_mail("$sentcount","INTERNALDATE"))));
					}
				}

				if($msguid !== false)
				{
					$uque="update external_uidls_sent set uidls=TRIM(LEADING '|^|' FROM CONCAT_WS('$def_pop_suidl',uidls,'".addslashes($msguid)."')),luidl='".addslashes($msguid)."',last_rdate='".$sentdate."' WHERE extsno=$extsno";
					mysql_query($uque,$db);
				}
			}
			$pop3->imap_close();
		}
	}
?>