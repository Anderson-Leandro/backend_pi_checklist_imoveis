<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Services\AgendamentoService;

class AgendamentoController
{
    public function __construct(
        private readonly AgendamentoService $agendamentoService
    ) {}

    public function criar(array $parametros): void
    {
        $agendamento = $this->agendamentoService->criar($parametros['id'], Requisicao::corpo());
        Response::sucesso($agendamento, 201);
    }

    public function listarPorContrato(array $parametros): void
    {
        $agendamentos = $this->agendamentoService->listarPorContrato($parametros['id']);
        Response::sucesso($agendamentos);
    }

    public function atualizar(array $parametros): void
    {
        $agendamento = $this->agendamentoService->atualizar($parametros['id'], Requisicao::corpo());
        Response::sucesso($agendamento);
    }

    public function excluir(array $parametros): void
    {
        $this->agendamentoService->excluir($parametros['id']);
        Response::sucesso(null, 204);
    }
}
