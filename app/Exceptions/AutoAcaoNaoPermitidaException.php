<?php

namespace App\Exceptions;

/**
 * Usuário tentou inativar/excluir a própria conta.
 */
class AutoAcaoNaoPermitidaException extends DomainException
{
}
