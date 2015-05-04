<?php
	function seveSentItems($extsno,$mailContent)
	{
		global $maildb,$db,$curtime_header;

		require_once("class.pop3.inc");

		$sentdate = strtotime($curtime_header);
		$def_pop_suidl = "|^|";

		$ique="select imaddress,import,account,passwd,imsslchk,ogaddress,host_exchange from external_mail where sno=$extsno";
		$ires=mysql_query($ique,$db);
		$irow=mysql_fetch_row($ires);

		$sque="select sno, sfolder from external_uidls where afolder='sentmessages' AND extsno=$extsno";
		$sres=mysql_query($sque,$db);
		$srow=mysql_fetch_row($sres);

		$imaddress=$irow[0];
		$im_port=$irow[1];
		$account=$irow[2];
		$passwd=$irow[3];
		$imsslchk=$irow[4];
		$omaddress=$irow[5];
		$host_exchange=$irow[6];

		$sextno=$srow[0];
		$imap_sentfolder=$srow[1];

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

				if(strpos($omaddress,"gmail.com"))
				{
					$pop3->imap_select_mailbox();
					if($host_exchange=="N")
					{
						$msguid=$pop3->imap_get_list("UIDNEXT")-1;
					}
					else
					{
						$sentcount=$pop3->imap_get_list("MESSAGES");
						$msguid=strtotime(eregi_replace("[\\\"]", "", $pop3->imap_msguid($pop3->imap_fetch_mail("$sentcount","INTERNALDATE"))));
					}
				}
				else
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

				if($msguid !== false)
				{
					$uque="update external_uidls set uidls=TRIM(LEADING '|^|' FROM CONCAT_WS('$def_pop_suidl',uidls,'".addslashes($msguid)."')),luidl='".addslashes($msguid)."',last_rdate='".$sentdate."' WHERE sno=$sextno";
					mysql_query($uque,$db);
				}
			}
			$pop3->imap_close();
		}
	}
?>