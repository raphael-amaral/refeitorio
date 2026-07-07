<?php

namespace App\Exceptions;

/**
 * Já existe um usuário com essa matrícula (violação da constraint UNIQUE).
 */
class MatriculaDuplicadaException extends DomainException
{
}
