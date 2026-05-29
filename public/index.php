<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Database\Conexao;
use App\Exceptions\AcessoNegadoException;
use App\Exceptions\NaoAutorizadoException;
use App\Exceptions\NaoEncontradoException;
use App\Exceptions\RegraDeNegocioException;
use App\Exceptions\ValidacaoException;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\RefreshTokenModel;
use App\Models\UsuarioModel;
use App\Router;
use App\Services\AuthService;
use App\Services\LogService;
use App\Services\MfaService;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$uri    = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$metodo = $_SERVER['REQUEST_METHOD'];

$conexao           = Conexao::obter();
$usuarioModel      = new UsuarioModel($conexao);
$refreshTokenModel = new RefreshTokenModel($conexao);
$logService        = new LogService($conexao);
$mfaService        = new MfaService($usuarioModel, $logService);
$authService       = new AuthService($usuarioModel, $refreshTokenModel, $logService, $mfaService);
$authController    = new AuthController($authService, $mfaService);
$authMiddleware    = new AuthMiddleware();
$roleMiddleware    = new RoleMiddleware();

$roteador = new Router();

// ── Fase 1 ────────────────────────────────────────────────────────────────────
$roteador->get('/api/v1/health', function (): void {
    Response::sucesso(['status' => 'ok', 'versao' => 'v1', 'horario' => date('Y-m-d H:i:s')]);
});

// ── Fase 2: autenticação e MFA ─────────────────────────────────────────────────
$roteador->post('/api/v1/auth/login',      [$authController, 'login']);
$roteador->post('/api/v1/auth/mfa/verify', [$authController, 'verificarMfa']);
$roteador->post('/api/v1/auth/refresh',    [$authController, 'refresh']);

$roteador->post('/api/v1/auth/logout', [$authController, 'logout'], [
    [$authMiddleware, 'verificar'],
]);
$roteador->get('/api/v1/auth/mfa/setup', [$authController, 'configurarMfa'], [
    [$authMiddleware, 'verificar'],
]);
$roteador->post('/api/v1/auth/mfa/activate', [$authController, 'ativarMfa'], [
    [$authMiddleware, 'verificar'],
]);
$roteador->post('/api/v1/auth/mfa/disable', [$authController, 'desativarMfa'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// ── Captura central de erros ──────────────────────────────────────────────────
try {
    $roteador->despachar($uri, $metodo);
} catch (ValidacaoException $excecao) {
    Response::erro($excecao->erros, $excecao->getCode());
} catch (NaoEncontradoException $excecao) {
    Response::erro($excecao->getMessage(), $excecao->getCode());
} catch (NaoAutorizadoException $excecao) {
    Response::erro($excecao->getMessage(), $excecao->getCode());
} catch (AcessoNegadoException $excecao) {
    Response::erro($excecao->getMessage(), $excecao->getCode());
} catch (RegraDeNegocioException $excecao) {
    Response::erro($excecao->getMessage(), $excecao->getCode());
} catch (Throwable $excecao) {
    error_log('[OneCheck] Erro inesperado: ' . $excecao->getMessage());
    Response::erro('Erro interno do servidor', 500);
}
