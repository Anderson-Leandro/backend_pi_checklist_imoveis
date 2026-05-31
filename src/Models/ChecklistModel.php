<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ChecklistModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Insere um novo checklist e retorna o ID gerado.
     *
     * @param array{id: string, contrato_id: string, vistoriador_id: string, tipo: string} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO checklist (id, contrato_id, vistoriador_id, tipo)
             VALUES (:id, :contrato_id, :vistoriador_id, :tipo)'
        );
        $stmt->execute([
            ':id'             => $dados['id'],
            ':contrato_id'    => $dados['contrato_id'],
            ':vistoriador_id' => $dados['vistoriador_id'],
            ':tipo'           => $dados['tipo'],
        ]);
        return $dados['id'];
    }

    /**
     * Busca um checklist pelo ID.
     *
     * @return array{id: string, contrato_id: string, vistoriador_id: string, tipo: string, status: string, data_vistoria: string|null, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, contrato_id, vistoriador_id, tipo, status, data_vistoria, created_at
             FROM checklist WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lista todos os checklists de um contrato.
     *
     * @return list<array{id: string, contrato_id: string, vistoriador_id: string, tipo: string, status: string, data_vistoria: string|null, created_at: string}>
     */
    public function listarPorContrato(string $contratoId): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, contrato_id, vistoriador_id, tipo, status, data_vistoria, created_at
             FROM checklist WHERE contrato_id = :contrato_id ORDER BY created_at DESC'
        );
        $stmt->execute([':contrato_id' => $contratoId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Atualiza o status de um checklist.
     */
    public function atualizarStatus(string $id, string $status): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE checklist SET status = :status WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * Atualiza a data de vistoria.
     */
    public function atualizarDataVistoria(string $id, string $dataVistoria): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE checklist SET data_vistoria = :data_vistoria WHERE id = :id'
        );
        $stmt->execute([':data_vistoria' => $dataVistoria, ':id' => $id]);
    }

    /**
     * Conta checklists pendentes (em preenchimento, aguardando aceite ou em revisão).
     * Usado pelo dashboard.
     */
    public function contarPendentes(): int
    {
        $stmt = $this->conexao->prepare(
            "SELECT COUNT(*) FROM checklist
             WHERE status IN ('em_preenchimento', 'pendente_aceite', 'pendente_revisao')"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca o checklist mais recente de um contrato.
     *
     * @return array{id: string, contrato_id: string, vistoriador_id: string, tipo: string, status: string, data_vistoria: string|null, created_at: string}|null
     */
    public function buscarUltimoPorContrato(string $contratoId): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, contrato_id, vistoriador_id, tipo, status, data_vistoria, created_at
             FROM checklist WHERE contrato_id = :contrato_id ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([':contrato_id' => $contratoId]);
        return $stmt->fetch() ?: null;
    }
}
