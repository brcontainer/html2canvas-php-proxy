<?php
namespace Inphinit\CrossDomainProxy;

use Inphinit\CrossDomainProxy\Exception\CoreException;

use Inphinit\CrossDomainProxy\Service\CurlService;
use Inphinit\CrossDomainProxy\Service\NativeService;

class Proxy
{
    private $preferCurl = false;
    private $dataUri = false;
    private $path = 'cache';
    private $cache = 300000;
    private $timeout = 30;
    private $maxRedirs = 5;
    private $certificateAuthority;
    private $nonValidate = false;
    private $headers = array();
    private $allowedUrls = array('*');

    private static $secureSupported;

    public function __construct($url, $timeout = null)
    {
        if (!self::isHttpScheme($url)) {
            throw new CoreException("Invalid URL: \"{$url}\"");
        }

        $this->url = $url;

        if ($timeout) {
            $this->timeout = $timeout;
        }
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setMaxRedirs($max)
    {
        $this->maxRedirs = $max;
    }

    public function getMaxRedirs()
    {
        return $this->maxRedirs;
    }

    public function setCertificateAuthority($cainfo)
    {
        $this->certificateAuthority = $cainfo;
    }

    public function getCertificateAuthority()
    {
        return $this->certificateAuthority;
    }

    public function withoutSecurityValidation($disable = null)
    {
        if ($allow === null) {
            return $this->nonValidate;
        }

        $this->nonValidate = $disable === true;
    }

    public function setHeader($header, $value)
    {
        $header = strtolower($header);

        if ($header !== 'host' && $header !== 'connection') {
            $this->headers[$header] = trim($value);
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getTemp()
    {
        return fopen('php://temp', 'w');
    }

    public function addURLPattern($pattern)
    {
        if ($pattern === '*') {
            $this->allowedUrls = array('*');
        } else {
            $this->allowedUrls[] = array($pattern);
        }
    }

    public function removeURLPattern($pattern)
    {
        $pos = array_search($pattern, $this->allowedUrls);

        if ($pos !== false) {
            $this->allowedUrls = array_slice($this->allowedUrls, $pos, 1, true);

            if (count($this->allowedUrls) === 0) {
                $this->allowedUrls = array('*');
            }
        }
    }

    public function curl($prefer = null)
    {
        if ($prefer === null) {
            return $this->preferCurl;
        }

        $this->preferCurl = $prefer === true;
    }

    public static function secureSupport()
    {
        //Array ( [0] => tcp [1] => udp [2] => ssl [3] => tls [4] => tlsv1.0 [5] => tlsv1.1 [6] => tlsv1.2 )

        if (self::$secureSupported === null) {
            self::$secureSupported = in_array('ssl', \stream_get_transports());
        }

        return self::$secureSupported;
    }

    private function service()
    {
        if ($this->preferCurl && function_exists('curl_init')) {
            $service = new CurlService($this);
        } else {
            $service = new NativeService($this);
        }

        $service->get();
    }

    public function output()
    {
        if (isset($_GET['callback'])) {
            return $this->jsonp($_GET['callback']);
        } else {
            return $this->resource();
        }
    }

    public function resource()
    {
        $this->service();
    }

    public function jsonp($callback = null, $catchExceptions = true)
    {
        if ($callback === null) {
            $callback = $_GET['callback'];
        }

        if (ctype_print($callback) === false) {
            throw new CoreException('Invalid callback');
        }

        if ($catchExceptions) {
            try {
                $this->service();
            } catch ($ee) {
                echo $callback, '(', json_encode($ee->getMessage()), ')';
            }
        } else {
            $this->service();
        }
    }
    public static function parseUri($url)

    {
        $uri = parse_url($url);

        if (isset($uri['user'])) {
            $uri['authorization'] = base64_encode($uri['user'] . ':' . (isset($uri['pass']) ? $uri['pass'] : ''));
        }

        if (isset($uri['port']) === false) {
            $uri['port'] = $uri['scheme'] === 'https' ? 443 : 80;
        }

        $uri['path'] = isset($uri['path'])  ? $uri['path'] : '/';
        $uri['query'] = isset($uri['query']) ? ('?' . $uri['query']) : '';

        return (object) $uri;
    }

    public static function isHttpScheme($url)
    {
        return $url && in_array(strtolower(parse_url($url, PHP_URL_SCHEME)), array( 'http', 'https' ));
    }
}
