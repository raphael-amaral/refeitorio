<?php

namespace App\Core;

use DateTime;

class Agendamento
{
    /**
     * Retorna true se o prazo de agendamento/cancelamento já encerrou para a data informada.
     * O prazo é até LIMIT_TIME no próprio dia; dias passados são sempre fora do prazo.
     */
    public static function foraPrazo(string $data): bool
    {
        $limitTime   = $_ENV['LIMIT_TIME'] ?? '10:00';
        [$h, $m]     = explode(':', $limitTime);
        $agora       = new DateTime();
        $diaCardapio = new DateTime($data);
        $hoje        = $agora->format('Y-m-d');
        $fimPrazo    = (clone $diaCardapio)->setTime((int) $h, (int) $m);

        return $diaCardapio->format('Y-m-d') < $hoje ||
            $diaCardapio->format('Y-m-d') === $hoje && $agora >= $fimPrazo;
    }

    /**
     * Retorna true se a data cair em sábado (N=6) ou domingo (N=7).
     */
    public static function fimDeSemana(string $data): bool
    {
        $dt = new DateTime($data);
        return (int) $dt->format('N') >= 6;
    }
}
