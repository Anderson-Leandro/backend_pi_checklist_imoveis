<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Services\ImovelService;

class ImovelController
{
    public function __construct(
        private readonly ImovelService $imovelService
    ) {}

    public function criar(array $parametros): void
    {
        $imovel = $this->imovelService->criar(Requisicao::corpo());
        Response::sucesso($imovel, 201);
    }

    public function listar(array $parametros): void
    {
        $query          = Requisicao::query();
        $pagina         = (int) ($query['pagina']    ?? 1);
        $itensPorPagina = (int) ($query['por_pagina'] ?? 20);
        $status         = isset($query['status']) && $query['status'] !== '' ? $query['status'] : null;

        $resultado = $this->imovelService->listar($pagina, $itensPorPagina, $status);

        Response::paginado(
            itens:          $resultado['itens'],
            total:          $resultado['total'],
            pagina:         $resultado['pagina'],
            itensPorPagina: $resultado['itensPorPagina'],
        );
    }

    public function buscarPorId(array $parametros): void
    {
        $imovel = $this->imovelService->buscarPorId($parametros['id']);
        Response::sucesso($imovel);
    }

    public function atualizar(array $parametros): void
    {
        $imovel = $this->imovelService->atualizar($parametros['id'], Requisicao::corpo());
        Response::sucesso($imovel);
    }

    public function excluir(array $parametros): void
    {
        $this->imovelService->excluir($parametros['id']);
        Response::sucesso(dados: null, codigo: 204);
    }

    public function salvarEndereco(array $parametros): void
    {
        $endereco = $this->imovelService->salvarEndereco($parametros['id'], Requisicao::corpo());
        Response::sucesso($endereco, 200);
    }

    public function buscarEndereco(array $parametros): void
    {
        $endereco = $this->imovelService->buscarEndereco($parametros['id']);
        Response::sucesso($endereco);
    }

    public function criarComodo(array $parametros): void
    {
        $comodo = $this->imovelService->criarComodo($parametros['id'], Requisicao::corpo());
        Response::sucesso($comodo, 201);
    }

    public function listarComodos(array $parametros): void
    {
        $comodos = $this->imovelService->listarComodos($parametros['id']);
        Response::sucesso($comodos);
    }

    public function atualizarComodo(array $parametros): void
    {
        $comodo = $this->imovelService->atualizarComodo(
            $parametros['id'],
            $parametros['comodo_id'],
            Requisicao::corpo()
        );
        Response::sucesso($comodo);
    }

    public function excluirComodo(array $parametros): void
    {
        $this->imovelService->excluirComodo($parametros['id'], $parametros['comodo_id']);
        Response::sucesso(dados: null, codigo: 204);
    }
}
