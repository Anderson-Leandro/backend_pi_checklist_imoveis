<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Requisicao;
use App\Helpers\UsuarioAutenticado;
use App\Helpers\Uuid;
use PDO;

class LogService
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

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
