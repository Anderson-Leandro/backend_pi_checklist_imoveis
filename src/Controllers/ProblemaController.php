<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Services\ProblemaService;

class ProblemaController
{
    public function __construct(
        private readonly ProblemaService $problemaService
    ) {}

    public function criar(array $parametros): void
    {
        $dados   = !empty($_FILES) ? $_POST : Requisicao::corpo();
        $arquivo = $_FILES['foto'] ?? null;

        $problema = $this->problemaService->criar($parametros['id'], $dados, $arquivo);
        Response::sucesso($problema, 201);
    }

    public function listarPorContrato(array $parametros): void
    {
        $problemas = $this->problemaService->listarPorContrato($parametros['id']);
        Response::sucesso($problemas);
    }

    public function buscarPorId(array $parametros): void
    {
        $problema = $this->problemaService->buscarPorId($parametros['id']);
        Response::sucesso($problema);
    }

    public function atualizarStatus(array $parametros): void
    {
        $problema = $this->problemaService->atualizarStatus($parametros['id'], Requisicao::corpo());
        Response::sucesso($problema);
    }

    public function criarAtualizacao(array $parametros): void
    {
        $dados   = !empty($_FILES) ? $_POST : Requisicao::corpo();
        $arquivo = $_FILES['foto'] ?? null;

        $atualizacao = $this->problemaService->criarAtualizacao($parametros['id'], $dados, $arquivo);
        Response::sucesso($atualizacao, 201);
    }

    public function listarAtualizacoes(array $parametros): void
    {
        $atualizacoes = $this->problemaService->listarAtualizacoes($parametros['id']);
        Response::sucesso($atualizacoes);
    }
}
