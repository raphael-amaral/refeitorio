<?php

namespace App\Exceptions;

/**
 * Agendamento para sábado/domingo bloqueado (AGENDAMENTO_SABADO_DOMINGO=false).
 */
class FimDeSemanaException extends DomainException
{
    public function __construct(public readonly string $dataCardapio)
    {
        parent::__construct("Agendamento não permitido para fim de semana (data: {$dataCardapio})");
    }
}
