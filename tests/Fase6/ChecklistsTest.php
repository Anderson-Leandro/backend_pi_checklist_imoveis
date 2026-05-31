<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'admin_token'       => '',
    'vistoriador_id'    => '',
    'vistoriador_token' => '',
    'locatario_id'      => '',
    'locatario_token'   => '',
    'locatario2_id'     => '',
    'locatario2_token'  => '',
    'imovel_id'         => '',
    'comodo_id'         => '',
    'item_vistoria_id'  => '',
    'contrato_id'       => '',
    'checklist_id'      => '',
    'checklist_item_id' => '',
    'foto_id'           => '',
    'checklist_para_submeter_id' => '',
    'arquivo_jpg_tmp'   => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];

    $vistoriador = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Vistoriador F6',
        'email' => 'vist_f6_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'vistoriador',
    ]);
    $estado['vistoriador_id']    = $vistoriador['id'];
    $estado['vistoriador_token'] = $vistoriador['token'];

    $locatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F6',
        'email' => 'loc_f6_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];

    $locatario2 = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F6 B',
        'email' => 'loc_f6b_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario2_id']    = $locatario2['id'];
    $estado['locatario2_token'] = $locatario2['token'];

    // Imóvel + cômodo
    $imovel = apiComToken($admin['token'])->post('/api/v1/imoveis', [
        'tipo' => 'Apartamento', 'tamanho' => '70m²',
    ]);
    $estado['imovel_id'] = $imovel->json('dados.id');

    $comodo = apiComToken($admin['token'])->post(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos',
        ['tipo' => 'Sala', 'descricao' => 'Sala de estar']
    );
    $estado['comodo_id'] = $comodo->json('dados.id');

    // Contrato
    $contrato = apiComToken($admin['token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['imovel_id'],
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);
    $estado['contrato_id'] = $contrato->json('dados.id');

    // Item de vistoria padrão
    $itens = apiComToken($admin['token'])->get('/api/v1/itens-vistoria');
    $estado['item_vistoria_id'] = $itens->json('dados.0.id');

    // Arquivo JPG temporário (1x1 pixel JPEG real)
    $jpgContent = base64_decode(
        '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
        . 'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwh'
        . 'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAAB'
        . 'AAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/'
        . 'xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8A'
        . 'JQAB/9k='
    );
    $tmpFile = tempnam(sys_get_temp_dir(), 'f6_jpg_') . '.jpg';
    file_put_contents($tmpFile, $jpgContent);
    $estado['arquivo_jpg_tmp'] = $tmpFile;
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    if (!empty($estado['arquivo_jpg_tmp']) && file_exists($estado['arquivo_jpg_tmp'])) {
        @unlink($estado['arquivo_jpg_tmp']);
    }

    if (!empty($estado['imovel_id'])) {
        apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], ['status' => 'disponivel']);
        apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado['imovel_id']);
    }

    foreach (['vistoriador_id', 'locatario_id', 'locatario2_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado[$campo]);
        }
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/itens-vistoria
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista itens de vistoria e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/itens-vistoria');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()->not->toBeEmpty()
        ->and(uuidValido($resp->json('dados.0.id')))->toBeTrue()
        ->and($resp->json('dados.0.nome'))->toBeString()
        ->and($resp->json('dados.0.categoria'))->toBeString();
})->group('fase6', 'itens-vistoria', 'listar');

test('vistoriador lista itens de vistoria e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->get('/api/v1/itens-vistoria');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()->not->toBeEmpty();
})->group('fase6', 'itens-vistoria', 'listar');

test('locatario nao pode listar itens de vistoria e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/itens-vistoria');

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'itens-vistoria', 'listar', 'autorizacao');

test('sem token nao pode listar itens de vistoria e recebe 401', function (): void {
    $resp = api()->get('/api/v1/itens-vistoria');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'itens-vistoria', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/contratos/{id}/checklists — criar checklist
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria checklist valido e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        [
            'vistoriador_id' => $estado['vistoriador_id'],
            'tipo'           => 'inicial',
        ]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.contrato_id'))->toBe($estado['contrato_id'])
        ->and($resp->json('dados.vistoriador_id'))->toBe($estado['vistoriador_id'])
        ->and($resp->json('dados.tipo'))->toBe('inicial')
        ->and($resp->json('dados.status'))->toBe('em_preenchimento');

    $estado['checklist_id'] = $resp->json('dados.id');
})->group('fase6', 'checklists', 'criar');

test('admin cria segundo checklist para testes de submissao', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        [
            'vistoriador_id' => $estado['vistoriador_id'],
            'tipo'           => 'encerramento',
        ]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('dados.tipo'))->toBe('encerramento');

    $estado['checklist_para_submeter_id'] = $resp->json('dados.id');
})->group('fase6', 'checklists', 'criar');

test('criar checklist com body vazio retorna 422 com campos obrigatorios', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.vistoriador_id'))->toBeString()
        ->and($resp->json('erros.tipo'))->toBeString();
})->group('fase6', 'checklists', 'criar', 'validacao');

test('campo invalido ao criar checklist retorna 422', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $base = ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial'];
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        array_merge($base, [$campo => $valor])
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'vistoriador_id ausente'      => ['vistoriador_id', ''],
    'vistoriador_id nao uuid'     => ['vistoriador_id', 'nao-e-uuid'],
    'tipo ausente'                => ['tipo', ''],
    'tipo invalido'               => ['tipo', 'revisao'],
])->group('fase6', 'checklists', 'criar', 'validacao');

test('criar checklist em contrato inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/00000000-0000-4000-a000-000000000000/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial']
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'criar');

test('sem token nao pode criar checklist e recebe 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/contratos/' . $estado['contrato_id'] . '/checklists', []);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'checklists', 'criar', 'autorizacao');

test('vistoriador nao pode criar checklist e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'criar', 'autorizacao');

test('locatario nao pode criar checklist e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/contratos/{id}/checklists — listar por contrato
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista checklists do contrato e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()->not->toBeEmpty();
})->group('fase6', 'checklists', 'listar');

test('locatario lista checklists do proprio contrato e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray();
})->group('fase6', 'checklists', 'listar');

test('locatario nao acessa checklists de contrato alheio e recebe 403', function () use (&$estado): void {
    // Cria outro imóvel e contrato para locatario2
    $imovel2 = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo' => 'Casa', 'tamanho' => '50m²',
    ]);
    $imovel2Id = $imovel2->json('dados.id');

    $contrato2 = apiComToken($estado['admin_token'])->post('/api/v1/contratos', [
        'imovel_id'    => $imovel2Id,
        'locatario_id' => $estado['locatario2_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);
    $contrato2Id = $contrato2->json('dados.id');

    // locatario tenta acessar checklist do contrato de locatario2
    $resp = apiComToken($estado['locatario_token'])->get(
        '/api/v1/contratos/' . $contrato2Id . '/checklists'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();

    // Cleanup
    apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $imovel2Id, ['status' => 'disponivel']);
    apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $imovel2Id);
})->group('fase6', 'checklists', 'listar', 'autorizacao');

test('sem token nao pode listar checklists e recebe 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/contratos/' . $estado['contrato_id'] . '/checklists');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'checklists', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/checklists/{id}/itens — adicionar item
// ═══════════════════════════════════════════════════════════════════════════

test('vistoriador adiciona item ao checklist e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens',
        [
            'comodo_id'        => $estado['comodo_id'],
            'item_vistoria_id' => $estado['item_vistoria_id'],
            'estado'           => 'bom',
            'observacao'       => 'Pintura em bom estado',
        ]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.checklist_id'))->toBe($estado['checklist_id'])
        ->and($resp->json('dados.comodo_id'))->toBe($estado['comodo_id'])
        ->and($resp->json('dados.item_vistoria_id'))->toBe($estado['item_vistoria_id'])
        ->and($resp->json('dados.estado'))->toBe('bom')
        ->and($resp->json('dados.observacao'))->toBe('Pintura em bom estado')
        ->and($resp->json('dados.fotos'))->toBeArray()->toBeEmpty();

    $estado['checklist_item_id'] = $resp->json('dados.id');
})->group('fase6', 'itens', 'criar');

test('vistoriador adiciona item ao segundo checklist para teste de submissao', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_para_submeter_id'] . '/itens',
        [
            'comodo_id'        => $estado['comodo_id'],
            'item_vistoria_id' => $estado['item_vistoria_id'],
            'estado'           => 'otimo',
        ]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue();
})->group('fase6', 'itens', 'criar');

test('body vazio ao adicionar item retorna 422 com campos obrigatorios', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens',
        []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.comodo_id'))->toBeString()
        ->and($resp->json('erros.item_vistoria_id'))->toBeString()
        ->and($resp->json('erros.estado'))->toBeString();
})->group('fase6', 'itens', 'criar', 'validacao');

test('campo invalido ao adicionar item retorna 422', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $base = [
        'comodo_id'        => $estado['comodo_id'],
        'item_vistoria_id' => $estado['item_vistoria_id'],
        'estado'           => 'bom',
    ];
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens',
        array_merge($base, [$campo => $valor])
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'comodo_id ausente'        => ['comodo_id', ''],
    'comodo_id nao uuid'       => ['comodo_id', 'nao-e-uuid'],
    'item_vistoria_id ausente' => ['item_vistoria_id', ''],
    'item_vistoria_id nao uuid' => ['item_vistoria_id', 'nao-e-uuid'],
    'estado ausente'           => ['estado', ''],
    'estado invalido'          => ['estado', 'perfeito'],
])->group('fase6', 'itens', 'criar', 'validacao');

test('comodo inexistente ao adicionar item retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens',
        [
            'comodo_id'        => '00000000-0000-4000-a000-000000000000',
            'item_vistoria_id' => $estado['item_vistoria_id'],
            'estado'           => 'bom',
        ]
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'itens', 'criar');

test('admin nao pode adicionar item (apenas vistoriador) e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens',
        [
            'comodo_id'        => $estado['comodo_id'],
            'item_vistoria_id' => $estado['item_vistoria_id'],
            'estado'           => 'bom',
        ]
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'itens', 'criar', 'autorizacao');

test('sem token nao pode adicionar item e recebe 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/checklists/' . $estado['checklist_id'] . '/itens', []);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'itens', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/checklists/{id}/itens/{item_id} — atualizar item
// ═══════════════════════════════════════════════════════════════════════════

test('vistoriador atualiza item do checklist e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->put(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'],
        ['estado' => 'ruim', 'observacao' => 'Pintura danificada']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.estado'))->toBe('ruim')
        ->and($resp->json('dados.observacao'))->toBe('Pintura danificada')
        ->and($resp->json('dados.fotos'))->toBeArray();
})->group('fase6', 'itens', 'atualizar');

test('estado invalido ao atualizar item retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->put(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'],
        ['estado' => 'pessimo']
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.estado'))->toBeString();
})->group('fase6', 'itens', 'atualizar', 'validacao');

test('item inexistente ao atualizar retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->put(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/00000000-0000-4000-a000-000000000000',
        ['estado' => 'bom']
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'itens', 'atualizar');

test('admin nao pode atualizar item e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'],
        ['estado' => 'otimo']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'itens', 'atualizar', 'autorizacao');

test('sem token nao pode atualizar item e recebe 401', function () use (&$estado): void {
    $resp = api()->put(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'],
        ['estado' => 'bom']
    );

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'itens', 'atualizar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/checklists/{id}/itens/{item_id}/fotos — upload de foto
// ═══════════════════════════════════════════════════════════════════════════

test('vistoriador faz upload de foto JPG e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->postArquivo(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'] . '/fotos',
        [],
        ['foto' => $estado['arquivo_jpg_tmp']]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.url'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.checklist_item_id'))->toBe($estado['checklist_item_id']);

    $estado['foto_id'] = $resp->json('dados.id');
})->group('fase6', 'fotos', 'upload');

test('upload sem arquivo foto retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'] . '/fotos',
        []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'fotos', 'upload', 'validacao');

test('upload de arquivo PDF retorna 422', function () use (&$estado): void {
    $pdfPath = tempnam(sys_get_temp_dir(), 'f6_pdf_') . '.pdf';
    file_put_contents($pdfPath, '%PDF-1.4 teste');

    $resp = apiComToken($estado['vistoriador_token'])->postArquivo(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'] . '/fotos',
        [],
        ['foto' => $pdfPath]
    );

    @unlink($pdfPath);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'fotos', 'upload', 'validacao');

test('admin nao pode fazer upload de foto e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->postArquivo(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'] . '/fotos',
        [],
        ['foto' => $estado['arquivo_jpg_tmp']]
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'fotos', 'upload', 'autorizacao');

test('sem token nao pode fazer upload de foto e recebe 401', function () use (&$estado): void {
    $resp = api()->post(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'] . '/fotos',
        []
    );

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'fotos', 'upload', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/checklists/{id} — buscar checklist com itens e fotos
// ═══════════════════════════════════════════════════════════════════════════

test('admin busca checklist por id e retorna itens com fotos', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/checklists/' . $estado['checklist_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['checklist_id'])
        ->and($resp->json('dados.itens'))->toBeArray()->not->toBeEmpty()
        ->and($resp->json('dados.itens.0.fotos'))->toBeArray()->not->toBeEmpty();
})->group('fase6', 'checklists', 'buscar');

test('vistoriador acessa proprio checklist e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->get('/api/v1/checklists/' . $estado['checklist_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['checklist_id']);
})->group('fase6', 'checklists', 'buscar');

test('locatario acessa checklist do proprio contrato e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/checklists/' . $estado['checklist_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['checklist_id']);
})->group('fase6', 'checklists', 'buscar');

test('checklist inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/checklists/00000000-0000-4000-a000-000000000000');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'buscar');

test('sem token nao pode buscar checklist e recebe 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/checklists/' . $estado['checklist_id']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'checklists', 'buscar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// DELETE /api/v1/checklists/{id}/itens/{item_id}/fotos/{foto_id}
// ═══════════════════════════════════════════════════════════════════════════

test('vistoriador exclui foto e retorna 204', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->delete(
        '/api/v1/checklists/' . $estado['checklist_id']
        . '/itens/' . $estado['checklist_item_id']
        . '/fotos/' . $estado['foto_id']
    );

    expect($resp->status)->toBe(204);
})->group('fase6', 'fotos', 'excluir');

test('excluir foto inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->delete(
        '/api/v1/checklists/' . $estado['checklist_id']
        . '/itens/' . $estado['checklist_item_id']
        . '/fotos/00000000-0000-4000-a000-000000000000'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'fotos', 'excluir');

test('admin nao pode excluir foto e recebe 403', function () use (&$estado): void {
    // Upload nova foto para testar deleção
    $tmpFoto = tempnam(sys_get_temp_dir(), 'f6_del_') . '.jpg';
    copy($estado['arquivo_jpg_tmp'], $tmpFoto);

    $uploadResp = apiComToken($estado['vistoriador_token'])->postArquivo(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens/' . $estado['checklist_item_id'] . '/fotos',
        [],
        ['foto' => $tmpFoto]
    );
    @unlink($tmpFoto);

    $novaFotoId = $uploadResp->json('dados.id');

    $resp = apiComToken($estado['admin_token'])->delete(
        '/api/v1/checklists/' . $estado['checklist_id']
        . '/itens/' . $estado['checklist_item_id']
        . '/fotos/' . $novaFotoId
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();

    // Cleanup
    apiComToken($estado['vistoriador_token'])->delete(
        '/api/v1/checklists/' . $estado['checklist_id']
        . '/itens/' . $estado['checklist_item_id']
        . '/fotos/' . $novaFotoId
    );
})->group('fase6', 'fotos', 'excluir', 'autorizacao');

test('sem token nao pode excluir foto e recebe 401', function () use (&$estado): void {
    $resp = api()->delete(
        '/api/v1/checklists/' . $estado['checklist_id']
        . '/itens/' . $estado['checklist_item_id']
        . '/fotos/00000000-0000-4000-a000-000000000001'
    );

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'fotos', 'excluir', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PATCH /api/v1/checklists/{id}/submeter — vistoriador finaliza preenchimento
// ═══════════════════════════════════════════════════════════════════════════

test('vistoriador submete checklist com itens e retorna 200 com data_vistoria', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->patch(
        '/api/v1/checklists/' . $estado['checklist_para_submeter_id'] . '/submeter'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['checklist_para_submeter_id'])
        ->and($resp->json('dados.status'))->toBe('em_preenchimento')
        ->and($resp->json('dados.data_vistoria'))->toBeString()->not->toBeEmpty();
})->group('fase6', 'checklists', 'submeter');

test('submeter checklist sem itens retorna 422', function () use (&$estado): void {
    // Criar checklist extra sem itens
    $chk = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial']
    );
    $chkId = $chk->json('dados.id');

    $resp = apiComToken($estado['vistoriador_token'])->patch(
        '/api/v1/checklists/' . $chkId . '/submeter'
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'submeter', 'regra-negocio');

test('admin nao pode submeter checklist e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/submeter'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'submeter', 'autorizacao');

test('sem token nao pode submeter checklist e recebe 401', function () use (&$estado): void {
    $resp = api()->patch('/api/v1/checklists/' . $estado['checklist_id'] . '/submeter');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'checklists', 'submeter', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PATCH /api/v1/checklists/{id}/enviar-para-aceite — admin envia para aceite
// ═══════════════════════════════════════════════════════════════════════════

test('admin envia checklist para aceite e retorna 200 com status pendente_aceite', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/enviar-para-aceite'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['checklist_id'])
        ->and($resp->json('dados.status'))->toBe('pendente_aceite');
})->group('fase6', 'checklists', 'enviar-aceite');

test('enviar checklist sem itens para aceite retorna 422', function () use (&$estado): void {
    // Cria checklist extra sem itens
    $chk = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial']
    );
    $chkId = $chk->json('dados.id');

    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/checklists/' . $chkId . '/enviar-para-aceite'
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'enviar-aceite', 'regra-negocio');

test('enviar checklist ja em pendente_aceite retorna 422', function () use (&$estado): void {
    // O checklist principal já foi enviado para aceite no teste anterior
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/enviar-para-aceite'
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'enviar-aceite', 'regra-negocio');

test('nao pode adicionar itens a checklist com status pendente_aceite e retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/itens',
        [
            'comodo_id'        => $estado['comodo_id'],
            'item_vistoria_id' => $estado['item_vistoria_id'],
            'estado'           => 'bom',
        ]
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'itens', 'regra-negocio');

test('vistoriador nao pode enviar para aceite e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->patch(
        '/api/v1/checklists/' . $estado['checklist_id'] . '/enviar-para-aceite'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase6', 'checklists', 'enviar-aceite', 'autorizacao');

test('sem token nao pode enviar para aceite e recebe 401', function () use (&$estado): void {
    $resp = api()->patch('/api/v1/checklists/' . $estado['checklist_id'] . '/enviar-para-aceite');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase6', 'checklists', 'enviar-aceite', 'autorizacao');
