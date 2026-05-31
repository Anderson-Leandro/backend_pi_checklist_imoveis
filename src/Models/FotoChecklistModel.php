<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class FotoChecklistModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Insere um novo registro de foto.
     *
     * @param array{id: string, checklist_item_id: string, url: string} $dados
     */
    public function inserir(array $dados): void
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO foto_checklist (id, checklist_item_id, url)
             VALUES (:id, :checklist_item_id, :url)'
        );
        $stmt->execute([
            ':id'               => $dados['id'],
            ':checklist_item_id' => $dados['checklist_item_id'],
            ':url'              => $dados['url'],
        ]);
    }

    /**
     * Busca uma foto pelo ID.
     *
     * @return array{id: string, checklist_item_id: string, url: string, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, checklist_item_id, url, created_at
             FROM foto_checklist WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Remove uma foto pelo ID.
     */
    public function excluir(string $id): void
    {
        $stmt = $this->conexao->prepare(
            'DELETE FROM foto_checklist WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * Lista todas as fotos de um item de checklist.
     *
     * @return list<array{id: string, checklist_item_id: string, url: string, created_at: string}>
     */
    public function listarPorItem(string $checklistItemId): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, checklist_item_id, url, created_at
             FROM foto_checklist WHERE checklist_item_id = :checklist_item_id
             ORDER BY created_at ASC'
        );
        $stmt->execute([':checklist_item_id' => $checklistItemId]);
        return $stmt->fetchAll() ?: [];
    }
}
