<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class UsuarioModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    public function buscarPorEmailComCredenciais(string $email): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, email, senha_hash, role, mfa_secret, mfa_ativo, created_at
             FROM usuario WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, email, role, mfa_ativo, created_at
             FROM usuario WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function buscarMfaSecretPorId(string $id): string|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT mfa_secret FROM usuario WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $resultado = $stmt->fetch();
        return $resultado ? ($resultado['mfa_secret'] ?: null) : null;
    }

    public function atualizarMfaSecret(string $id, string $secret): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE usuario SET mfa_secret = :secret WHERE id = :id'
        );
        $stmt->execute([':secret' => $secret, ':id' => $id]);
    }

    public function ativarMfa(string $id): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE usuario SET mfa_ativo = 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function desativarMfa(string $id): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE usuario SET mfa_ativo = 0, mfa_secret = NULL WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }
}
