<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ChecklistItemModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Insere um novo item de checklist e retorna o ID.
     *
     * @param array{id: string, checklist_id: string, comodo_id: string, item_vistoria_id: string, estado: string, observacao: string|null} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO checklist_item (id, checklist_id, comodo_id, item_vistoria_id, estado, observacao)
             VALUES (:id, :checklist_id, :comodo_id, :item_vistoria_id, :estado, :observacao)'
        );
        $stmt->execute([
            ':id'               => $dados['id'],
            ':checklist_id'     => $dados['checklist_id'],
            ':comodo_id'        => $dados['comodo_id'],
            ':item_vistoria_id' => $dados['item_vistoria_id'],
            ':estado'           => $dados['estado'],
            ':observacao'       => $dados['observacao'] ?? null,
        ]);
        return $dados['id'];
    }

    /**
     * Busca um item de checklist pelo ID.
     *
     * @return array{id: string, checklist_id: string, comodo_id: string, item_vistoria_id: string, estado: string, observacao: string|null, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, checklist_id, comodo_id, item_vistoria_id, estado, observacao, created_at
             FROM checklist_item WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lista todos os itens de um checklist, incluindo as fotos de cada item.
     *
     * @return list<array{id: string, checklist_id: string, comodo_id: string, item_vistoria_id: string, estado: string, observacao: string|null, created_at: string, fotos: list<array{id: string, url: string, created_at: string}>}>
     */
    public function listarPorChecklist(string $checklistId): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT ci.id, ci.checklist_id, ci.comodo_id, ci.item_vistoria_id,
                    ci.estado, ci.observacao, ci.created_at
             FROM checklist_item ci
             WHERE ci.checklist_id = :checklist_id
             ORDER BY ci.created_at ASC'
        );
        $stmt->execute([':checklist_id' => $checklistId]);
        $itens = $stmt->fetchAll() ?: [];

        if (empty($itens)) {
            return [];
        }

        $ids = array_map(fn($item) => $item['id'], $itens);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmtFotos = $this->conexao->prepare(
            "SELECT id, checklist_item_id, url, created_at
             FROM foto_checklist
             WHERE checklist_item_id IN ({$placeholders})
             ORDER BY created_at ASC"
        );
        $stmtFotos->execute($ids);
        $todasFotos = $stmtFotos->fetchAll() ?: [];

        $fotosPorItem = [];
        foreach ($todasFotos as $foto) {
            $fotosPorItem[$foto['checklist_item_id']][] = [
                'id'         => $foto['id'],
                'url'        => $foto['url'],
                'created_at' => $foto['created_at'],
            ];
        }

        foreach ($itens as &$item) {
            $item['fotos'] = $fotosPorItem[$item['id']] ?? [];
        }

        return $itens;
    }

    /**
     * Atualiza estado e observação de um item.
     *
     * @param array{estado: string, observacao?: string|null} $dados
     */
    public function atualizar(string $id, array $dados): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE checklist_item SET estado = :estado, observacao = :observacao WHERE id = :id'
        );
        $stmt->execute([
            ':estado'     => $dados['estado'],
            ':observacao' => $dados['observacao'] ?? null,
            ':id'         => $id,
        ]);
    }

    /**
     * Conta itens de um checklist.
     */
    public function contarPorChecklist(string $checklistId): int
    {
        $stmt = $this->conexao->prepare(
            'SELECT COUNT(*) FROM checklist_item WHERE checklist_id = :checklist_id'
        );
        $stmt->execute([':checklist_id' => $checklistId]);
        return (int) $stmt->fetchColumn();
    }
}
