<?php
class HttpClient 
{
	// Request vars
	var $host;
	var $port;
	var $path;
	var $method;
	var $postdata = '';
	var $cookies = array();
	var $referer;
	var $accept = 'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
	var $accept_encoding = 'gzip';
	var $accept_language = 'en-us';
	var $user_agent = 'AkkenCloud HttpClient';

	// Options
	var $timeout = 10;
	var $use_gzip = true;
	var $persist_cookies = true;  // If true, received cookies are placed in the $this->cookies array ready for the next request Note: This currently ignores the cookie path (and time) completely. Time is not important, but path could possibly lead to security problems.
	var $persist_referers = true; // For each request, sends path of last request as referer
	var $debug = false;
	var $handle_redirects = true; // Auaomtically redirect if Location or URI header is found
	var $headers_only = false;    // If true, stops receiving once headers have been read.

	// Basic authorization variables
	var $username;
	var $password;
	var $atype;

	// Response vars
	var $res_status;
	var $res_error;
	var $status;
	var $status_string;
	var $headers = array();
	var $content = '';
	var $errormsg;

	// Tracker variables
	var $redirect_count = 0;
	var $cookie_host = '';

	function HttpClient($host, $port=80)
	{
		$this->host = $host;
		$this->port = $port;
	}

	function get($path, $data = false)
	{
		$this->path = $path;
		$this->method = 'GET';

		if($data)
			$this->path.='?'.http_build_query($data);

		return $this->doRequest();
	}


	function post($path, $data)
	{
		$this->path = $path;
		$this->method = 'POST';

		if($data)
			$this->postdata = json_encode($data);

		return $this->doRequest();
	}

	function put($path, $data)
	{
		$this->path = $path;
		$this->method = 'PUT';

		if($data)
			$this->postdata = json_encode($data);

		return $this->doRequest();
	}

	function delete($path, $data = false)
	{
		$this->path = $path;
		$this->method = 'DELETE';

		if($data)
			$this->postdata = json_encode($data);

		return $this->doRequest();
	}

	function buildQueryString($data)
	{
		$querystring = '';
		if(is_array($data))
		{
			foreach($data as $key => $val)
			{
				if(is_array($val))
				{
					foreach($val as $key2 => $val2)
						$querystring.=urlencode($key2).'='.urlencode($val2).'&';
				}
				else
				{
					$querystring.=urlencode($key).'='.urlencode($val).'&';
				}
			}
			$querystring=substr($querystring, 0, -1);
		}
		else
		{
			$querystring=$data;
		}
		return $querystring;
	}

	function doRequest()
	{
		if(!$fp = @fsockopen("ssl://".$this->host, $this->port, $errno, $errstr, $this->timeout))
		{
			switch($errno)
			{
				case -3:
					$this->errormsg = 'Socket creation failed (-3)';
				case -4:
					$this->errormsg = 'DNS lookup failure (-4)';
				case -5:
					$this->errormsg = 'Connection refused or timed out (-5)';
				default:
					$this->errormsg = 'Connection failed ('.$errno.')';
				$this->errormsg .= ' '.$errstr;
				$this->debug($this->errormsg);
			}
			return false;
		}

		socket_set_timeout($fp, $this->timeout);

		if($this->OAUTH_String!="")
			$request = $this->buildOAUTHRequest();
		else
			$request = $this->buildRequest();

		$this->debug('Request', $request);
		fwrite($fp, $request);

		$this->headers = array();
		$this->content = '';
		$this->errormsg = '';

		$inHeaders = true;
		$atStart = true;

		while (!feof($fp))
		{
			$line = fgets($fp, 4096);

			if($atStart)
			{
				$atStart = false;
				if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m))
				{
					$this->errormsg = "Status code line invalid: ".htmlentities($line);
					$this->debug($this->errormsg);
					return false;
				}

				$http_version = $m[1];
				$this->status = $m[2];
				$this->status_string = $m[3];
				$this->debug('Response Status', trim($line));
				continue;
			}

			if ($inHeaders) 
			{
				if (trim($line) == '')
				{
					$inHeaders = false;
					$this->debug('Received Headers', $this->headers);
					if ($this->headers_only)
						break;
					continue;
				}

				if(!preg_match('/([^:]+):\\s*(.*)/', $line, $m))
					continue;

				$key = strtolower(trim($m[1]));
				$val = trim($m[2]);

				if(isset($this->headers[$key]))
				{
					if(is_array($this->headers[$key]))
						$this->headers[$key][] = $val;
					else 
						$this->headers[$key] = array($this->headers[$key], $val);
    	        } 
				else 
				{
					$this->headers[$key] = $val;
				}
				continue;
			}

			$this->content .= $line;
		}
		fclose($fp);

		if (isset($this->headers['content-encoding']) && $this->headers['content-encoding'] == 'gzip')
		{
			$this->debug('Content is gzip encoded, unzipping it');
			$this->content = substr($this->content, 10);
			$this->content = gzinflate($this->content);
		}

		if ($this->persist_cookies && isset($this->headers['set-cookie']) && $this->host == $this->cookie_host)
		{
			$cookies = $this->headers['set-cookie'];
			if(!is_array($cookies)) 
				$cookies = array($cookies);

			foreach($cookies as $cookie)
			{
				if (preg_match('/([^=]+)=([^;]+);/', $cookie, $m))
					$this->cookies[$m[1]] = $m[2];
			}
			$this->cookie_host = $this->host;
		}

		if($this->persist_referers)
		{
			$this->debug('Persisting referer: '.$this->getRequestURL());
			$this->referer = $this->getRequestURL();
		}

		if($this->handle_redirects)
		{
			$location = isset($this->headers['location']) ? $this->headers['location'] : '';
			$uri = isset($this->headers['uri']) ? $this->headers['uri'] : '';

			if($location || $uri)
			{
				$url = parse_url($location.$uri);
				return $this->get($url['path']);
			}
		}

		if($this->getStatus()>="200" && $this->getStatus()<"300")
		{
			$this->res_status=true;
			return true;
		}
		else
		{
			$this->res_status=false;
			if(trim($this->getContent())!="")
				$this->res_error.=$this->getContent()."\n\n\n";

			return false;
		}
	}

	function buildRequest()
	{
		$headers = array();

		$headers[] = "{$this->method} {$this->path} HTTP/1.0";
		$headers[] = "Host: {$this->host}";
		$headers[] = "User-Agent: {$this->user_agent}";
		$headers[] = 'Content-Type: application/json';

		if ($this->username && $this->password)
			$headers[] = 'Authorization: SHR apiKey="'.$this->username.'" hash="'.$this->password.'"';

		if($this->postdata)
			$headers[] = 'Content-Length: '.strlen($this->postdata);

		$request = implode("\r\n", $headers)."\r\n\r\n".$this->postdata;
		$this->postdata = "";

		return $request;
	}

	function getStatus()
	{
		return $this->status;
	}

	function getContent() 
	{
		$this->debug('Response Content: '.$this->content);
		return $this->content;
	}

	function getHeaders() 
	{
		return $this->headers;
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

	function split_header($string, $split_char)
	{
		$quoted = false;
		$in_brackets = 0;
		$split_arr = array();
		$old_pos = 0;
		$len = strlen($string);

		for($i = 0; $i < $len; $i++)
		{
			$char = $string[$i];

			if (!$in_brackets && $char == '"')
			{
				$quoted = !$quoted;
				continue;
			}
			else if (!$quoted && $char == '(')
			{
				$in_brackets++;
				continue;
			}
			else if (!$quoted && $in_brackets && $char == ')')
			{
				$in_brackets--;
				continue;
			}
			
			if (!$in_brackets && !$quoted && $char == $split_char)
			{
				$s = trim(substr($string, $old_pos, $i - $old_pos));
				$split_arr[] = $s;
				$old_pos = $i + 1;
			}
		}
		
		$s = trim(substr($string, $old_pos, $len - $old_pos));
		if (!empty($s))
			$split_arr[] = $s;

		return $split_arr;
	}

	function getHeader($name) 
	{
		$name = strtolower($name);

		$header = stripslashes($this->headers[$name]);
		if(!$header)
			return false;

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

	function getError() 
	{
		return $this->errormsg;
	}

	function getCookies() 
	{
		return $this->cookies;
	}

	function getRequestURL() 
	{
		$url='https://'.$this->host;
		$url.=$this->path;
		return $url;
	}

	function setUserAgent($string) 
	{
		$this->user_agent = $string;
	}

	function setAuthorization($username, $password) 
	{
		$this->username = $username;
		$this->password = $password;
	}

	function setCookies($array) 
	{
		$this->cookies = $array;
	}

	function useGzip($boolean) 
	{
		$this->use_gzip = $boolean;
	}

	function setPersistCookies($boolean) 
	{
		$this->persist_cookies = $boolean;
	}

	function setPersistReferers($boolean) 
	{
		$this->persist_referers = $boolean;
	}

	function setHandleRedirects($boolean) 
	{
		$this->handle_redirects = $boolean;
	}

	function debug($msg, $object = false) 
	{
		$this->debug_string.='<div style="border: 1px solid red; padding: 0.5em; margin: 0.5em;"><strong>HttpClient Debug:</strong> '.$msg;
		if ($object) 
		{
			ob_start();
			print_r($object);
			$content = htmlentities(ob_get_contents());
			ob_end_clean();

			$this->debug_string.='<pre>'.$content.'</pre>';
		}
		$this->debug_string.='</div>';
	}
}
?>