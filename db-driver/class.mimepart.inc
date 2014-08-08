<?php
require("class.headers.inc");

class mimepart
{
	var $body;
	var $mimeparts = array();
	var $offset;
	var $part_count;
	var $boundary;
	var $headers;
	var $Body_Charset;

	function mimepart($part = 0)
	{
		$this->headers = new headers;
		if ($part)
		{
			$this->from_text($part);
		}
	}

	function from_text(&$part)
	{
		$this->headers->extract($part);
		$this->body = $this->extract_whole_body($part);	
	}

	function extract_whole_body(&$mail)
	{
		$divider = "\r\n\r\n";
		$pos = strpos($mail, $divider);
		if($pos === false) 	//return substr($mail, $pos + strlen($divider));
			return $mail;
		else
		{
			$subStrPos=($pos + strlen($divider));
			return substr($mail, $subStrPos);
		}		
	}
	
	// Merged converting underscore(_) into space if the mail is quoted printable -- kumar raju k.
	function subject_decode($data, $subject)
	{
		preg_match("/=\?(.*?)\?([QB])\?(.*?)\?=/sexi", $data, $matches);
		if($matches[2]=="Q")
			return str_replace("_"," ",$subject);
		else
			return $subject;
	}

	function charset_decode($string)
	{
		return preg_replace("/=\?(.*?)\?([QB])\?(.*?)\?=/sexi", '$this->decode("\\3", "\\2")', $string);
	}

	function decode_header($data)
	{
		global $CharSet_mail_array;

		preg_match('/=\?(.*?)\?/',$data,$matches);
		$charset=strtolower($matches[1]);

		if($charset!='' && $charset!=CONVERT_DEFAULT_MAIL_CHAR && in_array($charset,$CharSet_mail_array))
		{
			$convertCharset=new ConvertCharset($charset,CONVERT_DEFAULT_MAIL_CHAR,false);
			return $convertCharset->Convert($this->charset_decode($data));
		}
		else
		{
			return $this->charset_decode($data);
		}
	}

	function qp_decode($string)
	{
		// Remove all soft linebreaks wich are an = followed by a CRLF pair All whitespaces at the end of the line are ignored
		$string = preg_replace("/(=\s*\r\n)/m", "", $string);
		// Replace the =XY encoded parts with the correct character
		$string = preg_replace("/(=([0-9A-F]){1,1}([0-9A-F]){1,1})/e", "chr(hexdec('\\2'.'\\3'))", $string);
		return $string;
	}
	
	function decode($data = "", $encoding = "")
	{
		if (empty($encoding))
		{
			if (empty($data))
				$data = $this->body;

			$ctf = $this->get_header("Content-Transfer-Encoding");
			if (!$ctf)
				return $data;

			if (strcasecmp($ctf["CONTENT-TRANSFER-ENCODING"], "quoted-printable") == 0)
				$encoding = "Q";
			else if (strcasecmp($ctf["CONTENT-TRANSFER-ENCODING"], "base64") == 0)
				$encoding = "B";
		}
	
		if ($encoding == "Q")
			return quoted_printable_decode($data);
		else if ($encoding == "B")
			return base64_decode($data);
		else
			return $data;
	}	
	
	// Strip Header comments from the string Comments are in brackets outside of quotes
	function strip_comment($string, &$comment)
	{
		$len = strlen($string);
		$ret_str = "";
		$quoted = false;
		$in_brackets = 0;
		for($i = 0; $i < $len; $i++)
		{
			$char = $string[$i];
			if (!$quoted && $in_brackets && $char == ')')
			{
				// A comment ends
				$in_brackets--;
				continue;
			}
			if (!$quoted && $char == '(')
			{
				// A comment begins
				$in_brackets++;
				continue;
			}
			if (!$quoted && $in_brackets)
			{
				// Add the char in brackets to the comment string
				$comment .= $char;
				continue;
			}
			if ($char == '"')
			{
				$quoted = !$quoted;
			}
			// We are not in brackets. Copy the character to the new string
			$str_ret .= $char;
		}
		return $str_ret;
	}
	
	function get_header($name)
	{
		$header = stripslashes($this->headers->get($name));
		if (!$header)
		{
			return false;
		}
		$parameter = $this->split_header($header, ";");
		$header_arr[strtoupper($name)] = $parameter[0];
		reset($parameter);
		next($parameter);
		while(list($key, $p) = each($parameter))
		{
			$split = $this->split_header($p, "=");
			$header_arr[strtoupper($split[0])] = $this->un_quote($split[1]);
		}
		return $header_arr;
	}

	// Extract part number $num
	function extract_part($num)
	{
		// Did we extract this part already?
		if (isset($this->mimeparts[$num]) && !empty($this->mimeparts[$num]))
		{
			// Yes, we extracted it
			return true;
		}

		// Get the boundary
		$c_type = $this->get_header("Content-Type");
		$type = $c_type["CONTENT-TYPE"];
		$is_multipart = (strtoupper(substr($type, 0, strlen("MULTIPART/"))) == "MULTIPART/");
		if (empty($c_type["BOUNDARY"]))
		{
			return false;
		}
		$boundary = "--".$c_type["BOUNDARY"]."\r\n";
		if (!isset($this->part_count) || empty($this->part_count))
		{
			$this->part_count = substr_count($this->body, $boundary);
		}
		if ($num > $this->part_count)
		{
			return false;
		}
		$endboundary = "\r\n--".$c_type["BOUNDARY"]."--";
		$pos = 0;
		//echo nl2br($this->body); We get all the parts from 1 to $num

		for($i = 1; $i <= $num; $i++)
		{
			// Check if we already extracted this part
			if (isset($this->mimeparts[$i]) && !empty($this->mimeparts[$i]))
			{
				// Yes, we extracted it, so keep on going
				continue;
			}
			
			if ($i > 1)
			{
				// The startposition is the offset of the previous part
				$pos = $this->mimeparts[$i - 1]->offset;
			}
			else
			{
				// This is the first part. Search for the first boundary
				$pos = strpos($this->body, $boundary);

				if ($pos === false)
				{
					// No boundary was found
					return false;
				}
				$pos += strlen($boundary);
			}
			
			// Search the boundary that closes this part
			$pos2 = strpos($this->body, $boundary, $pos);

			if ($pos2 === false)
			{
				// The 2nd boundary was not found. Check for end boundary
				$pos2 = strpos($this->body, $endboundary, $pos);
				if ($pos2 === false)
				{
					// The endboundary is also not present. The mail is corrupt.
					// We assume that the end of the mail is the end of the part
					$pos2 = strlen($this->body);
				}
				$end = true;
			}
			$part_str = substr($this->body, $pos, $pos2 - $pos);
	
			$part = new mimepart($part_str);
			// This is the offset in the body where the part ends
			$part->offset = $pos2 + strlen($boundary);

			$this->mimeparts[$i] = $part;
			$j = 1;
			while($this->mimeparts[$i]->extract_part($j))
			{
				//echo($this->mimeparts[$i]->get_attachment($j));
				$j++;
			}
			if ($end)
				break;
		}
		return true;
	}

	function get_part($num, $part)
	{
		if ($num == 0)
		{
			return $this;
		}
		if ($part->extract_part($num) === false)
		{
			return false;
		}
		return $part->mimeparts[$num];
	}

	function get_attachment($att_path)
	{
		$part = $this;
		$part = $part->get_part($att_path, $part);
		if ($part === false)
		{
			return false;
		}
		return $part;
	}
	
	function get_attachment_data($att_path)
	{
		$part = $this->get_attachment($att_path);
		if ($part === false)
		{
			return false;
		}
		return $part->decode($part->body);
	}

	function get_attachment_cid($num)
	{
		$inline = $this->mimeparts[$num]->get_header("Content-Id");
		$contId=$inline["CONTENT-ID"];
		if(trim($inline["CONTENT-ID"])=="")
		{
			$inline = $this->mimeparts[$num]->get_header("Content-Location");
			$contId=$inline["CONTENT-LOCATION"];
		}
		return $contId;
	}
	
    function get_attachment_filename($num)
    {
        if ($num == 0)
            $c_type = $this->get_header("Content-Disposition");
        else
            $c_type = $this->mimeparts[$num]->get_header("Content-Disposition");

        if($c_type["FILENAME"]!="")
            return $c_type["FILENAME"];
        else
            return $this->get_attachment_name($num);
    }

    function get_attachment_method($num)
    {
		if ($num == 0)
			$c_type = $this->get_header("Content-Type");
		else
			$c_type = $this->mimeparts[$num]->get_header("Content-Type");
		return $c_type["METHOD"];
    }

	function get_attachment_name($num)
	{
		if ($num == 0)
			$c_type = $this->get_header("Content-Type");
		else
			$c_type = $this->mimeparts[$num]->get_header("Content-Type");
		return $c_type["NAME"];
	}

	function get_attachment_type($num)
	{
		if ($num == 0)
			$c_type = $this->get_header("Content-Type");			
		else
			$c_type = $this->mimeparts[$num]->get_header("Content-Type");
		return $c_type["CONTENT-TYPE"];
	}

	function extract_headers(&$part)
	{
		$header_arr = array();
		$header_str = $this->get_header_str($part)."\r\n";
		$line = strtok($header_str, "\n");
		while(1)
		{
			// Remove trailing whitespaces (the \r)
			$line = rtrim($line);
			if (preg_match("/^\s/", $line))
			{
				// The new line starts with a whitespace. This indicates That the headerpart is "folded" (multiline) and that the current line belongs to the same header entity
				$header .= " $line";
				$line = strtok("\n");
				continue;
			}
			else if (!empty($header))
			{
				// The $header now contains a header which we split up into its parts now
				if (preg_match("/^(.*?): (.*)$/", $header, $matches))
				{
					$header_arr[$matches[1]] = $matches[2];
				}
			}
			if (!$line)
			{
				// This was the last line
				break;
			}
			// A new header entity begins
			$header = $line;
			$line = strtok("\n");
		}
		return $header_arr;
	}
	
	function un_quote($string)
	{
		$splitRegex = '/([^;\'"]*[\'"]([^"]*([^"]*)*)[\'"][^;\'"]*|([^;]+))(;|$)/';
		preg_match_all($splitRegex, $string, $matches);

		$parameters = array();
		for ($i=0; $i<count($matches[0]); $i++)
		{
			$param = $matches[0][$i];
			while (substr($param, -2) == '\;')
				$param .= $matches[0][++$i];
			$parameters[] = $param;
		}

		for ($i = 0; $i < count($parameters); $i++)
			$param_value=trim($parameters[$i], "'\";\t\\ ");

		return $param_value;
	}
	
	function split_header ($string, $split_char)
	{
		$quoted = false; // true if in a quoted part
		$in_brackets = 0; // Reference counter of open brackets
		$split_arr = array();
		$old_pos = 0; // The last position of the $split_char
		$len = strlen($string);
		// Run throught the string
		for($i = 0; $i < $len; $i++)
		{
			$char = $string[$i];

			// $quoted == true after the first occurence of " $quoted == false after the 2nd occurence of " Quotes inside brackets are ignored
			if (!$in_brackets && $char == '"')
			{
				$quoted = !$quoted;
				continue;
			}
			// Brackets inside a quote are ignored
			else if (!$quoted && $char == '(')
			{
				$in_brackets++; // Increase the open brackets reference counter
				continue;
			}
			// Check for closing bracket if one was opened previously Brackets inside a quote are ignored
			else if (!$quoted && $in_brackets && $char == ')')
			{
				$in_brackets--; // Decrease the open brackets reference counter
				continue;
			}
			
			if (!$in_brackets && !$quoted && $char == $split_char)
			{
				// We found a string. Add it to the array
				$s = trim(substr($string, $old_pos, $i - $old_pos));
				$split_arr[] = $s;
				$old_pos = $i + 1;
			}
		}
		
		// Add the last part of the string to the array
		$s = trim(substr($string, $old_pos, $len - $old_pos));
		if (!empty($s))
		{
			$split_arr[] = $s;
		}
		return $split_arr;
	}

	function make_timestamp($date)
	{
		if (empty($date))
			return mktime();
		else
			return strtotime($date);
	}
	
	//get the email charset
	function get_Charset($num)
	{
		if ($num == 0)
			$c_type = $this->get_header("Content-Type");			
		else
			$c_type = $this->mimeparts[$num]->get_header("Content-Type");
				
		$ThisCharset = strtolower($c_type["CHARSET"]);
		return $ThisCharset;
	}

	function get_method($num)
	{
		if ($num == 0)
			$c_type = $this->get_header("Content-Type");			
		else
			$c_type = $this->mimeparts[$num]->get_header("Content-Type");
				
		return strtolower($c_type["method"]);
	}
}
?>