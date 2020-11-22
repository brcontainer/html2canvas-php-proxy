<?php
namespace Inphinit\CrossDomainProxy\Exception;

abstract class BaseException extends \Exception
{
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
