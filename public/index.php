<?php

declare(strict_types=1);

use App\Controllers\AgendamentoController;
use App\Controllers\LogController;
use App\Controllers\ApiKeyController;
use App\Controllers\AuthController;
use App\Controllers\ChecklistController;
use App\Controllers\ContratoController;
use App\Controllers\DashboardController;
use App\Controllers\ImovelController;
use App\Controllers\ItemVistoriaController;
use App\Controllers\ProblemaController;
use App\Controllers\PublicController;
use App\Controllers\UsuarioController;
use App\Database\Conexao;
use App\Exceptions\AcessoNegadoException;
use App\Exceptions\NaoAutorizadoException;
use App\Exceptions\NaoEncontradoException;
use App\Exceptions\RegraDeNegocioException;
use App\Exceptions\ValidacaoException;
use App\Helpers\Response;
use App\Middleware\ApiKeyMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\AceiteChecklistModel;
use App\Models\AgendamentoModel;
use App\Models\ApiKeyModel;
use App\Models\AtualizacaoProblemaModel;
use App\Models\ChecklistItemModel;
use App\Models\ChecklistModel;
use App\Models\ComodoModel;
use App\Models\ContratoModel;
use App\Models\EnderecoModel;
use App\Models\FotoChecklistModel;
use App\Models\ImovelModel;
use App\Models\ItemVistoriaModel;
use App\Models\RefreshTokenModel;
use App\Models\RegistroProblemaModel;
use App\Models\UsuarioModel;
use App\Router;
use App\Services\AgendamentoService;
use App\Services\ApiKeyService;
use App\Services\AuthService;
use App\Services\ChecklistService;
use App\Services\ContratoService;
use App\Services\DashboardService;
use App\Services\GeocodingService;
use App\Services\ImovelService;
use App\Services\ItemVistoriaService;
use App\Services\LogService;
use App\Services\MfaService;
use App\Services\NotificationService;
use App\Services\PdfService;
use App\Services\ProblemaService;
use App\Services\PublicService;
use App\Services\Storage\StorageFactory;
use App\Services\UsuarioService;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS — deve vir antes do roteador para cobrir também o preflight OPTIONS
$origemPermitida = $_ENV['CORS_ALLOW_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origemPermitida);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri    = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$metodo = $_SERVER['REQUEST_METHOD'];

$conexao           = Conexao::obter();
$usuarioModel      = new UsuarioModel($conexao);
$refreshTokenModel = new RefreshTokenModel($conexao);
$logService        = new LogService($conexao);
$mfaService        = new MfaService($usuarioModel, $logService);
$authService       = new AuthService($usuarioModel, $refreshTokenModel, $logService, $mfaService);
$authController    = new AuthController($authService, $mfaService);
$usuarioService    = new UsuarioService($usuarioModel, $logService);
$usuarioController = new UsuarioController($usuarioService, $mfaService);
$imovelModel       = new ImovelModel($conexao);
$enderecoModel     = new EnderecoModel($conexao);
$comodoModel       = new ComodoModel($conexao);
$geocodingService  = new GeocodingService();
$imovelService     = new ImovelService($imovelModel, $enderecoModel, $comodoModel, $geocodingService, $logService);
$imovelController  = new ImovelController($imovelService);
$contratoModel          = new ContratoModel($conexao);
$contratoService        = new ContratoService($contratoModel, $imovelModel, $usuarioModel, $logService);
$contratoController     = new ContratoController($contratoService);
$itemVistoriaModel      = new ItemVistoriaModel($conexao);
$itemVistoriaService    = new ItemVistoriaService($itemVistoriaModel);
$itemVistoriaController = new ItemVistoriaController($itemVistoriaService);
$checklistModel         = new ChecklistModel($conexao);
$checklistItemModel     = new ChecklistItemModel($conexao);
$fotoChecklistModel     = new FotoChecklistModel($conexao);
$aceiteChecklistModel   = new AceiteChecklistModel($conexao);
$storageService         = StorageFactory::criar();
$pdfService             = new PdfService();
$checklistService       = new ChecklistService(
    $checklistModel,
    $checklistItemModel,
    $fotoChecklistModel,
    $contratoModel,
    $comodoModel,
    $itemVistoriaModel,
    $storageService,
    $logService,
    $aceiteChecklistModel,
    $imovelModel,
    $enderecoModel,
    $usuarioModel,
    $pdfService
);
$checklistController    = new ChecklistController($checklistService);
$agendamentoModel      = new AgendamentoModel($conexao);
$agendamentoService    = new AgendamentoService($agendamentoModel, $contratoModel, $logService);
$agendamentoController = new AgendamentoController($agendamentoService);
$notificationService    = new NotificationService($usuarioModel);
$registroProblemaModel  = new RegistroProblemaModel($conexao);
$atualizacaoProblemaModel = new AtualizacaoProblemaModel($conexao);
$problemaService        = new ProblemaService(
    $registroProblemaModel,
    $atualizacaoProblemaModel,
    $contratoModel,
    $comodoModel,
    $storageService,
    $logService,
    $notificationService
);
$problemaController     = new ProblemaController($problemaService);
$dashboardService       = new DashboardService(
    $imovelModel,
    $checklistModel,
    $registroProblemaModel,
    $agendamentoModel
);
$dashboardController    = new DashboardController($dashboardService);
$apiKeyModel            = new ApiKeyModel($conexao);
$apiKeyService          = new ApiKeyService($apiKeyModel, $logService);
$apiKeyController       = new ApiKeyController($apiKeyService);
$apiKeyMiddleware       = new ApiKeyMiddleware($apiKeyModel, $logService);
$publicService          = new PublicService(
    $imovelModel,
    $enderecoModel,
    $contratoModel,
    $checklistModel
);
$publicController       = new PublicController($publicService);
$logController         = new LogController($logService);
$authMiddleware        = new AuthMiddleware();
$roleMiddleware        = new RoleMiddleware();

$roteador = new Router();

// ── Documentação Swagger UI ───────────────────────────────────────────────────
$roteador->get('/docs', function (): void {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8">
      <title>One Check API — Documentação</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    </head>
    <body>
      <div id="swagger-ui"></div>
      <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
      <script>
        SwaggerUIBundle({
          url: '/openapi.json',
          dom_id: '#swagger-ui',
          presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
          layout: 'BaseLayout',
          deepLinking: true,
          persistAuthorization: true,
        });
      </script>
    </body>
    </html>
    HTML;
});

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

// Auto-gestão de MFA pelo próprio usuário autenticado
$roteador->post('/api/v1/auth/mfa/habilitar', [$authController, 'habilitarMfaSelf'], [
    [$authMiddleware, 'verificar'],
]);
$roteador->post('/api/v1/auth/mfa/desabilitar', [$authController, 'desabilitarMfaSelf'], [
    [$authMiddleware, 'verificar'],
]);

// Fluxo de setup obrigatório durante o login (usa temp_token, sem Bearer)
$roteador->get('/api/v1/auth/mfa/setup-login',    [$authController, 'setupLoginMfa']);
$roteador->post('/api/v1/auth/mfa/activate-login', [$authController, 'ativarLoginMfa']);

// ── Fase 3: usuários ──────────────────────────────────────────────────────────
// Rotas fixas (/me, /me/senha) devem vir antes da rota dinâmica (/{id})
$roteador->get('/api/v1/usuarios/me', [$usuarioController, 'obterPerfil'], [
    [$authMiddleware, 'verificar'],
]);
$roteador->put('/api/v1/usuarios/me/senha', [$usuarioController, 'alterarSenha'], [
    [$authMiddleware, 'verificar'],
]);
$roteador->post('/api/v1/usuarios', [$usuarioController, 'criar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->get('/api/v1/usuarios', [$usuarioController, 'listar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->get('/api/v1/usuarios/{id}', [$usuarioController, 'buscarPorId'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->put('/api/v1/usuarios/{id}', [$usuarioController, 'atualizar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->delete('/api/v1/usuarios/{id}', [$usuarioController, 'excluir'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// Gestão de MFA de um usuário pelo admin
$roteador->post('/api/v1/usuarios/{id}/mfa/habilitar', [$usuarioController, 'habilitarMfa'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->post('/api/v1/usuarios/{id}/mfa/desabilitar', [$usuarioController, 'desabilitarMfa'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// ── Fase 4: imóveis, endereços e cômodos ──────────────────────────────────────
$roteador->post('/api/v1/imoveis', [$imovelController, 'criar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->get('/api/v1/imoveis', [$imovelController, 'listar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
// GET por ID: admin e locatário — a autorização por contrato é feita no Service
$roteador->get('/api/v1/imoveis/{id}', [$imovelController, 'buscarPorId'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);
$roteador->put('/api/v1/imoveis/{id}', [$imovelController, 'atualizar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->delete('/api/v1/imoveis/{id}', [$imovelController, 'excluir'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// Endereço — admin gerencia, locatário visualiza (via Service)
$roteador->post('/api/v1/imoveis/{id}/endereco', [$imovelController, 'salvarEndereco'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->get('/api/v1/imoveis/{id}/endereco', [$imovelController, 'buscarEndereco'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);

// Cômodos — admin gerencia, vistoriador e admin visualizam
$roteador->post('/api/v1/imoveis/{id}/comodos', [$imovelController, 'criarComodo'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->get('/api/v1/imoveis/{id}/comodos', [$imovelController, 'listarComodos'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('vistoriador'),
]);
$roteador->put('/api/v1/imoveis/{id}/comodos/{comodo_id}', [$imovelController, 'atualizarComodo'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->delete('/api/v1/imoveis/{id}/comodos/{comodo_id}', [$imovelController, 'excluirComodo'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// ── Fase 5: contratos ─────────────────────────────────────────────────────────
$roteador->post('/api/v1/contratos', [$contratoController, 'criar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
// GET lista: admin vê todos, locatário vê apenas os seus (filtro no Service)
$roteador->get('/api/v1/contratos', [$contratoController, 'listar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);
// GET por ID: admin e locatário (só o seu — verificação no Service)
$roteador->get('/api/v1/contratos/{id}', [$contratoController, 'buscarPorId'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);
$roteador->patch('/api/v1/contratos/{id}/encerrar', [$contratoController, 'encerrar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->patch('/api/v1/contratos/{id}/cancelar', [$contratoController, 'cancelar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// ── Fase 6: checklists, itens e fotos ────────────────────────────────────────
$roteador->get('/api/v1/itens-vistoria', [$itemVistoriaController, 'listar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('vistoriador'),
]);

// Checklists por contrato
$roteador->post('/api/v1/contratos/{id}/checklists', [$checklistController, 'criar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->get('/api/v1/contratos/{id}/checklists', [$checklistController, 'listarPorContrato'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);

// Checklist individual
$roteador->get('/api/v1/checklists/{id}', [$checklistController, 'buscarPorId'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);
$roteador->patch('/api/v1/checklists/{id}/enviar-para-aceite', [$checklistController, 'enviarParaAceite'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->patch('/api/v1/checklists/{id}/submeter', [$checklistController, 'submeter'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('vistoriador'),
]);

// Itens de checklist
$roteador->post('/api/v1/checklists/{id}/itens', [$checklistController, 'adicionarItem'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('vistoriador'),
]);
$roteador->put('/api/v1/checklists/{id}/itens/{item_id}', [$checklistController, 'atualizarItem'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('vistoriador'),
]);

// Fotos de item
$roteador->post('/api/v1/checklists/{id}/itens/{item_id}/fotos', [$checklistController, 'uploadFoto'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('vistoriador'),
]);
$roteador->delete('/api/v1/checklists/{id}/itens/{item_id}/fotos/{foto_id}', [$checklistController, 'excluirFoto'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('vistoriador'),
]);

// ── Fase 8: agendamentos de vistoria ─────────────────────────────────────────
$roteador->post('/api/v1/contratos/{id}/agendamentos', [$agendamentoController, 'criar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
// GET: admin e vistoriador visualizam
$roteador->get('/api/v1/contratos/{id}/agendamentos', [$agendamentoController, 'listarPorContrato'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('vistoriador'),
]);
$roteador->put('/api/v1/agendamentos/{id}', [$agendamentoController, 'atualizar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->delete('/api/v1/agendamentos/{id}', [$agendamentoController, 'excluir'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// ── Fase 9: registro de problemas ────────────────────────────────────────────
// Problemas por contrato
$roteador->post('/api/v1/contratos/{id}/problemas', [$problemaController, 'criar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);
$roteador->get('/api/v1/contratos/{id}/problemas', [$problemaController, 'listarPorContrato'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);

// Problema individual
$roteador->get('/api/v1/problemas/{id}', [$problemaController, 'buscarPorId'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);
$roteador->patch('/api/v1/problemas/{id}/status', [$problemaController, 'atualizarStatus'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// Atualizações de problema
$roteador->post('/api/v1/problemas/{id}/atualizacoes', [$problemaController, 'criarAtualizacao'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->get('/api/v1/problemas/{id}/atualizacoes', [$problemaController, 'listarAtualizacoes'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);

// ── Fase 7: aceite do checklist e download PDF ────────────────────────────────
$roteador->post('/api/v1/checklists/{id}/aceitar', [$checklistController, 'aceitar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);
$roteador->post('/api/v1/checklists/{id}/rejeitar', [$checklistController, 'rejeitar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);
$roteador->get('/api/v1/checklists/{id}/download', [$checklistController, 'downloadPdf'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('locatario'),
]);

// ── Fase 11: logs de operação ────────────────────────────────────────────────
$roteador->get('/api/v1/logs', [$logController, 'listar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// ── Fase 10: dashboard, API keys e API pública ───────────────────────────────
$roteador->get('/api/v1/dashboard', [$dashboardController, 'obter'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// Gerenciamento de API keys (admin)
$roteador->post('/api/v1/api-keys', [$apiKeyController, 'criar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->get('/api/v1/api-keys', [$apiKeyController, 'listar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);
$roteador->delete('/api/v1/api-keys/{id}', [$apiKeyController, 'revogar'], [
    [$authMiddleware, 'verificar'],
    $roleMiddleware->verificar('admin'),
]);

// API pública autenticada via X-API-Key
$roteador->get('/api/v1/public/imoveis', [$publicController, 'listarImoveis'], [
    [$apiKeyMiddleware, 'verificar'],
]);
$roteador->get('/api/v1/public/imoveis/{id}/checklist-status', [$publicController, 'obterStatusChecklist'], [
    [$apiKeyMiddleware, 'verificar'],
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
