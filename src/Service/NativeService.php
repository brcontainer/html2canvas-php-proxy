<?php
namespace Inphinit\CrossDomainProxy\Service;

use Inphinit\CrossDomainProxy\Exception\ConnectionException;
use Inphinit\CrossDomainProxy\Exception\HttpException;
use Inphinit\CrossDomainProxy\Exception\TimeoutException;

use Inphinit\CrossDomainProxy\Proxy;

class NativeService
{
    private $proxy;
    private $temp;

    public function __construct(Proxy $proxy)
    {
        $this->proxy = $proxy;
        $this->temp = $proxy->getTemp();
    }

    public function get()
    {
        $this->download($this->proxy->getUrl(), 0);
    }

    private function download($url, $caller)
    {
        $maxRedirs = $this->proxy->getMaxRedirs();

        if ($caller > $maxRedirs) {
            throw new HttpException("Request exceeded the limit of {$maxRedirs} HTTP redirects due to probable configuration error and not Proxy");
        }

        $uri = Proxy::parseUri($url);

        $ssl = null;

        $host = $uri->host;
        $port = $uri->port;

        ftruncate($this->temp, 0);
        rewind($this->temp);

        $timeout = $this->proxy->getTimeout();

        if ($uri->scheme === 'https') {
            if ($this->proxy->withoutSecurityValidation()) {
                $ssl = array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                );
            } else if (!Proxy::secureSupport()) {
                throw new ConnectionException('Secure connections isn\'t supported by your PHP (check php.ini or php version)');
            } else {
                $ca = $this->proxy->getCertificateAuthority();

                if ($ca) {
                    $ssl = array(
                        'verify_peer'  => true,
                        'cafile'       => $ca,
                        'verify_depth' => 5,
                        'CN_match'     => $host
                    );
                }
            }

            $server = 'ssl://' . $host;
        } else {
            $server = $host;
        }

        if ($ssl === null) {
            $socket = fsockopen($server, $port, $errno, $errstr, $timeout);
        } else {
            $context = stream_context_create(array( 'ssl' => $ssl ));
            $socket = stream_socket_client("$server:$port", $errno, $errstr, $timeout, \STREAM_CLIENT_CONNECT, $context);
        }

        if ($socket === false) {
            throw new ConnectionException($errstr || 'Unknown connection error', $errno);
        }

        fwrite($socket, "GET {$uri->path} HTTP/1.0\n");

        foreach ($this->proxy->getHeaders() as $header => $value) {
            fwrite($socket, "{$header}: {$value}\n");
        }

        if (isset($uri->authorization)) {
            fwrite($socket, "Authorization: {$uri->authorization}\n");
        }

        fwrite($socket, "Host: {$host}\n");
        fwrite($socket, "Connection: close\n\n");

        $isRedirect = $isBody = $isHttp = false;

        $contentType = null;

        while (false === feof($socket)) {
            if (false) {
                throw new TimeoutException(10);
            }

            $data = fgets($socket);

            if ($isHttp === false) {
                if (stripos($data, 'HTTP/1.') !== 0) {
                    fclose($socket);

                    $socket = $data = null;

                    throw new HttpException('This request did not return a HTTP response valid');
                }

                $status = substr($data, 9, 3);

                if ($status === '304') {
                    fclose($socket);

                    $socket = $data = null;

                    throw new HttpException('HTTP response returned 304, this status code is incorrect because the proxy don\'t send Etag', 304);
                } else {
                    $isRedirect = strpos($status, '3') === 0;

                    if ($isRedirect === false && strpos($status, '2') !== 0) {
                        fclose($socket);

                        $socket = $data = null;

                        throw new HttpException("HTTP response returned {$status}", $status);
                    }

                    $isHttp = true;

                    continue;
                }
            }

            if ($isBody === false) {
                if (stripos($data, 'location:') === 0) {
                    fclose($socket);

                    $location = trim(substr($data, 9));

                    if ($location === '') {
                        throw new HttpException('"Location:" header is blank');
                    }

                    $location = self::getAbsoluteURL($url, $location);

                    if ($url === $location) {
                        throw new HttpException('"Location:" header is in a loop always directing to the same URL');
                    }

                    if (Proxy::isHttpScheme($data) === false) {
                        throw new Exception("\"Location:\" header redirected for a non-http url ({$data})");
                    }

                    return $this->download($location, ++$caller);
                } elseif (preg_match('#^content-length[:](\\s)?0$#i', $data) !== 0) {
                    fclose($socket);

                    $socket = $data = null;

                    throw new HttpException('source is blank (Content-length: 0)');
                } elseif (stripos($data, 'content-type:') === 0) {
                    $contentType = trim(substr($data, 13));
                    continue;
                } elseif ($isBody === false && trim($data) === '') {
                    $isBody = true;
                    continue;
                }
            } elseif ($isRedirect) {
                fclose($socket);

                $socket = $data = null;

                throw new HttpException("The response should be a redirect \"{$url}\", but did not inform which header \"Localtion:\"");
            } elseif ($contentType === null) {
                fclose($socket);

                $socket = $data = null;

                throw new HttpException("Not set the mimetype from \"{$url}\"");
            } else {
                fwrite($this->temp, $data);

                continue;
            }
        }
    }

    private static function getAbsoluteURL($url, $location)
    {
    }
}
