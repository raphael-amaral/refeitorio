<?php

namespace App\Exceptions;

/**
 * O agendamento do usuário para hoje está cancelado (fluxo do scanner).
 */
class AgendamentoCanceladoException extends DomainException
{
    public function __construct(public readonly int $idCardapio)
    {
        parent::__construct("Agendamento cancelado para o cardápio: {$idCardapio}");
    }
}
