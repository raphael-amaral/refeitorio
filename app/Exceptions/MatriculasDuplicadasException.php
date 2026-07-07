<?php

namespace App\Exceptions;

/**
 * Uma ou mais matrículas da importação em lote já existem no banco.
 */
class MatriculasDuplicadasException extends DomainException
{
    /**
     * @param string[] $duplicadas Matrículas que já existem no banco.
     */
    public function __construct(public readonly array $duplicadas)
    {
        parent::__construct(count($duplicadas) . ' matrícula(s) duplicada(s) na importação');
    }
}
