<?php

namespace App\Exceptions;

/**
 * Não há cardápio de almoço cadastrado para hoje (fluxo de exceção do scanner).
 */
class SemCardapioHojeException extends DomainException
{
}
