<?php

/**
 * Configurações gerais da aplicação.
 * A conexão com o banco é gerenciada por App\Core\Database.
 */
return [
    'name'  => 'Sistema de Refeitório',
    'debug' => ($_ENV['APP_ENV'] ?? 'production') === 'development',
];
