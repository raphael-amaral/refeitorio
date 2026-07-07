<?php

namespace App\Exceptions;

/**
 * Base para exceções que representam regras de negócio violadas (não erros de infraestrutura).
 * Permite um catch genérico no Controller quando o caso específico não precisa de tratamento próprio.
 */
abstract class DomainException extends \RuntimeException
{
}
