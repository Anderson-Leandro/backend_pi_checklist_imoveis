<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'admin_token'        => '',
    'locatario_id'       => '',
    'locatario_token'    => '',
    'locatario2_id'      => '',
    'locatario2_token'   => '',
    'imovel_id'          => '',
    'imovel2_id'         => '',
    'contrato_id'        => '',
    'contrato_id_para_encerrar' => '',
    'contrato_id_para_cancelar' => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];

    $locatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F5',
        'email' => 'loc_f5_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];

    $locatario2 = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F5 B',
        'email' => 'loc_f5b_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario2_id']    = $locatario2['id'];
    $estado['locatario2_token'] = $locatario2['token'];

    // Três imóveis: principal, secundário e terceiro
    foreach (['imovel_id', 'imovel2_id'] as $campo) {
        $imovel = apiComToken($admin['token'])->post('/api/v1/imoveis', [
            'tipo' => 'Apartamento', 'tamanho' => '60m²',
        ]);
        $estado[$campo] = $imovel->json('dados.id');
    }
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    foreach (['imovel_id', 'imovel2_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado[$campo], ['status' => 'disponivel']);
            apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado[$campo]);
        }
    }
    foreach (['locatario_id', 'locatario2_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado[$campo]);
        }
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/contratos — criar contrato
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria contrato valido e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['imovel_id'],
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.imovel_id'))->toBe($estado['imovel_id'])
        ->and($resp->json('dados.locatario_id'))->toBe($estado['locatario_id'])
        ->and($resp->json('dados.status'))->toBe('ativo')
        ->and($resp->json('dados.data_inicio'))->toBe('2026-01-01')
        ->and($resp->json('dados.data_fim'))->toBe('2027-01-01');

    $estado['contrato_id'] = $resp->json('dados.id');
})->group('fase5', 'contratos', 'criar');

test('criar contrato atualiza status do imovel para locado', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.status'))->toBe('locado');
})->depends('admin cria contrato valido e retorna 201')->group('fase5', 'contratos', 'criar');

test('body vazio retorna 422 com todos os campos obrigatorios', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/contratos', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.imovel_id'))->toBeString()
        ->and($resp->json('erros.locatario_id'))->toBeString()
        ->and($resp->json('erros.data_inicio'))->toBeString()
        ->and($resp->json('erros.data_fim'))->toBeString();
})->group('fase5', 'contratos', 'criar', 'validacao');

test('campo invalido retorna 422 com erro no campo correto', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $base = [
        'imovel_id'    => $estado['imovel_id'],
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ];
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/contratos',
        array_merge($base, [$campo => $valor])
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'imovel_id ausente'        => ['imovel_id', ''],
    'imovel_id nao uuid'       => ['imovel_id', 'nao-e-uuid'],
    'locatario_id ausente'     => ['locatario_id', ''],
    'locatario_id nao uuid'    => ['locatario_id', 'nao-e-uuid'],
    'data_inicio ausente'      => ['data_inicio', ''],
    'data_inicio formato errado' => ['data_inicio', '01/01/2026'],
    'data_fim ausente'         => ['data_fim', ''],
    'data_fim formato errado'  => ['data_fim', '2027-31-12'],
])->group('fase5', 'contratos', 'criar', 'validacao');

test('data_fim anterior ou igual a data_inicio retorna 422', function (
    string $dataFim
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['imovel2_id'],
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-06-01',
        'data_fim'     => $dataFim,
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.data_fim'))->toBeString()->not->toBeEmpty();
})->with([
    'data_fim igual a data_inicio'    => ['2026-06-01'],
    'data_fim anterior a data_inicio' => ['2026-05-31'],
])->group('fase5', 'contratos', 'criar', 'validacao', 'regra-negocio');

test('segundo contrato ativo no mesmo imovel retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['imovel_id'],
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-06-01',
        'data_fim'     => '2027-06-01',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase5', 'contratos', 'criar', 'regra-negocio');

test('imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/contratos', [
        'imovel_id'    => '00000000-0000-4000-a000-000000000000',
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase5', 'contratos', 'criar');

test('locatario_id com role admin retorna 422', function () use (&$estado): void {
    $adminId = loginAdmin()['usuario']['id'];

    $resp = apiComToken($estado['admin_token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['imovel2_id'],
        'locatario_id' => $adminId,
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase5', 'contratos', 'criar', 'regra-negocio');

test('criar contrato sem token retorna 401', function (): void {
    $resp = api()->post('/api/v1/contratos', []);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase5', 'contratos', 'criar', 'autorizacao');

test('locatario nao pode criar contrato e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['imovel2_id'],
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase5', 'contratos', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/contratos — listagem
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista contratos com paginacao completa', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/contratos');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()
        ->and($resp->json('paginacao.total'))->toBeInt()->toBeGreaterThan(0)
        ->and($resp->json('paginacao.pagina'))->toBeInt()
        ->and($resp->json('paginacao.itensPorPagina'))->toBeInt()
        ->and($resp->json('paginacao.totalPaginas'))->toBeInt();
})->group('fase5', 'contratos', 'listar');

test('filtro por status retorna apenas contratos com aquele status', function (
    string $status
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/contratos', ['status' => $status]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue();

    foreach ($resp->json('dados') as $contrato) {
        expect($contrato['status'])->toBe($status);
    }
})->with([
    'filtro ativo'     => ['ativo'],
    'filtro encerrado' => ['encerrado'],
    'filtro cancelado' => ['cancelado'],
])->group('fase5', 'contratos', 'listar');

test('filtro por imovel_id retorna apenas contratos daquele imovel', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/contratos', [
        'imovel_id' => $estado['imovel_id'],
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue();

    foreach ($resp->json('dados') as $contrato) {
        expect($contrato['imovel_id'])->toBe($estado['imovel_id']);
    }
})->group('fase5', 'contratos', 'listar');

test('locatario lista apenas os proprios contratos', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/contratos');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue();

    foreach ($resp->json('dados') as $contrato) {
        expect($contrato['locatario_id'])->toBe($estado['locatario_id']);
    }
})->group('fase5', 'contratos', 'listar', 'autorizacao');

test('paginacao com por_pagina=1 retorna no maximo 1 item', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/contratos', [
        'pagina' => 1, 'por_pagina' => 1,
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('paginacao.itensPorPagina'))->toBe(1)
        ->and(count($resp->json('dados')))->toBeLessThanOrEqual(1);
})->group('fase5', 'contratos', 'listar');

test('listar contratos sem token retorna 401', function (): void {
    $resp = api()->get('/api/v1/contratos');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase5', 'contratos', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/contratos/{id} — buscar por ID
// ═══════════════════════════════════════════════════════════════════════════

test('admin busca contrato por id valido', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/contratos/' . $estado['contrato_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['contrato_id'])
        ->and($resp->json('dados.status'))->toBe('ativo')
        ->and($resp->json('dados.imovel_id'))->toBe($estado['imovel_id'])
        ->and($resp->json('dados.locatario_id'))->toBe($estado['locatario_id']);
})->group('fase5', 'contratos', 'buscar');

test('locatario acessa o proprio contrato', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/contratos/' . $estado['contrato_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.locatario_id'))->toBe($estado['locatario_id']);
})->group('fase5', 'contratos', 'buscar', 'autorizacao');

test('locatario nao acessa contrato de outro e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario2_token'])->get('/api/v1/contratos/' . $estado['contrato_id']);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase5', 'contratos', 'buscar', 'autorizacao');

test('contrato inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/contratos/00000000-0000-4000-a000-000000000000');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase5', 'contratos', 'buscar');

test('buscar contrato sem token retorna 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/contratos/' . $estado['contrato_id']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase5', 'contratos', 'buscar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PATCH /api/v1/contratos/{id}/encerrar
// ═══════════════════════════════════════════════════════════════════════════

test('admin encerra contrato ativo e retorna status encerrado', function () use (&$estado): void {
    // Criar contrato dedicado para encerrar
    $imovelEnc = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo' => 'Ap Encerrar', 'tamanho' => '50m²',
    ]);
    $imovelEncId = $imovelEnc->json('dados.id');

    $contrato = apiComToken($estado['admin_token'])->post('/api/v1/contratos', [
        'imovel_id'    => $imovelEncId,
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-02-01',
        'data_fim'     => '2027-02-01',
    ]);
    $estado['contrato_id_para_encerrar'] = $contrato->json('dados.id');

    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/contratos/' . $estado['contrato_id_para_encerrar'] . '/encerrar'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.status'))->toBe('encerrado');

    // Verificar que o imóvel voltou a disponivel
    $imovel = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $imovelEncId);
    expect($imovel->json('dados.status'))->toBe('disponivel');

    // Cleanup do imóvel criado neste teste
    apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $imovelEncId);
})->group('fase5', 'contratos', 'encerrar');

test('historico preservado — contrato encerrado ainda e acessivel por ID', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/contratos/' . $estado['contrato_id_para_encerrar']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.status'))->toBe('encerrado');
})->depends('admin encerra contrato ativo e retorna status encerrado')->group('fase5', 'contratos', 'encerrar');

test('encerrar contrato ja encerrado retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/contratos/' . $estado['contrato_id_para_encerrar'] . '/encerrar'
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->depends('admin encerra contrato ativo e retorna status encerrado')->group('fase5', 'contratos', 'encerrar', 'regra-negocio');

test('encerrar contrato inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch('/api/v1/contratos/00000000-0000-4000-a000-000000000000/encerrar');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase5', 'contratos', 'encerrar');

test('encerrar contrato sem token retorna 401', function () use (&$estado): void {
    $resp = api()->patch('/api/v1/contratos/' . $estado['contrato_id'] . '/encerrar');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase5', 'contratos', 'encerrar', 'autorizacao');

test('locatario nao pode encerrar contrato e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->patch(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/encerrar'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase5', 'contratos', 'encerrar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PATCH /api/v1/contratos/{id}/cancelar
// ═══════════════════════════════════════════════════════════════════════════

test('admin cancela contrato ativo e retorna status cancelado', function () use (&$estado): void {
    // Criar contrato dedicado para cancelar
    $imovelCan = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo' => 'Ap Cancelar', 'tamanho' => '55m²',
    ]);
    $imovelCanId = $imovelCan->json('dados.id');

    $contrato = apiComToken($estado['admin_token'])->post('/api/v1/contratos', [
        'imovel_id'    => $imovelCanId,
        'locatario_id' => $estado['locatario2_id'],
        'data_inicio'  => '2026-03-01',
        'data_fim'     => '2027-03-01',
    ]);
    $estado['contrato_id_para_cancelar'] = $contrato->json('dados.id');

    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/contratos/' . $estado['contrato_id_para_cancelar'] . '/cancelar'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.status'))->toBe('cancelado');

    // Verificar que o imóvel voltou a disponivel
    $imovel = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $imovelCanId);
    expect($imovel->json('dados.status'))->toBe('disponivel');

    // Cleanup do imóvel criado neste teste
    apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $imovelCanId);
})->group('fase5', 'contratos', 'cancelar');

test('cancelar contrato ja cancelado retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/contratos/' . $estado['contrato_id_para_cancelar'] . '/cancelar'
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->depends('admin cancela contrato ativo e retorna status cancelado')->group('fase5', 'contratos', 'cancelar', 'regra-negocio');

test('cancelar contrato inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch('/api/v1/contratos/00000000-0000-4000-a000-000000000000/cancelar');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase5', 'contratos', 'cancelar');

test('cancelar contrato sem token retorna 401', function () use (&$estado): void {
    $resp = api()->patch('/api/v1/contratos/' . $estado['contrato_id'] . '/cancelar');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase5', 'contratos', 'cancelar', 'autorizacao');

test('locatario nao pode cancelar contrato e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->patch(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/cancelar'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase5', 'contratos', 'cancelar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// Integração Fase 4 + 5: locatário com contrato ativo acessa o imóvel
// ═══════════════════════════════════════════════════════════════════════════

test('locatario com contrato ativo acessa o proprio imovel', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['imovel_id']);
})->group('fase5', 'contratos', 'integracao');

test('locatario sem contrato no imovel recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario2_token'])->get('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase5', 'contratos', 'integracao');
