<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class AtualizacaoProblemaModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Insere uma nova atualização de problema e retorna o ID.
     *
     * @param array{id: string, problema_id: string, autor_id: string, descricao: string, foto_url: string|null} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO atualizacao_problema (id, problema_id, autor_id, descricao, foto_url)
             VALUES (:id, :problema_id, :autor_id, :descricao, :foto_url)'
        );
        $stmt->execute([
            ':id'          => $dados['id'],
            ':problema_id' => $dados['problema_id'],
            ':autor_id'    => $dados['autor_id'],
            ':descricao'   => $dados['descricao'],
            ':foto_url'    => $dados['foto_url'] ?? null,
        ]);
        return $dados['id'];
    }

    /**
     * Busca uma atualização pelo ID.
     *
     * @return array{id: string, problema_id: string, autor_id: string, descricao: string, foto_url: string|null, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, problema_id, autor_id, descricao, foto_url, created_at
             FROM atualizacao_problema WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lista todas as atualizações de um problema em ordem cronológica ascendente.
     *
     * @return list<array{id: string, problema_id: string, autor_id: string, descricao: string, foto_url: string|null, created_at: string}>
     */
    public function listarPorProblema(string $problemaId): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, problema_id, autor_id, descricao, foto_url, created_at
             FROM atualizacao_problema
             WHERE problema_id = :problema_id
             ORDER BY created_at ASC'
        );
        $stmt->execute([':problema_id' => $problemaId]);
        return $stmt->fetchAll() ?: [];
    }
}
