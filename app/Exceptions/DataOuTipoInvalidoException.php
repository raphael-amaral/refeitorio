<?php

namespace App\Exceptions;

/**
 * Tipo de refeição inválido, ou data fora da semana atual (única janela editável pela SND).
 */
class DataOuTipoInvalidoException extends DomainException
{
}
