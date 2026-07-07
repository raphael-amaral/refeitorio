<?php

namespace App\Services;

use App\Core\Audit;
use App\Exceptions\DataOuTipoInvalidoException;
use App\Exceptions\DescricaoVaziaException;
use App\Models\Cardapio;
use DateTime;

class CardapioService
{
    /**
     * Salva (cria ou atualiza) o cardápio de um dia/tipo (SND).
     * Só é permitido editar a semana atual (segunda a domingo).
     */
    public static function salvarDia(string $data, string $tipo, string $descricao): void
    {
        if (!in_array($tipo, ['almoco', 'jantar'], true) || !self::dataNaSemanaAtual($data)) {
            Audit::write('cardapio.salvar_dia', 'blocked', $data . ':' . $tipo, ['motivo' => 'fora_intervalo_ou_tipo']);
            throw new DataOuTipoInvalidoException();
        }

        $descricaoNormalizada = mb_strtoupper(trim($descricao));

        if ($descricaoNormalizada === '') {
            Audit::write('cardapio.salvar_dia', 'blocked', $data . ':' . $tipo, ['motivo' => 'descricao_vazia']);
            throw new DescricaoVaziaException();
        }

        Cardapio::salvarDia($data, $tipo, $descricaoNormalizada);
        Audit::write('cardapio.salvar_dia', 'success', $data . ':' . $tipo, ['descricao' => $descricaoNormalizada]);
    }

    /**
     * Verifica se $data (Y-m-d) está no intervalo editável: segunda-feira até o domingo da semana atual.
     */
    private static function dataNaSemanaAtual(string $data): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return false;
        }

        $hoje    = (new DateTime())->setTime(0, 0, 0);
        $diaNum  = (int) $hoje->format('N'); // 1=Seg … 7=Dom
        $segunda = (clone $hoje)->modify('-' . ($diaNum - 1) . ' days')->setTime(0, 0, 0);
        $domingo = (clone $hoje)->modify('+' . (7 - $diaNum) . ' days')->setTime(23, 59, 59);

        $alvo = DateTime::createFromFormat('Y-m-d', $data);
        if (!$alvo) {
            return false;
        }

        return $alvo >= $segunda && $alvo <= $domingo;
    }
}
