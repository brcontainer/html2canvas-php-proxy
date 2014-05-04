<?php
/*
html2canvas-proxy-php 0.1.5
Copyright (c) 2014 Guilherme Nascimento (brcontainer@yahoo.com.br)

Released under the MIT license
*/

error_reporting(0);//Turn off erros because the script already own uses error_get_last

//constants
define('EOL', chr(10));
define('WOL', chr(13));
define('GMDATECACHE', gmdate('D, d M Y H:i:s'));

//setup
define('JSLOG', 'console.log'); //Configure alternative function log, eg. console.log, alert, custom_function
define('PATH', 'images');//relative folder where the images are saved
define('CCACHE', 60 * 5 * 1000);//Limit access-control and cache, define 0/false/null/-1 to not use "http heade cache"
define('TIMEOUT', 30);//Timeout from load Socket

/*
If execution has reached the time limit prevents page goes blank (off errors)
or generate an error in PHP, which does not work with the DEBUG (from html2canvas.js)
*/
$maxExec = (int) ini_get('max_execution_time');
define('MAX_EXEC', empty($maxExec) || $maxExec < 1 ? 0 : ($maxExec - 5));//reduces 5 seconds to ensure the execution of the DEBUG

if(isset($_SERVER['REQUEST_TIME']) && strlen($_SERVER['REQUEST_TIME']) > 0) {
	$initExec = (int) $_SERVER['REQUEST_TIME'];
} else {
	$initExec = time();
}
define('INIT_EXEC', $initExec);

define('SECPREFIX', 'h2c_');

$http_port = 0;

//set mime-type
header('Content-Type: application/javascript');

$param_callback = JSLOG;//force use alternative log error
$tmp = null;//tmp var usage

function remove_old_files() {
	$p = PATH . '/';

	if(
		(MAX_EXEC === 0 || (time() - INIT_EXEC) < MAX_EXEC) && //prevents this function locks the process that was completed
		(file_exists($p) === true || is_dir($p) === true)
	) {
		$h = opendir($p);
		if(false !== $h) {
			while(false !== ($f = readdir($h))) {
				if(
					is_file($p . $f) === true &&
					is_dir($p . $f) === false &&
					strpos($f, SECPREFIX) !== false &&
					(INIT_EXEC - filectime($p . $f)) > (CCACHE * 2)
				) {
					unlink($p . $f);
				}
			}
		}
	}
}

/**
 * this function does not exist by default in php4.3,
 * error_get_last is only to prevent error message: Function not defined
 * @return array   if has errors
*/
function get_error() {
	if(function_exists('error_get_last') === false) {
		return error_get_last();
	}
	return null;
}

/**
 * enconde string in json
 * @param string $s    to encode
 * @return string      always return string
*/
function json_encode_string($s) {
	$vetor = array();
	$vetor[0]  = '\\0';
	$vetor[8]  = '\\b';
	$vetor[9]  = '\\t';
	$vetor[10] = '\\n';
	$vetor[12] = '\\f';
	$vetor[13] = '\\r';
	$vetor[34] = '\\"';
	$vetor[47] = '\\/';
	$vetor[92] = '\\\\';

	$tmp = '';
	$enc = '';
	$e = array();
	$j = strlen($s);

	for($i = 0; $i < $j; ++$i) {
		$tmp = substr($s, $i, 1);
		$c = ord($tmp);
		if($c > 126) {
			$d = '000' . dechex($c);
			$tmp = '\\u' . substr($d, strlen($d)-4);
		} else {
			if (isset($vetor[$c]) === true) {
				$tmp = $vetor[$c];
			} else if (($c > 31) === false) {
				$d = '000' . dechex($c);
				$tmp = '\\u' . substr($d, strlen($d)-4);
			}
		}

		$enc .= $tmp;
	}

	return '"' . $enc . '"';
}

/**
 * download http request recursive (If found HTTP 3xx)
 * @param string $url               to download
 * @param resource $toSource        to download
 * @return array|string             If Ok return array, If has an error return string
*/
function downloadSource($url, $toSource) {
	$errno = 0;
	$errstr = '';

	$uri = parse_url($url);
	$secure = strcasecmp($uri['scheme'], 'https') === 0;

	$port = isset($uri['port']) && strlen($uri['port']) > 0 ? (int) $uri['port'] : ($secure ? 443 : 80);
	$host = ($secure ? 'ssl://' : '') . $uri['host'];

	$fp = fsockopen($host, $port, $errno, $errstr, TIMEOUT);
	if($fp === false) {
		return array('error' => 'SOCKET: ' . $errstr . '(' . ((string) $errno) . ')');
	} else {
		fwrite(
			$fp, 'GET ' . (
				isset($uri['path']) && strlen($uri['path']) > 0 ? $uri['path'] : '/'
			) . (
				isset($uri['query']) && strlen($uri['query']) > 0 ? ('?' . $uri['query']) : ''
			) . ' HTTP/1.0' . WOL . EOL
		);

		if(isset($_SERVER['HTTP_ACCEPT']) && strlen($_SERVER['HTTP_ACCEPT']) > 0) {
			fwrite($fp, 'Accept: ' . $_SERVER['HTTP_ACCEPT'] . WOL . EOL);
		}
		if(isset($_SERVER['HTTP_USER_AGENT']) && strlen($_SERVER['HTTP_USER_AGENT']) > 0) {
			fwrite($fp, 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] . WOL . EOL);
		}

		if(isset($_SERVER['HTTP_REFERER']) && strlen($_SERVER['HTTP_REFERER']) > 0) {
			fwrite($fp, 'Referer: ' . $_SERVER['HTTP_REFERER'] . WOL . EOL);
		}

		fwrite($fp, 'Host: ' . $uri['host'] . WOL . EOL);
		fwrite($fp, 'Connection: close' . WOL . EOL . WOL . EOL);

		$isRedirect = true;
		$isBody = false;
		$isHttp = false;
		$mime = null;
		$data = '';

		while(false === feof($fp)) {
			if(MAX_EXEC !== 0 && (time() - INIT_EXEC) >= MAX_EXEC) {
				return array('error' => 'Maximum execution time of ' . ((string) (MAX_EXEC + 5)) . ' seconds exceeded, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled)');
			}
			$data = fgets($fp);
			if($data === false) { continue; }
			if($isHttp === false) {
				if(preg_match('#^HTTP[/]1[.]#i', $data) === 0) {
					fclose($fp);//Close connection
					$data = '';
					return array('error' => 'This request did not return a HTTP response valid');
				}

				$tmp = preg_replace('#(HTTP/1[.]\\d |[^0-9])#i', '', 
					preg_replace('#^(HTTP/1[.]\\d \\d{3}) [\\w\\W]+$#i', '$1', $data)
				);

				if($tmp === '304') {
					fclose($fp);//Close connection
					$data = '';
					return array('error' => 'The image returned HTTP_304, this status code is incorrect because the html2canvas not send Etag');
				} else {
					$isRedirect = preg_match('#^(301|302|303|307|308)$#', $tmp) !== 0;
					if($isRedirect === false && $tmp !== '200') {
						fclose($fp);
						$data = '';
						return array('error' => 'The image returned HTTP_' . $tmp);
					}
					$isHttp = true;
					continue;
				}
			}
			if($isBody === false) {
				if(preg_match('#^location[:]#i', $data) !== 0) {//200 force 302
					fclose($fp);//Close connection
					
					$data = trim(preg_replace('#^location[:]#i', '', $data));
					if($data === '') {
						return array('error' => '"Location:" header is blank');
					}

					$data = relative2absolute($url, $data);
					
					if(isHttpUrl($data) === false) {
						return array('error' => '"Location:" header redirected for a non-http url (' . $data . ')');
					}
					return downloadSource($data, $toSource);
				} else if(preg_match('#^content[-]length[:]( 0|0)$#i', $data) !== 0) {
					fclose($fp);
					$data = '';
					return array('error' => 'source is blank (Content-length: 0)');
				} else if(preg_match('#^content[-]type[:]#i', $data) !== 0) {
					$mime = trim(
						preg_replace('/[;]([\\s\\S]|)+$/', '', 
							str_replace('content-type:', '',
								str_replace('/x-', '/', strtolower($data))
							)
						)
					);

					if(in_array($mime, array(
						'image/bmp','image/windows-bmp','image/ms-bmp',
						'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
						'text/html', 'application/xhtml', 'application/xhtml+xml'
					)) === false) {
						fclose($fp);
						$data = '';
						return array('error' => $mime . ' mimetype is invalid');
					}
				} else if($isBody === false && trim($data) === '') {
					$isBody = true;
					continue;
				}
			} else if($isRedirect === true) {
				fclose($fp);
				$data = '';
				return array('error' => 'The response should be a redirect "' . $url . '", but did not inform which header "Localtion:"');
			} else if($mime === null) {
				fclose($fp);
				$data = '';
				return array('error' => 'Not set the mimetype from "' . $url . '"');
			} else {
				fwrite($toSource, $data);
				continue;
			}
		}

		fclose($fp);
		$data = '';
		if($isBody === false) {
			return array('error' => 'Content body is empty');
		} else if($mime === null) {
			return array('error' => 'Not set the mimetype from "' . $url . '"');
		}
		return array(
			'mime' => $mime
		);
	}
}

/**
 * set headers in document
 * @param boolean $nocache      If false set cache (if CCACHE > 0), If true set no-cache in document
 * @return void                 return always void
*/
function setHeaders($nocache) {
	if($nocache === false && is_int(CCACHE) && CCACHE > 0) {
		//save to browser cache
		header('Last-Modified: ' . GMDATECACHE . ' GMT');
		header('Cache-Control: max-age=' . (CCACHE - 1));
		header('Pragma: max-age=' . (CCACHE - 1));
		header('Expires: ' . gmdate('D, d M Y H:i:s', INIT_EXEC + CCACHE - 1));
		header('Access-Control-Max-Age:' . CCACHE);
	} else {
		//no-cache
		header('Pragma: no-cache');
		header('Cache-Control: no-cache');
		header('Expires: '. GMDATECACHE .' GMT');
	}

	//set access-control
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Request-Method: *');
	header('Access-Control-Allow-Methods: OPTIONS, GET');
	header('Access-Control-Allow-Headers: *');
}

/**
 * Converte relative-url to absolute-url
 * @param string $u set base url
 * @param string $m set relative url
 * @return string   return always string
*/
function relative2absolute($u, $m) {
	if(strpos($m, '//') === 0) {
		return 'http:' . $m;
	}

	if(preg_match('#^[a-zA-Z0-9]+[:]#', $m) !== 0) {
		return $m;
	}

	if(preg_match('/^[?#]/', $m) !== 0) {
		return $u . $m;
	}

	$pu = parse_url($u);
	$pu['path'] = isset($pu['path']) ? preg_replace('#/[^/]*$#', '', $pu['path']) : '';

	if(stripos($m, '/') === 0) {
		$pu['path'] = '';
	}

	$abs = '???/???/???/' . $pu['path'] . '/' . $m;

	$re = array('#(/[.]?/)#', '#/(?![.][.])[^/]+/[.][.]/#');
	for($n = 1; $n > 0;  $abs = preg_replace($re, '/', $abs, -1, $n)) {}

	$abs = $pu['host'] . (
		isset($pu['port']) ? $pu['port'] : ''
	) . '/' . str_replace('???/', '', $abs);

	return $pu['scheme'] . '://' . $abs;
}

/**
 * validate url
 * @param string $u  set base url
 * @return boolean   return always boolean
*/
function isHttpUrl($u) {
	return preg_match('#^http(|s)[:][/][/][a-z0-9]#i', $u) !== 0;
}

/**
 * create folder for images download
 * @return boolean      return always boolean
*/
function createFolder() {
	if(file_exists(PATH) === false || is_dir(PATH) === false) {
		return true === mkdir(PATH, 755);
	}
	return true;
}

/**
 * create temp file which will receive the download
 * @param string  $basename		set url
 * @param boolean $isEncode		If true uses the "first" temporary name
 * @return boolean|array		If you can not create file return false, If create file return array
*/
function createTmpFile($basename, $isEncode) {
	$folder = preg_replace('#[/]$#', '', PATH) . '/';
	if($isEncode === false) {
		$basename = SECPREFIX . sha1($basename);
	}

	//$basename .= $basename;
	$tmpMime = '.' . mt_rand(0,1000) . '_';
	if ($isEncode === true) {
		$tmpMime .= isset($_SERVER['REQUEST_TIME']) && strlen($_SERVER['REQUEST_TIME']) > 0 ? $_SERVER['REQUEST_TIME'] : (string) time();
	} else {
		$tmpMime .= (string) INIT_EXEC;
	}

	if(file_exists($folder . $basename . $tmpMime) === true) {
		return createTmpFile($basename, true);
	}

	$source = fopen($folder . $basename . $tmpMime, 'w');
	if($source !== false) {
		return array(
			'location' => $folder . $basename . $tmpMime,
			'source' => $source
		);
	}
	return false;
}

if(isset($_GET['callback']) !== false && strlen($_GET['callback']) > 0) {
	$param_callback = $_GET['callback'];
}

if(isset($_SERVER['HTTP_HOST']) === false || strlen($_SERVER['HTTP_HOST']) === 0) {
	$response = array('error' => 'The client did not send the Host header');
} else if(isset($_SERVER['SERVER_PORT']) === false) {
	$response = array('error' => 'The Server-proxy did not send the PORT (configure PHP)');
} else if(MAX_EXEC < 10) {
	$response = array('error' => 'Execution time is less 15 seconds, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended time is 30 seconds or more');
} else if(isset($_GET['url']) === false || strlen($_GET['url']) === 0) {
	$response = array('error' => 'No such parameter "url"');
} else if(isHttpUrl($_GET['url']) === false) {
	$response = array('error' => 'Only http scheme and https scheme are allowed');
} else if(preg_match('#[^A-Za-z0-9_[.]\\[\\]]#', $param_callback) !== 0) {
	$response = array('error' => 'Parameter "callback" contains invalid characters');
	$param_callback = JSLOG;
} else if(createFolder() === false) {
	$err = get_error();
	$response = array('error' => 'Can not create directory'. (
		$err !== null && isset($err['message']) && strlen($err['message']) > 0 ? (': ' . $err['message']) : ''
	));
	$err = null;
} else {
	$http_port = (int) $_SERVER['SERVER_PORT'];

	$tmp = createTmpFile($_GET['url'], false);
	if($tmp === false) {
		$err = get_error();
		$response = array('error' => 'Can not create file'. (
			$err !== null && isset($err['message']) && strlen($err['message']) > 0 ? (': ' . $err['message']) : ''
		));
		$err = null;
	} else {
		$response = downloadSource($_GET['url'], $tmp['source']);
		fclose($tmp['source']);
	}
}

if(is_array($response) && isset($response['mime']) && strlen($response['mime']) > 0) {
	clearstatcache();
	if(false === file_exists($tmp['location'])) {
		$response = array('error' => 'An error and the file can not be found occurred, try again');
	} else if(filesize($tmp['location']) < 1) {
		$response = array('error' => 'Download the file was made, but there was some problem and now the file is empty, try again');
	} else {
		$response['mime'] = str_replace(array('windows-bmp', 'ms-bmp'), 'bmp', //mimetype bitmap to bmp extension
			str_replace('jpeg', 'jpg', //jpeg to jpg extesion
				str_replace('xhtml+xml', 'xhtml',//fix mime to xhtml
					str_replace(array('image/', 'text/', 'application/'), '',
						$response['mime']
					)
				)
			)
		);

		$locationFile = preg_replace('#[.][0-9_]+$#', '.' . $response['mime'], $tmp['location']);
		if(file_exists($locationFile)) {
			unlink($locationFile);
		}

		if(rename($tmp['location'], $locationFile)) {
			//success
			$tmp = $response = null;

			//set cache
			setHeaders(false);

			remove_old_files();

			echo $param_callback, '(',
				json_encode_string(
					($http_port === 443 ? 'https://' : 'http://') .
					preg_replace('#:[0-9]+$#', '', $_SERVER['HTTP_HOST']) .
					($http_port === 80 || $http_port === 443 ? '' : (
						':' . $_SERVER['SERVER_PORT']
					)) .
					dirname($_SERVER['SCRIPT_NAME']). '/' .
					$locationFile
				),
			');';
			exit;
		} else {
			$response = 'Failed to rename the temporary file';
		}
	}
}

if(is_array($tmp) && isset($tmp['location']) && file_exists($tmp['location'])) {
	//remove temporary file if an error occurred
	unlink($tmp['location']);
}

//errors
setHeaders(true);//no-cache

remove_old_files();

echo $param_callback, '(',
	json_encode_string(
		'error: html2canvas-proxy-php: ' . $response['error']
	),
');';
?>
