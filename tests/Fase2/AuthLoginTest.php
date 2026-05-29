<?php

declare(strict_types=1);

// ── Estado compartilhado no escopo do arquivo ─────────────────────────────────
$estado = [
    'access_token'  => '',
    'refresh_token' => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    // Nenhuma preparação necessária — o seed já criou o admin
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    if ($estado['refresh_token']) {
        // Revogar qualquer refresh token aberto
        api()->post('/api/v1/auth/logout', ['refresh_token' => $estado['refresh_token']]);
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// Happy paths
// ═══════════════════════════════════════════════════════════════════════════

test('login valido sem MFA retorna access_token refresh_token e usuario', function () use (&$estado): void {
    $resp = api()->post('/api/v1/auth/login', [
        'email' => 'admin@onecheck.com.br',
        'senha' => 'Admin@1234',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.access_token'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.refresh_token'))->toBeString()->not->toBeEmpty()
        ->and(uuidValido($resp->json('dados.usuario.id')))->toBeTrue()
        ->and($resp->json('dados.usuario.nome'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.usuario.email'))->toBe('admin@onecheck.com.br')
        ->and($resp->json('dados.usuario.role'))->toBe('admin')
        ->and($resp->json('dados.mfa_required'))->not->toBeTrue();

    assertSemCamposSensiveis($resp);

    $estado['access_token']  = $resp->json('dados.access_token');
    $estado['refresh_token'] = $resp->json('dados.refresh_token');
})->group('fase2', 'login');

test('login com MFA ativo retorna mfa_required true e temp_token', function (): void {
    // Requer um usuário com MFA já ativado no banco.
    // Como o seed não cria tal usuário, este teste é pulado.
})->skip(
    'Requer usuário com MFA ativo no banco. ' .
    'Teste manual: 1) Crie um usuário; 2) Chame GET /api/v1/auth/mfa/setup; ' .
    '3) Escaneie o QR com um app autenticador; 4) Ative via POST /api/v1/auth/mfa/activate; ' .
    '5) Faça login com esse usuário e verifique mfa_required: true.'
)->group('fase2', 'login', 'mfa');

// ═══════════════════════════════════════════════════════════════════════════
// Erros de validação — campos obrigatórios e formatos
// ═══════════════════════════════════════════════════════════════════════════

test('body vazio retorna 422 com erros nos campos obrigatorios', function (): void {
    $resp = api()->post('/api/v1/auth/login', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros'))->toBeArray()
        ->and(count($resp->erros()))->toBeGreaterThan(0);
})->group('fase2', 'login');

test('email invalido retorna 422 com erro especifico no campo email', function (string $email): void {
    $resp = api()->post('/api/v1/auth/login', [
        'email' => $email,
        'senha' => 'Qualquer@1234',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.email'))->toBeString()->not->toBeEmpty();
})->with([
    'sem arroba'       => ['naoemail'],
    'sem dominio'      => ['user@'],
    'sem usuario'      => ['@dominio.com'],
    'string com espaco'=> ['user @email.com'],
])->group('fase2', 'login');

test('senha ausente retorna 422 com erro no campo senha', function (): void {
    $resp = api()->post('/api/v1/auth/login', [
        'email' => 'admin@onecheck.com.br',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.senha'))->toBeString()->not->toBeEmpty();
})->group('fase2', 'login');

// ═══════════════════════════════════════════════════════════════════════════
// Erros de autenticação — credenciais inválidas
// ═══════════════════════════════════════════════════════════════════════════

test('email nao cadastrado retorna 401 com mensagem generica', function (): void {
    $resp = api()->post('/api/v1/auth/login', [
        'email' => 'nao_existe_' . uniqid() . '@teste.com',
        'senha' => 'Qualquer@1234',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString()
        // Mensagem genérica — não deve revelar se o campo errado é email ou senha
        ->and($resp->json('erro'))->toContain('Credenciais');
})->group('fase2', 'login');

test('senha incorreta retorna 401 com mesma mensagem generica', function (): void {
    $resp = api()->post('/api/v1/auth/login', [
        'email' => 'admin@onecheck.com.br',
        'senha' => 'SenhaErrada@' . uniqid(),
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toContain('Credenciais');
})->group('fase2', 'login');

test('resposta de erro nao expoe dados internos nem stack trace', function (): void {
    $resp = api()->post('/api/v1/auth/login', [
        'email' => 'naoexiste@teste.com',
        'senha' => 'Qualquer@1234',
    ]);

    expect($resp->json('trace'))->toBeNull()
        ->and($resp->json('file'))->toBeNull()
        ->and($resp->json('line'))->toBeNull();
})->group('fase2', 'login', 'seguranca');
