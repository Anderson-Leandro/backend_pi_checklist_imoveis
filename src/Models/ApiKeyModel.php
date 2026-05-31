<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ApiKeyModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Insere uma nova API key e retorna o ID.
     *
     * @param array{id: string, nome: string, key_hash: string} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO api_key (id, nome, key_hash)
             VALUES (:id, :nome, :key_hash)'
        );
        $stmt->execute([
            ':id'       => $dados['id'],
            ':nome'     => $dados['nome'],
            ':key_hash' => $dados['key_hash'],
        ]);
        return $dados['id'];
    }

    /**
     * Busca uma API key pelo ID.
     *
     * @return array{id: string, nome: string, key_hash: string, ativo: int, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, key_hash, ativo, created_at
             FROM api_key WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Busca uma API key pelo hash da chave (para validação em requests).
     *
     * @return array{id: string, nome: string, key_hash: string, ativo: int, created_at: string}|null
     */
    public function buscarPorHash(string $keyHash): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, key_hash, ativo, created_at
             FROM api_key WHERE key_hash = :key_hash LIMIT 1'
        );
        $stmt->execute([':key_hash' => $keyHash]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lista todas as API keys sem expor o key_hash.
     *
     * @return list<array{id: string, nome: string, ativo: int, created_at: string}>
     */
    public function listar(): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, ativo, created_at
             FROM api_key ORDER BY created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Desativa uma API key (revogação).
     */
    public function desativar(string $id): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE api_key SET ativo = 0 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }
}
