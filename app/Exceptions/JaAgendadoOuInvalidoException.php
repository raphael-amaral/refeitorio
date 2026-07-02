<?php

namespace App\Exceptions;

/**
 * O cardápio informado é inválido (id <= 0) ou o usuário já tem agendamento ativo para ele.
 */
class JaAgendadoOuInvalidoException extends DomainException
{
    public function __construct(public readonly int $idCardapio)
    {
        parent::__construct("Cardápio inválido ou já agendado: {$idCardapio}");
    }
}
