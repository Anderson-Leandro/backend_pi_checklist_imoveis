<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Services\MfaService;
use App\Services\UsuarioService;

class UsuarioController
{
    public function __construct(
        private readonly UsuarioService $usuarioService,
        private readonly MfaService     $mfaService,
    ) {}

    public function criar(array $parametros): void
    {
        $usuario = $this->usuarioService->criar(Requisicao::corpo());
        Response::sucesso($usuario, 201);
    }

    public function listar(array $parametros): void
    {
        $query          = Requisicao::query();
        $pagina         = (int) ($query['pagina'] ?? 1);
        $itensPorPagina = (int) ($query['por_pagina'] ?? 20);
        $role           = isset($query['role']) && $query['role'] !== '' ? $query['role'] : null;

        $resultado = $this->usuarioService->listar($pagina, $itensPorPagina, $role);

        Response::paginado(
            itens:          $resultado['itens'],
            total:          $resultado['total'],
            pagina:         $resultado['pagina'],
            itensPorPagina: $resultado['itensPorPagina'],
        );
    }

    public function buscarPorId(array $parametros): void
    {
        $usuario = $this->usuarioService->buscarPorId($parametros['id']);
        Response::sucesso($usuario);
    }

    public function atualizar(array $parametros): void
    {
        $usuario = $this->usuarioService->atualizar($parametros['id'], Requisicao::corpo());
        Response::sucesso($usuario);
    }

    public function excluir(array $parametros): void
    {
        $this->usuarioService->excluir($parametros['id']);
        Response::sucesso(dados: null, codigo: 204);
    }

    public function obterPerfil(array $parametros): void
    {
        $usuario = $this->usuarioService->obterPerfil();
        Response::sucesso($usuario);
    }

    public function alterarSenha(array $parametros): void
    {
        $this->usuarioService->alterarSenha(Requisicao::corpo());
        Response::sucesso(['mensagem' => 'Senha alterada com sucesso']);
    }

    /**
     * Habilita o MFA para um usuário específico (admin).
     * O secret TOTP existente é preservado; se não houver, o login forçará a configuração.
     */
    public function habilitarMfa(array $parametros): void
    {
        $this->mfaService->habilitar($parametros['id']);
        Response::sucesso(['mensagem' => 'MFA habilitado para o usuário']);
    }

    /**
     * Desabilita o MFA de um usuário específico (admin) e apaga o secret TOTP.
     */
    public function desabilitarMfa(array $parametros): void
    {
        $this->mfaService->desativar($parametros['id']);
        Response::sucesso(['mensagem' => 'MFA desabilitado para o usuário']);
    }
}
