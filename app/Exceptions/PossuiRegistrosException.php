<?php

namespace App\Exceptions;

/**
 * Usuário não pode ser excluído porque possui agendamentos vinculados.
 */
class PossuiRegistrosException extends DomainException
{
}
