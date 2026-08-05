<?php

namespace App\Exceptions;

class PeriodoInvalidoException extends DomainException
{
    public function __construct()
    {
        parent::__construct('A data inicial não pode ser posterior à data final.');
    }
}
