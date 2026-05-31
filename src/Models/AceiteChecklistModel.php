<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class AceiteChecklistModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Insere um registro de aceite ou rejeição de checklist.
     *
     * @param array{id: string, checklist_id: string, locatario_id: string, status: string, motivo_rejeicao: string|null} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO aceite_checklist (id, checklist_id, locatario_id, status, motivo_rejeicao)
             VALUES (:id, :checklist_id, :locatario_id, :status, :motivo_rejeicao)'
        );
        $stmt->execute([
            ':id'              => $dados['id'],
            ':checklist_id'    => $dados['checklist_id'],
            ':locatario_id'    => $dados['locatario_id'],
            ':status'          => $dados['status'],
            ':motivo_rejeicao' => $dados['motivo_rejeicao'] ?? null,
        ]);
        return $dados['id'];
    }

    /**
     * Busca o aceite de um checklist pelo ID do checklist.
     *
     * @return array{id: string, checklist_id: string, locatario_id: string, status: string, motivo_rejeicao: string|null, created_at: string}|null
     */
    public function buscarPorChecklistId(string $checklistId): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, checklist_id, locatario_id, status, motivo_rejeicao, created_at
             FROM aceite_checklist WHERE checklist_id = :checklist_id LIMIT 1'
        );
        $stmt->execute([':checklist_id' => $checklistId]);
        return $stmt->fetch() ?: null;
    }
}
