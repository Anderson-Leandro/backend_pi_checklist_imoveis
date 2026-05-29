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

// ═══════════════════════════════════════════════════════════════════════════
// Refresh Token
// ═══════════════════════════════════════════════════════════════════════════

test('refresh token valido emite novo access_token e novo refresh_token', function () use (&$estado): void {
    sleep(1); // garante iat diferente no JWT

    $resp = api()->post('/api/v1/auth/refresh', [
        'refresh_token' => $estado['refresh_token'],
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.access_token'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.refresh_token'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.access_token'))->not->toBe($estado['access_token']);

    // Atualizar estado para os próximos testes
    $estado['access_token']  = $resp->json('dados.access_token');
    $estado['refresh_token'] = $resp->json('dados.refresh_token');
})->group('fase2', 'refresh');

test('refresh token usado uma vez se torna invalido na segunda chamada', function () use (&$estado): void {
    // Usar o token atual para obter novo
    $resp1 = api()->post('/api/v1/auth/refresh', ['refresh_token' => $estado['refresh_token']]);
    expect($resp1->status)->toBe(200);

    $tokenAntigo = $estado['refresh_token'];
    $estado['access_token']  = $resp1->json('dados.access_token');
    $estado['refresh_token'] = $resp1->json('dados.refresh_token');

    // Tentar usar o token antigo novamente
    $resp2 = api()->post('/api/v1/auth/refresh', ['refresh_token' => $tokenAntigo]);
    expect($resp2->status)->toBe(401)
        ->and($resp2->json('sucesso'))->toBeFalse();
})->group('fase2', 'refresh');

test('refresh token invalido retorna 401', function (): void {
    $resp = api()->post('/api/v1/auth/refresh', [
        'refresh_token' => 'token_invalido_que_nao_existe_' . uniqid(),
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'refresh');

test('body sem refresh_token retorna 422', function (): void {
    $resp = api()->post('/api/v1/auth/refresh', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.refresh_token'))->toBeString()->not->toBeEmpty();
})->group('fase2', 'refresh');

// ═══════════════════════════════════════════════════════════════════════════
// Logout
// ═══════════════════════════════════════════════════════════════════════════

test('logout sem Authorization header retorna 401', function (): void {
    $resp = api()->post('/api/v1/auth/logout', [
        'refresh_token' => 'qualquer',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase2', 'logout');

test('logout com JWT invalido retorna 401', function (): void {
    $resp = apiComToken('jwt.invalido.aqui')->post('/api/v1/auth/logout', [
        'refresh_token' => 'qualquer',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('trace'))->toBeNull();
})->group('fase2', 'logout', 'seguranca');

test('logout sem refresh_token no body retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['access_token'])->post('/api/v1/auth/logout', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.refresh_token'))->toBeString();
})->group('fase2', 'logout');

test('logout valido retorna 204 e invalida o refresh_token', function () use (&$estado): void {
    $resp = apiComToken($estado['access_token'])->post('/api/v1/auth/logout', [
        'refresh_token' => $estado['refresh_token'],
    ]);

    expect($resp->status)->toBe(204);

    // Verificar que o refresh token foi revogado
    $respRefresh = api()->post('/api/v1/auth/refresh', [
        'refresh_token' => $estado['refresh_token'],
    ]);
    expect($respRefresh->status)->toBe(401);

    $estado['refresh_token'] = ''; // marcado como revogado
})->group('fase2', 'logout');
