<?php
namespace Inphinit\CrossDomainProxy\Exception;

class CoreException extends \Exception
{
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
