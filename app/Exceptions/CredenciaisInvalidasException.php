<?php

namespace App\Exceptions;

/**
 * Matrícula/senha inválidas, ou o usuário não tem o tipo exigido para a operação
 * (ex: login do scanner exige tipo snd/dev). Não distingue os dois casos de propósito,
 * para não vazar informação sobre quais contas existem.
 */
class CredenciaisInvalidasException extends DomainException
{
}
