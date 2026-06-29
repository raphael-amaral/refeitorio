<?php

/**
 * Sobrescreve header() nos namespaces de produção durante os testes.
 *
 * PHP resolve funções buscando primeiro no namespace atual — ao definir
 * header() em App\Middleware e App\Core, interceptamos redirects sem alterar
 * o código de produção. A exceção propaga antes do exit(), que nunca executa.
 */

namespace App\Middleware {
    function header(string $string, bool $replace = true, int $response_code = 0): void
    {
        if (str_starts_with($string, 'Location: ')) {
            throw new \Tests\Support\RedirectException(substr($string, 10));
        }
        // outros headers (Content-Type, etc.) são silenciosamente ignorados
    }

    /**
     * Lança RedirectException com código HTTP para interceptar respostas de erro
     * (ex: 401 em apiSnd) antes que o exit() subsequente encerre o processo.
     */
    function http_response_code(int $code = 200): int
    {
        if ($code >= 400) {
            throw new \Tests\Support\RedirectException((string) $code);
        }
        $GLOBALS['_test_http_status'] = $code;
        return $code;
    }
}

namespace App\Core {
    function header(string $string, bool $replace = true, int $response_code = 0): void
    {
        if (str_starts_with($string, 'Location: ')) {
            throw new \Tests\Support\RedirectException(substr($string, 10));
        }
        $GLOBALS['_test_headers'][] = $string;
    }

    function http_response_code(int $code = 200): int
    {
        $GLOBALS['_test_http_status'] = $code;
        return $code;
    }
}
