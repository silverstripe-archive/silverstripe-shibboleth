<?php

/**
 * Misc static functions that is used several places.in example parsing and id generation.
 *
 * @author Andreas Åkre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @package simpleSAMLphp
 * @version $Id: Utilities.php 2354 2010-06-23 11:20:37Z olavmrk $
 */
class SimpleSAML_Utilities {

	/**
	 * List of log levels.
	 *
	 * This list is used to restore the log levels after some log levels are disabled.
	 *
	 * @var array
	 */
	private static $logLevelStack = array();


	/**
	 * The current mask of disabled log levels.
	 *
	 * Note: This mask is not directly related to the PHP error reporting level.
	 *
	 * @var int
	 */
	public static $logMask = 0;


	/**
	 * Will return sp.example.org
	 */
	public static function getSelfHost() {
	
		if (array_key_exists('HTTP_HOST', $_SERVER)) {
			$currenthost = $_SERVER['HTTP_HOST'];
		} elseif (array_key_exists('SERVER_NAME', $_SERVER)) {
			$currenthost = $_SERVER['SERVER_NAME'];
		} else {
			/* Almost certainly not what you want, but ... */
			$currenthost = 'localhost';
		}

		if(strstr($currenthost, ":")) {
				$currenthostdecomposed = explode(":", $currenthost);
				$currenthost = $currenthostdecomposed[0];
		}
		return $currenthost;# . self::getFirstPathElement() ;
	}

	/**
	 * Will return https
	 */
	public static function getSelfProtocol() {
		$s = empty($_SERVER["HTTPS"]) ? ''
			: ($_SERVER["HTTPS"] == "on") ? 's'
			: '';
		if ( empty($_SERVER["HTTPS"]) && (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] == 443)) $s = 's';
		$protocol = self::strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
		return $protocol;
	}

	/**
	 * Will return https://sp.example.org
	 */
	public static function selfURLhost() {
	
		$currenthost = self::getSelfHost();
	
		$protocol = self::getSelfProtocol();
		
		$portnumber = isset($_SERVER["SERVER_PORT"]) ? $_SERVER["SERVER_PORT"] : 80;
		$port = ':' . $portnumber;
		if ($protocol == 'http') {
			if ($portnumber == '80') $port = '';
		} elseif ($protocol == 'https') {
			if ($portnumber == '443') $port = '';
		}
			
		$querystring = '';
		return $protocol."://" . $currenthost . $port;
	
	}
	
	/**
	 * This function checks if we should set a secure cookie.
	 *
	 * @return TRUE if the cookie should be secure, FALSE otherwise.
	 */
	public static function isHTTPS() {

		if(!array_key_exists('HTTPS', $_SERVER)) {
			/* Not a https-request. */
			return FALSE;
		}

		if($_SERVER['HTTPS'] === 'off') {
			/* IIS with HTTPS off. */
			return FALSE;
		}

		/* Otherwise, HTTPS will be a non-empty string. */
		return $_SERVER['HTTPS'] !== '';
	}
	
	/**
	 * Will return https://sp.example.org/universities/ruc/baz/simplesaml/saml2/SSOService.php
	 */
	public static function selfURLNoQuery() {
	
		$selfURLhost = self::selfURLhost();
		return $selfURLhost . self::getScriptName();
	
	}
	
	public static function getScriptName() {
		$scriptname = $_SERVER['SCRIPT_NAME'];
		if (preg_match('|^/.*?(/.*)$|', $_SERVER['SCRIPT_NAME'], $matches)) {
			#$scriptname = $matches[1];
		}
		if (array_key_exists('PATH_INFO', $_SERVER)) $scriptname .= $_SERVER['PATH_INFO'];
		
		return $scriptname;
	}
	
	
	/**
	 * Will return sp.example.org/foo
	 */
	public static function getSelfHostWithPath() {
	
		$selfhostwithpath = self::getSelfHost();
		if (preg_match('|^(/.*?)/|', $_SERVER['SCRIPT_NAME'], $matches)) {
			$selfhostwithpath .= $matches[1];
		}
		return $selfhostwithpath;
	
	}
	
	/**
	 * Will return foo
	 */
	public static function getFirstPathElement($trailingslash = true) {
	
		if (preg_match('|^/(.*?)/|', $_SERVER['SCRIPT_NAME'], $matches)) {
			return ($trailingslash ? '/' : '') . $matches[1];
		}
		return '';
	}
	

	public static function selfURL() {
		$selfURLhost = self::selfURLhost();
		return $selfURLhost . self::getRequestURI();	
	}
	
	public static function getRequestURI() {
		
		$requesturi = $_SERVER['REQUEST_URI'];

		if ($requesturi[0] !== '/') {
			/* We probably have an url on the form: http://server/. */
			if (preg_match('#^https?://[^/]*(/.*)#i', $requesturi, $matches)) {
				$requesturi = $matches[1];
			}
		}

		return $requesturi;
	}


	/**
	 * Retrieve the absolute base URL for the simpleSAMLphp installation.
	 *
	 * This function will return the absolute base URL for the simpleSAMLphp
	 * installation. For example: https://idp.example.org/simplesaml/
	 *
	 * The URL will always end with a '/'.
	 *
	 * @return string  The absolute base URL for the simpleSAMLphp installation.
	 */
	public static function getBaseURL() {

		$globalConfig = SimpleSAML_Configuration::getInstance();
		$ret = SimpleSAML_Utilities::selfURLhost() . '/' . $globalConfig->getBaseURL();
		if (substr($ret, -1) !== '/') {
			throw new SimpleSAML_Error_Exception('Invalid value of \'baseurl\' in ' .
				'config.php. It must end with a \'/\'.');
		}

		return $ret;
	}


	/**
	 * Add one or more query parameters to the given URL.
	 *
	 * @param $url  The URL the query parameters should be added to.
	 * @param $parameter  The query parameters which should be added to the url. This should be
	 *                    an associative array. For backwards comaptibility, it can also be a
	 *                    query string representing the new parameters. This will write a warning
	 *                    to the log.
	 * @return The URL with the new query parameters.
	 */
	public static function addURLparameter($url, $parameter) {

		/* For backwards compatibility - allow $parameter to be a string. */
		if(is_string($parameter)) {
			/* Print warning to log. */
			$backtrace = debug_backtrace();
			$where = $backtrace[0]['file'] . ':' . $backtrace[0]['line'];
			SimpleSAML_Logger::warning(
				'Deprecated use of SimpleSAML_Utilities::addURLparameter at ' .	$where .
				'. The parameter "$parameter" should now be an array, but a string was passed.');

			$parameter = self::parseQueryString($parameter);
		}
		assert('is_array($parameter)');

		$queryStart = strpos($url, '?');
		if($queryStart === FALSE) {
			$oldQuery = array();
			$url .= '?';
		} else {
			$oldQuery = substr($url, $queryStart + 1);
			if($oldQuery === FALSE) {
				$oldQuery = array();
			} else {
				$oldQuery = self::parseQueryString($oldQuery);
			}
			$url = substr($url, 0, $queryStart + 1);
		}

		$query = array_merge($oldQuery, $parameter);
		$url .= http_build_query($query, '', '&');

		return $url;
	}


	public static function strleft($s1, $s2) {
		return substr($s1, 0, strpos($s1, $s2));
	}
	
	public static function checkDateConditions($start=NULL, $end=NULL) {
		$currentTime = time();
	
		if (! empty($start)) {
			$startTime = self::parseSAML2Time($start);
			/* Allow for a 10 minute difference in Time */
			if (($startTime < 0) || (($startTime - 600) > $currentTime)) {
				return FALSE;
			}
		}
		if (! empty($end)) {
			$endTime = self::parseSAML2Time($end);
			if (($endTime < 0) || ($endTime <= $currentTime)) {
				return FALSE;
			}
		}
		return TRUE;
	}
	
	public static function cert_fingerprint($pem) {
		$x509data = base64_decode( $pem );
		return strtolower( sha1( $x509data ) );
	}
	
	public static function generateID() {
		return '_' . self::stringToHex(self::generateRandomBytes(21));
	}
	

	/**
	 * This function generates a timestamp on the form used by the SAML protocols.
	 *
	 * @param $instant  The time the timestamp should represent.
	 * @return The timestamp.
	 */
	public static function generateTimestamp($instant = NULL) {
		if($instant === NULL) {
			$instant = time();
		}
		return gmdate('Y-m-d\TH:i:s\Z', $instant);
	}
	
	public static function generateTrackID() {		
		$uniqueid = substr(md5(uniqid(rand(), true)), 0, 10);
		return $uniqueid;
	}
	
	public static function array_values_equals($array, $equalsvalue) {
		$foundkeys = array();
		foreach ($array AS $key => $value) {
			if ($value === $equalsvalue) $foundkeys[] = $key;
		}
		return $foundkeys;
	}
	
	public static function checkAssocArrayRules($target, $required, $optional = array()) {

		$results = array(
			'required.found' 		=> array(),
			'required.notfound'		=> array(),
			'optional.found'		=> array(),
			'optional.notfound'		=> array(),
			'leftovers'				=> array()
		);
		
		foreach ($target AS $key => $value) {
			if(in_array($key, $required)) {
				$results['required.found'][$key] = $value;
			} elseif (in_array($key, $optional)) {
				$results['optional.found'][$key] = $value;
			} else {
				$results['leftovers'][$key] = $value;
			}
		}
		
		foreach ($required AS $key) {
			if (!array_key_exists($key, $target)) {
				$results['required.notfound'][] = $key;
			}
		}
		
		foreach ($optional AS $key) {
			if (!array_key_exists($key, $target)) {
				$results['optional.notfound'][] = $key;
			}
		}
		return $results;
	}
	

	/**
	 * Build a backtrace.
	 *
	 * This function takes in an exception and optionally a start depth, and
	 * builds a backtrace from that depth. The backtrace is returned as an
	 * array of strings, where each string represents one level in the stack.
	 *
	 * @param Exception $exception  The exception.
	 * @param int $startDepth  The depth we should print the backtrace from.
	 * @return array  The backtrace as an array of strings.
	 */
	public static function buildBacktrace(Exception $exception, $startDepth = 0) {

		assert('is_int($startDepth)');

		$bt = array();

		/* Position in the top function on the stack. */
		$pos = $exception->getFile() . ':' . $exception->getLine();

		foreach($exception->getTrace() as $t) {

			$function = $t['function'];
			if(array_key_exists('class', $t)) {
				$function = $t['class'] . '::' . $function;
			}

			$bt[] = $pos . ' (' . $function . ')';

			if(array_key_exists('file', $t)) {
				$pos = $t['file'] . ':' . $t['line'];
			} else {
				$pos = '[builtin]';
			}
		}

		$bt[] = $pos . ' (N/A)';

		/* Remove $startDepth elements from the top of the backtrace. */
		$bt = array_slice($bt, $startDepth);

		return $bt;
	}


	/**
	 * Format a backtrace from an exception.
	 *
	 * This function formats a backtrace from an exception in a simple format
	 * which doesn't include the variables passed to functions.
	 *
	 * The bactrace has the following format:
	 *  0: <filename>:<line> (<current function>)
	 *  1: <filename>:<line> (<previous fucntion>)
	 *  ...
	 *  N: <filename>:<line> (N/A)
	 *
	 * @param Exception $e  The exception we should format the backtrace for.
	 * @param int $startDepth  The first frame we should include in the backtrace.
	 * @return string  The formatted backtrace.
	 */
	public static function formatBacktrace(Exception $e, $startDepth = 0) {
		assert('$e instanceof Exception');
		assert('is_int($startDepth)');

		$trace = '';

		$bt = self::buildBacktrace($e, $startDepth);
		foreach($bt as $depth => $t) {
			$trace .= $depth . ': ' . $t . "\n";
		}

		return $trace;
	}


	/* This function converts a SAML2 timestamp on the form
	 * yyyy-mm-ddThh:mm:ss(\.s+)?Z to a UNIX timestamp. The sub-second
	 * part is ignored.
	 *
	 * Andreas comments:
	 *  I got this timestamp from Shibboleth 1.3 IdP: 2008-01-17T11:28:03.577Z
	 *  Therefore I added to possibliity to have microseconds to the format.
	 * Added: (\.\\d{1,3})? to the regex.
	 *
	 *
	 * Parameters:
	 *  $time     The time we should convert.
	 *
	 * Returns:
	 *  $time converted to a unix timestamp.
	 */
	public static function parseSAML2Time($time) {
		$matches = array();


		/* We use a very strict regex to parse the timestamp. */
		if(preg_match('/^(\\d\\d\\d\\d)-(\\d\\d)-(\\d\\d)' .
		              'T(\\d\\d):(\\d\\d):(\\d\\d)(?:\\.\\d+)?Z$/D',
		              $time, $matches) == 0) {
			throw new Exception(
				'Invalid SAML2 timestamp passed to' .
				' parseSAML2Time: ' . $time);
		}

		/* Extract the different components of the time from the
		 * matches in the regex. intval will ignore leading zeroes
		 * in the string.
		 */
		$year = intval($matches[1]);
		$month = intval($matches[2]);
		$day = intval($matches[3]);
		$hour = intval($matches[4]);
		$minute = intval($matches[5]);
		$second = intval($matches[6]);

		/* We use gmmktime because the timestamp will always be given
		 * in UTC.
		 */
		$ts = gmmktime($hour, $minute, $second, $month, $day, $year);

		return $ts;
	}


	/**
	 * Interpret a ISO8601 duration value relative to a given timestamp.
	 *
	 * @param string $duration  The duration, as a string.
	 * @param int $timestamp  The unix timestamp we should apply the duration to. Optional, default
	 *                        to the current time.
	 * @return int  The new timestamp, after the duration is applied.
	 */
	public static function parseDuration($duration, $timestamp = NULL) {
		assert('is_string($duration)');
		assert('is_null($timestamp) || is_int($timestamp)');

		/* Parse the duration. We use a very strict pattern. */
		$durationRegEx = '#^(-?)P(?:(?:(?:(\\d+)Y)?(?:(\\d+)M)?(?:(\\d+)D)?(?:T(?:(\\d+)H)?(?:(\\d+)M)?(?:(\\d+)S)?)?)|(?:(\\d+)W))$#D';
		if (!preg_match($durationRegEx, $duration, $matches)) {
			throw new Exception('Invalid ISO 8601 duration: ' . $duration);
		}

		$durYears = (empty($matches[2]) ? 0 : (int)$matches[2]);
		$durMonths = (empty($matches[3]) ? 0 : (int)$matches[3]);
		$durDays = (empty($matches[4]) ? 0 : (int)$matches[4]);
		$durHours = (empty($matches[5]) ? 0 : (int)$matches[5]);
		$durMinutes = (empty($matches[6]) ? 0 : (int)$matches[6]);
		$durSeconds = (empty($matches[7]) ? 0 : (int)$matches[7]);
		$durWeeks = (empty($matches[8]) ? 0 : (int)$matches[8]);

		if (!empty($matches[1])) {
			/* Negative */
			$durYears = -$durYears;
			$durMonths = -$durMonths;
			$durDays = -$durDays;
			$durHours = -$durHours;
			$durMinutes = -$durMinutes;
			$durSeconds = -$durSeconds;
			$durWeeks = -$durWeeks;
		}

		if ($timestamp === NULL) {
			$timestamp = time();
		}

		if ($durYears !== 0 || $durMonths !== 0) {
			/* Special handling of months and years, since they aren't a specific interval, but
			 * instead depend on the current time.
			 */

			/* We need the year and month from the timestamp. Unfortunately, PHP doesn't have the
			 * gmtime function. Instead we use the gmdate function, and split the result.
			 */
			$yearmonth = explode(':', gmdate('Y:n', $timestamp));
			$year = (int)($yearmonth[0]);
			$month = (int)($yearmonth[1]);

			/* Remove the year and month from the timestamp. */
			$timestamp -= gmmktime(0, 0, 0, $month, 1, $year);

			/* Add years and months, and normalize the numbers afterwards. */
			$year += $durYears;
			$month += $durMonths;
			while ($month > 12) {
				$year += 1;
				$month -= 12;
			}
			while ($month < 1) {
				$year -= 1;
				$month += 12;
			}

			/* Add year and month back into timestamp. */
			$timestamp += gmmktime(0, 0, 0, $month, 1, $year);
		}

		/* Add the other elements. */
		$timestamp += $durWeeks * 7 * 24 * 60 * 60;
		$timestamp += $durDays * 24 * 60 * 60;
		$timestamp += $durHours * 60 * 60;
		$timestamp += $durMinutes * 60;
		$timestamp += $durSeconds;

		return $timestamp;
	}


	/**
	 * Show and log fatal error message.
	 *
	 * This function logs a error message to the error log and shows the
	 * message to the user. Script execution terminates afterwards.
	 *
	 * The error code comes from the errors-dictionary. It can optionally include parameters, which
	 * will be substituted into the output string.
	 *
	 * @param string $trackId  The trackid of the user, from $session->getTrackID().
	 * @param mixed $errorCode  Either a string with the error code, or an array with the error code and
	 *                          additional parameters.
	 * @param Exception $e  The exception which caused the error.
	 */
	public static function fatalError($trackId = 'na', $errorCode = null, Exception $e = null) {

		$config = SimpleSAML_Configuration::getInstance();
		$session = SimpleSAML_Session::getInstance();

		if (is_array($errorCode)) {
			$parameters = $errorCode;
			unset($parameters[0]);
			$errorCode = $errorCode[0];
		} else {
			$parameters = array();
		}

		// Get the exception message if there is any exception provided.
		$emsg   = (empty($e) ? 'No exception available' : $e->getMessage());
		$etrace = (empty($e) ? 'No exception available' : self::formatBacktrace($e));

		if (!empty($errorCode) && count($parameters) > 0) {
			$reptext = array();
			foreach($parameters as $k => $v) {
				$reptext[] = '"' . $k . '"' . ' => "' . $v . '"';
			}
			$reptext = '(' . implode(', ', $reptext) . ')';
			$error = $errorCode . $reptext;
		} elseif(!empty($errorCode)) {
			$error = $errorCode;
		} else {
			$error = 'na';
		}

		// Log a error message
		SimpleSAML_Logger::error($_SERVER['PHP_SELF'].' - UserError: ErrCode:' . $error . ': ' . urlencode($emsg) );
		if (!empty($e)) {
			SimpleSAML_Logger::error('Exception: ' . get_class($e));
			SimpleSAML_Logger::error('Backtrace:');
			foreach (explode("\n", $etrace) as $line) {
				SimpleSAML_Logger::error($line);
			}
		}

		$reportId = SimpleSAML_Utilities::stringToHex(SimpleSAML_Utilities::generateRandomBytes(4));
		SimpleSAML_Logger::error('Error report with id ' . $reportId . ' generated.');

		$errorData = array(
			'exceptionMsg' => $emsg,
			'exceptionTrace' => $etrace,
			'reportId' => $reportId,
			'trackId' => $trackId,
			'url' => self::selfURLNoQuery(),
			'version' => $config->getVersion(),
		);
		$session->setData('core:errorreport', $reportId, $errorData);

		$t = new SimpleSAML_XHTML_Template($config, 'error.php', 'errors');
		$t->data['showerrors'] = $config->getBoolean('showerrors', true);
		$t->data['error'] = $errorData;
		$t->data['errorCode'] = $errorCode;
		$t->data['parameters'] = $parameters;

		/* Check if there is a valid technical contact email address. */
		if($config->getString('technicalcontact_email', 'na@example.org') !== 'na@example.org') {
			/* Enable error reporting. */
			$baseurl = SimpleSAML_Utilities::selfURLhost() . '/' . $config->getBaseURL();
			$t->data['errorReportAddress'] = $baseurl . 'errorreport.php';
		}

		$attributes = $session->getAttributes();
		if (is_array($attributes) && array_key_exists('mail', $attributes) && count($attributes['mail']) > 0) {
			$email = $attributes['mail'][0];
		} else {
			$email = '';
		}
		$t->data['email'] = $email;

		$t->show();
		exit;
	}


	/**
	 * Check whether an IP address is part of an CIDR.
	 */
	static function ipCIDRcheck($cidr, $ip = null) {
		if ($ip == null) $ip = $_SERVER['REMOTE_ADDR'];
		list ($net, $mask) = explode('/', $cidr);
		
		$ip_net = ip2long ($net);
		$ip_mask = ~((1 << (32 - $mask)) - 1);
		
		$ip_ip = ip2long ($ip);
		
		$ip_ip_net = $ip_ip & $ip_mask;
		
		return ($ip_ip_net == $ip_net);
	}


	/* This function redirects the user to the specified address.
	 * An optional set of query parameters can be appended by passing
	 * them in an array.
	 *
	 * This function will use the HTTP 303 See Other redirect if the
	 * current request is a POST request and the HTTP version is HTTP/1.1.
	 * Otherwise a HTTP 302 Found redirect will be used.
	 *
	 * The fuction will also generate a simple web page with a clickable
	 * link to the target page.
	 *
	 * Parameters:
	 *  $url         URL we should redirect to. This URL may include
	 *               query parameters. If this URL is a relative URL
	 *               (starting with '/'), then it will be turned into an
	 *               absolute URL by prefixing it with the absolute URL
	 *               to the root of the website.
	 *  $parameters  Array with extra query string parameters which should
	 *               be appended to the URL. The name of the parameter is
	 *               the array index. The value of the parameter is the
	 *               value stored in the index. Both the name and the value
	 *               will be urlencoded. If the value is NULL, then the
	 *               parameter will be encoded as just the name, without a
	 *               value.
	 *
	 * Returns:
	 *  This function never returns.
	 */
	public static function redirect($url, $parameters = array()) {
		assert(is_string($url));
		assert(strlen($url) > 0);
		assert(is_array($parameters));

		/* Check for relative URL. */
		if(substr($url, 0, 1) === '/') {
			/* Prefix the URL with the url to the root of the
			 * website.
			 */
			$url = self::selfURLhost() . $url;
		}

		/* Determine which prefix we should put before the first
		 * parameter.
		 */
		if(strpos($url, '?') === FALSE) {
			$paramPrefix = '?';
		} else {
			$paramPrefix = '&';
		}

		/* Iterate over the parameters and append them to the query
		 * string.
		 */
		foreach($parameters as $name => $value) {

			/* Encode the parameter. */
			if($value === NULL) {
				$param = urlencode($name);
			} elseif (is_array($value)) {
				$param = "";
				foreach ($value as $val) {
					$param .= urlencode($name) . "[]=" . urlencode($val) . '&';				
				}
			} else {
				$param = urlencode($name) . '=' .
					urlencode($value);
			}

			/* Append the parameter to the query string. */
			$url .= $paramPrefix . $param;

			/* Every following parameter is guaranteed to follow
			 * another parameter. Therefore we use the '&' prefix.
			 */
			$paramPrefix = '&';
		}


		/* Set the HTTP result code. This is either 303 See Other or
		 * 302 Found. HTTP 303 See Other is sent if the HTTP version
		 * is HTTP/1.1 and the request type was a POST request.
		 */
		if($_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1' &&
			$_SERVER['REQUEST_METHOD'] === 'POST') {
			$code = 303;
		} else {
			$code = 302;
		}

		/* Set the location header. */
		header('Location: ' . $url, TRUE, $code);

		/* Disable caching of this response. */
		header('Pragma: no-cache');
		header('Cache-Control: no-cache, must-revalidate');

		/* Show a minimal web page with a clickable link to the URL. */
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"' .
			' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . "\n";
		echo '<html xmlns="http://www.w3.org/1999/xhtml">';
		echo '<head>
					<meta http-equiv="content-type" content="text/html; charset=utf-8">
					<title>Redirect</title>
				</head>';
		echo '<body>';
		echo '<h1>Redirect</h1>';
		echo '<p>';
		echo 'You were redirected to: ';
		echo '<a id="redirlink" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a>';
		echo '<script type="text/javascript">document.getElementById("redirlink").focus();</script>';
		echo '</p>';
		echo '</body>';
		echo '</html>';

		/* End script execution. */
		exit;
	}


	/**
	 * This function transposes a two-dimensional array, so that
	 * $a['k1']['k2'] becomes $a['k2']['k1'].
	 *
	 * @param $in   Input two-dimensional array.
	 * @return      The transposed array.
	 */
	public static function transposeArray($in) {
		assert('is_array($in)');

		$ret = array();

		foreach($in as $k1 => $a2) {
			assert('is_array($a2)');

			foreach($a2 as $k2 => $v) {
				if(!array_key_exists($k2, $ret)) {
					$ret[$k2] = array();
				}

				$ret[$k2][$k1] = $v;
			}
		}

		return $ret;
	}


	/**
	 * This function checks if the DOMElement has the correct localName and namespaceURI.
	 *
	 * We also define the following shortcuts for namespaces:
	 * - '@ds':      'http://www.w3.org/2000/09/xmldsig#'
	 * - '@md':      'urn:oasis:names:tc:SAML:2.0:metadata'
	 * - '@saml1':   'urn:oasis:names:tc:SAML:1.0:assertion'
	 * - '@saml1md': 'urn:oasis:names:tc:SAML:profiles:v1metadata'
	 * - '@saml1p':  'urn:oasis:names:tc:SAML:1.0:protocol'
	 * - '@saml2':   'urn:oasis:names:tc:SAML:2.0:assertion'
	 * - '@saml2p':  'urn:oasis:names:tc:SAML:2.0:protocol'
	 *
	 * @param $element The element we should check.
	 * @param $name The localname the element should have.
	 * @param $nsURI The namespaceURI the element should have.
	 * @return TRUE if both namespace and localname matches, FALSE otherwise.
	 */
	public static function isDOMElementOfType(DOMNode $element, $name, $nsURI) {
		assert('is_string($name)');
		assert('is_string($nsURI)');
		assert('strlen($nsURI) > 0');

		if (!($element instanceof DOMElement)) {
			/* Most likely a comment-node. */
			return FALSE;
		}

		/* Check if the namespace is a shortcut, and expand it if it is. */
		if($nsURI[0] == '@') {

			/* The defined shortcuts. */
			$shortcuts = array(
				'@ds' => 'http://www.w3.org/2000/09/xmldsig#',
				'@md' => 'urn:oasis:names:tc:SAML:2.0:metadata',
				'@saml1' => 'urn:oasis:names:tc:SAML:1.0:assertion',
				'@saml1md' => 'urn:oasis:names:tc:SAML:profiles:v1metadata',
				'@saml1p' => 'urn:oasis:names:tc:SAML:1.0:protocol',
				'@saml2' => 'urn:oasis:names:tc:SAML:2.0:assertion',
				'@saml2p' => 'urn:oasis:names:tc:SAML:2.0:protocol',
				'@shibmd' => 'urn:mace:shibboleth:metadata:1.0',
				);

			/* Check if it is a valid shortcut. */
			if(!array_key_exists($nsURI, $shortcuts)) {
				throw new Exception('Unknown namespace shortcut: ' . $nsURI);
			}

			/* Expand the shortcut. */
			$nsURI = $shortcuts[$nsURI];
		}


		if($element->localName !== $name) {
			return FALSE;
		}

		if($element->namespaceURI !== $nsURI) {
			return FALSE;
		}

		return TRUE;
	}


	/**
	 * This function finds direct descendants of a DOM element with the specified
	 * localName and namespace. They are returned in an array.
	 *
	 * This function accepts the same shortcuts for namespaces as the isDOMElementOfType function.
	 *
	 * @param DOMElement $element  The element we should look in.
	 * @param string $localName  The name the element should have.
	 * @param string $namespaceURI  The namespace the element should have.
	 * @return array  Array with the matching elements in the order they are found. An empty array is
	 *         returned if no elements match.
	 */
	public static function getDOMChildren(DOMElement $element, $localName, $namespaceURI) {
		assert('is_string($localName)');
		assert('is_string($namespaceURI)');

		$ret = array();

		for($i = 0; $i < $element->childNodes->length; $i++) {
			$child = $element->childNodes->item($i);

			/* Skip text nodes and comment elements. */
			if($child instanceof DOMText || $child instanceof DOMComment) {
				continue;
			}

			if(self::isDOMElementOfType($child, $localName, $namespaceURI) === TRUE) {
				$ret[] = $child;
			}
		}

		return $ret;
	}


	/**
	 * This function extracts the text from DOMElements which should contain
	 * only text content.
	 *
	 * @param $element The element we should extract text from.
	 * @return The text content of the element.
	 */
	public static function getDOMText($element) {
		assert('$element instanceof DOMElement');

		$txt = '';

		for($i = 0; $i < $element->childNodes->length; $i++) {
			$child = $element->childNodes->item($i);
			if(!($child instanceof DOMText)) {
				throw new Exception($element->localName . ' contained a non-text child node.');
			}

			$txt .= $child->wholeText;
		}

		$txt = trim($txt);
		return $txt;
	}


	/**
	 * This function parses the Accept-Language http header and returns an associative array with each
	 * language and the score for that language.
	 *
	 * If an language includes a region, then the result will include both the language with the region
	 * and the language without the region.
	 *
	 * The returned array will be in the same order as the input.
	 *
	 * @return An associative array with each language and the score for that language.
	 */
	public static function getAcceptLanguage() {

		if(!array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
			/* No Accept-Language header - return empty set. */
			return array();
		}

		$languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

		$ret = array();

		foreach($languages as $l) {
			$opts = explode(';', $l);

			$l = trim(array_shift($opts)); /* The language is the first element.*/

			$q = 1.0;

			/* Iterate over all options, and check for the quality option. */
			foreach($opts as $o) {
				$o = explode('=', $o);
				if(count($o) < 2) {
					/* Skip option with no value. */
					continue;
				}

				$name = trim($o[0]);
				$value = trim($o[1]);

				if($name === 'q') {
					$q = (float)$value;
				}
			}

			/* Remove the old key to ensure that the element is added to the end. */
			unset($ret[$l]);

			/* Set the quality in the result. */
			$ret[$l] = $q;

			if(strpos($l, '-')) {
				/* The language includes a region part. */

				/* Extract the language without the region. */
				$l = explode('-', $l);
				$l = $l[0];

				/* Add this language to the result (unless it is defined already). */
				if(!array_key_exists($l, $ret)) {
					$ret[$l] = $q;
				}
			}
		}

		return $ret;
	}


	/**
	 * This function attempts to validate an XML string against the specified schema.
	 *
	 * It will parse the string into a DOM document and validate this document against the schema.
	 *
	 * @param $xml     The XML string or document which should be validated.
	 * @param $schema  The schema which should be used.
	 * @return Returns a string with the errors if validation fails. An empty string is
	 *         returned if validation passes.
	 */
	public static function validateXML($xml, $schema) {
		assert('is_string($xml) || $xml instanceof DOMDocument');
		assert('is_string($schema)');

		SimpleSAML_XML_Errors::begin();

		if($xml instanceof DOMDocument) {
			$dom = $xml;
			$res = TRUE;
		} else {
			$dom = new DOMDocument;
			$res = $dom->loadXML($xml);
		}

		if($res) {

			$config = SimpleSAML_Configuration::getInstance();
			$schemaPath = $config->resolvePath('schemas') . '/';
			$schemaFile = $schemaPath . $schema;

			$res = $dom->schemaValidate($schemaFile);
			if($res) {
				SimpleSAML_XML_Errors::end();
				return '';
			}

			$errorText = "Schema validation failed on XML string:\n";
		} else {
			$errorText = "Failed to parse XML string for schema validation:\n";
		}

		$errors = SimpleSAML_XML_Errors::end();
		$errorText .= SimpleSAML_XML_Errors::formatErrors($errors);

		return $errorText;
	}


	/**
	 * This function performs some sanity checks on XML documents, and optionally validates them
	 * against their schema. A warning will be printed to the log if validation fails.
	 *
	 * @param $message  The message which should be validated, as a string.
	 * @param $type     The type of document - can be either 'saml20', 'saml11' or 'saml-meta'.
	 */
	public static function validateXMLDocument($message, $type) {
		assert('is_string($message)');
		assert($type === 'saml11' || $type === 'saml20' || $type === 'saml-meta');

		/* A SAML message should not contain a doctype-declaration. */
		if(strpos($message, '<!DOCTYPE') !== FALSE) {
			throw new Exception('XML contained a doctype declaration.');
		}

		$enabled = SimpleSAML_Configuration::getInstance()->getBoolean('debug.validatexml', NULL);
		if($enabled === NULL) {
			/* Fall back to old configuration option. */
			$enabled = SimpleSAML_Configuration::getInstance()->getBoolean('debug.validatesamlmessages', NULL);
			if($enabled === NULL) {
				/* Fall back to even older configuration option. */
				$enabled = SimpleSAML_Configuration::getInstance()->getBoolean('debug.validatesaml2messages', FALSE);
			}
		}

		if(!$enabled) {
			return;
		}

		switch($type) {
		case 'saml11':
			$result = self::validateXML($message, 'oasis-sstc-saml-schema-protocol-1.1.xsd');
			break;
		case 'saml20':
			$result = self::validateXML($message, 'saml-schema-protocol-2.0.xsd');
			break;
		case 'saml-meta':
			$result = self::validateXML($message, 'saml-schema-metadata-2.0.xsd');
			break;
		default:
			throw new Exception('Invalid message type.');
		}

		if($result !== '') {
			SimpleSAML_Logger::warning($result);
		}
	}


	/**
	 * This function is used to generate a non-revesible unique identifier for a user.
	 * The identifier should be persistent (unchanging) for a given SP-IdP federation.
	 * The identifier can be shared between several different SPs connected to the same IdP, or it
	 * can be unique for each SP.
	 *
	 * @param $idpEntityId  The entity id of the IdP.
	 * @param $spEntityId   The entity id of the SP.
	 * @param $attributes   The attributes of the user.
	 * @param $idpset       Allows to select another metadata set. (to support both saml2 or shib13)
	 * @param $sppset       Allows to select another metadata set. (to support both saml2 or shib13)
	 * @return A non-reversible unique identifier for the user.
	 */
	public static function generateUserIdentifier($idpEntityId, $spEntityId, array &$state, $idpset = 'saml20-idp-hosted', $spset = 'saml20-sp-remote') {
	
		$metadataHandler = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$idpMetadata = $metadataHandler->getMetaData($idpEntityId, $idpset);
		$spMetadata = $metadataHandler->getMetaData($spEntityId, $spset);

		if (isset($state['UserID'])) {
			$attributeValue = $state['UserID'];
		} else {
			if(array_key_exists('userid.attribute', $spMetadata)) {
				$attributeName = $spMetadata['userid.attribute'];
			} elseif(array_key_exists('userid.attribute', $idpMetadata)) {
				$attributeName = $idpMetadata['userid.attribute'];
			} else {
				$attributeName = 'eduPersonPrincipalName';
			}

			if(!array_key_exists($attributeName, $attributes)) {
				throw new Exception('Missing attribute "' . $attributeName . '" for user. Cannot' .
					' generate user id.');
			}

			$attributeValue = $attributes[$attributeName];
			if(count($attributeValue) !== 1) {
				throw new Exception('Attribute "' . $attributeName . '" for user did not contain exactly' .
					' one value. Cannot generate user id.');
			}

			$attributeValue = $attributeValue[0];
			if(empty($attributeValue)) {
				throw new Exception('Attribute "' . $attributeName . '" for user was empty. Cannot' .
					' generate user id.');
			}
		}

		$secretSalt = self::getSecretSalt();

		$uidData = 'uidhashbase' . $secretSalt;
		$uidData .= strlen($idpEntityId) . ':' . $idpEntityId;
		$uidData .= strlen($spEntityId) . ':' . $spEntityId;
		$uidData .= strlen($attributeValue) . ':' . $attributeValue;
		$uidData .= $secretSalt;

		$userid = hash('sha1', $uidData);

		return $userid;
	}

	public static function generateRandomBytesMTrand($length) {
	
		/* Use mt_rand to generate $length random bytes. */
		$data = '';
		for($i = 0; $i < $length; $i++) {
			$data .= chr(mt_rand(0, 255));
		}

		return $data;
	}


	/**
	 * This function generates a binary string containing random bytes.
	 *
	 * It will use /dev/urandom if available, and fall back to the builtin mt_rand()-function if not.
	 *
	 * @param $length  The number of random bytes to return.
	 * @return A string of lenght $length with random bytes.
	 */
	public static function generateRandomBytes($length, $fallback = TRUE) {
		static $fp = NULL;
		assert('is_int($length)');

		if($fp === NULL) {
			if (file_exists('/dev/urandom')) {
				$fp = fopen('/dev/urandom', 'rb');
			} else {
				$fp = FALSE;
			}
		}

		if($fp !== FALSE) {
			/* Read random bytes from /dev/urandom. */
			$data = fread($fp, $length);
			if($data === FALSE) {
				throw new Exception('Error reading random data.');
			}
			if(strlen($data) != $length) {
				SimpleSAML_Logger::warning('Did not get requested number of bytes from random source. Requested (' . $length . ') got (' . strlen($data) . ')');
				if ($fallback) {
					$data = self::generateRandomBytesMTrand($length);
				} else {
					throw new Exception('Did not get requested number of bytes from random source. Requested (' . $length . ') got (' . strlen($data) . ')');
				}
			}
		} else {
			/* Use mt_rand to generate $length random bytes. */
			$data = self::generateRandomBytesMTrand($length);
		}

		return $data;
	}


	/**
	 * This function converts a binary string to hexadecimal characters.
	 *
	 * @param $bytes  Input string.
	 * @return String with lowercase hexadecimal characters.
	 */
	public static function stringToHex($bytes) {
		$ret = '';
		for($i = 0; $i < strlen($bytes); $i++) {
			$ret .= sprintf('%02x', ord($bytes[$i]));
		}
		return $ret;
	}


	/**
	 * Resolve a (possibly) relative path from the given base path.
	 *
	 * A path which starts with a '/' is assumed to be absolute, all others are assumed to be
	 * relative. The default base path is the root of the simpleSAMPphp installation.
	 *
	 * @param $path  The path we should resolve.
	 * @param $base  The base path, where we should search for $path from. Default value is the root
	 *               of the simpleSAMLphp installation.
	 * @return An absolute path referring to $path.
	 */
	public static function resolvePath($path, $base = NULL) {
		if($base === NULL) {
			$config = SimpleSAML_Configuration::getInstance();
			$base =  $config->getBaseDir();
		}

		/* Remove trailing slashes from $base. */
		while(substr($base, -1) === '/') {
			$base = substr($base, 0, -1);
		}

		/* Check for absolute path. */
		if(substr($path, 0, 1) === '/') {
			/* Absolute path. */
			$ret = '/';
		} else {
			/* Path relative to base. */
			$ret = $base;
		}

		$path = explode('/', $path);
		foreach($path as $d) {
			if($d === '.') {
				continue;
			} elseif($d === '..') {
				$ret = dirname($ret);
			} else {
				if(substr($ret, -1) !== '/') {
					$ret .= '/';
				}
				$ret .= $d;
			}
		}

		return $ret;
	}


	/**
	 * Resolve a (possibly) relative URL relative to a given base URL.
	 *
	 * This function supports these forms of relative URLs:
	 *  ^\w+: Absolute URL
	 *  ^// Same protocol.
	 *  ^/ Same protocol and host.
	 *  ^? Same protocol, host and path, replace query string & fragment
	 *  ^# Same protocol, host, path and query, replace fragment
	 *  The rest: Relative to the base path.
	 *
	 * @param $url  The relative URL.
	 * @param $base  The base URL. Defaults to the base URL of this installation of simpleSAMLphp.
	 * @return An absolute URL for the given relative URL.
	 */
	public static function resolveURL($url, $base = NULL) {
		if($base === NULL) {
			$config = SimpleSAML_Configuration::getInstance();
			$base = self::selfURLhost() . '/' . $config->getBaseURL();
		}


		if(!preg_match('$^((((\w+:)//[^/]+)(/[^?#]*))(?:\?[^#]*)?)(?:#.*)?$', $base, $baseParsed)) {
			throw new Exception('Unable to parse base url: ' . $base);
		}

		$baseDir = dirname($baseParsed[5] . 'filename');
		$baseScheme = $baseParsed[4];
		$baseHost = $baseParsed[3];
		$basePath = $baseParsed[2];
		$baseQuery = $baseParsed[1];

		if(preg_match('$^\w+:$', $url)) {
			return $url;
		}

		if(substr($url, 0, 2) === '//') {
			return $baseScheme . $url;
		}

		$firstChar = substr($url, 0, 1);

		if($firstChar === '/') {
			return $baseHost . $url;
		}

		if($firstChar === '?') {
			return $basePath . $url;
		}

		if($firstChar === '#') {
			return $baseQuery . $url;
		}


		/* We have a relative path. Remove query string/fragment and save it as $tail. */
		$queryPos = strpos($url, '?');
		$fragmentPos = strpos($url, '#');
		if($queryPos !== FALSE || $fragmentPos !== FALSE) {
			if($queryPos === FALSE) {
				$tailPos = $fragmentPos;
			} elseif($fragmentPos === FALSE) {
				$tailPos = $queryPos;
			} elseif($queryPos < $fragmentPos) {
				$tailPos = $queryPos;
			} else {
				$tailPos = $fragmentPos;
			}

			$tail = substr($url, $tailPos);
			$dir = substr($url, 0, $tailPos);
		} else {
			$dir = $url;
			$tail = '';
		}

		$dir = self::resolvePath($dir, $baseDir);

		return $baseHost . $dir . $tail;
	}


	/**
	 * Parse a query string into an array.
	 *
	 * This function parses a query string into an array, similar to the way the builtin
	 * 'parse_str' works, except it doesn't handle arrays, and it doesn't do "magic quotes".
	 *
	 * Query parameters without values will be set to an empty string.
	 *
	 * @param $query_string  The query string which should be parsed.
	 * @return The query string as an associative array.
	 */
	public static function parseQueryString($query_string) {
		assert('is_string($query_string)');

		$res = array();
		foreach(explode('&', $query_string) as $param) {
			$param = explode('=', $param);
			$name = urldecode($param[0]);
			if(count($param) === 1) {
				$value = '';
			} else {
				$value = urldecode($param[1]);
			}

			$res[$name] = $value;
		}

		return $res;
	}


	/**
	 * Parse and validate an array with attributes.
	 *
	 * This function takes in an associative array with attributes, and parses and validates
	 * this array. On success, it will return a normalized array, where each attribute name
	 * is an index to an array of one or more strings. On failure an exception will be thrown.
	 * This exception will contain an message describing what is wrong.
	 *
	 * @param array $attributes  The attributes we should parse and validate.
	 * @return array  The parsed attributes.
	 */
	public static function parseAttributes($attributes) {

		if (!is_array($attributes)) {
			throw new Exception('Attributes was not an array. Was: ' . var_export($attributes, TRUE));
		}

		$newAttrs = array();
		foreach ($attributes as $name => $values) {
			if (!is_string($name)) {
				throw new Exception('Invalid attribute name: ' . var_export($name, TRUE));
			}

			if (!is_array($values)) {
				$values = array($values);
			}

			foreach ($values as $value) {
				if (!is_string($value)) {
					throw new Exception('Invalid attribute value for attribute ' . $name .
						': ' . var_export($value, TRUE));
				}
			}

			$newAttrs[$name] = $values;
		}

		return $newAttrs;
	}


	/**
	 * Retrieve secret salt.
	 *
	 * This function retrieves the value which is configured as the secret salt. It will
	 * check that the value exists and is set to a non-default value. If it isn't, an
	 * exception will be thrown.
	 *
	 * The secret salt can be used as a component in hash functions, to make it difficult to
	 * test all possible values in order to retrieve the original value. It can also be used
	 * as a simple method for signing data, by hashing the data together with the salt.
	 *
	 * @return string  The secret salt.
	 */
	public static function getSecretSalt() {

		$secretSalt = SimpleSAML_Configuration::getInstance()->getString('secretsalt');
		if ($secretSalt === 'defaultsecretsalt') {
			throw new Exception('The "secretsalt" configuration option must be set to a secret' .
			                    ' value.');
		}

		return $secretSalt;
	}


	/**
	 * Retrieve last error message.
	 *
	 * This function retrieves the last error message. If no error has occured,
	 * '[No error message found]' will be returned. If the required function isn't available,
	 * '[Cannot get error message]' will be returned.
	 *
	 * @return string  Last error message.
	 */
	public static function getLastError() {

		if (!function_exists('error_get_last')) {
			return '[Cannot get error message]';
		}

		$error = error_get_last();
		if ($error === NULL) {
			return '[No error message found]';
		}

		return $error['message'];
	}


	/**
	 * Resolves a path that may be relative to the cert-directory.
	 *
	 * @param string $path  The (possibly relative) path to the file.
	 * @return string  The file path.
	 */
	public static function resolveCert($path) {
		assert('is_string($path)');

		$globalConfig = SimpleSAML_Configuration::getInstance();
		$base = $globalConfig->getPathValue('certdir', 'cert/');
		return SimpleSAML_Utilities::resolvePath($path, $base);
	}


	/**
	 * Get public key or certificate from metadata.
	 *
	 * This function implements a function to retrieve the public key or certificate from
	 * a metadata array.
	 *
	 * It will search for the following elements in the metadata:
	 * 'certData'  The certificate as a base64-encoded string.
	 * 'certificate'  A file with a certificate or public key in PEM-format.
	 * 'certFingerprint'  The fingerprint of the certificate. Can be a single fingerprint,
	 *                    or an array of multiple valid fingerprints.
	 *
	 * This function will return an array with these elements:
	 * 'PEM'  The public key/certificate in PEM-encoding.
	 * 'certData'  The certificate data, base64 encoded, on a single line. (Only
	 *             present if this is a certificate.)
	 * 'certFingerprint'  Array of valid certificate fingerprints. (Only present
	 *                    if this is a certificate.)
	 *
	 * @param SimpleSAML_Configuration $metadata  The metadata.
	 * @param bool $required  Whether the private key is required. If this is TRUE, a
	 *                        missing key will cause an exception. Default is FALSE.
	 * @param string $prefix  The prefix which should be used when reading from the metadata
	 *                        array. Defaults to ''.
	 * @return array|NULL  Public key or certificate data, or NULL if no public key or
	 *                     certificate was found.
	 */
	public static function loadPublicKey(SimpleSAML_Configuration $metadata, $required = FALSE, $prefix = '') {
		assert('is_bool($required)');
		assert('is_string($prefix)');

		$ret = array();

		if ($metadata->hasValue($prefix . 'certData')) {
			/* Full certificate data available from metadata. */
			$certData = $metadata->getString($prefix . 'certData');
			$certData = str_replace(array("\r", "\n", "\t", ' '), '', $certData);
			$ret['certData'] = $certData;

			/* Recreate PEM-encoded certificate. */
			$ret['PEM'] = "-----BEGIN CERTIFICATE-----\n" .
				chunk_split($ret['certData'], 64) .
				"-----END CERTIFICATE-----\n";

		} elseif ($metadata->hasValue($prefix . 'certificate')) {
			/* Reference to certificate file. */
			$file = SimpleSAML_Utilities::resolveCert($metadata->getString($prefix . 'certificate'));
			$data = @file_get_contents($file);
			if ($data === FALSE) {
				throw new Exception('Unable to load certificate/public key from file "' . $file . '"');
			}
			$ret['PEM'] = $data;

			/* Extract certificate data (if this is a certificate). */
			$pattern = '/^-----BEGIN CERTIFICATE-----([^-]*)^-----END CERTIFICATE-----/m';
			if (preg_match($pattern, $data, $matches)) {
				/* We have a certificate. */
				$ret['certData'] = str_replace(array("\r", "\n"), '', $matches[1]);
			}

		} elseif ($metadata->hasValue($prefix . 'certFingerprint')) {
			/* We only have a fingerprint available. */
			$fps = $metadata->getArrayizeString($prefix . 'certFingerprint');

			/* Normalize fingerprint(s) - lowercase and no colons. */
			foreach($fps as &$fp) {
				assert('is_string($fp)');
				$fp = strtolower(str_replace(':', '', $fp));
			}

			/* We can't build a full certificate from a fingerprint, and may as well
			 * return an array with only the fingerprint(s) immediately.
			 */
			return array('certFingerprint' => $fps);

		} else {
			/* No public key/certificate available. */
			if ($required) {
				throw new Exception('No public key / certificate found in metadata.');
			} else {
				return NULL;
			}
		}

		if (array_key_exists('certData', $ret)) {
			/* This is a certificate - calculate the fingerprint. */
			$ret['certFingerprint'] = array(
				strtolower(sha1(base64_decode($ret['certData'])))
			);
		}

		return $ret;
	}


	/**
	 * Load private key from metadata.
	 *
	 * This function loads a private key from a metadata array. It searches for the
	 * following elements:
	 * 'privatekey'  Name of a private key file in the cert-directory.
	 * 'privatekey_pass'  Password for the private key.
	 *
	 * It returns and array with the following elements:
	 * 'PEM'  Data for the private key, in PEM-format
	 * 'password'  Password for the private key.
	 *
	 * @param SimpleSAML_Configuration $metadata  The metadata array the private key should be loaded from.
	 * @param bool $required  Whether the private key is required. If this is TRUE, a
	 *                        missing key will cause an exception. Default is FALSE.
	 * @param string $prefix  The prefix which should be used when reading from the metadata
	 *                        array. Defaults to ''.
	 * @return array|NULL  Extracted private key, or NULL if no private key is present.
	 */
	public static function loadPrivateKey(SimpleSAML_Configuration $metadata, $required = FALSE, $prefix = '') {
		assert('is_bool($required)');
		assert('is_string($prefix)');

		$file = $metadata->getString($prefix . 'privatekey', NULL);
		if ($file === NULL) {
			/* No private key found. */
			if ($required) {
				throw new Exception('No private key found in metadata.');
			} else {
				return NULL;
			}
		}

		$file = SimpleSAML_Utilities::resolveCert($file);
		$data = @file_get_contents($file);
		if ($data === FALSE) {
			throw new Exception('Unable to load private key from file "' . $file . '"');
		}

		$ret = array(
			'PEM' => $data,
		);

		if ($metadata->hasValue($prefix . 'privatekey_pass')) {
			$ret['password'] = $metadata->getString($prefix . 'privatekey_pass');
		}

		return $ret;
	}


	/**
	 * Format a DOM element.
	 *
	 * This function takes in a DOM element, and inserts whitespace to make it more
	 * readable. Note that whitespace added previously will be removed.
	 *
	 * @param DOMElement $root  The root element which should be formatted.
	 * @param string $indentBase  The indentation this element should be assumed to
	 *                         have. Default is an empty string.
	 */
	public static function formatDOMElement(DOMElement $root, $indentBase = '') {
		assert(is_string($indentBase));

		/* Check what this element contains. */
		$fullText = ''; /* All text in this element. */
		$textNodes = array(); /* Text nodes which should be deleted. */
		$childNodes = array(); /* Other child nodes. */
		for ($i = 0; $i < $root->childNodes->length; $i++) {
			$child = $root->childNodes->item($i);

			if($child instanceof DOMText) {
				$textNodes[] = $child;
				$fullText .= $child->wholeText;

			} elseif ($child instanceof DOMComment || $child instanceof DOMElement) {
				$childNodes[] = $child;

			} else {
				/* Unknown node type. We don't know how to format this. */
				return;
			}
		}

		$fullText = trim($fullText);
		if (strlen($fullText) > 0) {
			/* We contain text. */
			$hasText = TRUE;
		} else {
			$hasText = FALSE;
		}

		$hasChildNode = (count($childNodes) > 0);

		if ($hasText && $hasChildNode) {
			/* Element contains both text and child nodes - we don't know how to format this one. */
			return;
		}

		/* Remove text nodes. */
		foreach ($textNodes as $node) {
			$root->removeChild($node);
		}

		if ($hasText) {
			/* Only text - add a single text node to the element with the full text. */
			$root->appendChild(new DOMText($fullText));
			return;

		}

		if (!$hasChildNode) {
			/* Empty node. Nothing to do. */
			return;
		}

		/* Element contains only child nodes - add indentation before each one, and
		 * format child elements.
		 */
		$childIndentation = $indentBase . '  ';
		foreach ($childNodes as $node) {
			/* Add indentation before node. */
			$root->insertBefore(new DOMText("\n" . $childIndentation), $node);

			/* Format child elements. */
			if ($node instanceof DOMElement) {
				self::formatDOMElement($node, $childIndentation);
			}
		}

		/* Add indentation before closing tag. */
		$root->appendChild(new DOMText("\n" . $indentBase));
	}


	/**
	 * Format an XML string.
	 *
	 * This function formats an XML string using the formatDOMElement function.
	 *
	 * @param string $xml  XML string which should be formatted.
	 * @param string $indentBase  Optional indentation which should be applied to all
	 *                            the output. Optional, defaults to ''.
	 * @return string  Formatted string.
	 */
	public static function formatXMLString($xml, $indentBase = '') {
		assert('is_string($xml)');
		assert('is_string($indentBase)');

		$doc = new DOMDocument();
		if (!$doc->loadXML($xml)) {
			throw new Exception('Error parsing XML string.');
		}

		$root = $doc->firstChild;
		self::formatDOMElement($root);

		return $doc->saveXML($root);
	}

	/*
	 * Input is single value or array, returns an array.
	 */
	public static function arrayize($data, $index = 0) {
		if (is_array($data)) {
			return $data;
		} else {
			return array($index => $data);
		}
	}


	/**
	 * Check whether the current user is a admin user.
	 *
	 * @return bool  TRUE if the current user is a admin user, FALSE if not.
	 */
	public static function isAdmin() {

		$session = SimpleSAML_Session::getInstance();

		return $session->isValid('admin') || $session->isValid('login-admin');
	}


	/**
	 * Retrieve a admin login URL.
	 *
	 * @param string|NULL $returnTo  The URL the user should arrive on after admin authentication.
	 * @return string  An URL which can be used for admin authentication.
	 */
	public static function getAdminLoginURL($returnTo = NULL) {
		assert('is_string($returnTo) || is_null($returnTo)');

		if ($returnTo === NULL) {
			$returnTo = SimpleSAML_Utilities::selfURL();
		}

		return SimpleSAML_Module::getModuleURL('core/login-admin.php', array('ReturnTo' => $returnTo));
	}


	/**
	 * Require admin access for current page.
	 *
	 * This is a helper-function for limiting a page to admin access. It will redirect
	 * the user to a login page if the current user doesn't have admin access.
	 */
	public static function requireAdmin() {

		if (self::isAdmin()) {
			return;
		}

		$returnTo = SimpleSAML_Utilities::selfURL();

		/* Not authenticated as admin user. Start authentication. */

		if (SimpleSAML_Auth_Source::getById('admin') !== NULL) {
			SimpleSAML_Auth_Default::initLogin('admin', $returnTo);
		} else {
			/* For backwards-compatibility. */

			$config = SimpleSAML_Configuration::getInstance();
			SimpleSAML_Utilities::redirect('/' . $config->getBaseURL() . 'auth/login-admin.php',
				array('RelayState' => $returnTo)
						       );
		}
	}


	/**
	 * Do a POST redirect to a page.
	 *
	 * This function never returns.
	 *
	 * @param string $destination  The destination URL.
	 * @param array $post  An array of name-value pairs which will be posted.
	 */
	public static function postRedirect($destination, $post) {
		assert('is_string($destination)');
		assert('is_array($post)');

		$config = SimpleSAML_Configuration::getInstance();

		$p = new SimpleSAML_XHTML_Template($config, 'post.php');
		$p->data['destination'] = $destination;
		$p->data['post'] = $post;
		$p->show();
		exit(0);
	}

	/**
	 * Create a link which will POST data.
	 *
	 * @param string $destination  The destination URL.
	 * @param array $post  The name-value pairs which will be posted to the destination.
	 * @return string  An URL which can be accessed to post the data.
	 */
	public static function createPostRedirectLink($destination, $post) {
		assert('is_string($destination)');
		assert('is_array($post)');

		$id = SimpleSAML_Utilities::generateID();
		$postData = array(
			'post' => $post,
			'url' => $destination,
		);

		$session = SimpleSAML_Session::getInstance();
		$session->setData('core_postdatalink', $id, $postData);

		return SimpleSAML_Module::getModuleURL('core/postredirect.php', array('RedirId' => $id));
	}


	/**
	 * Validate a certificate against a CA file, by using the builtin
	 * openssl_x509_checkpurpose function
	 *
	 * @param string $certificate  The certificate, in PEM format.
	 * @param string $caFile  File with trusted certificates, in PEM-format.
	 * @return boolean|string TRUE on success, or a string with error messages if it failed.
	 */
	private static function validateCABuiltIn($certificate, $caFile) {
		assert('is_string($certificate)');
		assert('is_string($caFile)');

		/* Clear openssl errors. */
		while(openssl_error_string() !== FALSE);

		$res = openssl_x509_checkpurpose($certificate, X509_PURPOSE_ANY, array($caFile));

		$errors = '';
		/* Log errors. */
		while( ($error = openssl_error_string()) !== FALSE) {
			$errors .= ' [' . $error . ']';
		}

		if($res !== TRUE) {
			return $errors;
		}

		return TRUE;
	}


	/**
	 * Validate the certificate used to sign the XML against a CA file, by using the "openssl verify" command.
	 *
	 * This function uses the openssl verify command to verify a certificate, to work around limitations
	 * on the openssl_x509_checkpurpose function. That function will not work on certificates without a purpose
	 * set.
	 *
	 * @param string $certificate  The certificate, in PEM format.
	 * @param string $caFile  File with trusted certificates, in PEM-format.
	 * @return boolean|string TRUE on success, a string with error messages on failure.
	 */
	private static function validateCAExec($certificate, $caFile) {
		assert('is_string($certificate)');
		assert('is_string($caFile)');

		$command = array(
			'openssl', 'verify',
			'-CAfile', $caFile,
			'-purpose', 'any',
			);

		$cmdline = '';
		foreach($command as $c) {
			$cmdline .= escapeshellarg($c) . ' ';
		}

		$cmdline .= '2>&1';
		$descSpec = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			);
		$process = proc_open($cmdline, $descSpec, $pipes);
		if (!is_resource($process)) {
			throw new Exception('Failed to execute verification command: ' . $cmdline);
		}

		if (fwrite($pipes[0], $certificate) === FALSE) {
			throw new Exception('Failed to write certificate for verification.');
		}
		fclose($pipes[0]);

		$out = '';
		while (!feof($pipes[1])) {
			$line = trim(fgets($pipes[1]));
			if(strlen($line) > 0) {
				$out .= ' [' . $line . ']';
			}
		}
		fclose($pipes[1]);

		$status = proc_close($process);
		if ($status !== 0 || $out !== ' [stdin: OK]') {
			return $out;
		}

		return TRUE;
	}


	/**
	 * Validate the certificate used to sign the XML against a CA file.
	 *
	 * This function throws an exception if unable to validate against the given CA file.
	 *
	 * @param string $certificate  The certificate, in PEM format.
	 * @param string $caFile  File with trusted certificates, in PEM-format.
	 */
	public static function validateCA($certificate, $caFile) {
		assert('is_string($certificate)');
		assert('is_string($caFile)');

		if (!file_exists($caFile)) {
			throw new Exception('Could not load CA file: ' . $caFile);
		}

		SimpleSAML_Logger::debug('Validating certificate against CA file: ' . var_export($caFile, TRUE));

		$resBuiltin = self::validateCABuiltIn($certificate, $caFile);
		if ($resBuiltin !== TRUE) {
			SimpleSAML_Logger::debug('Failed to validate with internal function: ' . var_export($resBuiltin, TRUE));

			$resExternal = self::validateCAExec($certificate, $caFile);
			if ($resExternal !== TRUE) {
				SimpleSAML_Logger::debug('Failed to validate with external function: ' . var_export($resExternal, TRUE));
				throw new Exception('Could not verify certificate against CA file "'
					. $caFile . '". Internal result:' . $resBuiltin .
					' External result:' . $resExternal);
			}
		}

		SimpleSAML_Logger::debug('Successfully validated certificate.');
	}


	/**
	 * Initialize the timezone.
	 *
	 * This function should be called before any calls to date().
	 */
	public static function initTimezone() {
		static $initialized = FALSE;

		if ($initialized) {
			return;
		}

		$initialized = TRUE;

		$globalConfig = SimpleSAML_Configuration::getInstance();

		$timezone = $globalConfig->getString('timezone', NULL);
		if ($timezone !== NULL) {
			if (!date_default_timezone_set($timezone)) {
				throw new SimpleSAML_Error_Exception('Invalid timezone set in the \'timezone\'-option in config.php.');
			}
			return;
		}

		/* We don't have a timezone configured. */

		/*
		 * The date_default_timezone_get()-function is likely to cause a warning.
		 * Since we have a custom error handler which logs the errors with a backtrace,
		 * this error will be logged even if we prefix the function call with '@'.
		 * Instead we temporarily replace the error handler.
		 */
		function ignoreError() {
			/* Don't do anything with this error. */
			return TRUE;
		}
		set_error_handler('ignoreError');
		$serverTimezone = date_default_timezone_get();
		restore_error_handler();

		/* Set the timezone to the default. */
		date_default_timezone_set($serverTimezone);
	}


	/**
	 * Atomically write a file.
	 *
	 * This is a helper function for safely writing file data atomically.
	 * It does this by writing the file data to a temporary file, and then
	 * renaming this to the correct name.
	 *
	 * @param string $filename  The name of the file.
	 * @param string $data  The data we should write to the file.
	 */
	public static function writeFile($filename, $data) {
		assert('is_string($filename)');
		assert('is_string($data)');

		$tmpFile = $filename . '.new.' . getmypid() . '.' . php_uname('n');

		$res = file_put_contents($tmpFile, $data);
		if ($res === FALSE) {
			throw new SimpleSAML_Error_Exception('Error saving file ' . $tmpFile .
				': ' . SimpleSAML_Utilities::getLastError());
		}

		$res = rename($tmpFile, $filename);
		if ($res === FALSE) {
			unlink($tmpFile);
			throw new SimpleSAML_Error_Exception('Error renaming ' . $tmpFile . ' to ' .
				$filename . ': ' . SimpleSAML_Utilities::getLastError());
		}
	}


	/**
	 * Get temp directory path.
	 *
	 * This function retrieves the path to a directory where
	 * temporary files can be saved.
	 *
	 * @return string  Path to temp directory, without a trailing '/'.
	 */
	public static function getTempDir() {

		$globalConfig = SimpleSAML_Configuration::getInstance();

		$tempDir = $globalConfig->getString('tempdir', '/tmp/simplesaml');

		while (substr($tempDir, -1) === '/') {
			$tempDir = substr($tempDir, 0, -1);
		}

		if (!is_dir($tempDir)) {
			$ret = mkdir($tempDir, 0700, TRUE);
			if (!$ret) {
				throw new SimpleSAML_Error_Exception('Error creating temp dir ' .
					var_export($tempDir, TRUE) . ': ' . SimpleSAML_Utilities::getLastError());
			}
		} elseif (function_exists('posix_getuid')) {

			/* Check that the owner of the temp diretory is the current user. */
			$stat = lstat($tempDir);
			if ($stat['uid'] !== posix_getuid()) {
				throw new SimpleSAML_Error_Exception('Temp directory (' . var_export($tempDir, TRUE) .
					') not owned by current user.');
			}
		}

		return $tempDir;
	}


	/**
	 * Disable reporting of the given log levels.
	 *
	 * Every call to this function must be followed by a call to popErrorMask();
	 *
	 * @param int $mask  The log levels that should be masked.
	 */
	public static function maskErrors($mask) {
		assert('is_int($mask)');

		$currentEnabled = error_reporting();
		self::$logLevelStack[] = array($currentEnabled, self::$logMask);

		$currentEnabled &= ~$mask;
		error_reporting($currentEnabled);
		self::$logMask |= $mask;
	}


	/**
	 * Pop an error mask.
	 *
	 * This function restores the previous error mask.
	 */
	public static function popErrorMask() {

		$lastMask = array_pop(self::$logLevelStack);
		error_reporting($lastMask[0]);
		self::$logMask = $lastMask[1];
	}


	/**
	 * Find the default endpoint in an endpoint array.
	 *
	 * @param array $endpoints  Array with endpoints.
	 * @param array $bindings  Array with acceptable bindings. Can be NULL if any binding is allowed.
	 * @return  array|NULL  The default endpoint, or NULL if no acceptable endpoints are used.
	 */
	public static function getDefaultEndpoint(array $endpoints, array $bindings = NULL) {

		$firstNotFalse = NULL;
		$firstAllowed = NULL;

		/* Look through the endpoint list for acceptable endpoints. */
		foreach ($endpoints as $i => $ep) {
			if ($bindings !== NULL && !in_array($ep['Binding'], $bindings, TRUE)) {
				/* Unsupported binding. Skip it. */
				continue;
			}

			if (array_key_exists('isDefault', $ep)) {
				if ($ep['isDefault'] === TRUE) {
					/* This is the first endpoitn with isDefault set to TRUE. */
					return $ep;
				}
				/* isDefault is set to FALSE, but the endpoint is still useable as a last resort. */
				if ($firstAllowed === NULL) {
					/* This is the first endpoint that we can use. */
					$firstAllowed = $ep;
				}
			} else {
				if ($firstNotFalse === NULL) {
					/* This is the first endpoint without isDefault set. */
					$firstNotFalse = $ep;
				}
			}
		}

		if ($firstNotFalse !== NULL) {
			/* We have an endpoint without isDefault set to FALSE. */
			return $firstNotFalse;
		}

		/*
		 * $firstAllowed either contains the first endpoint we can use, or it
		 * contains NULL if we cannot use any of the endpoints. Either way we
		 * return the value of it.
		 */
		return $firstAllowed;
	}


	/**
	 * Retrieve the authority for the given IdP metadata.
	 *
	 * This function provides backwards-compatibility with
	 * previous versions of simpleSAMLphp.
	 *
	 * @param array $idpmetadata  The IdP metadata.
	 * @return string  The authority that should be used to validate the session.
	 */
	public static function getAuthority(array $idpmetadata) {

		if (isset($idpmetadata['authority'])) {
			return $idpmetadata['authority'];
		}

		$candidates = array(
			'auth/login-admin.php' => 'login-admin',
			'auth/login-auto.php' => 'login-auto',
			'auth/login-cas-ldap.php' => 'login-cas-ldap',
			'auth/login-feide.php' => 'login-feide',
			'auth/login-ldapmulti.php' => 'login-ldapmulti',
			'auth/login-radius.php' => 'login-radius',
			'auth/login-tlsclient.php' => 'tlsclient',
			'auth/login-wayf-ldap.php' => 'login-wayf-ldap',
			'auth/login.php' => 'login',
		);
		if (isset($candidates[$idpmetadata['auth']])) {
			return $candidates[$idpmetadata['auth']];
		}
		throw new SimpleSAML_Error_Exception('You need to set \'authority\' in the metadata for ' .
			var_export($idpmetadata['entityid'], TRUE) . '.');
	}


	/**
	 * Check for session cookie, and show missing-cookie page if it is missing.
	 *
	 * @param string|NULL $retryURL  The URL the user should access to retry the operation.
	 */
	public static function checkCookie($retryURL = NULL) {
		assert('is_string($retryURL) || is_null($retryURL)');

		$session = SimpleSAML_Session::getInstance();
		if ($session->hasSessionCookie()) {
			return;
		}

		/* We didn't have a session cookie. Redirect to the no-cookie page. */

		$url = SimpleSAML_Module::getModuleURL('core/no_cookie.php');
		if ($retryURL !== NULL) {
			$url = SimpleSAML_Utilities::addURLParameter($url, array('retryURL' => $retryURL));
		}
		SimpleSAML_Utilities::redirect($url);
	}

}

?>
