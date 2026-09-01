<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NaoAutorizadoException;
use App\Exceptions\NaoEncontradoException;
use App\Exceptions\ValidacaoException;
use App\Helpers\Env;
use App\Helpers\Validator;
use App\Models\RefreshTokenModel;
use App\Models\UsuarioModel;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

class AuthService
{
    public function __construct(
        private readonly UsuarioModel      $usuarioModel,
        private readonly RefreshTokenModel $refreshTokenModel,
        private readonly LogService        $logService,
        private readonly MfaService        $mfaService
    ) {}

    public function login(array $dados): array
    {
        $erros = Validator::validar($dados, [
            'email' => 'obrigatorio|email',
            'senha' => 'obrigatorio',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $usuario = $this->usuarioModel->buscarPorEmailComCredenciais($dados['email']);

        if ($usuario === null || !password_verify($dados['senha'], $usuario['senha_hash'])) {
            $this->logService->registrar(
                acao:       'LOGIN_FALHO',
                entidade:   'usuario',
                entidadeId: $usuario['id'] ?? null,
                payload:    ['email' => $dados['email']],
            );
            throw new NaoAutorizadoException('Credenciais inválidas');
        }

        if ((bool) $usuario['mfa_ativo']) {
            // secret ausente → usuário ainda não configurou o TOTP, forçar setup
            if ($usuario['mfa_secret'] === null || $usuario['mfa_secret'] === '') {
                return [
                    'mfa_setup_required' => true,
                    'temp_token'         => $this->gerarTempToken($usuario['id']),
                ];
            }

            return [
                'mfa_required' => true,
                'temp_token'   => $this->gerarTempToken($usuario['id']),
            ];
        }

        $this->logService->registrarComUsuario(
            acao:       'LOGIN',
            entidade:   'usuario',
            entidadeId: $usuario['id'],
            payload:    ['email' => $usuario['email'], 'role' => $usuario['role']],
            usuarioId:  $usuario['id'],
        );

        return $this->montarRespostaDeToken($usuario);
    }

    public function verificarMfa(array $dados): array
    {
        $erros = Validator::validar($dados, [
            'temp_token' => 'obrigatorio',
            'codigo'     => 'obrigatorio|tamanho:6',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $payload   = $this->decodificarToken($dados['temp_token'], tipoEsperado: 'temp_mfa');
        $usuarioId = $payload->sub;

        $mfaSecret = $this->usuarioModel->buscarMfaSecretPorId($usuarioId);

        if ($mfaSecret === null) {
            throw new NaoAutorizadoException('MFA não configurado para este usuário');
        }

        if (!$this->mfaService->verificar($mfaSecret, $dados['codigo'])) {
            throw new NaoAutorizadoException('Código MFA inválido');
        }

        $emailUsuario = $this->buscarEmailPorId($usuarioId);
        $usuario      = $this->usuarioModel->buscarPorEmailComCredenciais($emailUsuario);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        $this->logService->registrarComUsuario(
            acao:       'LOGIN',
            entidade:   'usuario',
            entidadeId: $usuario['id'],
            payload:    ['email' => $usuario['email'], 'role' => $usuario['role'], 'via' => 'mfa'],
            usuarioId:  $usuario['id'],
        );

        return $this->montarRespostaDeToken($usuario);
    }

    /**
     * Gera o QR code TOTP para o fluxo de setup obrigatório no login.
     * Recebe o temp_token (tipo temp_mfa) em vez de um access_token.
     *
     * @throws NaoAutorizadoException Se o temp_token for inválido ou expirado
     * @throws NaoEncontradoException Se o usuário não existir
     */
    public function setupLogin(array $dados): array
    {
        $erros = Validator::validar($dados, ['temp_token' => 'obrigatorio']);
        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $payload   = $this->decodificarToken($dados['temp_token'], tipoEsperado: 'temp_mfa');
        $usuarioId = $payload->sub;
        $email     = $this->buscarEmailPorId($usuarioId);

        return $this->mfaService->configurar($usuarioId, $email);
    }

    /**
     * Valida o código TOTP configurado durante o login, ativa o MFA e devolve os tokens.
     * Completa o login em um único passo após o setup obrigatório.
     *
     * @throws ValidacaoException     Se os dados forem inválidos
     * @throws NaoAutorizadoException Se o temp_token ou o código forem inválidos
     * @throws NaoEncontradoException Se o usuário não existir
     */
    public function ativarLogin(array $dados): array
    {
        $erros = Validator::validar($dados, [
            'temp_token' => 'obrigatorio',
            'codigo'     => 'obrigatorio|tamanho:6',
        ]);
        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $payload   = $this->decodificarToken($dados['temp_token'], tipoEsperado: 'temp_mfa');
        $usuarioId = $payload->sub;

        $this->mfaService->ativar($usuarioId, $dados['codigo']);

        $email   = $this->buscarEmailPorId($usuarioId);
        $usuario = $this->usuarioModel->buscarPorEmailComCredenciais($email);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        $this->logService->registrarComUsuario(
            acao:       'LOGIN',
            entidade:   'usuario',
            entidadeId: $usuario['id'],
            payload:    ['email' => $usuario['email'], 'role' => $usuario['role'], 'via' => 'mfa_setup'],
            usuarioId:  $usuario['id'],
        );

        return $this->montarRespostaDeToken($usuario);
    }

    public function refresh(array $dados): array
    {
        $erros = Validator::validar($dados, ['refresh_token' => 'obrigatorio']);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $tokenHash     = hash('sha256', $dados['refresh_token']);
        $registroToken = $this->refreshTokenModel->buscarPorHash($tokenHash);

        if ($registroToken === null) {
            throw new NaoAutorizadoException('Refresh token inválido ou expirado');
        }

        $emailUsuario = $this->buscarEmailPorId($registroToken['usuario_id']);
        $usuario      = $this->usuarioModel->buscarPorEmailComCredenciais($emailUsuario);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        $this->refreshTokenModel->revogar($tokenHash);
        $novoRefreshToken = $this->gerarRefreshToken($usuario['id']);

        return [
            'access_token'  => $this->gerarAccessToken($usuario),
            'refresh_token' => $novoRefreshToken,
        ];
    }

    public function logout(array $dados): void
    {
        $erros = Validator::validar($dados, ['refresh_token' => 'obrigatorio']);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $tokenHash = hash('sha256', $dados['refresh_token']);
        $this->refreshTokenModel->revogar($tokenHash);

        $this->logService->registrar(
            acao:       'LOGOUT',
            entidade:   'usuario',
            entidadeId: null,
        );
    }

    public function gerarAccessToken(array $usuario): string
    {
        $expiry = Env::int('JWT_EXPIRY', 900);

        $payload = [
            'iss'   => 'one-check-api',
            'sub'   => $usuario['id'],
            'iat'   => time(),
            'exp'   => time() + $expiry,
            'type'  => 'access',
            'email' => $usuario['email'],
            'role'  => $usuario['role'],
        ];

        return JWT::encode($payload, Env::get('JWT_SECRET'), 'HS256');
    }

    private function gerarTempToken(string $usuarioId): string
    {
        $payload = [
            'iss'  => 'one-check-api',
            'sub'  => $usuarioId,
            'iat'  => time(),
            'exp'  => time() + 300,
            'type' => 'temp_mfa',
        ];

        return JWT::encode($payload, Env::get('JWT_SECRET'), 'HS256');
    }

    private function gerarRefreshToken(string $usuarioId): string
    {
        $tokenBruto = bin2hex(random_bytes(32));
        $tokenHash  = hash('sha256', $tokenBruto);
        $expiry     = Env::int('JWT_REFRESH_EXPIRY', 604800);
        $expiresAt  = date('Y-m-d H:i:s', time() + $expiry);

        $this->refreshTokenModel->criar($usuarioId, $tokenHash, $expiresAt);

        return $tokenBruto;
    }

    private function decodificarToken(string $token, string $tipoEsperado): object
    {
        try {
            $payload = JWT::decode($token, new Key(Env::get('JWT_SECRET'), 'HS256'));

            if (($payload->type ?? '') !== $tipoEsperado) {
                throw new NaoAutorizadoException('Tipo de token inválido');
            }

            return $payload;
        } catch (ExpiredException) {
            throw new NaoAutorizadoException('Token expirado');
        } catch (SignatureInvalidException) {
            throw new NaoAutorizadoException('Assinatura do token inválida');
        } catch (NaoAutorizadoException $excecao) {
            throw $excecao;
        } catch (\Throwable) {
            throw new NaoAutorizadoException('Token inválido');
        }
    }

    private function montarRespostaDeToken(array $usuario): array
    {
        return [
            'access_token'  => $this->gerarAccessToken($usuario),
            'refresh_token' => $this->gerarRefreshToken($usuario['id']),
            'usuario'       => [
                'id'        => $usuario['id'],
                'nome'      => $usuario['nome'],
                'email'     => $usuario['email'],
                'role'      => $usuario['role'],
                'mfa_ativo' => (bool) ($usuario['mfa_ativo'] ?? false),
            ],
        ];
    }

    private function buscarEmailPorId(string $id): string
    {
        $usuario = $this->usuarioModel->buscarPorId($id);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        return $usuario['email'];
    }
}
