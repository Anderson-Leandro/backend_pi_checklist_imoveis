<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NaoEncontradoException;
use App\Exceptions\RegraDeNegocioException;
use App\Exceptions\ValidacaoException;
use App\Helpers\Uuid;
use App\Helpers\Validator;
use App\Models\AgendamentoModel;
use App\Models\ContratoModel;

class AgendamentoService
{
    public function __construct(
        private readonly AgendamentoModel $agendamentoModel,
        private readonly ContratoModel    $contratoModel,
        private readonly LogService       $logService
    ) {}

    /**
     * Cria um novo agendamento de vistoria para um contrato (admin).
     *
     * @param  array{tipo: string, data_agendada: string, observacao?: string|null} $dados
     * @return array{id: string, contrato_id: string, tipo: string, data_agendada: string, observacao: string|null, created_at: string}
     * @throws ValidacaoException      Se os dados forem inválidos ou data estiver no passado
     * @throws NaoEncontradoException  Se o contrato não existir
     * @throws RegraDeNegocioException Se o contrato não estiver ativo
     */
    public function criar(string $contratoId, array $dados): array
    {
        $erros = Validator::validar($dados, [
            'tipo'          => 'obrigatorio|enum:inicial,encerramento',
            'data_agendada' => 'obrigatorio|datetime',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        if (strtotime($dados['data_agendada']) <= time()) {
            throw new ValidacaoException(['data_agendada' => 'A data agendada deve ser uma data futura']);
        }

        $contrato = $this->contratoModel->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        if ($contrato['status'] !== 'ativo') {
            throw new RegraDeNegocioException('Agendamentos só podem ser criados em contratos ativos');
        }

        $novoId = Uuid::gerar();

        $this->agendamentoModel->inserir([
            'id'            => $novoId,
            'contrato_id'   => $contratoId,
            'tipo'          => $dados['tipo'],
            'data_agendada' => $dados['data_agendada'],
            'observacao'    => $dados['observacao'] ?? null,
        ]);

        $agendamento = $this->agendamentoModel->buscarPorId($novoId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'agendamento_vistoria',
            entidadeId: $novoId,
            payload:    [
                'contrato_id'   => $contratoId,
                'tipo'          => $dados['tipo'],
                'data_agendada' => $dados['data_agendada'],
            ],
        );

        return $agendamento;
    }

    /**
     * Lista os agendamentos de um contrato (admin e vistoriador).
     *
     * @return list<array{id: string, contrato_id: string, tipo: string, data_agendada: string, observacao: string|null, created_at: string}>
     * @throws NaoEncontradoException Se o contrato não existir
     */
    public function listarPorContrato(string $contratoId): array
    {
        $contrato = $this->contratoModel->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        return $this->agendamentoModel->listarPorContrato($contratoId);
    }

    /**
     * Atualiza um agendamento (admin).
     *
     * @param  array{tipo?: string, data_agendada?: string, observacao?: string|null} $dados
     * @return array{id: string, contrato_id: string, tipo: string, data_agendada: string, observacao: string|null, created_at: string}
     * @throws ValidacaoException     Se os dados forem inválidos ou data estiver no passado
     * @throws NaoEncontradoException Se o agendamento não existir
     */
    public function atualizar(string $id, array $dados): array
    {
        $regras = [];
        if (array_key_exists('tipo', $dados)) {
            $regras['tipo'] = 'obrigatorio|enum:inicial,encerramento';
        }
        if (array_key_exists('data_agendada', $dados)) {
            $regras['data_agendada'] = 'obrigatorio|datetime';
        }

        if (!empty($regras)) {
            $erros = Validator::validar($dados, $regras);
            if (!empty($erros)) {
                throw new ValidacaoException($erros);
            }
        }

        if (!empty($dados['data_agendada']) && strtotime($dados['data_agendada']) <= time()) {
            throw new ValidacaoException(['data_agendada' => 'A data agendada deve ser uma data futura']);
        }

        $agendamento = $this->agendamentoModel->buscarPorId($id);
        if ($agendamento === null) {
            throw new NaoEncontradoException('Agendamento');
        }

        $camposAtualizar = array_filter(
            array_intersect_key($dados, array_flip(['tipo', 'data_agendada', 'observacao'])),
            fn($v) => $v !== null || true
        );

        if (array_key_exists('observacao', $dados)) {
            $camposAtualizar['observacao'] = $dados['observacao'];
        }

        if (!empty($camposAtualizar)) {
            $this->agendamentoModel->atualizar($id, $camposAtualizar);
        }

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'agendamento_vistoria',
            entidadeId: $id,
            payload:    $camposAtualizar,
        );

        return $this->agendamentoModel->buscarPorId($id);
    }

    /**
     * Remove um agendamento (admin).
     *
     * @throws NaoEncontradoException Se o agendamento não existir
     */
    public function excluir(string $id): void
    {
        $agendamento = $this->agendamentoModel->buscarPorId($id);
        if ($agendamento === null) {
            throw new NaoEncontradoException('Agendamento');
        }

        $this->agendamentoModel->excluir($id);

        $this->logService->registrar(
            acao:       'DELETE',
            entidade:   'agendamento_vistoria',
            entidadeId: $id,
            payload:    ['contrato_id' => $agendamento['contrato_id'], 'tipo' => $agendamento['tipo']],
        );
    }
}
