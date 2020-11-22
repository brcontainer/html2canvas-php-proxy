<?php
namespace Inphinit\CrossDomainProxy\Service;

use Inphinit\CrossDomainProxy\Exception\ConnectionException;
use Inphinit\CrossDomainProxy\Exception\HttpException;
use Inphinit\CrossDomainProxy\Exception\TimeoutException;

use Inphinit\CrossDomainProxy\Proxy;

class CurlService
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
        $this->download($this->proxy->getUrl());
    }

    private function download($url)
    {
        $uri = Proxy::parseUri($url);

        ftruncate($this->temp, 0);
        rewind($this->temp);

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_FILE, $this->temp);

        if ($uri->scheme === 'https') {
            if ($this->proxy->nonSecureAllowed()) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_CAINFO, $this->proxy->getCertificateAuthority());
            }
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, $this->proxy->getTimeout());
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->proxy->getMaxRedirs());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        if (isset($uri->authorization)) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $uri->authorization);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->proxy->getHeaders());

        curl_exec($ch);

        $curl_err = curl_errno($ch);

        if ($curl_err !== 0) {
            throw new ConnectionException(curl_error($ch) || 'Unknown connection error', $curl_err);
        } else {
            $status = (string) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (strpos($status, '2') !== 0) {
                throw new HttpException("HTTP response returned {$status}", $status);
            }

            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            if (!$contentType) {
                throw new HttpException("Content-Type not informed by {$url}", 500);
            }
        }

        curl_close($ch);
    }
}
