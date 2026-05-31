<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class LogModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Lista registros de log com filtros e paginação.
     *
     * @return list<array{id: string, usuario_id: string|null, acao: string, entidade: string, entidade_id: string|null, payload: string|null, ip: string|null, created_at: string}>
     */
    public function listar(
        int $pagina,
        int $itensPorPagina,
        string|null $entidade,
        string|null $usuarioId,
        string|null $de,
        string|null $ate
    ): array {
        [$where, $params] = $this->construirFiltro($entidade, $usuarioId, $de, $ate);

        $offset = ($pagina - 1) * $itensPorPagina;
        $sql    = "SELECT * FROM log_operacao{$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conexao->prepare($sql);
        foreach ($params as $chave => $valor) {
            $stmt->bindValue($chave, $valor);
        }
        $stmt->bindValue(':limit', $itensPorPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Conta o total de registros com os filtros aplicados.
     */
    public function contar(
        string|null $entidade,
        string|null $usuarioId,
        string|null $de,
        string|null $ate
    ): int {
        [$where, $params] = $this->construirFiltro($entidade, $usuarioId, $de, $ate);

        $stmt = $this->conexao->prepare("SELECT COUNT(*) FROM log_operacao{$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function construirFiltro(
        string|null $entidade,
        string|null $usuarioId,
        string|null $de,
        string|null $ate
    ): array {
        $condicoes = [];
        $params    = [];

        if ($entidade !== null) {
            $condicoes[] = 'entidade = :entidade';
            $params[':entidade'] = $entidade;
        }

        if ($usuarioId !== null) {
            $condicoes[] = 'usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        if ($de !== null) {
            $condicoes[] = 'created_at >= :de';
            $params[':de'] = $de . ' 00:00:00';
        }

        if ($ate !== null) {
            $condicoes[] = 'created_at <= :ate';
            $params[':ate'] = $ate . ' 23:59:59';
        }

        $where = !empty($condicoes) ? ' WHERE ' . implode(' AND ', $condicoes) : '';

        return [$where, $params];
    }
}
