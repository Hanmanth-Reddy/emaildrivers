<?php
class POP3
{
	var $ERROR		= "";			//	Error string.
	var $TIMEOUT	= 30;			//	Default timeout before giving up on a network operation.
	var $COUNT		= -1;			//	Mailbox msg count
	var $BUFFER		= 8192;			//	Socket buffer for socket fgets() calls. Per RFC 1939 the returned line a POP3 server can send is 512 bytes.
	var $UID_BUFFER		= 128;			//	UIDL Buffer Length
	var $FP			= "";			//	The connection to the server's file descriptor
	var $DEBUG		= false;		//  set to true to echo pop3 commands and responses to error_log this WILL log passwords!
	var $BANNER		= "";			//	Holds the banner returned by the pop server - used for apop()
	var $RFC1939	= true;			//	Set by noop(). See rfc1939.txt
	var $ALLOWAPOP	= false;		//	Allow or disallow apop() This must be set to true manually.
	var $VMAILMGR	= false;		//	Set to true if using VMailMgr
	var $MAILSERVER	= "";			//	Holds the server name
	var $PORT		= "";			//	Holds the server port
	var $TLS		= 0;			//	Establish secure connections using TLS
	var $TAG		= "";			//  Need TAG to access IMAP Accounts
	var $MAIL_BOX	= "INBOX";		//  Default folder we process for IMAP Accounts
	var $SENT_BOX	= "Sent Items";	//  Sent Items folder to save sent items on IMAP Server
	var $MUST_UPDATE= false;		//  Optional for us, we do not switch over the folders for IMAP
	var $STAGE		= "INIT";		//  Not required
	var $TRASH_BOX		= "[Gmail]/Trash";		//  Not required

	function POP3 ( $server = "localhost", $port = "110", $timeout = "" )
	{
		settype($this->BUFFER,"integer");
		if(!empty($server))
			$this->MAILSERVER = $server;
		if(!empty($port))
			$this->PORT = $port;

		if(!empty($timeout))
		{
			settype($timeout,"integer");
			$this->TIMEOUT = $timeout;
			@set_time_limit($this->TIMEOUT);
		}
		return true;
	}

	function pop_login ($login, $pass)
	{
		if (!$this->connect($this->MAILSERVER, $this->PORT, "") )
			return false;
		$count = $this->apop($login, $pass);
		if ( $count < 0 )
			return $count;
		return $count;
	}

	function update_timer ()
	{
		@stream_set_timeout($this->FP,$this->TIMEOUT);
		@set_time_limit($this->TIMEOUT);
		return true;
	}

	function connect ($server, $port, $type)
	{
		if(empty($server))
		{
			unset($this->FP);
			return false;
		}
		if(empty($port))
		{
			unset($this->FP);
			return false;
		}
		else
		{
			$this->MAILSERVER = $server;
			$this->PORT = $port;
		}

		$fp = @fsockopen(($this->TLS ? "tls://" : "")."$server", $port, &$errno, &$errstr, 30);
		if(!$fp)
		{
			unset($this->FP);
			return false;
		}

		set_socket_blocking($fp,-1);
		$this->update_timer();
		$reply = fgets($fp,$this->BUFFER);
		$reply = $this->strip_clf($reply);

		if($type=="")
		{
			if($this->DEBUG) { error_log("POP3 SEND [connect: $server] GOT [$reply]",0); }

			if(!$this->is_ok($reply))
			{
				unset($this->FP);
				return false;
			}

			$this->FP = $fp;
			$this->BANNER = $this->parse_banner($reply);
		}
		else
		{
			if(!$this->is_imap_ok($reply))
			{
				unset($this->FP);
				return false;
			}
			$this->FP = $fp;
		}
		return true;
	}

	function is_imap_ok ($cmd = "")
	{
		if(empty($cmd))
		{
			return false;
		}
		else
		{
			$a=explode(" ", $cmd);
			if (strcasecmp($a[1],"OK")==0)
				return true;
		}
		return false;
	}

	function noop ()
	{
		if(!isset($this->FP))
			return false;
		$cmd = "NOOP";
		$reply = $this->send_cmd($cmd);
		if(!$this->is_ok($reply))
			return false;
		return true;
	}

	function user ($user = "")
	{
		if(empty($user))
			return false;
		if(!isset($this->FP))
			return false;

		if ($this->VMAILMGR)
			$user = sprintf("%s:%s", $user, $this->MAILSERVER);
		$reply = $this->send_cmd("USER $user");
		if(!$this->is_ok($reply))
			return false;
		return true;
	}

	function pass ($pass = "")
	{
		if(empty($pass))
			return -2;
		if(!isset($this->FP))
			return -3;

		$reply = $this->send_cmd("PASS $pass");
		if(!$this->is_ok($reply))
		{
			$this->quit();
			return -4;
		}

		$count = $this->last("count");
		$this->COUNT = $count;
		$this->RFC1939 = $this->noop();
		if(!$this->RFC1939)
		{
			$this->quit();
			return -5;
		}
		return $count;
	}

	function apop ($login,$pass)
	{
		if(!isset($this->FP))
			return false;

		if(!$this->ALLOWAPOP)
		{
			$retVal = $this->login($login,$pass);
			return $retVal;
		}

		if(empty($login))
			return false;

		if(empty($pass))
			return false;

		$banner = $this->BANNER;
		if( (!$banner) or (empty($banner)) )
		{
			$retVal = $this->login($login,$pass);
			return $retVal;
		}
		$AuthString = $banner;
		$AuthString .= $pass;
		$APOPString = md5($AuthString);
		$cmd = "APOP $login $APOPString";
		$reply = $this->send_cmd($cmd);
		if(!$this->is_ok($reply))
		{
			$retVal = $this->login($login,$pass);
			return $retVal;
		}

		$count = $this->last("count");
		$this->COUNT = $count;
		$this->RFC1939 = $this->noop();
		if(!$this->RFC1939)
		{
			$this->quit();
			return false;
		}
		return $count;
	}

	function login ($login = "", $pass = "")
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;
		if(!$this->user($login))
			return false;
		$count = $this->pass($pass);
		return $count;
	}

	function top ($msgNum, $numLines = "0")
	{
		if(!isset($this->FP))
			return false;
		$this->update_timer();
		$fp = $this->FP;
		$buffer = $this->BUFFER;
		$cmd = "TOP $msgNum $numLines";
		fwrite($fp, "TOP $msgNum $numLines\r\n");
		$reply = fgets($fp, $buffer);
		$reply = $this->strip_clf($reply);

		if($this->DEBUG) { @error_log("POP3 SEND [$cmd] GOT [$reply]",0); }

		if(!$this->is_ok($reply))
			return false;

		$count = 0;

		$line = fgets($fp,$buffer);
		while(!ereg("^\.\r\n",$line))
		{
			$msg .= $line;
			$line = fgets($fp,$buffer);
			if(empty($line))
				break;
		}
		return $msg;
	}

	function pop_list ($msgNum = "")
	{
		if(!isset($this->FP))
			return false;

		$fp = $this->FP;
		$Total = $this->COUNT;
		if( (!$Total) or ($Total == -1) )
			return false;
		if($Total == 0)
			return array("0","0");
		$this->update_timer();

		if(!empty($msgNum))
		{
			$cmd = "LIST $msgNum";
			fwrite($fp,"$cmd\r\n");
			$reply = fgets($fp,$this->BUFFER);
			$reply = $this->strip_clf($reply);

			if($this->DEBUG) { @error_log("POP3 SEND [$cmd] GOT [$reply]",0); }

			if(!$this->is_ok($reply))
				return false;
			list($junk,$num,$size) = explode(" ",$reply);
			return $size;
		}
		$cmd = "LIST";
		$reply = $this->send_cmd($cmd);
		if(!$this->is_ok($reply))
		{
			$reply = $this->strip_clf($reply);
			return false;
		}
		$MsgArray = array();
		$MsgArray[0] = $Total;
		$line = fgets($fp,$this->BUFFER);
		$line = $this->strip_clf($line);

		while(!ereg("^\.", $line))
		{
			list($thisMsg,$msgSize) = explode(" ",$line);
			$MsgArray["$thisMsg"] = $msgSize;
			$line = fgets($fp,$this->BUFFER);
			$line = $this->strip_clf($line);
		}
		return $MsgArray;
	}

	function get ($msgNum)
	{
		if(!isset($this->FP))
			return false;
		$this->update_timer();
		$fp = $this->FP;
		$buffer = $this->BUFFER;
		$cmd = "RETR $msgNum";
		$reply = $this->send_cmd($cmd);

		if(!$this->is_ok($reply))
			return false;

		$MsgArray = array();
		$line = fgets($fp,$buffer);
		while(!ereg("^\.\r\n",$line))
		{
			$MsgArray[] = $line;
			$line = fgets($fp,$buffer);
			if(empty($line))
				break;
		}
		return $MsgArray;
	}

	function get_text($msgNum)
	{
		if(!isset($this->FP))
			return false;
		$this->update_timer();
		$fp = $this->FP;
		$buffer = $this->BUFFER;
		$cmd = "RETR $msgNum";
		$reply = $this->send_cmd($cmd);

		if(!$this->is_ok($reply))
			return false;

		$line = fgets($fp,$buffer);
		while(!ereg("^\.\r\n",$line))
		{
			if(substr($line,0,2)=="..")
				$line=".".substr($line,2);

			$msg .= $line;
			$line = fgets($fp,$buffer);
			if(empty($line))
				break;
		}
		return $msg;
	}

	function get_mail($msgNum)
	{
		$message = $this->get($msgNum);
		$mail = new mime_mail($message);
		return $mail;
	}

	function last ( $type = "count" )
	{
		$last = -1;
		if(!isset($this->FP))
			return $last;
		$reply = $this->send_cmd("STAT");
		if(!$this->is_ok($reply))
			return $last;
		$Vars = explode(" ",$reply);
		$count = $Vars[1];
		$size = $Vars[2];
		settype($count,"integer");
		settype($size,"integer");
		if($type != "count")
			return array($count,$size);
		return $count;
	}

	function reset ()
	{
		if(!isset($this->FP))
			return false;
		$reply = $this->send_cmd("RSET");
		if(!$this->is_ok($reply))
		{
			@error_log("POP3 reset: ERROR [$reply]",0);
		}
		$this->quit();
		return true;
	}

	function send_cmd ( $cmd = "" )
	{
		if(!isset($this->FP))
			return false;

		if(empty($cmd))
			return "";

		$fp = $this->FP;
		$buffer = $this->BUFFER;
		$this->update_timer();
		fwrite($fp,"$cmd\r\n");
		$reply = fgets($fp,$buffer);
		$reply = $this->strip_clf($reply);

		if($this->DEBUG) { @error_log("POP3 SEND [$cmd] GOT [$reply]",0); }

		return $reply;
	}

	function quit()
	{
		if(!isset($this->FP))
			return false;

		$fp = $this->FP;
		$reply = $this->send_cmd("QUIT");

		fclose($fp);
		unset($this->FP);
		return true;
	}

	function popstat ()
	{
		$PopArray = $this->last("array");
		if($PopArray == -1)
			return false;
		if( (!$PopArray) or (empty($PopArray)) )
			return false;
		return $PopArray;
	}

	function uidl()
	{
		if(!isset($this->FP))
			return false;

		$fp = $this->FP;
		$buffer = $this->UID_BUFFER;

		$this->update_timer();
		$UIDLArray = array();
		$Total = $this->COUNT;

		if ($Total < 1)
			return $UIDLArray;

		$cmd = "UIDL";
		fwrite($fp, "UIDL\r\n");
		$reply = fgets($fp, $buffer);
		$reply = $this->strip_clf($reply);

		if($this->DEBUG) { @error_log("POP3 SEND [$cmd] GOT [$reply]",0); }

		$line = "";
		$line = fgets($fp,$buffer);
		while ( !ereg("^\.\r\n",$line))
		{
			if(ereg("^\.\r\n",$line))
				break;

			$cnt=substr_count($line," ");
			$sline=explode(" ",$line);
			$msg=$sline[0];
			$msgUidl = $this->strip_clf($sline[$cnt]);
			$UIDLArray[$msg] = $msgUidl;
			$line = fgets($fp,$buffer);
		}
		return $UIDLArray;
	}

	function delete ($msgNum = "")
	{
		if(!isset($this->FP))
			return false;
		if(empty($msgNum))
			return false;
		$reply = $this->send_cmd("DELE $msgNum");
		if(!$this->is_ok($reply))
			return false;
		return true;
	}

	function is_ok ($cmd = "")
	{
		if(empty($cmd))
			return false;
		if(ereg("^\+OK",$cmd))
			return true;
		return false;
	}

	function strip_clf ($text = "")
	{
		if(empty($text))
			return $text;
		$stripped = ereg_replace("\r","",$text);
		$stripped = ereg_replace("\n","",$stripped);
		return $stripped;
	}

	function parse_banner ( $server_text )
	{
		$outside = true;
		$banner = "";
		$length = strlen($server_text);
		for($count =0; $count < $length; $count++)
		{
			$digit = substr($server_text,$count,1);
			if(!empty($digit))
			{
				if( (!$outside) and ($digit != '<') and ($digit != '>') )
					$banner .= $digit;
				if ($digit == '<')
					$outside = false;
				if($digit == '>')
					$outside = true;
			}
		}
		$banner = $this->strip_clf($banner);
		return "<$banner>";
	}

	function imap_is_ok($cmd)
	{
		if(substr($cmd,strpos($cmd,"$this->TAG ")+strlen($this->TAG)+1,2)!="OK")
			return false;
		else
			return true;
	}

	function imap_put_line($cmd="")
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		return fwrite($fp,"$cmd\r\n");
	}

	function imap_get_line()
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		$buffer=$this->BUFFER;
		while(!feof($fp))
		{
			$line.=fread($fp,$buffer);
			if(strlen($line)>=2 && substr($line,-2)=="\r\n")
				return(substr($line,0,-2));
		}
	}

	function imap_get_server_responce()
	{
		while(1)
		{
			$line="\r\n".$this->imap_get_line();
			$reply.=$line;
			if(substr($reply,strpos($reply,$this->TAG),strlen($this->TAG))==$this->TAG)
				break;
		}
		return $reply;
	}

	function imap_login ($login, $pass)
	{
		$this->TAG = uniqid("AKKEN");
		if (!$this->connect($this->MAILSERVER, $this->PORT, "IMAP"))
			return false;

		$count = $this->imap_mbox_login($login, $pass);
		$this->COUNT = $count;

		if ( $count < 0 )
			return $count;
		return $count;
	}

	function imap_mbox_login ($login = "", $pass = "")
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		$cmd=$this->TAG." LOGIN $login $pass";
		if($this->imap_put_line($cmd))
		{
			$reply=$this->imap_get_server_responce();

			if($this->DEBUG) { @error_log("IMAP SEND [$cmd] GOT [$reply]",0); }

			if(!$this->imap_is_ok($reply))
				return -4;

			$count = $this->imap_get_list("MESSAGES");
			return $count;
		}
		else
		{
			return false;
		}
	}

	function imap_unseen($message)
	{
		if($this->imap_put_line($this->TAG." STORE $message -FLAGS (\\Seen)"))
		{
			$reply=$this->imap_get_server_responce();
			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}
		return $reply;
	}

	function imap_explodeQuotedString($delimiter, $string)
	{
		$quotes=explode("\"", $string);
		while ( list($key, $val) = each($quotes))
			if (($key % 2) == 1) 
				$quotes[$key] = str_replace($delimiter, "_!@!_", $quotes[$key]);
		$string=implode("\"", $quotes);
	
		$result=explode($delimiter, $string);
		while ( list($key, $val) = each($result) )
			$result[$key] = str_replace("_!@!_", $delimiter, $result[$key]);
	
		return $result;
	}

	function imap_uidl()
	{
		global $companyuser;

		$exp_list = array("pentacan");

		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		$uidl=array();
		$count=$this->COUNT;

		if($companyuser!="" && in_array($companyuser,$exp_list))
			$limit=1000;
		else
			$limit=100000;

		$pmod=intval($count/$limit);
		if($count%$limit==0)
			$pmod--;

		for($j=0;$j<=$pmod;$j++)
		{
			$start=(($j*$limit)+1);
			$end=(($j+1)*$limit);
			if($j==$pmod)
				$end=$count;

			$select=$this->imap_select_mailbox();
			$uidl=$this->imap_fetch_mail("$start:$end","UID FLAGS");
			$suidls=explode("\r\n",$uidl);

			for($k=1;$k<count($suidls);$k++)
			{
				$line=$suidls[$k];
				if ($line[0]=="*")
				{
					$open_pos = strpos($line, "(") + 1;
					$close_pos = strrpos($line, ")");

					$pre_a = explode(" ", $line);
					$id = $pre_a[1];

					$len = $close_pos - $open_pos;
					$str = substr($line, $open_pos, $len);
					$str=eregi_replace("[()]", "\"", $str);
					$a=$this->imap_explodeQuotedString(" ", $str);

					for ($i=0;$i<count($a);$i=$i+2)
					{
						if (strcasecmp($a[$i],"UID")==0)
							$uidl_flags["uid"][$id]=$a[$i+1];
						else if (strcasecmp($a[$i],"FLAGS")==0)
							$flags_str = $a[$i+1];
					}

					// process flags
					$flags_str = eregi_replace("[\\\"]", "", $flags_str);
					$flags_a = explode(" ", $flags_str);

					/*
					$uidl_flags["seen"][$id]=false;
					$uidl_flags["answered"][$id]=false;
					$uidl_flags["flagged"][$id]=false;
					*/

					if (is_array($flags_a))
					{
						reset($flags_a);
						while (list($key,$val)=each($flags_a))
						{
							if (strcasecmp($val,"Seen")==0)
								$uidl_flags["seen"][$id]=$uidl_flags["uid"][$id];
							else if (strcasecmp($val, "Answered")==0)
								$uidl_flags["answered"][$id]=$uidl_flags["uid"][$id];
							else if (strcasecmp($val, "Flagged")==0)
								$uidl_flags["flagged"][$id]=$uidl_flags["uid"][$id];
						}
					}
				}
			}
		}

		return $uidl_flags;
	}

	function imap_custom_uidl()
	{
		global $companyuser;

		$exp_list = array("pentacan");

		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		$uidl=array();
		$count=$this->COUNT;

		if($companyuser!="" && in_array($companyuser,$exp_list))
			$limit=1000;
		else
			$limit=100000;

		$pmod=intval($count/$limit);
		if($count%$limit==0)
			$pmod--;

		for($j=0;$j<=$pmod;$j++)
		{
			$start=(($j*$limit)+1);
			$end=(($j+1)*$limit);
			if($j==$pmod)
				$end=$count;

			$select=$this->imap_select_mailbox();
			$uidl=$this->imap_fetch_mail("$start:$end","INTERNALDATE FLAGS");
			$suidls=explode("\r\n",$uidl);

			for($k=1;$k<count($suidls);$k++)
			{
				$line=$suidls[$k];
				if ($line[0]=="*")
				{
					$open_pos = strpos($line, "(") + 1;
					$close_pos = strrpos($line, ")");

					$pre_a = explode(" ", $line);
					$id = $pre_a[1];

					$len = $close_pos - $open_pos;
					$str = substr($line, $open_pos, $len);
					$str=eregi_replace("[()]", "\"", $str);
					$a=$this->imap_explodeQuotedString(" ", $str);

					for ($i=0;$i<count($a);$i=$i+2)
					{
						if (strcasecmp($a[$i],"INTERNALDATE")==0)
							$internaldate_str=$a[$i+1];
						else if (strcasecmp($a[$i],"FLAGS")==0)
							$flags_str = $a[$i+1];
					}

					$uidl_flags["uid"][$id] = strtotime(eregi_replace("[\\\"]", "", $internaldate_str));

					// process flags
					$flags_str = eregi_replace("[\\\"]", "", $flags_str);
					$flags_a = explode(" ", $flags_str);

					/*
					$uidl_flags["seen"][$id]=false;
					$uidl_flags["answered"][$id]=false;
					*/

					if (is_array($flags_a))
					{
						reset($flags_a);
						while (list($key,$val)=each($flags_a))
						{
							if (strcasecmp($val,"Seen")==0)
								$uidl_flags["seen"][$id]=$uidl_flags["uid"][$id];
							else if (strcasecmp($val, "Answered")==0)
								$uidl_flags["answered"][$id]=$uidl_flags["uid"][$id];
						}
					}
				}
			}
		}
		return $uidl_flags;
	}

	function imap_msguid($reply)
	{
		$msguidl = false;
		$suidls=explode("\r\n",$reply);
		for($k=1;$k<count($suidls);$k++)
		{
			$line=$suidls[$k];
			if ($line[0]=="*")
			{
				$open_pos = strpos($line, "(") + 1;
				$close_pos = strrpos($line, ")");

				$pre_a = explode(" ", $line);
				$id = $pre_a[1];

				$len = $close_pos - $open_pos;
				$str = substr($line, $open_pos, $len);
				$str=eregi_replace("[()]", "\"", $str);
				$a=$this->imap_explodeQuotedString(" ", $str);

				$msguidl=$a[$i+1];
			}
		}
		return $msguidl;
	}

	function imap_get_delimiter()
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		$delimiter = false;
		$ref_mail_box="\"\" \"\"";

		if($this->imap_put_line($this->TAG." LIST $ref_mail_box"))
		{
			$reply=$this->imap_get_server_responce();
			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}

		$sreply=explode("\r\n",$reply);
		for($k=1;$k<count($sreply);$k++)
		{
			$line=$sreply[$k];
			if ($line[0]=="*")
			{
				$line = rtrim($line);
				$a=$this->imap_explodeQuotedString(" ", $line);
				if ($a[0]=="*")
					$delimiter = str_replace("\"", "", $a[count($a)-2]);
			}
		}

		if (strlen($delimiter)>0)
			return $delimiter;
		else
			return false;
	}

	function imap_append_mail($mailContent)
	{
		$sentBox=$this->SENT_BOX;

		$mailContent = str_replace("\r", "", $mailContent);
		$mailContent = str_replace("\n", "\r\n", $mailContent);
		$mailSize=strlen($mailContent);

		if($this->imap_put_line($this->TAG." APPEND \"$sentBox\" (\\Seen) {".$mailSize."}"))
		{
			if($this->imap_put_line($mailContent))
			{
				$reply=$this->imap_get_server_responce();
				if(!$this->imap_is_ok($reply))
					return false;
			}
		}
		else
		{
			return false;
		}
		return $reply;
	}

	function imap_fetch_mail($msg_set,$msg_data_name)
	{
		$msg_set=trim($msg_set);
		$msg_data_name=trim($msg_data_name);

		if($this->imap_put_line($this->TAG." FETCH $msg_set ($msg_data_name)"))
		{
			$reply=$this->imap_get_server_responce();
			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}
		return $reply;
	}

	function imap_sent_date($msg_set)
	{
		$msg_set=trim($msg_set);
		$reply=$this->imap_fetch_mail($msg_set,"BODY.PEEK[HEADER.FIELDS (DATE)]");

		$return_date="";
		$temp_arr=explode("\r\n",$reply);
		for($i=0;$i<count($temp_arr)-1;$i++)
		{
			$line=strtoupper($temp_arr[$i]);
			$a=$this->imap_explodeQuotedString(" ", $line);
			if($a[0]=="DATE:")
			{
				$return_date=trim(str_replace("DATE:","",$line));
				break;
			}
		}
		return $return_date;
	}

	function imap_list_mailbox()
	{
		$ref_mail_box="\"\"";
		$wild_card="*";

		if($this->imap_put_line($this->TAG." LIST $ref_mail_box $wild_card"))
		{
			$reply=$this->imap_get_server_responce();
			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}

		$return_arr=array();
		$temp_arr=explode("\r\n",$reply);
		for($i=0;$i<count($temp_arr)-1;$i++)
		{
			$line=$temp_arr[$i];
			$a=$this->imap_explodeQuotedString(" ", $line);
			array_push($return_arr,str_replace("\"", "", $a[count($a)-1]));
		}
		return $return_arr;
	}

	function imap_create_mailbox()
	{
		$sentBox="\"".$this->SENT_BOX."\"";
		if($this->imap_put_line($this->TAG." CREATE $sentBox"))
		{
			$reply=$this->imap_get_server_responce();
			if(!$this->imap_is_ok($reply))			
				return false;
		}
		else
		{
			return false;
		}
		return true;
	}

	function imap_subscribe_mailbox()
	{
		$sentBox="\"".$this->SENT_BOX."\"";
		if($this->imap_put_line($this->TAG." SUBSCRIBE $sentBox"))
		{
			$reply=$this->imap_get_server_responce();
			if(!$this->imap_is_ok($reply))			
				return false;
		}
		else
		{
			return false;
		}
		return true;
	}

	function imap_get_message($msgno)
	{
		$response=$this->imap_fetch_mail($msgno,"BODY[]");
		$temp_arr=explode("\n",$response);
		array_shift($temp_arr);
		array_shift($temp_arr);
		array_pop($temp_arr);
		array_pop($temp_arr);

		return implode("\n",$temp_arr);
	}

	function imap_get_header($msgno)
	{
		$response=$this->imap_fetch_mail($msgno,"BODY[HEADER]");
		$temp_arr=explode("\n",$response);
		array_shift($temp_arr);
		array_shift($temp_arr);
		array_pop($temp_arr);
		array_pop($temp_arr);

		return implode("\n",$temp_arr)."\r\n\r\n";
	}

	function imap_get_list($flag)
	{
		$reply=$this->imap_get_status($this->MAIL_BOX,$flag);
		$reply=spliti($flag,$reply);
		return intval($reply[1]);
	}

	function imap_get_status($mail_box,$status_cmd)
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		$cmd=$this->TAG." STATUS \"$mail_box\" ($status_cmd)";
		if($this->imap_put_line($cmd))
		{
			$reply=$this->imap_get_server_responce();

			if($this->DEBUG) { @error_log("IMAP SEND [$cmd] GOT [$reply]",0); }

			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}
		return $reply;
	}

	function imap_select_mailbox()
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		$cmd=$this->TAG." SELECT \"$this->MAIL_BOX\"";
		if($this->imap_put_line($cmd))
		{
			$reply=$this->imap_get_server_responce();

			if($this->DEBUG) { @error_log("IMAP SEND [$cmd] GOT [$reply]",0); }

			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}
		return $reply;
	}

	function imap_close()
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		if($this->MUST_UPDATE)
			$this->imap_close_mailbox();

		$this->imap_logout();

		fclose($fp);
		unset($this->FP);
		return true;
	}

	function imap_logout()
	{
		$cmd=$this->TAG." LOGOUT";
		$reply = $this->send_cmd($cmd);

		if($this->DEBUG) { @error_log("IMAP SEND [$cmd] GOT [$reply]",0); }

		return true;
	}

	function imap_close_mailbox()
	{
		if(!isset($this->FP))
			return false;
		$fp = $this->FP;

		$cmd=$this->TAG." CLOSE";
		if($this->imap_put_line($cmd))
		{
			$reply=$this->imap_get_server_responce();

			if($this->DEBUG) { @error_log("IMAP SEND [$cmd] GOT [$reply]",0); }

			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}
		return $reply;
	}

	function imap_store_mail_flag($msg_set,$msg_data_name,$value)
	{
		$msg_set =trim($msg_set);
		$msg_data_name =trim($msg_data_name);
		$value =trim($value);

		$cmd=$this->TAG." STORE $msg_set $msg_data_name ($value)";

		if($this->imap_put_line($cmd))
		{
			$reply=$this->imap_get_server_responce();

			if($this->DEBUG) { @error_log("IMAP SEND [$cmd] GOT [$reply]",0); }

			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}
		return $reply;
	}

	function imap_delete_message($msgno,$uid)
	{
		$this->MUST_UPDATE=true;
		return $this->imap_store_mail_flag($msgno,"+Flags","\\Deleted");
	}

	function imap_seen_message($msgno)
	{
		return $this->imap_store_mail_flag($msgno,"+Flags","\\Seen");
	}

	function imap_unseen_message($msgno)
	{
		return $this->imap_store_mail_flag($msgno,"-Flags","\\Seen");
	}

	function imap_answered_message($msgno)
	{
		return $this->imap_store_mail_flag($msgno,"+Flags","\\Answered");
	}

	function imap_flagged_message($msgno)
	{
		return $this->imap_store_mail_flag($msgno,"+Flags","\\Flagged");
	}

	function imap_move_messages($messages, $tofolder)
	{
		$cmd=$this->TAG." MOVE $messages \"$tofolder\"";

		if($this->imap_put_line($cmd))
		{
			$reply=$this->imap_get_server_responce();

			if($this->DEBUG) { @error_log("IMAP SEND [$cmd] GOT [$reply]",0); }

			if(!$this->imap_is_ok($reply))
				return false;
			else
				return true;
		}
		else
		{
			return false;
		}
	}

	function imap_search_uid($uid)
	{
		$cmd=$this->TAG." SEARCH UID $uid";

		if($this->imap_put_line($cmd))
		{
			$reply=$this->imap_get_server_responce();

			if(!$this->imap_is_ok($reply))
				return false;
		}
		else
		{
			return false;
		}

		$reply=spliti("SEARCH",$reply);
		return intval($reply[1]);
	}
}
?>
