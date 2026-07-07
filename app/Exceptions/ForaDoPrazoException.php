<?php

namespace App\Exceptions;

/**
 * Ação (agendar ou cancelar) fora do prazo permitido (LIMIT_TIME) para a data do cardápio.
 */
class ForaDoPrazoException extends DomainException
{
    public function __construct(public readonly string $dataCardapio)
    {
        parent::__construct("Fora do prazo para a data: {$dataCardapio}");
    }
}
