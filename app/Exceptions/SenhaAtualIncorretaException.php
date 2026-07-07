<?php

namespace App\Exceptions;

/**
 * Senha atual informada não confere com a senha cadastrada (troca de senha pelo próprio usuário).
 */
class SenhaAtualIncorretaException extends DomainException
{
}
