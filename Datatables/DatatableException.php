<?php

namespace Hype\DynamicDatatablesBundle\Datatables;


class DatatableException extends \Exception
{
    public function __construct($message, $code = 0, $previous = null)
    {
        parent::__construct($message, $code);
    }
}
