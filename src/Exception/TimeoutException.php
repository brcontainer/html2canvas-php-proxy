<?php
namespace Inphinit\CrossDomainProxy\Exception;

class TimeoutException extends CoreException
{
    public function __construct($timeouted)
    {
        parent::__construct('Maximum execution time of ' . $timeouted . ' seconds exceeded, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled)');
    }
}
