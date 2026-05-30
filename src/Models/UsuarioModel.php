<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class UsuarioModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Busca usuário ativo pelo e-mail incluindo campos sensíveis para autenticação.
     *
     * @return array{id: string, nome: string, email: string, senha_hash: string, role: string, mfa_secret: string|null, mfa_ativo: int, created_at: string}|null
     */
    public function buscarPorEmailComCredenciais(string $email): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, email, senha_hash, role, mfa_secret, mfa_ativo, created_at
             FROM usuario WHERE email = :email AND ativo = 1 LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Busca usuário ativo pelo ID sem campos sensíveis.
     *
     * @return array{id: string, nome: string, email: string, role: string, mfa_ativo: int, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, nome, email, role, mfa_ativo, created_at
             FROM usuario WHERE id = :id AND ativo = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Retorna o MFA secret de um usuário ativo.
     */
    public function buscarMfaSecretPorId(string $id): string|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT mfa_secret FROM usuario WHERE id = :id AND ativo = 1 LIMIT 1'
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

    // ──── Fase 3 — CRUD de usuários ──────────────────────────────────────────

    /**
     * Lista usuários ativos com paginação e filtro opcional por role.
     *
     * @return list<array{id: string, nome: string, email: string, role: string, mfa_ativo: int, created_at: string}>
     */
    public function listar(int $pagina, int $itensPorPagina, string|null $role): array
    {
        $offset = ($pagina - 1) * $itensPorPagina;
        $sql    = 'SELECT id, nome, email, role, mfa_ativo, created_at
                   FROM usuario WHERE ativo = 1';
        $params = [];

        if ($role !== null) {
            $sql .= ' AND role = :role';
            $params[':role'] = $role;
        }

        $sql .= ' ORDER BY nome ASC LIMIT :limite OFFSET :offset';

        $stmt = $this->conexao->prepare($sql);

        foreach ($params as $chave => $valor) {
            $stmt->bindValue($chave, $valor);
        }
        $stmt->bindValue(':limite', $itensPorPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Conta o total de usuários ativos com filtro opcional por role.
     */
    public function contar(string|null $role): int
    {
        $sql    = 'SELECT COUNT(*) FROM usuario WHERE ativo = 1';
        $params = [];

        if ($role !== null) {
            $sql .= ' AND role = :role';
            $params[':role'] = $role;
        }

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Insere um novo usuário e retorna o ID gerado.
     *
     * @param array{id: string, nome: string, email: string, senha_hash: string, role: string} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO usuario (id, nome, email, senha_hash, role)
             VALUES (:id, :nome, :email, :senha_hash, :role)'
        );
        $stmt->execute([
            ':id'         => $dados['id'],
            ':nome'       => $dados['nome'],
            ':email'      => $dados['email'],
            ':senha_hash' => $dados['senha_hash'],
            ':role'       => $dados['role'],
        ]);

        return $dados['id'];
    }

    /**
     * Atualiza campos específicos de um usuário.
     * Apenas os campos presentes em $campos são alterados.
     *
     * @param array<string, string> $campos
     */
    public function atualizar(string $id, array $campos): void
    {
        if (empty($campos)) {
            return;
        }

        $atribuicoes = implode(', ', array_map(
            fn(string $campo) => "{$campo} = :{$campo}",
            array_keys($campos)
        ));

        $stmt = $this->conexao->prepare(
            "UPDATE usuario SET {$atribuicoes} WHERE id = :id"
        );

        $stmt->execute([...$campos, 'id' => $id]);
    }

    /**
     * Realiza soft delete marcando o usuário como inativo.
     */
    public function desativar(string $id): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE usuario SET ativo = 0 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * Verifica se um e-mail já está em uso por outro usuário ativo.
     */
    public function emailJaCadastrado(string $email, string|null $excluirId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM usuario WHERE email = :email AND ativo = 1';
        $params = [':email' => $email];

        if ($excluirId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excluirId;
        }

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
