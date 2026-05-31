<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ContratoModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Lista contratos com paginação e filtros opcionais.
     *
     * @return list<array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string, status: string, created_at: string}>
     */
    public function listar(
        int $pagina,
        int $itensPorPagina,
        string|null $status,
        string|null $imovelId,
        string|null $locatarioId
    ): array {
        $offset = ($pagina - 1) * $itensPorPagina;
        $sql    = 'SELECT id, imovel_id, locatario_id, data_inicio, data_fim, status, created_at
                   FROM contrato WHERE 1=1';
        $params = [];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }
        if ($imovelId !== null) {
            $sql .= ' AND imovel_id = :imovel_id';
            $params[':imovel_id'] = $imovelId;
        }
        if ($locatarioId !== null) {
            $sql .= ' AND locatario_id = :locatario_id';
            $params[':locatario_id'] = $locatarioId;
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
     * Conta o total de contratos com filtros opcionais.
     */
    public function contar(
        string|null $status,
        string|null $imovelId,
        string|null $locatarioId
    ): int {
        $sql    = 'SELECT COUNT(*) FROM contrato WHERE 1=1';
        $params = [];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }
        if ($imovelId !== null) {
            $sql .= ' AND imovel_id = :imovel_id';
            $params[':imovel_id'] = $imovelId;
        }
        if ($locatarioId !== null) {
            $sql .= ' AND locatario_id = :locatario_id';
            $params[':locatario_id'] = $locatarioId;
        }

        $stmt = $this->conexao->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca um contrato pelo ID.
     *
     * @return array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string, status: string, created_at: string}|null
     */
    public function buscarPorId(string $id): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, imovel_id, locatario_id, data_inicio, data_fim, status, created_at
             FROM contrato WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Insere um novo contrato e retorna o ID.
     *
     * @param array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string} $dados
     */
    public function inserir(array $dados): string
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO contrato (id, imovel_id, locatario_id, data_inicio, data_fim)
             VALUES (:id, :imovel_id, :locatario_id, :data_inicio, :data_fim)'
        );
        $stmt->execute([
            ':id'           => $dados['id'],
            ':imovel_id'    => $dados['imovel_id'],
            ':locatario_id' => $dados['locatario_id'],
            ':data_inicio'  => $dados['data_inicio'],
            ':data_fim'     => $dados['data_fim'],
        ]);
        return $dados['id'];
    }

    /**
     * Atualiza o status de um contrato.
     */
    public function atualizarStatus(string $id, string $status): void
    {
        $stmt = $this->conexao->prepare(
            'UPDATE contrato SET status = :status WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * Verifica se o imóvel já possui um contrato com status 'ativo'.
     */
    public function imovelPossuiContratoAtivo(string $imovelId): bool
    {
        $stmt = $this->conexao->prepare(
            'SELECT COUNT(*) FROM contrato WHERE imovel_id = :imovel_id AND status = \'ativo\' LIMIT 1'
        );
        $stmt->execute([':imovel_id' => $imovelId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Busca o contrato ativo de um imóvel (para API pública).
     *
     * @return array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string, status: string, created_at: string}|null
     */
    public function buscarAtivoPorImovel(string $imovelId): array|null
    {
        $stmt = $this->conexao->prepare(
            "SELECT id, imovel_id, locatario_id, data_inicio, data_fim, status, created_at
             FROM contrato WHERE imovel_id = :imovel_id AND status = 'ativo' LIMIT 1"
        );
        $stmt->execute([':imovel_id' => $imovelId]);
        return $stmt->fetch() ?: null;
    }
}
