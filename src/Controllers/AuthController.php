<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Helpers\UsuarioAutenticado;
use App\Services\AuthService;
use App\Services\MfaService;

class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly MfaService  $mfaService
    ) {}

    public function login(array $parametros): void
    {
        $resultado = $this->authService->login(Requisicao::corpo());
        Response::sucesso($resultado);
    }

    public function verificarMfa(array $parametros): void
    {
        $resultado = $this->authService->verificarMfa(Requisicao::corpo());
        Response::sucesso($resultado);
    }

    public function refresh(array $parametros): void
    {
        $resultado = $this->authService->refresh(Requisicao::corpo());
        Response::sucesso($resultado);
    }

    public function logout(array $parametros): void
    {
        $this->authService->logout(Requisicao::corpo());
        Response::sucesso(dados: null, codigo: 204);
    }

    public function configurarMfa(array $parametros): void
    {
        $usuario   = UsuarioAutenticado::obterOuFalhar();
        $resultado = $this->mfaService->configurar($usuario['id'], $usuario['email']);
        Response::sucesso($resultado);
    }

    public function ativarMfa(array $parametros): void
    {
        $usuario = UsuarioAutenticado::obterOuFalhar();
        $corpo   = Requisicao::corpo();

        $this->mfaService->ativar($usuario['id'], $corpo['codigo'] ?? '');
        Response::sucesso(['mensagem' => 'MFA ativado com sucesso']);
    }

    public function desativarMfa(array $parametros): void
    {
        $corpo     = Requisicao::corpo();
        $usuarioId = $corpo['usuario_id'] ?? '';

        $this->mfaService->desativar($usuarioId);
        Response::sucesso(['mensagem' => 'MFA desativado com sucesso']);
    }
}
