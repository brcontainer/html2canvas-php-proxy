<?php
/*
 * html2canvas-php-proxy 0.2.0
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

//Turn off errors because the script already own uses "error_get_last"
ini_set('display_errors', 'Off');

//setup
define('PATH', 'images');         //relative folder where the images are saved
define('PATH_PERMISSION', 0666);  //use 644 or 666 for remove execution for prevent sploits
define('CCACHE', 60 * 5 * 1000);  //Limit access-control and cache, define 0/false/null/-1 to not use "http header cache"
define('TIMEOUT', 30);            //Timeout from load Socket
define('MAX_LOOP', 10);           //Configure loop limit for redirects (location header)
define('CROSS_DOMAIN', false);    //Enable use of "data URI scheme"
define('PREFER_CURL', true);      //Enable curl if avaliable or disable

/*
 * Set false for disable SSL check
 * Set true for enable SSL check, require config `curl.cainfo=/path/to/cacert.pem` in php.ini
 * Set path (string) if need config CAINFO manualy like this define('SSL_VERIFY_PEER', '/path/to/cacert.pem');
 */

define('SSL_VERIFY_PEER', false);

//constants
define('EOL', chr(10));
define('WOL', chr(13));
define('GMDATECACHE', gmdate('D, d M Y H:i:s'));

/*
If execution has reached the time limit prevents page goes blank (off errors)
or generate an error in PHP, which does not work with the DEBUG (from html2canvas.js)
*/
$maxExec = (int) ini_get('max_execution_time');
define('MAX_EXEC', $maxExec < 1 ? 0 : ($maxExec - 5));//reduces 5 seconds to ensure the execution of the DEBUG

define('INIT_EXEC', time());
define('SECPREFIX', 'h2c_');

$http_port = 0;

$tmp = null;//tmp var usage
$response = array();

/**
 * For show ASCII documents with "data uri scheme"
 * @param string $s    to encode
 * @return string      always return string
 */
function asciiToInline($str)
{
    $trans = array();
    $trans[EOL] = '%0A';
    $trans[WOL] = '%0D';
    $trans[' '] = '%20';
    $trans['"'] = '%22';
    $trans['#'] = '%23';
    $trans['&'] = '%26';
    $trans['\/'] = '%2F';
    $trans['\\'] = '%5C';
    $trans[':'] = '%3A';
    $trans['?'] = '%3F';
    $trans[chr(0)] = '%00';
    $trans[chr(8)] = '';
    $trans[chr(9)] = '%09';

    return strtr($str, $trans);
}

/**
 * Detect SSL stream transport
 * @return boolean|string        If returns string has an problem, returns true if ok
*/
function supportSSL()
{
    if (defined('SOCKET_SSL_STREAM')) {
        return true;
    }

    if (!function_exists('stream_get_transports')) {
        /* PHP 5 */
        if (in_array('ssl', stream_get_transports())) {
            defined('SOCKET_SSL_STREAM', '1');
            return true;
        }
    } else {
        /* PHP 4 */
        ob_start();
        phpinfo(1);

        $info = strtolower(ob_get_clean());

        if (preg_match('/socket\stransports/', $info) !== 0) {
            if (preg_match('/(ssl[,]|ssl [,]|[,] ssl|[,]ssl)/', $info) !== 0) {
                defined('SOCKET_SSL_STREAM', '1');
                return true;
            }
        }
    }

    return 'No SSL stream support detected';
}

/**
 * Remove old files defined by CCACHE
 * @return void           return always void
 */
function removeOldFiles()
{
    $p = PATH . '/';

    if (
        (MAX_EXEC === 0 || (time() - INIT_EXEC) < MAX_EXEC) && //prevents this function locks the process that was completed
        (file_exists($p) || is_dir($p))
    ) {
        $h = opendir($p);
        if (false !== $h) {
            while (false !== ($f = readdir($h))) {
                if (
                    is_file($p . $f) && is_dir($p . $f) === false &&
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
 * this function does not exist by default in php4.3, get detailed error in php5
 * @return array   if has errors
 */
function getError()
{
    if (function_exists('error_get_last') === false) {
        return error_get_last();
    }

    return null;
}

/**
 * Detect if content-type is valid and get charset if available
 * @param string $content    content-type
 * @return array             always return array
 */
function checkContentType($content)
{
    $content = strtolower($content);
    $encode = null;

    if (preg_match('#[;](\s|)+charset[=]#', $content) !== 0) {
        $encode = preg_split('#[;](\s|)+charset[=]#', $content);
        $encode = empty($encode[1]) ? null : trim($encode[1]);
    }

    $mime = trim(
        preg_replace('/[;]([\\s\\S]|)+$/', '',
            str_replace('content-type:', '',
                str_replace('/x-', '/', $content)
            )
        )
    );

    if (in_array($mime, array(
        'image/bmp', 'image/windows-bmp', 'image/ms-bmp',
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'text/html', 'application/xhtml', 'application/xhtml+xml',
        'image/svg+xml', //SVG image
        'image/svg-xml' //Old servers (bug)
    )) === false) {
        return array('error' => $mime . ' mimetype is invalid');
    }

    return array(
        'mime' => $mime, 'encode' => $encode
    );
}

/**
 * enconde string in "json" (only strings), json_encode (native in php) don't support for php4
 * @param string $s    to encode
 * @return string      always return string
 */
function JsonEncodeString($s, $onlyEncode=false)
{
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
    $j = strlen($s);

    for ($i = 0; $i < $j; ++$i) {
        $tmp = substr($s, $i, 1);
        $c = ord($tmp);
        if ($c > 126) {
            $d = '000' . dechex($c);
            $tmp = '\\u' . substr($d, strlen($d) - 4);
        } else {
            if (isset($vetor[$c])) {
                $tmp = $vetor[$c];
            } elseif (($c > 31) === false) {
                $d = '000' . dechex($c);
                $tmp = '\\u' . substr($d, strlen($d) - 4);
            }
        }

        $enc .= $tmp;
    }

    if ($onlyEncode === true) {
        return $enc;
    } else {
        return '"' . $enc . '"';
    }
}

/**
 * set headers in document
 * @param boolean $nocache      If false set cache (if CCACHE > 0), If true set no-cache in document
 * @return void                 return always void
 */
function setHeaders($nocache)
{
    if ($nocache === false && is_int(CCACHE) && CCACHE > 0) {
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
 * @param string $u       set base url
 * @param string $m       set relative url
 * @return string         return always string, if have an error, return blank string (scheme invalid)
*/
function relativeToAbsolute($u, $m)
{
    if (strpos($m, '//') === 0) {//http link //site.com/test
        return 'http:' . $m;
    }

    if (preg_match('#^[a-zA-Z0-9]+[:]#', $m) !== 0) {
        $pu = parse_url($m);

        if (preg_match('/^(http|https)$/i', $pu['scheme']) === 0) {
            return '';
        }

        $m = '';
        if (isset($pu['path'])) {
            $m .= $pu['path'];
        }

        if (isset($pu['query'])) {
            $m .= '?' . $pu['query'];
        }

        if (isset($pu['fragment'])) {
            $m .= '#' . $pu['fragment'];
        }

        return relativeToAbsolute($pu['scheme'] . '://' . $pu['host'], $m);
    }

    if (preg_match('/^[?#]/', $m) !== 0) {
        return $u . $m;
    }

    $pu = parse_url($u);
    $pu['path'] = isset($pu['path']) ? preg_replace('#/[^/]*$#', '', $pu['path']) : '';

    $pm = parse_url('http://1/' . $m);
    $pm['path'] = isset($pm['path']) ? $pm['path'] : '';

    $isPath = $pm['path'] !== '' && strpos(strrev($pm['path']), '/') === 0 ? true : false;

    if (strpos($m, '/') === 0) {
        $pu['path'] = '';
    }

    $b = $pu['path'] . '/' . $pm['path'];
    $b = str_replace('\\', '/', $b);//Confuso ???

    $ab = explode('/', $b);
    $j = count($ab);

    $ab = array_filter($ab, 'strlen');
    $nw = array();

    for ($i = 0; $i < $j; ++$i) {
        if (isset($ab[$i]) === false || $ab[$i] === '.') {
            continue;
        }
        if ($ab[$i] === '..') {
            array_pop($nw);
        } else {
            $nw[] = $ab[$i];
        }
    }

    $m  = $pu['scheme'] . '://' . $pu['host'] . '/' . implode('/', $nw) . ($isPath === true ? '/' : '');

    if (isset($pm['query'])) {
        $m .= '?' . $pm['query'];
    }

    if (isset($pm['fragment'])) {
        $m .= '#' . $pm['fragment'];
    }

    $nw = null;
    $ab = null;
    $pm = null;
    $pu = null;

    return $m;
}

/**
 * validate url
 * @param string $u  set base url
 * @return boolean   return always boolean
*/
function isHttpUrl($u)
{
    return preg_match('#^http(|s)[:][/][/][a-z0-9]#i', $u) !== 0;
}

/**
 * create folder for images download
 * @return boolean      return always boolean
*/
function createFolder()
{
    if (file_exists(PATH) === false || is_dir(PATH) === false) {
        return mkdir(PATH, PATH_PERMISSION);
    }
    return true;
}

/**
 * create temp file which will receive the download
 * @param string  $basename        set url
 * @param boolean $isEncode        If true uses the "first" temporary name
 * @return boolean|array           If you can not create file return false, If create file return array
*/
function createTmpFile($basename, $isEncode)
{
    $folder = preg_replace('#[/]$#', '', PATH) . '/';

    if ($isEncode === false) {
        $basename = SECPREFIX . sha1($basename);
    }

    //$basename .= $basename;
    $tmpMime  = '.' . mt_rand(0, 1000) . '_';
    $tmpMime .= $isEncode === true ? time() : INIT_EXEC;

    if (file_exists($folder . $basename . $tmpMime)) {
        return createTmpFile($basename, true);
    }

    $source = fopen($folder . $basename . $tmpMime, 'w');

    if ($source !== false) {
        return array(
            'location' => $folder . $basename . $tmpMime,
            'source' => $source
        );
    }

    return false;
}

function curlDownloadSource($url, $toSource)
{
    $uri = parse_url($url);

    //Reformat url
    $currentUrl  = (empty($uri['scheme']) ? 'http': $uri['scheme']) . '://';
    $currentUrl .= empty($uri['host'])    ? '': $uri['host'];
    $currentUrl .= empty($uri['path'])    ? '/': $uri['path'];
    $currentUrl .= empty($uri['query'])   ? '': ('?' . $uri['query']);

    $ch = curl_init();

    if (SSL_VERIFY_PEER === true) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    } elseif (is_string(SSL_VERIFY_PEER)) {
        if (is_file(SSL_VERIFY_PEER)) {
            curl_close($ch);
            return array('error' => 'Not found certificate: ' . $SSL_VERIFY_PEER);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CAINFO, SSL_VERIFY_PEER);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT);
    curl_setopt($ch, CURLOPT_URL, $currentUrl);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, MAX_LOOP);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);

    if (isset($uri['user'])) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_USERPWD, $uri['user'] . ':' . (isset($uri['pass']) ? $uri['pass'] : ''));
    }

    $headers = array();

    if (false === empty($_SERVER['HTTP_ACCEPT'])) {
        $headers[] = 'Accept: ' . $_SERVER['HTTP_ACCEPT'];
    }

    if (false === empty($_SERVER['HTTP_USER_AGENT'])) {
        $headers[] = 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'];
    }

    if (false === empty($_SERVER['HTTP_REFERER'])) {
        $headers[] = 'Referer: ' . $_SERVER['HTTP_REFERER'];
    }

    $headers[] = 'Host: ' . $uri['host'];
    $headers[] = 'Connection: close';

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $data = curl_exec($ch);

    $curl_err = curl_errno($ch);

    $result = null;

    if ($curl_err !== 0) {
        $result = array('error' => 'CURL failed: ' . $curl_err);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        if ($httpCode != 200) {
            $result = array('error' => 'Request returned HTTP_' . $httpCode);
        }

        if ($result === null) {
            $result = checkContentType($contentType);

            if (empty($result['error'])) {
                fwrite($toSource, $data);
            }
        }
    }

    curl_close($ch);

    return $result;
}

/**
 * download http request recursive (If found HTTP 3xx)
 * @param string $url               to download
 * @param resource $toSource        to download
 * @return array                    retuns array
*/
function downloadSource($url, $toSource, $caller)
{
    $errno = 0;
    $errstr = '';

    ++$caller;

    if ($caller > MAX_LOOP) {
        return array('error' => 'Limit of ' . MAX_LOOP . ' redirects was exceeded, maybe there is a problem: ' . $url);
    }

    $uri = parse_url($url);
    $secure = strcasecmp($uri['scheme'], 'https') === 0;

    if ($secure) {
        $response = supportSSL();

        if ($response !== true) {
            return array('error' => $response);
        }
    }

    $port = empty($uri['port']) ? ($secure === true ? 443 : 80) : ((int) $uri['port']);
    $host = ($secure ? 'ssl://' : '') . $uri['host'];

    $fp = fsockopen($host, $port, $errno, $errstr, TIMEOUT);

    if ($fp === false) {
        return array('error' => 'SOCKET: ' . $errstr . '(' . $errno . ') - ' . $host . ':' . $port);
    } else {
        fwrite(
            $fp, 'GET ' . (
                empty($uri['path'])  ? '/' : $uri['path']
            ) . (
                empty($uri['query']) ? '' : ('?' . $uri['query'])
            ) . ' HTTP/1.0' . WOL . EOL
        );

        if (isset($uri['user'])) {
            $auth = base64_encode($uri['user'] . ':' . (isset($uri['pass']) ? $uri['pass'] : ''));
            fwrite($fp, 'Authorization: Basic ' . $auth . WOL . EOL);
        }

        if (false === empty($_SERVER['HTTP_ACCEPT'])) {
            fwrite($fp, 'Accept: ' . $_SERVER['HTTP_ACCEPT'] . WOL . EOL);
        }

        if (false === empty($_SERVER['HTTP_USER_AGENT'])) {
            fwrite($fp, 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] . WOL . EOL);
        }

        if (false === empty($_SERVER['HTTP_REFERER'])) {
            fwrite($fp, 'Referer: ' . $_SERVER['HTTP_REFERER'] . WOL . EOL);
        }

        fwrite($fp, 'Host: ' . $uri['host'] . WOL . EOL);
        fwrite($fp, 'Connection: close' . WOL . EOL . WOL . EOL);

        $isRedirect = true;
        $isBody = false;
        $isHttp = false;
        $encode = null;
        $mime = null;
        $data = '';

        while (false === feof($fp)) {
            if (MAX_EXEC !== 0 && (time() - INIT_EXEC) >= MAX_EXEC) {
                return array('error' => 'Maximum execution time of ' . (MAX_EXEC + 5) . ' seconds exceeded, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled)');
            }

            $data = fgets($fp);

            if ($data === false) {
                continue;
            }

            if ($isHttp === false) {
                if (preg_match('#^HTTP[/]1[.]#i', $data) === 0) {
                    fclose($fp);//Close connection
                    $data = '';
                    return array('error' => 'This request did not return a HTTP response valid');
                }

                $tmp = preg_replace('#(HTTP/1[.]\\d |[^0-9])#i', '',
                    preg_replace('#^(HTTP/1[.]\\d \\d{3}) [\\w\\W]+$#i', '$1', $data)
                );

                if ($tmp === '304') {
                    fclose($fp);//Close connection
                    $data = '';
                    return array('error' => 'Request returned HTTP_304, this status code is incorrect because the html2canvas not send Etag');
                } else {
                    $isRedirect = preg_match('#^(301|302|303|307|308)$#', $tmp) !== 0;

                    if ($isRedirect === false && $tmp !== '200') {
                        fclose($fp);
                        $data = '';
                        return array('error' => 'Request returned HTTP_' . $tmp);
                    }

                    $isHttp = true;

                    continue;
                }
            }

            if ($isBody === false) {
                if (preg_match('#^location[:]#i', $data) !== 0) {//200 force 302
                    fclose($fp);//Close connection

                    $data = trim(preg_replace('#^location[:]#i', '', $data));

                    if ($data === '') {
                        return array('error' => '"Location:" header is blank');
                    }

                    $nextUri = $data;
                    $data = relativeToAbsolute($url, $data);

                    if ($data === '') {
                        return array('error' => 'Invalid scheme in url (' . $nextUri . ')');
                    }

                    if (isHttpUrl($data) === false) {
                        return array('error' => '"Location:" header redirected for a non-http url (' . $data . ')');
                    }

                    return downloadSource($data, $toSource, $caller);
                } elseif (preg_match('#^content[-]length[:]( 0|0)$#i', $data) !== 0) {
                    fclose($fp);
                    $data = '';
                    return array('error' => 'source is blank (Content-length: 0)');
                } elseif (preg_match('#^content[-]type[:]#i', $data) !== 0) {
                    $response = checkContentType($data);

                    if (isset($response['error'])) {
                        fclose($fp);
                        return $response;
                    }

                    $encode = $response['encode'];
                    $mime = $response['mime'];
                } elseif ($isBody === false && trim($data) === '') {
                    $isBody = true;
                    continue;
                }
            } elseif ($isRedirect === true) {
                fclose($fp);
                $data = '';
                return array('error' => 'The response should be a redirect "' . $url . '", but did not inform which header "Localtion:"');
            } elseif ($mime === null) {
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

        if ($isBody === false) {
            return array('error' => 'Content body is empty');
        } elseif ($mime === null) {
            return array('error' => 'Not set the mimetype from "' . $url . '"');
        }

        return array(
            'mime' => $mime,
            'encode' => $encode
        );
    }
}

define('JSONP_CALLBACK', empty($_GET['callback']) ? false : $_GET['callback']);

if (empty($_SERVER['HTTP_HOST'])) {
    $response = array('error' => 'The client did not send the Host header');
} elseif (isset($_SERVER['SERVER_PORT']) === false) {
    $response = array('error' => 'The Server-proxy did not send the PORT (configure PHP)');
} elseif (MAX_EXEC < 10) {
    $response = array('error' => 'Execution time is less 15 seconds, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended time is 30 seconds or more');
} elseif (MAX_EXEC <= TIMEOUT) {
    $response = array('error' => 'The execution time is not configured enough to TIMEOUT in SOCKET, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended that the "max_execution_time =;" be a minimum of 5 seconds longer or reduce the TIMEOUT in "define(\'TIMEOUT\', ' . TIMEOUT . ');"');
} elseif (empty($_GET['url'])) {
    $response = array('error' => 'No such parameter "url"');
} elseif (isHttpUrl($_GET['url']) === false) {
    $response = array('error' => 'Only http scheme and https scheme are allowed');
} elseif (createFolder() === false) {
    $err = getError();
    $response = array('error' => 'Can not create directory'. (
        $err !== null && empty($err['message']) ? '' : (': ' . $err['message'])
    ));
    $err = null;
} else {
    $http_port = (int) $_SERVER['SERVER_PORT'];

    $tmp = createTmpFile($_GET['url'], false);

    if ($tmp === false) {
        $err = getError();
        $response = array('error' => 'Can not create file'. (
            $err !== null && empty($err['message']) ? '' : (': ' . $err['message'])
        ));
        $err = null;
    } else {
        $response = PREFER_CURL && function_exists('curl_init') ?
                        curlDownloadSource($_GET['url'], $tmp['source']) :
                            downloadSource($_GET['url'], $tmp['source'], 0);

        fclose($tmp['source']);
    }
}

//set mime-type
header('Content-Type: application/javascript');

if (is_array($response) && false === empty($response['mime'])) {
    clearstatcache();

    if (false === file_exists($tmp['location'])) {
        $response = array('error' => 'Request was downloaded, but file can not be found, try again');
    } elseif (filesize($tmp['location']) < 1) {
        $response = array('error' => 'Request was downloaded, but there was some problem and now the file is empty, try again');
    } else {
        $extension = str_replace(array('image/', 'text/', 'application/'), '', $response['mime']);
        $extension = str_replace(array('windows-bmp', 'ms-bmp'), 'bmp', $extension);
        $extension = str_replace(array('svg+xml', 'svg-xml'), 'svg', $extension);
        $extension = str_replace('xhtml+xml', 'xhtml', $extension);
        $extension = str_replace('jpeg', 'jpg', $extension);

        $locationFile = preg_replace('#[.][0-9_]+$#', '.' . $extension, $tmp['location']);

        if (file_exists($locationFile)) {
            unlink($locationFile);
        }

        if (rename($tmp['location'], $locationFile)) {
            //set cache
            setHeaders(false);

            removeOldFiles();

            $mime = $response['mime'];

            if ($response['encode'] !== null) {
                $mime .= ';charset=' . JsonEncodeString($response['encode'], true);
            }

            if (JSONP_CALLBACK === false) {
                header('Content-Type: ' . $mime);
                echo file_get_contents($locationFile);
            } elseif (CROSS_DOMAIN === true) {
                $tmp = $response = null;

                header('Content-Type: application/javascript');

                if (strpos($mime, 'image/svg') !== 0 && strpos($mime, 'image/') === 0) {
                    echo JSONP_CALLBACK, '("data:', $mime, ';base64,',
                        base64_encode(
                            file_get_contents($locationFile)
                        ),
                    '");';
                } else {
                    echo JSONP_CALLBACK, '("data:', $mime, ',',
                        asciiToInline(file_get_contents($locationFile)),
                    '");';
                }
            } else {
                $tmp = $response = null;

                header('Content-Type: application/javascript');

                $dir_name = dirname($_SERVER['SCRIPT_NAME']);

                if ($dir_name === '\/' || $dir_name === '\\') {
                    $dir_name = '';
                }

                echo JSONP_CALLBACK, '(',
                    JsonEncodeString(
                        ($http_port === 443 ? 'https://' : 'http://') .
                        preg_replace('#:[0-9]+$#', '', $_SERVER['HTTP_HOST']) .
                        ($http_port === 80 || $http_port === 443 ? '' : (
                            ':' . $_SERVER['SERVER_PORT']
                        )) .
                        $dir_name. '/' .
                        $locationFile
                    ),
                ');';
            }
            exit;
        } else {
            $response = array('error' => 'Failed to rename the temporary file');
        }
    }
}

if (is_array($tmp) && isset($tmp['location']) && file_exists($tmp['location'])) {
    //remove temporary file if an error occurred
    unlink($tmp['location']);
}

//errors
setHeaders(true);//no-cache

header('Content-Type: application/javascript');

removeOldFiles();

echo JSONP_CALLBACK, '(',
    JsonEncodeString(
        'error: html2canvas-proxy-php: ' . $response['error']
    ),
');';
