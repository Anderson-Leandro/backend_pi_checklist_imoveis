<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Services\ApiKeyService;

class ApiKeyController
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}

    public function criar(array $parametros): void
    {
        $apiKey = $this->apiKeyService->criar(Requisicao::corpo());
        Response::sucesso($apiKey, 201);
    }

    public function listar(array $parametros): void
    {
        $apiKeys = $this->apiKeyService->listar();
        Response::sucesso($apiKeys);
    }

    public function revogar(array $parametros): void
    {
        $this->apiKeyService->revogar($parametros['id']);
        Response::sucesso(null, 204);
    }
}
