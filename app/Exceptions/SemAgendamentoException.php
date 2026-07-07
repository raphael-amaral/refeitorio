<?php

namespace App\Exceptions;

/**
 * Nenhum agendamento encontrado para o usuário no dia de hoje (fluxo do scanner).
 */
class SemAgendamentoException extends DomainException
{
}
