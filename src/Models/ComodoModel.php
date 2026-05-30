<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ComodoModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Lista todos os cômodos de um imóvel.
     *
     * @return list<array{id: string, imovel_id: string, tipo: string, descricao: string|null}>
     */
    public function listarPorImovel(string $imovelId): array
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, imovel_id, tipo, descricao
             FROM comodo WHERE imovel_id = :imovel_id ORDER BY tipo ASC'
        );
        $stmt->execute([':imovel_id' => $imovelId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Busca um cômodo pelo ID verificando se pertence ao imóvel informado.
     *
     * @return array{id: string, imovel_id: string, tipo: string, descricao: string|null}|null
     */
    public function buscarPorId(string $id, string $imovelId): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, imovel_id, tipo, descricao
             FROM comodo WHERE id = :id AND imovel_id = :imovel_id LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':imovel_id' => $imovelId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Insere um novo cômodo e retorna o ID gerado.
     *
     * @param array{id: string, imovel_id: string, tipo: string, descricao: string|null} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO comodo (id, imovel_id, tipo, descricao)
             VALUES (:id, :imovel_id, :tipo, :descricao)'
        );
        $stmt->execute([
            ':id'        => $dados['id'],
            ':imovel_id' => $dados['imovel_id'],
            ':tipo'      => $dados['tipo'],
            ':descricao' => $dados['descricao'] ?? null,
        ]);

        return $dados['id'];
    }

    /**
     * Atualiza campos de um cômodo.
     *
     * @param array<string, mixed> $campos
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
            "UPDATE comodo SET {$atribuicoes} WHERE id = :id"
        );

        $stmt->execute([...$campos, 'id' => $id]);
    }

    /**
     * Remove permanentemente um cômodo.
     */
    public function deletar(string $id): void
    {
        $stmt = $this->conexao->prepare('DELETE FROM comodo WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
