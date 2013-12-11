<?php
/*
html2canvas-proxy-php 0.1.3
Copyright (c) 2013 Guilherme Nascimento (brcontainer@yahoo.com.br)

Released under the MIT license
*/

error_reporting(0);//Turn off erros because the script already own uses error_get_last

//constants
define('EOL', chr(10));
define('WEOL', chr(13));
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
$maxExec = ini_get('max_execution_time');
define('MAX_EXEC', empty($maxExec)||$maxExec<1 ? 0: (int) ($maxExec-5));//reduces 5 seconds to ensure the execution of the DEBUG
define('INIT_EXEC', isset($_SERVER['REQUEST_TIME']) && strlen($_SERVER['REQUEST_TIME'])>0 ? $_SERVER['REQUEST_TIME'] : time());

//set mime-type
header('Content-Type: application/javascript');

$param_callback = JSLOG;//force use alternative log error
$tmp = null;//tmp var usage

function remove_old_files(){
	if(MAX_EXEC!==0 && (time()-INIT_EXEC)>=MAX_EXEC){
		//prevents this function locks the process that was completed
		return null;
	}
	$p = PATH . '/';
	if($h=opendir($p)){
		while(false!==($f=readdir($h))){
			if((INIT_EXEC-filectime($p . $f))>(CCACHE*2)){
				unlink($p . $f);
			}
		}
	}
}

if(function_exists('error_get_last')===false){
	//this function does not exist by default in php4.3, error_get_last is only to prevent error message: Function not defined
	function error_get_last(){ return null; }
}

function json_encode_string($s){
	$s = utf8_encode($s);
	$vetor = Array();
	$vetor[0]  = '\0';
	$vetor[8]  = '\b';
	$vetor[9]  = '\t';
	$vetor[10] = '\n';
	$vetor[12] = '\f';
	$vetor[13] = '\r';
	$vetor[34] = '\"';
	$vetor[47] = '\/';
	$vetor[92] = '\\';

	$e = Array();
	$j = strlen($s);

	for($i=0;$i<$j;++$i) {
		$e[$i] = substr($s, $i, 1);
		$c = ord($e[$i]);
		if($c > 126){
			$d = '000' . bin2hex($c);
			$e[$i] = '\u' . substr($d, strlen($d)-4);
		} else {
			if (isset($vetor[$c]) && strlen($vetor[$c])>0) {
				$e[$i] = $vetor[$c];
			} else if (!($c > 31)) {
				$d = '000' . bin2hex($c);
				$e[$i] = '\u' . substr($d, strlen($d)-4);
			}
		}
	}
	return '"' . join($e,'') . '"';
}

function downloadSource($url, $toSource){
	$uri = parse_url($url);
	$secure = strcasecmp($uri['scheme'], 'https')===0;
	if(
		!$fp = fsockopen(
			($secure ? 'ssl://':'') . $uri['host'],
			isset($uri['port']) && strlen($uri['port'])>0 ? $uri['port'] : ( $secure ? 443 : 80 ),
			$errno,
			$errstr,
			TIMEOUT
		)
	){
		return 'SOCKET: ' . $errstr . '(' . $errno . ')';
	} else {
		fwrite(
			$fp,'GET ' . (
				isset($uri['path']) && strlen($uri['path'])>0 ? $uri['path']:'/'
			) . (
				isset($uri['query']) && strlen($uri['query'])>0 ? ('?' . $uri['query']):''
			) . ' HTTP/1.0' . EOL
		);

		if(isset($_SERVER['HTTP_ACCEPT']) && strlen($_SERVER['HTTP_ACCEPT'])>0){
			fwrite($fp, 'Accept: ' . $_SERVER['HTTP_ACCEPT'] . EOL);
		}
		if(isset($_SERVER['HTTP_USER_AGENT']) && strlen($_SERVER['HTTP_USER_AGENT'])>0){
			fwrite($fp, 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] . EOL);
		}
		fwrite($fp, 'Host: ' . $uri['host'] . EOL);
		fwrite($fp, 'Connection: close' . EOL . EOL);

		$isBody = false;
		$isHttp = null;
		$mime = null;
		$data = '';

		while(!feof($fp)){
			if(MAX_EXEC!==0 && (time()-INIT_EXEC)>=MAX_EXEC){
				return 'Maximum execution time of ' . (MAX_EXEC+5) . ' seconds exceeded, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled)';
			}
			if(($data = fgets($fp))===false){ continue; }
			if($isHttp===null){
				if(!preg_match('#^HTTP[/]1[.]#i',$data)){
					fclose($fp);//Close connection
					$data = '';
					return 'This request did not return a HTTP response valid';
				}

				$tmp = preg_replace('#(HTTP/1[.]\d |[^0-9])#i','',$data);
				if($tmp==='304'){
					fclose($fp);//Close connection
					$data = '';
					return 'The image returned HTTP_304, this status code is incorrect because the html2canvas not send Etag';
				} else {
					$isRedirect = preg_match('#^(301|302|303|307|308)$#', $tmp);
					if($isRedirect===false && $tmp!=='200'){
						fclose($fp);//Close connection
						$data = '';
						return '';
					}
					$isHttp = true;
					continue;
				}
			}
			if($isBody===false){
				if(preg_match('#^location[:]#i',$data)){//200 force 302
					fclose($fp);//Close connection
					$data = trim(preg_replace('#^location[:]#i', '', $data));
					if(!isHttpUrl($data)){
						return $data==='' ? '"Location:" header is blank':(
							'"Location:" header redirected for a non-http url (' . $data . ')'
						);
					}
					return downloadSource($data, $toSource);
				} else if(preg_match('#^content[-]length[:]( 0|0)$#i', $data)){
					fclose($fp);//Close connection
					$data = '';
					return 'source is blank (Content-length: 0)';
				} else if(preg_match('#^content[-]type[:]#i', $data)){
					$mime = trim(str_replace('content-type:', '',
						str_replace('/x-', '/', strtolower($data))
					));

					if(!in_array($mime, Array(
						'image/bmp','image/windows-bmp','image/ms-bmp',
						'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
						'text/html', 'application/xhtml', 'application/xhtml+xml'
					))){
						fclose($fp);//Close connection
						$data = '';
						return $mime . ' mimetype is invalid';
					}
				} else if($isBody===false && trim($data)===''){
					$isBody = true;
					continue;
				}
			} else if($isRedirect===true){
				fclose($fp);//Close connection
				$data = '';
				return 'The response should be a redirect "' . $url . '", but did not inform which header "Localtion:"';
			} else if($mime===null){
				fclose($fp);//Close connection
				$data = '';
				return 'Not set the mimetype from "' . $url . '"';
			} else {
				fwrite($toSource, $data);
				continue;
			}
		}

		fclose($fp);
		$data = '';
		if($isBody===false){
			return 'Content body is empty';
		} else if($mime===null){
			return 'Not set the mimetype from "' . $url . '"';
		}
		return Array(
			'mime'=>$mime
		);
	}
}

function setHeaders($nocache){
	if($nocache===false && is_int(CCACHE) && CCACHE>0){
		//save to browser cache
		header('Last-Modified: ' . GMDATECACHE . ' GMT');
		header('ETag: ' . md5(GMDATECACHE . ' GMT'));
		header('Cache-Control: max-age=' . (CCACHE-1) . ', must-revalidate');
		header('Pragma: max-age=' . (CCACHE-1));
		header('Expires: ' . gmdate('D, d M Y H:i:s', INIT_EXEC + (CCACHE-1)));
		header('Access-Control-Max-Age:' . CCACHE);
	} else {
		//no-cache
		header('Pragma: no-cache');
		header('Cache-control: no-cache');
		header('Expires: '. GMDATECACHE .' GMT');
	}

	//set access-control
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Request-Method: *');
	header('Access-Control-Allow-Methods: OPTIONS, GET');
	header('Access-Control-Allow-Headers: *');
}

function isHttpUrl($u){
	return !!preg_match('#^http(|s)[:]\/\/[a-z0-9]#i', $u);
}

function createFolder(){
	if(!is_dir(PATH)){
		return !!mkdir(PATH, 755);
	}
	return true;
}

function createTmpFile($basename, $isEncode){
	$folder = preg_replace('#\/$#', '', PATH).'/';
	if($isEncode===false){
		$basename = sha1($basename);
	}

	$basename .= $basename;
	$tmpMime = '.' . mt_rand(0,1000) . '_' . ($isEncode ? (
		isset($_SERVER['REQUEST_TIME']) && strlen($_SERVER['REQUEST_TIME'])>0 ? $_SERVER['REQUEST_TIME'] : time()
	):INIT_EXEC);

	if(file_exists($folder . $basename . $tmpMime)){
		return createTmpFile($basename, true);
	}

	if($source = fopen($folder . $basename . $tmpMime, 'w')){
		return Array(
			'location' => $folder . $basename . $tmpMime,
			'source' => $source
		);
	}
	return false;
}

if(isset($_GET['callback']) && strlen($_GET['callback'])>0){
	$param_callback = $_GET['callback'];
}

if(!(isset($_SERVER['HTTP_HOST']) && strlen($_SERVER['HTTP_HOST'])>0)){
	$response = 'The client did not send the Host header';
} else if(MAX_EXEC<10){
	$response = 'Execution time is less 15 seconds, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended time is 30 seconds or more';
} else if(!isset($_GET['url']) && strlen($_GET['url'])>0){
	$response = 'No such parameter "url"';
} else if(!isHttpUrl($_GET['url'])){
	$response = 'Only http scheme and https scheme are allowed';
} else if(preg_match('#[^A-Za-z0-9_\.\[\]]#', $param_callback)){
	$response = 'Parameter "callback" contains invalid characters';
	$param_callback = JSLOG;
} else if(!createFolder()){
	$err = error_get_last();
	$response = 'Can not create directory'. (
		isset($err['message']) && strlen($err['message'])>0 ? (': ' . $err['message']):''
	);
	$err = null;
} else {
	$tmp = createTmpFile($_GET['url'], false);
	if($tmp===false){
		$err = error_get_last();
		$response = 'Can not create file'. (
			isset($err['message']) && strlen($err['message'])>0 ? (': ' . $err['message']):''
		);
		$err = null;
	} else {
		$response = downloadSource($_GET['url'], $tmp['source']);
		fclose($tmp['source']);
	}
}

if(is_array($response) && isset($response['mime']) && strlen($response['mime'])>0){
	clearstatcache();
	if(!file_exists($tmp['location'])){
		$response = 'An error and the file can not be found occurred, try again';
	} else if(filesize($tmp['location'])<1){
		$response = 'Download the file was made, but there was some problem and now the file is empty, try again';
	} else {
		$response['mime'] = str_replace(Array('windows-bmp','ms-bmp'), 'bmp', //mimetype bitmap to bmp extension
			str_replace('jpeg', 'jpg', //jpeg to jpg extesion
				str_replace('xhtml+xml', 'xhtml',//fix mime to xhtml
					str_replace(Array('image/', 'text/', 'application/'), '',
						$response['mime']
					)
				)
			)
		);

		$locationFile = preg_replace('#[.][0-9_]+$#', '.' . $response['mime'], $tmp['location']);
		if(file_exists($locationFile)){
			unlink($locationFile);
		}

		if(rename($tmp['location'], $locationFile)){
			//success
			$tmp = $response = null;

			//set cache
			setHeaders(false);

			remove_old_files();

			echo $param_callback, '(',
				json_encode_string(
					($_SERVER['SERVER_PORT']==443 ? 'https://':'http://') .
					preg_replace('#:[0-9]+$#', '', $_SERVER['HTTP_HOST']) .
					($_SERVER['SERVER_PORT']==80 || $_SERVER['SERVER_PORT']==443 ? '':(
						':'.$_SERVER['SERVER_PORT']
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

if(is_array($tmp) && isset($tmp['location']) && file_exists($tmp['location'])){
	//remove temporary file if an error occurred
	unlink($tmp['location']);
}

//errors
setHeaders(true);//no-cache

remove_old_files();

echo $param_callback, '(',
	json_encode_string(
		'error: html2canvas-proxy-php: ' . $response
	),
');';
?>
