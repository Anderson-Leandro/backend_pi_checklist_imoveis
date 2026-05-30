<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'admin_token'     => '',
    'vistoriador_id'  => '',
    'vistoriador_token' => '',
    'locatario_id'    => '',
    'locatario_token' => '',
    'imovel_id'       => '',
    'comodo_id'       => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];

    $vistoriador = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Vistoriador Fase4',
        'email' => 'vistoriador_f4_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'vistoriador',
    ]);
    $estado['vistoriador_id']    = $vistoriador['id'];
    $estado['vistoriador_token'] = $vistoriador['token'];

    $locatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario Fase4',
        'email' => 'locatario_f4_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    if (!empty($estado['imovel_id'])) {
        apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado['imovel_id']);
    }
    if (!empty($estado['vistoriador_id'])) {
        apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado['vistoriador_id']);
    }
    if (!empty($estado['locatario_id'])) {
        apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado['locatario_id']);
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/imoveis — criar imóvel
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria imovel valido e retorna 201 com UUID', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo'          => 'Apartamento',
        'tamanho'       => '75m²',
        'garagem'       => true,
        'garagem_vagas' => 1,
    ]);

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('Apartamento')
        ->and($resp->json('dados.tamanho'))->toBe('75m²')
        ->and($resp->json('dados.garagem'))->toBe(1)
        ->and($resp->json('dados.garagem_vagas'))->toBe(1)
        ->and($resp->json('dados.status'))->toBe('disponivel');

    $estado['imovel_id'] = $resp->json('dados.id');
})->group('fase4', 'imoveis', 'criar');

test('criar imovel sem campos obrigatorios retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.tipo'))->toBeString()
        ->and($resp->json('erros.tamanho'))->toBeString();
})->group('fase4', 'imoveis', 'criar', 'validacao');

test('criar imovel sem token retorna 401', function (): void {
    $resp = api()->post('/api/v1/imoveis', ['tipo' => 'Casa', 'tamanho' => '60m²']);
    expect($resp->status)->toBe(401);
})->group('fase4', 'imoveis', 'criar', 'autorizacao');

test('locatario nao pode criar imovel retorna 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post('/api/v1/imoveis', [
        'tipo' => 'Casa', 'tamanho' => '60m²',
    ]);
    expect($resp->status)->toBe(403);
})->group('fase4', 'imoveis', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/imoveis — listar
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista imoveis com paginacao', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()
        ->and($resp->json('paginacao.total'))->toBeInt()->toBeGreaterThan(0)
        ->and($resp->json('paginacao.pagina'))->toBe(1);
})->group('fase4', 'imoveis', 'listar');

test('filtro por status retorna apenas imoveis com aquele status', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis', ['status' => 'disponivel']);

    expect($resp->status)->toBe(200);
    foreach ($resp->json('dados') as $imovel) {
        expect($imovel['status'])->toBe('disponivel');
    }
})->group('fase4', 'imoveis', 'listar');

test('locatario nao pode listar imoveis retorna 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/imoveis');
    expect($resp->status)->toBe(403);
})->group('fase4', 'imoveis', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/imoveis/{id} — buscar por ID
// ═══════════════════════════════════════════════════════════════════════════

test('admin busca imovel por id', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.id'))->toBe($estado['imovel_id'])
        ->and($resp->json('dados.tipo'))->toBe('Apartamento');
})->group('fase4', 'imoveis', 'buscar');

test('buscar imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/00000000-0000-4000-a000-000000000000');
    expect($resp->status)->toBe(404);
})->group('fase4', 'imoveis', 'buscar');

test('locatario sem contrato ativo recebe 403 ao buscar imovel', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/imoveis/' . $estado['imovel_id']);
    expect($resp->status)->toBe(403);
})->group('fase4', 'imoveis', 'buscar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/imoveis/{id} — atualizar
// ═══════════════════════════════════════════════════════════════════════════

test('admin atualiza tipo e status do imovel', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], [
        'tipo'   => 'Casa',
        'status' => 'em_vistoria',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.tipo'))->toBe('Casa')
        ->and($resp->json('dados.status'))->toBe('em_vistoria');
})->group('fase4', 'imoveis', 'atualizar');

test('atualizar status com valor invalido retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], [
        'status' => 'invalido',
    ]);
    expect($resp->status)->toBe(422);
})->group('fase4', 'imoveis', 'atualizar', 'validacao');

test('atualizar imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/imoveis/00000000-0000-4000-a000-000000000000', [
        'tipo' => 'Qualquer',
    ]);
    expect($resp->status)->toBe(404);
})->group('fase4', 'imoveis', 'atualizar');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/imoveis/{id}/endereco — salvar endereço
// ═══════════════════════════════════════════════════════════════════════════

test('admin salva endereco do imovel com coordenadas', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', [
        'rua'    => 'Rua das Flores',
        'numero' => '123',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'cep'    => '01310-100',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.imovel_id'))->toBe($estado['imovel_id'])
        ->and($resp->json('dados.rua'))->toBe('Rua das Flores')
        ->and($resp->json('dados.estado'))->toBe('SP')
        ->and($resp->json('dados.cep'))->toBe('01310-100');
    // lat/lng podem ser null se Nominatim não estiver acessível no container
})->group('fase4', 'imoveis', 'endereco');

test('endereco retorna latitude e longitude quando geocodificacao funciona', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco');

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.imovel_id'))->toBe($estado['imovel_id']);

    // lat/lng podem ser null em ambiente sem acesso externo — apenas verifica estrutura
    $lat = $resp->json('dados.latitude');
    $lng = $resp->json('dados.longitude');
    expect($lat === null || is_string($lat))->toBeTrue();
    expect($lng === null || is_string($lng))->toBeTrue();
})->group('fase4', 'imoveis', 'endereco');

test('salvar endereco sobrescreve o endereco anterior (upsert)', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', [
        'rua'    => 'Avenida Paulista',
        'numero' => '900',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'cep'    => '01310-100',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.rua'))->toBe('Avenida Paulista')
        ->and($resp->json('dados.numero'))->toBe('900');
})->group('fase4', 'imoveis', 'endereco');

test('salvar endereco com CEP invalido retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', [
        'rua'    => 'Rua Teste',
        'numero' => '1',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'cep'    => '00000000',
    ]);
    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.cep'))->toBeString();
})->group('fase4', 'imoveis', 'endereco', 'validacao');

test('salvar endereco sem campos obrigatorios retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase4', 'imoveis', 'endereco', 'validacao');

test('buscar endereco de imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/00000000-0000-4000-a000-000000000000/endereco');
    expect($resp->status)->toBe(404);
})->group('fase4', 'imoveis', 'endereco');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/imoveis/{id}/comodos — criar cômodo
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria comodo no imovel e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos', [
        'tipo'      => 'Sala',
        'descricao' => 'Sala de estar com varanda',
    ]);

    expect($resp->status)->toBe(201)
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('Sala')
        ->and($resp->json('dados.imovel_id'))->toBe($estado['imovel_id']);

    $estado['comodo_id'] = $resp->json('dados.id');
})->group('fase4', 'imoveis', 'comodos', 'criar');

test('criar comodo sem tipo retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos', []);
    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.tipo'))->toBeString();
})->group('fase4', 'imoveis', 'comodos', 'criar', 'validacao');

test('criar comodo em imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/00000000-0000-4000-a000-000000000000/comodos', [
        'tipo' => 'Quarto',
    ]);
    expect($resp->status)->toBe(404);
})->group('fase4', 'imoveis', 'comodos', 'criar');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/imoveis/{id}/comodos — listar cômodos
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista comodos do imovel', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos');

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados'))->toBeArray()
        ->and(count($resp->json('dados')))->toBeGreaterThan(0);
})->group('fase4', 'imoveis', 'comodos', 'listar');

test('vistoriador pode listar comodos', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos');
    expect($resp->status)->toBe(200);
})->group('fase4', 'imoveis', 'comodos', 'listar', 'autorizacao');

test('locatario nao pode listar comodos retorna 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos');
    expect($resp->status)->toBe(403);
})->group('fase4', 'imoveis', 'comodos', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/imoveis/{id}/comodos/{comodo_id} — atualizar cômodo
// ═══════════════════════════════════════════════════════════════════════════

test('admin atualiza comodo', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/' . $estado['comodo_id'],
        ['tipo' => 'Sala de Jantar', 'descricao' => 'Sala atualizada']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.tipo'))->toBe('Sala de Jantar')
        ->and($resp->json('dados.descricao'))->toBe('Sala atualizada');
})->group('fase4', 'imoveis', 'comodos', 'atualizar');

test('atualizar comodo inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/00000000-0000-4000-a000-000000000000',
        ['tipo' => 'Quarto']
    );
    expect($resp->status)->toBe(404);
})->group('fase4', 'imoveis', 'comodos', 'atualizar');

// ═══════════════════════════════════════════════════════════════════════════
// DELETE /api/v1/imoveis/{id}/comodos/{comodo_id} — excluir cômodo
// ═══════════════════════════════════════════════════════════════════════════

test('admin exclui comodo e retorna 204', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/' . $estado['comodo_id']
    );

    expect($resp->status)->toBe(204);
    $estado['comodo_id'] = '';
})->group('fase4', 'imoveis', 'comodos', 'deletar');

test('comodo excluido nao aparece mais na listagem', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos');

    $ids = array_column($resp->json('dados') ?? [], 'id');
    expect(in_array($estado['comodo_id'], $ids))->toBeFalse();
})->depends('admin exclui comodo e retorna 204')->group('fase4', 'imoveis', 'comodos', 'deletar');

// ═══════════════════════════════════════════════════════════════════════════
// DELETE /api/v1/imoveis/{id} — excluir imóvel
// ═══════════════════════════════════════════════════════════════════════════

test('nao pode excluir imovel com status locado', function () use (&$estado): void {
    // Primeiro marcar como locado
    apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], [
        'status' => 'locado',
    ]);

    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado['imovel_id']);
    expect($resp->status)->toBe(422);

    // Reverter para disponivel para o teardown poder excluir
    apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], [
        'status' => 'disponivel',
    ]);
})->group('fase4', 'imoveis', 'deletar');

test('admin exclui imovel disponivel e retorna 204', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado['imovel_id']);
    expect($resp->status)->toBe(204);
    $estado['imovel_id'] = '';
})->group('fase4', 'imoveis', 'deletar');

test('imovel excluido nao aparece mais na listagem', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis');
    $ids  = array_column($resp->json('dados') ?? [], 'id');
    expect(in_array($estado['imovel_id'], $ids))->toBeFalse();
})->depends('admin exclui imovel disponivel e retorna 204')->group('fase4', 'imoveis', 'deletar');

test('excluir imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/00000000-0000-4000-a000-000000000000');
    expect($resp->status)->toBe(404);
})->group('fase4', 'imoveis', 'deletar');
