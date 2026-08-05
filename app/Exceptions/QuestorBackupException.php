<?php

namespace App\Exceptions;

class QuestorBackupException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Não foi possível gerar o backup da exportação Questor.');
    }
}
