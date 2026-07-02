<?php

namespace App\Services;

use App\Core\Agendamento;
use App\Core\Audit;
use App\Exceptions\AgendamentoDesabilitadoException;
use App\Exceptions\FimDeSemanaException;
use App\Exceptions\ForaDoPrazoException;
use App\Exceptions\JaAgendadoOuInvalidoException;
use App\Exceptions\JantarNaoAgendavelException;
use App\Exceptions\TipoNaoPermitidoException;
use App\Models\Cardapio;
use App\Models\Registro;
use App\Models\Usuario;

class RegistroService
{
    /**
     * Cria um novo agendamento para o usuário informado.
     *
     * @param array{matricula: string, nome: string, tipo: string} $usuario
     */
    public static function registrar(array $usuario, int $idCardapio): void
    {
        if (!filter_var($_ENV['AGENDAMENTO_HABILITADO'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
            Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'agendamento_desabilitado', 'matricula_usuario' => $usuario['matricula']]);
            throw new AgendamentoDesabilitadoException();
        }

        // Apenas usuários comuns podem agendar
        if ($usuario['tipo'] !== 'comum') {
            Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'tipo_nao_permitido', 'matricula_usuario' => $usuario['matricula']]);
            throw new TipoNaoPermitidoException($usuario['tipo']);
        }

        $cardapio = null;

        // Jantar não é agendável
        if ($idCardapio > 0) {
            $cardapio = Cardapio::buscarPorId($idCardapio);
            if ($cardapio && $cardapio['tipo'] === 'jantar') {
                Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'jantar_nao_agendavel', 'matricula_usuario' => $usuario['matricula'], 'data_cardapio' => $cardapio['data']]);
                throw new JantarNaoAgendavelException($cardapio['data']);
            }
        }

        if ($idCardapio > 0 && $cardapio) {
            $podeAgendarFinalSemana = filter_var($_ENV['AGENDAMENTO_SABADO_DOMINGO'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

            if (!$podeAgendarFinalSemana && Agendamento::fimDeSemana($cardapio['data'])) {
                Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'fim_de_semana', 'matricula_usuario' => $usuario['matricula'], 'data_cardapio' => $cardapio['data']]);
                throw new FimDeSemanaException($cardapio['data']);
            }

            if (Agendamento::foraPrazo($cardapio['data'])) {
                Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'fora_prazo', 'matricula_usuario' => $usuario['matricula'], 'data_cardapio' => $cardapio['data']]);
                throw new ForaDoPrazoException($cardapio['data']);
            }
        }

        if ($idCardapio > 0 && !Registro::jaAgendado($usuario['matricula'], $idCardapio)) {
            Registro::upsertAgendado($usuario['matricula'], $idCardapio);
            Audit::write('registro.criar', 'success', (string) $idCardapio, [
                'matricula_usuario' => $usuario['matricula'],
                'nome_usuario'      => $usuario['nome'],
                'data_cardapio'     => $cardapio['data'] ?? null,
                'tipo_refeicao'     => $cardapio['tipo'] ?? null,
            ]);
            return;
        }

        Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'ja_agendado_ou_invalido', 'matricula_usuario' => $usuario['matricula']]);
        throw new JaAgendadoOuInvalidoException($idCardapio);
    }

    /**
     * Cancela um agendamento do próprio usuário.
     *
     * @param array{matricula: string, nome: string, tipo: string} $usuario
     */
    public static function cancelar(array $usuario, int $id): void
    {
        if (!filter_var($_ENV['AGENDAMENTO_HABILITADO'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
            Audit::write('registro.cancelar', 'blocked', (string) $id, ['motivo' => 'agendamento_desabilitado']);
            throw new AgendamentoDesabilitadoException();
        }

        $registro = Registro::buscarPorId($id);

        if ($registro) {
            $cardapio = Cardapio::buscarPorId($registro['id_cardapio']);

            if (Agendamento::foraPrazo($cardapio['data'])) {
                // Mantém o comportamento original: este caso não é auditado.
                throw new ForaDoPrazoException($cardapio['data']);
            }
        }

        $cancelado = Registro::cancelarDoUsuario($id, $usuario['matricula']);
        Audit::write('registro.cancelar', $cancelado ? 'success' : 'blocked', (string) $id, [
            'matricula_usuario' => $usuario['matricula'],
            'nome_usuario'      => $usuario['nome'],
            'id_cardapio'       => $registro['id_cardapio'] ?? null,
            'data_cardapio'     => $cardapio['data'] ?? null,
            'tipo_refeicao'     => $cardapio['tipo'] ?? null,
        ]);
    }

    /**
     * Marca um agendamento como concluído (ação da SND/admin/dev).
     */
    public static function concluir(int $id): void
    {
        $registro = Registro::buscarPorId($id);
        $cardapio = $registro ? Cardapio::buscarPorId($registro['id_cardapio']) : null;

        $nomeUsuario = $registro ? (Usuario::buscarPorMatricula($registro['matricula_usuario'])['nome'] ?? null) : null;
        $ok = Registro::concluir($id);
        Audit::write('registro.concluir', $ok ? 'success' : 'error', (string) $id, [
            'matricula_usuario' => $registro['matricula_usuario'] ?? null,
            'nome_usuario'      => $nomeUsuario,
            'id_cardapio'       => $registro['id_cardapio'] ?? null,
            'data_cardapio'     => $cardapio['data'] ?? null,
            'tipo_refeicao'     => $cardapio['tipo'] ?? null,
        ]);
    }
}
