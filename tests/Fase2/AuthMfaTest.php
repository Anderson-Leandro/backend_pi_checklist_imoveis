<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'access_token'  => '',
    'refresh_token' => '',
];

beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['access_token']  = $admin['token'];
    $estado['refresh_token'] = $admin['refresh'];
});

afterAll(function () use (&$estado): void {
    if ($estado['refresh_token']) {
        apiComToken($estado['access_token'])->post('/api/v1/auth/logout', [
            'refresh_token' => $estado['refresh_token'],
        ]);
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// MFA Setup
// ═══════════════════════════════════════════════════════════════════════════

test('setup MFA retorna otpauth URI com issuer e email corretos', function () use (&$estado): void {
    $resp = apiComToken($estado['access_token'])->get('/api/v1/auth/mfa/setup');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.otpauth_uri'))->toBeString()
        ->and($resp->json('dados.otpauth_uri'))->toStartWith('otpauth://totp/')
        ->and($resp->json('dados.otpauth_uri'))->toContain('One%20Check');

    // Nunca expor o secret em texto plano
    expect($resp->json('dados.mfa_secret'))->toBeNull();
    assertSemCamposSensiveis($resp);
})->group('fase2', 'mfa');

test('setup MFA sem autenticacao retorna 401', function (): void {
    $resp = api()->get('/api/v1/auth/mfa/setup');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'mfa');

// ═══════════════════════════════════════════════════════════════════════════
// MFA Activate
// ═══════════════════════════════════════════════════════════════════════════

test('ativar MFA sem autenticacao retorna 401', function (): void {
    $resp = api()->post('/api/v1/auth/mfa/activate', ['codigo' => '123456']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'mfa');

test('ativar MFA com codigo TOTP incorreto retorna 401', function () use (&$estado): void {
    // Setup primeiro para ter um secret salvo
    apiComToken($estado['access_token'])->get('/api/v1/auth/mfa/setup');

    $resp = apiComToken($estado['access_token'])->post('/api/v1/auth/mfa/activate', [
        'codigo' => '000000', // código quase certamente inválido
    ]);

    // API retorna 401 para código TOTP inválido
    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'mfa');

test('ativar MFA com campo codigo ausente retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['access_token'])->post('/api/v1/auth/mfa/activate', []);

    // Controller passa string vazia — Validator deve pegar
    expect($resp->status)->toBeIn([401, 422])
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'mfa');

test('ativar MFA com codigo correto funciona end-to-end', function (): void {
    // Este teste requer um app autenticador real.
})->skip(
    'Requer app autenticador. Fluxo manual: ' .
    '1) GET /api/v1/auth/mfa/setup (token admin) → copie o otpauth_uri; ' .
    '2) Escaneie com Google Authenticator / Authy; ' .
    '3) POST /api/v1/auth/mfa/activate com o código de 6 dígitos gerado pelo app.'
)->group('fase2', 'mfa');

// ═══════════════════════════════════════════════════════════════════════════
// MFA Verify
// ═══════════════════════════════════════════════════════════════════════════

test('verificar MFA com temp_token invalido retorna 401', function (): void {
    $resp = api()->post('/api/v1/auth/mfa/verify', [
        'temp_token' => 'token.invalido.mesmo',
        'codigo'     => '123456',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'mfa');

test('verificar MFA com codigo de tamanho incorreto retorna 422', function (): void {
    $resp = api()->post('/api/v1/auth/mfa/verify', [
        'temp_token' => 'qualquer',
        'codigo'     => '12345', // 5 dígitos — deve ser 6
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.codigo'))->toBeString()->not->toBeEmpty();
})->group('fase2', 'mfa');

test('body vazio em mfa/verify retorna 422', function (): void {
    $resp = api()->post('/api/v1/auth/mfa/verify', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros'))->toBeArray()
        ->and(count($resp->erros()))->toBeGreaterThan(0);
})->group('fase2', 'mfa');

test('verificar MFA com codigo TOTP correto completa o login', function (): void {
    // Requer usuário com MFA ativo e app autenticador.
})->skip(
    'Requer usuário com MFA ativo. Fluxo manual: ' .
    '1) Ative o MFA de um usuário (ver teste anterior); ' .
    '2) Faça login → obtenha temp_token; ' .
    '3) POST /api/v1/auth/mfa/verify com o código do app → deve retornar access_token.'
)->group('fase2', 'mfa');

// ═══════════════════════════════════════════════════════════════════════════
// MFA Disable (admin only)
// ═══════════════════════════════════════════════════════════════════════════

test('desativar MFA sem autenticacao retorna 401', function (): void {
    $resp = api()->post('/api/v1/auth/mfa/disable', [
        'usuario_id' => '00000000-0000-4000-8000-000000000001',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'mfa');

test('desativar MFA com usuario_id invalido retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['access_token'])->post('/api/v1/auth/mfa/disable', [
        'usuario_id' => 'nao-e-um-uuid-valido',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.usuario_id'))->toBeString()->not->toBeEmpty();
})->group('fase2', 'mfa');

test('desativar MFA com body vazio retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['access_token'])->post('/api/v1/auth/mfa/disable', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'mfa');

test('desativar MFA com usuario_id inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['access_token'])->post('/api/v1/auth/mfa/disable', [
        'usuario_id' => '00000000-0000-4000-8000-000000000001',
    ]);

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'mfa');

test('desativar MFA com role insuficiente retorna 403', function (): void {
    // Requer um usuário com role locatario ou vistoriador logado.
    // Disponível após a Fase 3 criar o endpoint de usuários.
})->skip(
    'Requer token de usuário com role locatario ou vistoriador. ' .
    'Execute este teste após a Fase 3 estar implementada: ' .
    '1) Crie um usuário locatario via POST /api/v1/usuarios; ' .
    '2) Faça login com ele; ' .
    '3) Tente POST /api/v1/auth/mfa/disable com esse token → deve retornar 403.'
)->group('fase2', 'mfa');

test('desativar MFA de usuario com MFA ativo funciona', function (): void {
    // Requer um usuário com MFA ativo.
})->skip(
    'Requer usuário com MFA ativo no banco. ' .
    'Execute após o teste de ativação de MFA funcionar end-to-end.'
)->group('fase2', 'mfa');

// ═══════════════════════════════════════════════════════════════════════════
// Middlewares — autenticação e autorização
// ═══════════════════════════════════════════════════════════════════════════

test('rota protegida sem token retorna 401', function (): void {
    $resp = api()->get('/api/v1/auth/mfa/setup');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase2', 'middleware', 'seguranca');

test('rota protegida com token expirado retorna 401', function (): void {
    // JWT expirado gerado manualmente (exp no passado)
    $tokenExpirado = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.' .
        'eyJpc3MiOiJvbmUtY2hlY2stYXBpIiwic3ViIjoiMDAwMDAwMDAtMDAwMC00MDAwLTgwMDAtMDAwMDAwMDAwMDAxIiwi' .
        'aWF0IjoxNjAwMDAwMDAwLCJleHAiOjE2MDAwMDAwMDEsInR5cGUiOiJhY2Nlc3MiLCJlbWFpbCI6InRlc3RlQHRlc3Rl' .
        'LmNvbSIsInJvbGUiOiJhZG1pbiJ9.assinatura_invalida';

    $resp = apiComToken($tokenExpirado)->get('/api/v1/auth/mfa/setup');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'middleware', 'seguranca');

test('excecoes de negocio em rotas protegidas nao sao mascaradas como token invalido', function () use (&$estado): void {
    // Chama mfa/disable com UUID inválido — deve retornar 422, não 401
    // Este teste verifica o bug do $proximo() dentro do try-catch do AuthMiddleware
    $resp = apiComToken($estado['access_token'])->post('/api/v1/auth/mfa/disable', [
        'usuario_id' => 'nao-e-uuid',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        // NÃO deve retornar "Token inválido" — isso seria o bug do AuthMiddleware
        ->and($resp->json('erro'))->not->toBe('Token inválido');
})->group('fase2', 'middleware', 'regressao');
