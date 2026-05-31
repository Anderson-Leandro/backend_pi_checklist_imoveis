<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'admin_token'      => '',
    'admin_id'         => '',
    'locatario_token'  => '',
    'locatario_id'     => '',
    'vistoriador_token' => '',
    'vistoriador_id'   => '',
    'usuario_criado_id' => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];
    $estado['admin_id']    = $admin['usuario']['id'];

    $locatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F11',
        'email' => 'loc_f11_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];

    $vistoriador = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Vistoriador F11',
        'email' => 'vist_f11_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'vistoriador',
    ]);
    $estado['vistoriador_id']    = $vistoriador['id'];
    $estado['vistoriador_token'] = $vistoriador['token'];
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    foreach (['locatario_id', 'vistoriador_id', 'usuario_criado_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado[$campo]);
        }
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/logs — acesso e autorização
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista logs e recebe 200 com estrutura paginada', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()
        ->and($resp->json('paginacao.total'))->toBeInt()
        ->and($resp->json('paginacao.pagina'))->toBe(1)
        ->and($resp->json('paginacao.itensPorPagina'))->toBeInt();
})->group('fase11', 'logs');

test('locatario nao pode listar logs e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/logs');

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase11', 'logs');

test('vistoriador nao pode listar logs e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->get('/api/v1/logs');

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase11', 'logs');

test('requisicao sem token retorna 401 ao tentar listar logs', function (): void {
    $resp = api()->get('/api/v1/logs');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase11', 'logs');

// ═══════════════════════════════════════════════════════════════════════════
// Cobertura de logs — login bem-sucedido e falho
// ═══════════════════════════════════════════════════════════════════════════

test('login bem-sucedido gera log de acao LOGIN', function () use (&$estado): void {
    // Faz um login para garantir que o log existe
    api()->post('/api/v1/auth/login', [
        'email' => 'admin@onecheck.com.br',
        'senha' => 'Admin@1234',
    ]);

    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs', [
        'entidade'   => 'usuario',
        'usuario_id' => $estado['admin_id'],
    ]);

    $itens = $resp->json('dados') ?? [];
    $acoesLogin = array_filter($itens, fn($log) => $log['acao'] === 'LOGIN');

    expect($resp->status)->toBe(200)
        ->and(count($acoesLogin))->toBeGreaterThanOrEqual(1);
})->group('fase11', 'logs');

test('login com credenciais erradas gera log de acao LOGIN_FALHO', function () use (&$estado): void {
    // Dispara um login falho
    api()->post('/api/v1/auth/login', [
        'email' => 'admin@onecheck.com.br',
        'senha'  => 'SenhaErrada!',
    ]);

    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs', [
        'entidade' => 'usuario',
    ]);

    $itens = $resp->json('dados') ?? [];
    $falhos = array_filter($itens, fn($log) => $log['acao'] === 'LOGIN_FALHO');

    expect($resp->status)->toBe(200)
        ->and(count($falhos))->toBeGreaterThanOrEqual(1);
})->group('fase11', 'logs');

// ═══════════════════════════════════════════════════════════════════════════
// Cobertura de logs — operações de escrita geram registro
// ═══════════════════════════════════════════════════════════════════════════

test('criar usuario gera exatamente um log de acao CREATE', function () use (&$estado): void {
    $emailNovo = 'novo_f11_' . uniqid() . '@teste.com';

    $criacao = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Novo F11',
        'email' => $emailNovo,
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    expect($criacao->status)->toBe(201);
    $estado['usuario_criado_id'] = $criacao->json('dados.id');

    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs', [
        'entidade' => 'usuario',
    ]);

    $itens      = $resp->json('dados') ?? [];
    $logsCreate = array_filter(
        $itens,
        fn($log) => $log['acao'] === 'CREATE' && $log['entidade_id'] === $estado['usuario_criado_id']
    );

    expect(count($logsCreate))->toBe(1);
})->group('fase11', 'logs');

// ═══════════════════════════════════════════════════════════════════════════
// Payload seguro — sem campos sensíveis
// ═══════════════════════════════════════════════════════════════════════════

test('payload dos logs nao contem senha_hash nem mfa_secret', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs');

    expect($resp->status)->toBe(200);

    assertSemCamposSensiveis($resp);

    $itens = $resp->json('dados') ?? [];
    foreach ($itens as $log) {
        $payload = $log['payload'] ?? null;
        if (is_array($payload)) {
            expect(array_key_exists('senha_hash', $payload))->toBeFalse()
                ->and(array_key_exists('mfa_secret', $payload))->toBeFalse()
                ->and(array_key_exists('senha', $payload))->toBeFalse();
        }
    }
})->group('fase11', 'logs');

// ═══════════════════════════════════════════════════════════════════════════
// Filtros
// ═══════════════════════════════════════════════════════════════════════════

test('filtro por entidade retorna apenas logs da entidade solicitada', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs', [
        'entidade' => 'imovel',
    ]);

    expect($resp->status)->toBe(200);

    $itens = $resp->json('dados') ?? [];
    foreach ($itens as $log) {
        expect($log['entidade'])->toBe('imovel');
    }
})->group('fase11', 'logs');

test('filtro por usuario_id retorna apenas logs do usuario informado', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs', [
        'usuario_id' => $estado['admin_id'],
    ]);

    expect($resp->status)->toBe(200);

    $itens = $resp->json('dados') ?? [];
    foreach ($itens as $log) {
        expect($log['usuario_id'])->toBe($estado['admin_id']);
    }
})->group('fase11', 'logs');

test('filtro por periodo retorna apenas logs no intervalo de datas', function () use (&$estado): void {
    $hoje = date('Y-m-d');

    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs', [
        'de'  => $hoje,
        'ate' => $hoje,
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue();

    $itens = $resp->json('dados') ?? [];
    foreach ($itens as $log) {
        $dataLog = substr($log['created_at'], 0, 10);
        expect($dataLog)->toBeGreaterThanOrEqual($hoje)
            ->and($dataLog)->toBeLessThanOrEqual($hoje);
    }
})->group('fase11', 'logs');

test('filtro por periodo futuro retorna lista vazia', function () use (&$estado): void {
    $futuro = date('Y-m-d', strtotime('+10 years'));

    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs', [
        'de'  => $futuro,
        'ate' => $futuro,
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados'))->toBeArray()
        ->and(count($resp->json('dados') ?? []))->toBe(0);
})->group('fase11', 'logs');

// ═══════════════════════════════════════════════════════════════════════════
// Paginação
// ═══════════════════════════════════════════════════════════════════════════

test('paginacao retorna o numero correto de itens por pagina', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/logs', [
        'pagina'    => 1,
        'por_pagina' => 5,
    ]);

    expect($resp->status)->toBe(200)
        ->and(count($resp->json('dados') ?? []))->toBeLessThanOrEqual(5)
        ->and($resp->json('paginacao.itensPorPagina'))->toBe(5);
})->group('fase11', 'logs');
