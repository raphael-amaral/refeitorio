<?php

namespace App\Exceptions;

/**
 * A refeição referente a este cardápio já foi concluída (crachá já passado, ou exceção já registrada).
 */
class JaConcluidoException extends DomainException
{
    public function __construct(public readonly int $idCardapio)
    {
        parent::__construct("Refeição já registrada para o cardápio: {$idCardapio}");
    }
}
