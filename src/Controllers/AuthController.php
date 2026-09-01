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

    /**
     * Habilita o MFA para o próprio usuário autenticado.
     * O TOTP deverá ser configurado no próximo login (se ainda não houver secret).
     */
    public function habilitarMfaSelf(array $parametros): void
    {
        $usuario = UsuarioAutenticado::obterOuFalhar();
        $this->mfaService->habilitar($usuario['id']);
        Response::sucesso(['mensagem' => 'MFA habilitado. Configure o autenticador no próximo login.']);
    }

    /**
     * Desativa o MFA do próprio usuário autenticado e apaga o secret TOTP.
     */
    public function desabilitarMfaSelf(array $parametros): void
    {
        $usuario = UsuarioAutenticado::obterOuFalhar();
        $this->mfaService->desativar($usuario['id']);
        Response::sucesso(['mensagem' => 'MFA desativado com sucesso']);
    }

    /**
     * Gera QR code TOTP para o fluxo de setup obrigatório durante o login.
     * Usa temp_token (retornado no login quando mfa_setup_required = true).
     */
    public function setupLoginMfa(array $parametros): void
    {
        $resultado = $this->authService->setupLogin(Requisicao::query());
        Response::sucesso($resultado);
    }

    /**
     * Valida o código TOTP, ativa o MFA e retorna os tokens de acesso.
     * Completa o login para usuários que precisavam configurar o MFA.
     */
    public function ativarLoginMfa(array $parametros): void
    {
        $resultado = $this->authService->ativarLogin(Requisicao::corpo());
        Response::sucesso($resultado);
    }
}
