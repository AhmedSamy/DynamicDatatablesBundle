<?php

namespace Hype\DyanmicDatatablesBundle\Datatables;


class DatatableExcpetion extends \Exception
{
    public function __construct($message, $code = 0, $previous = null)
    {
        parent::__construct($message, $code);
    }
} 