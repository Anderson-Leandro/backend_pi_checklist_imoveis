<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ItemVistoriaModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Lista todos os itens de vistoria padrão.
     *
     * @return list<array{id: string, nome: string, categoria: string}>
     */
    public function listar(): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, categoria FROM item_vistoria ORDER BY categoria, nome'
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Busca um item de vistoria pelo ID.
     *
     * @return array{id: string, nome: string, categoria: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, categoria FROM item_vistoria WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
