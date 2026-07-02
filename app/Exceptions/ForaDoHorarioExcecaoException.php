<?php

namespace App\Exceptions;

/**
 * Registro de exceção (crachá esquecido) fora do horário permitido (EXCECAO_LIMIT_TIME).
 */
class ForaDoHorarioExcecaoException extends DomainException
{
    public function __construct(public readonly string $limiteHora)
    {
        parent::__construct("Exceções encerradas após {$limiteHora}");
    }
}
