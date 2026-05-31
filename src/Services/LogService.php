<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Requisicao;
use App\Helpers\UsuarioAutenticado;
use App\Helpers\Uuid;
use App\Models\LogModel;
use PDO;

class LogService
{
    private readonly LogModel $logModel;

    public function __construct(
        private readonly PDO $conexao
    ) {
        $this->logModel = new LogModel($conexao);
    }

    public function registrar(
        string $acao,
        string $entidade,
        string|null $entidadeId,
        array $payload = []
    ): void {
        $usuarioId = UsuarioAutenticado::obter()['id'] ?? null;
        $this->gravar($acao, $entidade, $entidadeId, $payload, $usuarioId);
    }

    public function registrarComUsuario(
        string $acao,
        string $entidade,
        string|null $entidadeId,
        array $payload,
        string $usuarioId
    ): void {
        $this->gravar($acao, $entidade, $entidadeId, $payload, $usuarioId);
    }

    /**
     * Lista registros de log com filtros opcionais e paginação.
     *
     * @return array{itens: list<array>, total: int, pagina: int, itensPorPagina: int}
     */
    public function listar(
        int $pagina,
        int $itensPorPagina,
        string|null $entidade,
        string|null $usuarioId,
        string|null $de,
        string|null $ate
    ): array {
        $pagina         = max(1, $pagina);
        $itensPorPagina = max(1, min(100, $itensPorPagina));

        $itens = $this->logModel->listar($pagina, $itensPorPagina, $entidade, $usuarioId, $de, $ate);
        $total = $this->logModel->contar($entidade, $usuarioId, $de, $ate);

        $itens = array_map(static function (array $item): array {
            if ($item['payload'] !== null) {
                $item['payload'] = json_decode($item['payload'], associative: true);
            }
            return $item;
        }, $itens);

        return compact('itens', 'total', 'pagina', 'itensPorPagina');
    }

    private function gravar(
        string $acao,
        string $entidade,
        string|null $entidadeId,
        array $payload,
        string|null $usuarioId
    ): void {
        $payloadSanitizado = $payload;
        unset($payloadSanitizado['senha_hash'], $payloadSanitizado['mfa_secret'], $payloadSanitizado['senha']);

        $stmt = $this->conexao->prepare(
            'INSERT INTO log_operacao (id, usuario_id, acao, entidade, entidade_id, payload, ip)
             VALUES (:id, :usuario_id, :acao, :entidade, :entidade_id, :payload, :ip)'
        );

        $stmt->execute([
            ':id'          => Uuid::gerar(),
            ':usuario_id'  => $usuarioId,
            ':acao'        => $acao,
            ':entidade'    => $entidade,
            ':entidade_id' => $entidadeId,
            ':payload'     => !empty($payloadSanitizado)
                ? json_encode($payloadSanitizado, JSON_UNESCAPED_UNICODE)
                : null,
            ':ip'          => Requisicao::ip(),
        ]);
    }
}
