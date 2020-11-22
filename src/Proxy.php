<?php
namespace Inphinit\CrossDomainProxy;

class Proxy
{
    private $path = 'cache';
    private $cache = 300000;
    private $timeout = 30;
    private $maxRedirs = 5;
    private $dataUri = false;
    private $preferCurl = true;
    private $tempPrefix = 'inph_';
    private $allowedDomains = array('*');
    private $allowedPorts = array(80, 443);
    private $headers = array();
    private $caInfo;

    private $secureSupported;

    public function __construct($url, $timeout = null)
    {
        $this->url = $url;
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
        //'C:/openssl/cert/cacert.pem'
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

    public static function secureSupport()
    {
        //Array ( [0] => tcp [1] => udp [2] => ssl [3] => tls [4] => tlsv1.0 [5] => tlsv1.1 [6] => tlsv1.2 )

        if (self::$secureSupported !== null) {
            return self::$secureSupported;
        }

        return self::$secureSupported = in_array('ssl', \stream_get_transports());
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
