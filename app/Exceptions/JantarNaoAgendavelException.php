<?php

namespace App\Exceptions;

/**
 * Cardápio do tipo 'jantar' não é agendável.
 */
class JantarNaoAgendavelException extends DomainException
{
    public function __construct(public readonly string $dataCardapio)
    {
        parent::__construct("Jantar não é agendável (data: {$dataCardapio})");
    }
}
