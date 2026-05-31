<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Services\ContratoService;

class ContratoController
{
    public function __construct(
        private readonly ContratoService $contratoService
    ) {}

    public function criar(array $parametros): void
    {
        $contrato = $this->contratoService->criar(Requisicao::corpo());
        Response::sucesso($contrato, 201);
    }

    public function listar(array $parametros): void
    {
        $query          = Requisicao::query();
        $pagina         = (int) ($query['pagina']    ?? 1);
        $itensPorPagina = (int) ($query['por_pagina'] ?? 20);
        $status         = isset($query['status'])    && $query['status']    !== '' ? $query['status']    : null;
        $imovelId       = isset($query['imovel_id']) && $query['imovel_id'] !== '' ? $query['imovel_id'] : null;

        $resultado = $this->contratoService->listar($pagina, $itensPorPagina, $status, $imovelId);

        Response::paginado(
            itens:          $resultado['itens'],
            total:          $resultado['total'],
            pagina:         $resultado['pagina'],
            itensPorPagina: $resultado['itensPorPagina'],
        );
    }

    public function buscarPorId(array $parametros): void
    {
        $contrato = $this->contratoService->buscarPorId($parametros['id']);
        Response::sucesso($contrato);
    }

    public function encerrar(array $parametros): void
    {
        $contrato = $this->contratoService->encerrar($parametros['id']);
        Response::sucesso($contrato);
    }

    public function cancelar(array $parametros): void
    {
        $contrato = $this->contratoService->cancelar($parametros['id']);
        Response::sucesso($contrato);
    }
}
