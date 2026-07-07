<?php

namespace App\Exceptions;

/**
 * Usuário não é do tipo 'comum' e por isso não pode agendar refeições.
 */
class TipoNaoPermitidoException extends DomainException
{
    public function __construct(public readonly string $tipoUsuario)
    {
        parent::__construct("Tipo de usuário não permitido para agendar: {$tipoUsuario}");
    }
}
