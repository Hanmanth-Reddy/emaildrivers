<?php
class headers 
{
	var $header_arr = array();
	
	function get($name)
	{
		$element = $this->header_arr[strtolower($name)];
		return $element["content"];
	}
	
	function put($name, $content)
	{
		if(strtolower($name)=="received")
		{
			if($this->header_arr[strtolower($name)]=="")
			{
				$rdate=explode(";",$content);
				if(count($rdate)>1)
				{
					$element["name"] = $name;
					$element["content"] = $content;
					$this->header_arr[strtolower($name)] = $element;
				}
			}
		}
		else
		{
			$element["name"] = $name;
			$element["content"] = $content;
			$this->header_arr[strtolower($name)] = $element;
		}
	}
	
	function get_header_str(&$part)
	{
		$pos = strpos($part, "\r\n\r\n");
		return str_replace("\tX-Mailer:","X-Mailer:",substr($part, 0, $pos));
	}	

	function extract($part)
	{
		// Clear previous values
		$this->header_arr = array();
		@set_time_limit(60);
		$header_str = $this->get_header_str($part)."\r\n";

		$line = strtok($header_str, "\n");
		while(1)
		{
			// Remove trailing whitespaces (the \r)
			$rline = rtrim($line);

			if (preg_match("/^\s/", $rline))
			{
				// The new line starts with a whitespace. This indicates That the headerpart is "folded" (multiline) and that the current line belongs to the same header entity
				$header .=preg_replace("/^\s/","",$rline);	// removing first char space -- " $rline";
				$line = strtok("\n");
				continue;
			}
			else if (!empty($header))
			{
				// The $header now contains a header which we split up into its parts now
				if (preg_match("/^(.*?):(.*)$/", $header, $matches) || preg_match("/^(.*?):\t(.*)$/", $header, $matches))
					$this->put($matches[1], trim($matches[2]));
			}

			if (!$line)
				break;
	
			if($rline!="")
			{				
				// A new header entity begins
				if (preg_match("/^(.*?):(.*)$/", $line, $matches) || preg_match("/^(.*?):\t(.*)$/", $line, $matches))
				{
					if(strtolower($matches[1])=="received")
						$header = trim($line);
					else
						$header = $line;
				}
				else
				{
					$header = $line;
				}
			}

			$line = strtok("\n");
		}
	}

	// Strip Header comments from the string Comments are in brackets outside of quotes
	function strip_comment($string)
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
				$in_brackets--;
				continue;
			}
			if (!$quoted && $char == '(')
			{
				$in_brackets++;
				continue;
			}
			if (!$quoted && $in_brackets)
			{
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
	
	function split_header($string, $split_char)
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

			// $quoted == true after the first occurence of " // $quoted == false after the 2nd occurence of " // Quotes inside brackets are ignored
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
			// Check for closing bracket if one was opened previously // Brackets inside a quote are ignored	
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
			$split_arr[] = $s;

		return $split_arr;
	}	
	
	function get_splitted($name)
	{
		$header = stripslashes($this->get($name));
		if (!$header)
		{
			return false;
		}
		$parameter = $this->split_header($header, ";");
		$header_arr[$name] = $parameter[0];
		reset($parameter);
		next($parameter);
		while(list($key, $p) = each($parameter))
		{
			$split = $this->split_header($p, "=");
			$header_arr[$split[0]] = $this->un_quote($split[1]);
		}
		return $header_arr;
	}	
}
?>