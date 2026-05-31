<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\NaoAutorizadoException;
use App\Models\ApiKeyModel;
use App\Services\LogService;

class ApiKeyMiddleware
{
    public function __construct(
        private readonly ApiKeyModel $apiKeyModel,
        private readonly LogService  $logService
    ) {}

    /**
     * Valida o cabeçalho X-API-Key e registra o uso no log de operações.
     *
     * @throws NaoAutorizadoException Se a chave estiver ausente, inválida ou revogada
     */
    public function verificar(callable $proximo): void
    {
        $chave = $_SERVER['HTTP_X_API_KEY'] ?? null;

        if ($chave === null || $chave === '') {
            throw new NaoAutorizadoException('API key não fornecida');
        }

        $keyHash = hash('sha256', $chave);
        $apiKey  = $this->apiKeyModel->buscarPorHash($keyHash);

        if ($apiKey === null || !$apiKey['ativo']) {
            throw new NaoAutorizadoException('API key inválida ou revogada');
        }

        $this->logService->registrar(
            acao:       'API_KEY_USED',
            entidade:   'api_key',
            entidadeId: $apiKey['id'],
            payload:    [
                'nome'     => $apiKey['nome'],
                'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                'metodo'   => $_SERVER['REQUEST_METHOD'] ?? '',
            ],
        );

        $proximo();
    }
}
