<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ImovelModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Lista imóveis ativos com paginação e filtro opcional por status.
     *
     * @return list<array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string}>
     */
    public function listar(int $pagina, int $itensPorPagina, string|null $status): array
    {
        $offset = ($pagina - 1) * $itensPorPagina;
        $sql    = 'SELECT id, tipo, tamanho, garagem, garagem_vagas, status, created_at
                   FROM imovel WHERE ativo = 1';
        $params = [];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limite OFFSET :offset';

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
     * Conta o total de imóveis ativos com filtro opcional por status.
     */
    public function contar(string|null $status): int
    {
        $sql    = 'SELECT COUNT(*) FROM imovel WHERE ativo = 1';
        $params = [];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca um imóvel ativo pelo ID.
     *
     * @return array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, tipo, tamanho, garagem, garagem_vagas, status, created_at
             FROM imovel WHERE id = :id AND ativo = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Insere um novo imóvel e retorna o ID gerado.
     *
     * @param array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO imovel (id, tipo, tamanho, garagem, garagem_vagas)
             VALUES (:id, :tipo, :tamanho, :garagem, :garagem_vagas)'
        );
        $stmt->execute([
            ':id'           => $dados['id'],
            ':tipo'         => $dados['tipo'],
            ':tamanho'      => $dados['tamanho'],
            ':garagem'      => $dados['garagem'],
            ':garagem_vagas' => $dados['garagem_vagas'],
        ]);

        return $dados['id'];
    }

    /**
     * Atualiza campos específicos de um imóvel.
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
            "UPDATE imovel SET {$atribuicoes} WHERE id = :id"
        );

        $stmt->execute([...$campos, 'id' => $id]);
    }

    /**
     * Realiza soft delete marcando o imóvel como inativo.
     */
    public function desativar(string $id): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE imovel SET ativo = 0 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * Verifica se o locatário tem um contrato ativo vinculado ao imóvel.
     */
    public function locatarioPossuiContratoAtivo(string $imovelId, string $locatarioId): bool
    {
        $stmt = $this->conexao->prepare(
            'SELECT COUNT(*) FROM contrato
             WHERE imovel_id = :imovel_id AND locatario_id = :locatario_id AND status = \'ativo\' LIMIT 1'
        );
        $stmt->execute([':imovel_id' => $imovelId, ':locatario_id' => $locatarioId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
