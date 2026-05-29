<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Uuid;
use PDO;

class RefreshTokenModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    public function criar(string $usuarioId, string $tokenHash, string $expiresAt): void
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO refresh_token (id, usuario_id, token_hash, expires_at)
             VALUES (:id, :usuario_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            ':id'          => Uuid::gerar(),
            ':usuario_id'  => $usuarioId,
            ':token_hash'  => $tokenHash,
            ':expires_at'  => $expiresAt,
        ]);
    }

    public function buscarPorHash(string $tokenHash): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, usuario_id, token_hash, expires_at
             FROM refresh_token
             WHERE token_hash = :token_hash AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        return $stmt->fetch() ?: null;
    }

    public function revogar(string $tokenHash): void
    {
        $stmt = $this->conexao->prepare(
            'DELETE FROM refresh_token WHERE token_hash = :token_hash'
        );
        $stmt->execute([':token_hash' => $tokenHash]);
    }

    public function revogarTodosPorUsuario(string $usuarioId): void
    {
        $stmt = $this->conexao->prepare(
            'DELETE FROM refresh_token WHERE usuario_id = :usuario_id'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);
    }
}
