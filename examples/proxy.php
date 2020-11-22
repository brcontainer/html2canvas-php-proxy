<?php

use Inphinit\CrossDomainProxy\Proxy;

//Usage without autoload
// Proxy
require '../src/Proxy.php';

// Service used by proxy
require '../src/Service/CurlService.php';
require '../src/Service/NativeService.php';

// Exceptions
require '../src/Exception/CoreException.php';
require '../src/Exception/ConnectionException.php';
require '../src/Exception/HttpException.php';
require '../src/Exception/TimeoutException.php';

$proxy = new Proxy('https://www.google.com.br');
$proxy->jsonp('teste');

register_shutdown_function(function () {
    echo "\n", round(memory_get_peak_usage() / 1024 / 1024, 2), 'MB de memoria ram/virtual';
});
