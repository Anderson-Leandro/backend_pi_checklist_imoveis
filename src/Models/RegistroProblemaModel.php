<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class RegistroProblemaModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Insere um novo registro de problema e retorna o ID.
     *
     * @param array{id: string, contrato_id: string, comodo_id: string, titulo: string, descricao: string, foto_url: string|null} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO registro_problema (id, contrato_id, comodo_id, titulo, descricao, foto_url)
             VALUES (:id, :contrato_id, :comodo_id, :titulo, :descricao, :foto_url)'
        );
        $stmt->execute([
            ':id'          => $dados['id'],
            ':contrato_id' => $dados['contrato_id'],
            ':comodo_id'   => $dados['comodo_id'],
            ':titulo'      => $dados['titulo'],
            ':descricao'   => $dados['descricao'],
            ':foto_url'    => $dados['foto_url'] ?? null,
        ]);
        return $dados['id'];
    }

    /**
     * Busca um problema pelo ID.
     *
     * @return array{id: string, contrato_id: string, comodo_id: string, titulo: string, descricao: string, foto_url: string|null, status: string, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, contrato_id, comodo_id, titulo, descricao, foto_url, status, created_at
             FROM registro_problema WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lista todos os problemas de um contrato, do mais recente ao mais antigo.
     *
     * @return list<array{id: string, contrato_id: string, comodo_id: string, titulo: string, descricao: string, foto_url: string|null, status: string, created_at: string}>
     */
    public function listarPorContrato(string $contratoId): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, contrato_id, comodo_id, titulo, descricao, foto_url, status, created_at
             FROM registro_problema
             WHERE contrato_id = :contrato_id
             ORDER BY created_at DESC'
        );
        $stmt->execute([':contrato_id' => $contratoId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Atualiza o status de um problema.
     */
    public function atualizarStatus(string $id, string $status): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE registro_problema SET status = :status WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':id' => $id]);
    }
}
