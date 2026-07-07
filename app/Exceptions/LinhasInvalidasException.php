<?php

namespace App\Exceptions;

/**
 * Uma ou mais linhas da importação em lote não puderam ser interpretadas.
 */
class LinhasInvalidasException extends DomainException
{
    /**
     * @param string[] $invalidas Trechos das linhas inválidas, para exibição ao usuário.
     */
    public function __construct(public readonly array $invalidas)
    {
        parent::__construct(count($invalidas) . ' linha(s) inválida(s) na importação');
    }
}
