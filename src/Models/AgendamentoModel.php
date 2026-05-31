<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class AgendamentoModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Insere um novo agendamento e retorna o ID.
     *
     * @param array{id: string, contrato_id: string, tipo: string, data_agendada: string, observacao: string|null} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO agendamento_vistoria (id, contrato_id, tipo, data_agendada, observacao)
             VALUES (:id, :contrato_id, :tipo, :data_agendada, :observacao)'
        );
        $stmt->execute([
            ':id'            => $dados['id'],
            ':contrato_id'   => $dados['contrato_id'],
            ':tipo'          => $dados['tipo'],
            ':data_agendada' => $dados['data_agendada'],
            ':observacao'    => $dados['observacao'] ?? null,
        ]);
        return $dados['id'];
    }

    /**
     * Busca um agendamento pelo ID.
     *
     * @return array{id: string, contrato_id: string, tipo: string, data_agendada: string, observacao: string|null, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, contrato_id, tipo, data_agendada, observacao, created_at
             FROM agendamento_vistoria WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lista todos os agendamentos de um contrato, ordenados por data_agendada ASC.
     *
     * @return list<array{id: string, contrato_id: string, tipo: string, data_agendada: string, observacao: string|null, created_at: string}>
     */
    public function listarPorContrato(string $contratoId): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, contrato_id, tipo, data_agendada, observacao, created_at
             FROM agendamento_vistoria
             WHERE contrato_id = :contrato_id
             ORDER BY data_agendada ASC'
        );
        $stmt->execute([':contrato_id' => $contratoId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Atualiza os campos de um agendamento.
     *
     * @param array{tipo?: string, data_agendada?: string, observacao?: string|null} $dados
     */
    public function atualizar(string $id, array $dados): void
    {
        if (empty($dados)) {
            return;
        }

        $atribuicoes = implode(', ', array_map(
            fn(string $campo) => "{$campo} = :{$campo}",
            array_keys($dados)
        ));

        $stmt = $this->conexao->prepare(
            "UPDATE agendamento_vistoria SET {$atribuicoes} WHERE id = :id"
        );
        $stmt->execute([...$dados, 'id' => $id]);
    }

    /**
     * Remove um agendamento.
     */
    public function excluir(string $id): void
    {
        $stmt = $this->conexao->prepare(
            'DELETE FROM agendamento_vistoria WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * Conta agendamentos com data futura (a partir de agora).
     * Usado pelo dashboard.
     */
    public function contarFuturos(): int
    {
        $stmt = $this->conexao->prepare(
            'SELECT COUNT(*) FROM agendamento_vistoria WHERE data_agendada >= NOW()'
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
