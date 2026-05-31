<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'admin_token'       => '',
    'vistoriador_id'    => '',
    'vistoriador_token' => '',
    'locatario_id'      => '',
    'locatario_token'   => '',
    'imovel_id'         => '',
    'contrato_id'       => '',
    'agendamento_id'    => '',
    'data_futura'       => '',
    'data_futura2'      => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];

    // Data futura para os testes
    $estado['data_futura']  = date('Y-m-d H:i:s', strtotime('+30 days'));
    $estado['data_futura2'] = date('Y-m-d H:i:s', strtotime('+60 days'));

    $vistoriador = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Vistoriador F8',
        'email' => 'vist_f8_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'vistoriador',
    ]);
    $estado['vistoriador_id']    = $vistoriador['id'];
    $estado['vistoriador_token'] = $vistoriador['token'];

    $locatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F8',
        'email' => 'loc_f8_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];

    // Imóvel
    $imovel = apiComToken($admin['token'])->post('/api/v1/imoveis', [
        'tipo' => 'Apartamento', 'tamanho' => '60m²',
    ]);
    $estado['imovel_id'] = $imovel->json('dados.id');

    // Contrato
    $contrato = apiComToken($admin['token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['imovel_id'],
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);
    $estado['contrato_id'] = $contrato->json('dados.id');
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    if (!empty($estado['imovel_id'])) {
        apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], ['status' => 'disponivel']);
        apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado['imovel_id']);
    }

    foreach (['vistoriador_id', 'locatario_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado[$campo]);
        }
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/contratos/{id}/agendamentos — criar agendamento
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria agendamento valido e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos',
        [
            'tipo'          => 'inicial',
            'data_agendada' => $estado['data_futura'],
            'observacao'    => 'Primeira vistoria do imóvel',
        ]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.contrato_id'))->toBe($estado['contrato_id'])
        ->and($resp->json('dados.tipo'))->toBe('inicial')
        ->and($resp->json('dados.data_agendada'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.observacao'))->toBe('Primeira vistoria do imóvel');

    $estado['agendamento_id'] = $resp->json('dados.id');
})->group('fase8', 'agendamentos', 'criar');

test('admin cria agendamento sem observacao e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos',
        [
            'tipo'          => 'encerramento',
            'data_agendada' => $estado['data_futura2'],
        ]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('encerramento')
        ->and($resp->json('dados.observacao'))->toBeNull();
})->group('fase8', 'agendamentos', 'criar');

test('body vazio ao criar agendamento retorna 422 com campos obrigatorios', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos',
        []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.tipo'))->toBeString()
        ->and($resp->json('erros.data_agendada'))->toBeString();
})->group('fase8', 'agendamentos', 'criar', 'validacao');

test('campo invalido ao criar agendamento retorna 422', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $base = ['tipo' => 'inicial', 'data_agendada' => $estado['data_futura']];
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos',
        array_merge($base, [$campo => $valor])
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'tipo ausente'           => ['tipo', ''],
    'tipo invalido'          => ['tipo', 'revisao'],
    'data_agendada ausente'  => ['data_agendada', ''],
    'data_agendada invalida' => ['data_agendada', 'nao-e-data'],
])->group('fase8', 'agendamentos', 'criar', 'validacao');

test('data no passado ao criar agendamento retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos',
        [
            'tipo'          => 'inicial',
            'data_agendada' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.data_agendada'))->toBeString()->not->toBeEmpty();
})->group('fase8', 'agendamentos', 'criar', 'validacao');

test('criar agendamento em contrato inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/00000000-0000-4000-a000-000000000000/agendamentos',
        ['tipo' => 'inicial', 'data_agendada' => $estado['data_futura']]
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'criar');

test('vistoriador nao pode criar agendamento e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos',
        ['tipo' => 'inicial', 'data_agendada' => $estado['data_futura']]
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'criar', 'autorizacao');

test('locatario nao pode criar agendamento e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos',
        ['tipo' => 'inicial', 'data_agendada' => $estado['data_futura']]
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'criar', 'autorizacao');

test('sem token nao pode criar agendamento e recebe 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos', []);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase8', 'agendamentos', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/contratos/{id}/agendamentos — listar agendamentos
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista agendamentos do contrato e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()->not->toBeEmpty()
        ->and(uuidValido($resp->json('dados.0.id')))->toBeTrue()
        ->and($resp->json('dados.0.tipo'))->toBeString()
        ->and($resp->json('dados.0.data_agendada'))->toBeString();
})->group('fase8', 'agendamentos', 'listar');

test('vistoriador lista agendamentos e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray();
})->group('fase8', 'agendamentos', 'listar');

test('locatario nao pode listar agendamentos e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'listar', 'autorizacao');

test('sem token nao pode listar agendamentos e recebe 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase8', 'agendamentos', 'listar', 'autorizacao');

test('listar agendamentos de contrato inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/contratos/00000000-0000-4000-a000-000000000000/agendamentos'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'listar');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/agendamentos/{id} — atualizar agendamento
// ═══════════════════════════════════════════════════════════════════════════

test('admin atualiza agendamento e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/agendamentos/' . $estado['agendamento_id'],
        [
            'tipo'          => 'encerramento',
            'data_agendada' => $estado['data_futura2'],
            'observacao'    => 'Observação atualizada',
        ]
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['agendamento_id'])
        ->and($resp->json('dados.tipo'))->toBe('encerramento')
        ->and($resp->json('dados.observacao'))->toBe('Observação atualizada');
})->group('fase8', 'agendamentos', 'atualizar');

test('admin atualiza apenas o tipo do agendamento e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/agendamentos/' . $estado['agendamento_id'],
        ['tipo' => 'inicial']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('inicial');
})->group('fase8', 'agendamentos', 'atualizar');

test('tipo invalido ao atualizar agendamento retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/agendamentos/' . $estado['agendamento_id'],
        ['tipo' => 'periodica']
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.tipo'))->toBeString();
})->group('fase8', 'agendamentos', 'atualizar', 'validacao');

test('data no passado ao atualizar agendamento retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/agendamentos/' . $estado['agendamento_id'],
        ['data_agendada' => date('Y-m-d H:i:s', strtotime('-2 days'))]
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.data_agendada'))->toBeString();
})->group('fase8', 'agendamentos', 'atualizar', 'validacao');

test('agendamento inexistente ao atualizar retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/agendamentos/00000000-0000-4000-a000-000000000000',
        ['tipo' => 'inicial']
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'atualizar');

test('vistoriador nao pode atualizar agendamento e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->put(
        '/api/v1/agendamentos/' . $estado['agendamento_id'],
        ['tipo' => 'inicial']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'atualizar', 'autorizacao');

test('sem token nao pode atualizar agendamento e recebe 401', function () use (&$estado): void {
    $resp = api()->put('/api/v1/agendamentos/' . $estado['agendamento_id'], ['tipo' => 'inicial']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase8', 'agendamentos', 'atualizar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// DELETE /api/v1/agendamentos/{id} — excluir agendamento
// ═══════════════════════════════════════════════════════════════════════════

test('vistoriador nao pode excluir agendamento e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->delete(
        '/api/v1/agendamentos/' . $estado['agendamento_id']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'excluir', 'autorizacao');

test('sem token nao pode excluir agendamento e recebe 401', function () use (&$estado): void {
    $resp = api()->delete('/api/v1/agendamentos/' . $estado['agendamento_id']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase8', 'agendamentos', 'excluir', 'autorizacao');

test('excluir agendamento inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete(
        '/api/v1/agendamentos/00000000-0000-4000-a000-000000000000'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase8', 'agendamentos', 'excluir');

test('admin exclui agendamento e retorna 204', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete(
        '/api/v1/agendamentos/' . $estado['agendamento_id']
    );

    expect($resp->status)->toBe(204);
})->group('fase8', 'agendamentos', 'excluir');

test('agendamento excluido nao e retornado na listagem', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/agendamentos'
    );

    $ids = array_column($resp->json('dados') ?? [], 'id');
    expect($resp->status)->toBe(200)
        ->and($ids)->not->toContain($estado['agendamento_id']);
})->group('fase8', 'agendamentos', 'excluir');
