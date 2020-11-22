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
$proxy->output();
