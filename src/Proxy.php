<?php
namespace Inphinit\CrossDomainProxy;

class Proxy
{
    private $preferCurl = true;
    private $dataUri = false;
    private $path = 'cache';
    private $cache = 300000;
    private $timeout = 30;
    private $maxRedirs = 5;
    private $certificateAuthority;
    private $headers = array();
    private $allowedUrls = array('*');
    private $nonSecure = false;

    private $secureSupported;

    public function __construct($url, $timeout = null)
    {
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

    public function setHeader($header, $value)
    {
        $header = strtolower($header);

        if ($header === 'host' || $header === 'connection') {
            $this->headers[$header] = trim($value);
        }
    }

    public function getHeaders()
    {
        return $this->headers;
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

    public function nonSecureAllowed($allow = null)
    {
        if ($allow === null) {
            return $this->nonSecure;
        }

        $this->nonSecure = $allow === true;
    }

    public static function secureSupport()
    {
        //Array ( [0] => tcp [1] => udp [2] => ssl [3] => tls [4] => tlsv1.0 [5] => tlsv1.1 [6] => tlsv1.2 )

        if (self::$secureSupported !== null) {
            self::$secureSupported = in_array('ssl', \stream_get_transports());
        }

        return self::$secureSupported;
    }

    public static function parseUri($url)
    {
        if (self::$uri === null) {
            $uri = parse_url($url);

            if (isset($uri['user'])) {
                $uri['authorization'] = base64_encode($uri['user'] . ':' . (isset($uri['pass']) ? $uri['pass'] : ''));
            }

            $uri['path'] = empty($uri['path'])  ? '/' : $uri['path'];
            $uri['query'] = empty($uri['query']) ? '' : ('?' . $uri['query']);

            self::$uri = (\strClass) $uri;
        }

        return self::$uri;
    }
}
