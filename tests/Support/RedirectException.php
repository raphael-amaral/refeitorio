<?php

namespace Tests\Support;

/**
 * Lançada pelo override de header() nos namespaces de produção durante os testes,
 * permitindo capturar redirects sem que o exit subsequente chegue a executar.
 */
class RedirectException extends \RuntimeException
{
    public function __construct(string $location)
    {
        parent::__construct($location);
    }

    public function getLocation(): string
    {
        return $this->getMessage();
    }
}
