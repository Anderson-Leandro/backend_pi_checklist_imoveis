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
    'imovel_id_deletado' => '',
    'comodo_id'         => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];

    $vistoriador = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Vistoriador F4',
        'email' => 'vist_f4_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'vistoriador',
    ]);
    $estado['vistoriador_id']    = $vistoriador['id'];
    $estado['vistoriador_token'] = $vistoriador['token'];

    $locatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F4',
        'email' => 'loc_f4_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    foreach (['imovel_id', 'imovel_id_deletado'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado[$campo], ['status' => 'disponivel']);
            apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado[$campo]);
        }
    }
    foreach (['vistoriador_id', 'locatario_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado[$campo]);
        }
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/imoveis — criar imóvel
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria imovel com endereco e retorna 201 com dados completos', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo'          => 'Apartamento',
        'tamanho'       => '75m²',
        'garagem'       => true,
        'garagem_vagas' => 2,
        'endereco'      => [
            'rua'    => 'Avenida Paulista',
            'numero' => '1578',
            'cidade' => 'São Paulo',
            'estado' => 'SP',
            'cep'    => '01310-200',
        ],
    ]);

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('Apartamento')
        ->and($resp->json('dados.tamanho'))->toBe('75m²')
        ->and($resp->json('dados.garagem'))->toBe(1)
        ->and($resp->json('dados.garagem_vagas'))->toBe(2)
        ->and($resp->json('dados.status'))->toBe('disponivel')
        ->and($resp->json('dados.endereco'))->toBeArray()
        ->and($resp->json('dados.endereco.rua'))->toBe('Avenida Paulista')
        ->and($resp->json('dados.endereco.cidade'))->toBe('São Paulo')
        ->and($resp->json('dados.endereco.estado'))->toBe('SP')
        ->and($resp->json('dados.endereco.cep'))->toBe('01310-200')
        ->and($resp->json('dados.endereco.imovel_id'))->toBe($resp->json('dados.id'));

    $estado['imovel_id'] = $resp->json('dados.id');
})->group('fase4', 'imoveis', 'criar');

test('admin cria imovel sem endereco retorna 201 com endereco null', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo'    => 'Casa',
        'tamanho' => '120m²',
    ]);

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.endereco'))->toBeNull();

    // Limpa o imóvel criado
    apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $resp->json('dados.id'));
})->group('fase4', 'imoveis', 'criar');

test('criar imovel com endereco invalido retorna 422 com erros no endereco', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $enderecoBase = [
        'rua' => 'Rua Válida', 'numero' => '1',
        'cidade' => 'Cidade', 'estado' => 'SP', 'cep' => '01310-200',
    ];
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo'     => 'Apartamento',
        'tamanho'  => '80m²',
        'endereco' => array_merge($enderecoBase, [$campo => $valor]),
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.endereco'))->toBeArray()
        ->and($resp->json("erros.endereco.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'rua ausente'      => ['rua', ''],
    'cep sem mascara'  => ['cep', '01310200'],
    'estado tamanho 1' => ['estado', 'S'],
    'cidade ausente'   => ['cidade', ''],
])->group('fase4', 'imoveis', 'criar', 'validacao');

test('body vazio retorna 422 com erros nos campos obrigatorios', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.tipo'))->toBeString()
        ->and($resp->json('erros.tamanho'))->toBeString();
})->group('fase4', 'imoveis', 'criar', 'validacao');

test('campo tipo invalido retorna 422 com erro no campo tipo', function (
    string $valor
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo'    => $valor,
        'tamanho' => '60m²',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.tipo'))->toBeString()->not->toBeEmpty();
})->with([
    'tipo ausente'    => [''],
    'tipo muito curto' => ['a'],
])->group('fase4', 'imoveis', 'criar', 'validacao');

test('campo tamanho invalido retorna 422 com erro no campo tamanho', function (
    string $valor
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo'    => 'Apartamento',
        'tamanho' => $valor,
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.tamanho'))->toBeString()->not->toBeEmpty();
})->with([
    'tamanho ausente' => [''],
])->group('fase4', 'imoveis', 'criar', 'validacao');

test('criar imovel sem token retorna 401', function (): void {
    $resp = api()->post('/api/v1/imoveis', ['tipo' => 'Casa', 'tamanho' => '60m²']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'criar', 'autorizacao');

test('locatario nao pode criar imovel e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post('/api/v1/imoveis', [
        'tipo' => 'Casa', 'tamanho' => '60m²',
    ]);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase4', 'imoveis', 'criar', 'autorizacao');

test('vistoriador nao pode criar imovel e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post('/api/v1/imoveis', [
        'tipo' => 'Casa', 'tamanho' => '60m²',
    ]);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase4', 'imoveis', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/imoveis — listar
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista imoveis com paginacao completa e endereco embutido', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()
        ->and($resp->json('paginacao.total'))->toBeInt()->toBeGreaterThan(0)
        ->and($resp->json('paginacao.pagina'))->toBeInt()
        ->and($resp->json('paginacao.itensPorPagina'))->toBeInt()
        ->and($resp->json('paginacao.totalPaginas'))->toBeInt();

    // Todos os itens devem ter a chave 'endereco' (pode ser null ou array)
    foreach ($resp->json('dados') as $imovel) {
        expect(array_key_exists('endereco', $imovel))->toBeTrue();
    }
})->group('fase4', 'imoveis', 'listar');

test('filtro por status retorna apenas imoveis com aquele status', function (
    string $status
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis', ['status' => $status]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue();

    foreach ($resp->json('dados') as $imovel) {
        expect($imovel['status'])->toBe($status);
    }
})->with([
    'status disponivel'   => ['disponivel'],
    'status em_vistoria'  => ['em_vistoria'],
    'status locado'       => ['locado'],
])->group('fase4', 'imoveis', 'listar');

test('paginacao com por_pagina=1 retorna no maximo 1 item', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis', [
        'pagina' => 1, 'por_pagina' => 1,
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('paginacao.itensPorPagina'))->toBe(1)
        ->and(count($resp->json('dados')))->toBeLessThanOrEqual(1);
})->group('fase4', 'imoveis', 'listar');

test('listar imoveis sem token retorna 401', function (): void {
    $resp = api()->get('/api/v1/imoveis');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'listar', 'autorizacao');

test('locatario nao pode listar imoveis e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/imoveis');

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase4', 'imoveis', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/imoveis/{id} — buscar por ID
// ═══════════════════════════════════════════════════════════════════════════

test('admin busca imovel por id valido e retorna endereco embutido', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['imovel_id'])
        ->and($resp->json('dados.tipo'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.status'))->toBeString()
        ->and(array_key_exists('endereco', $resp->json('dados')))->toBeTrue()
        ->and($resp->json('dados.endereco'))->toBeArray()
        ->and($resp->json('dados.endereco.rua'))->toBe('Avenida Paulista')
        ->and($resp->json('dados.endereco.imovel_id'))->toBe($estado['imovel_id']);
})->group('fase4', 'imoveis', 'buscar');

test('buscar imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/00000000-0000-4000-a000-000000000000');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase4', 'imoveis', 'buscar');

test('buscar imovel sem token retorna 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'buscar', 'autorizacao');

test('locatario sem contrato ativo recebe 403 ao buscar imovel', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase4', 'imoveis', 'buscar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/imoveis/{id} — atualizar
// ═══════════════════════════════════════════════════════════════════════════

test('admin atualiza campos do imovel e resposta inclui endereco', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], [
        'tipo'   => 'Casa',
        'status' => 'em_vistoria',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('Casa')
        ->and($resp->json('dados.status'))->toBe('em_vistoria')
        ->and(array_key_exists('endereco', $resp->json('dados')))->toBeTrue();
})->group('fase4', 'imoveis', 'atualizar');

test('admin atualiza imovel enviando endereco junto atualiza ambos', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], [
        'tipo'     => 'Cobertura',
        'endereco' => [
            'rua'    => 'Rua das Flores',
            'numero' => '99',
            'cidade' => 'Campinas',
            'estado' => 'SP',
            'cep'    => '13010-050',
        ],
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('Cobertura')
        ->and($resp->json('dados.endereco.rua'))->toBe('Rua das Flores')
        ->and($resp->json('dados.endereco.cidade'))->toBe('Campinas')
        ->and($resp->json('dados.endereco.cep'))->toBe('13010-050');
})->group('fase4', 'imoveis', 'atualizar');

test('atualizar imovel com endereco invalido retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], [
        'endereco' => [
            'rua'    => 'Rua X',
            'numero' => '1',
            'cidade' => 'Cidade',
            'estado' => 'SP',
            'cep'    => '00000000',
        ],
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.endereco.cep'))->toBeString()->not->toBeEmpty();
})->group('fase4', 'imoveis', 'atualizar', 'validacao');

test('campo invalido na atualizacao retorna 422', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $estado['imovel_id'], [
        $campo => $valor,
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'tipo muito curto' => ['tipo', 'a'],
    'status invalido'  => ['status', 'invalido'],
])->group('fase4', 'imoveis', 'atualizar', 'validacao');

test('atualizar imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/imoveis/00000000-0000-4000-a000-000000000000', [
        'tipo' => 'Qualquer',
    ]);

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase4', 'imoveis', 'atualizar');

test('atualizar imovel sem token retorna 401', function () use (&$estado): void {
    $resp = api()->put('/api/v1/imoveis/' . $estado['imovel_id'], ['tipo' => 'X']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'atualizar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/imoveis/{id}/endereco — salvar endereço (upsert)
// ═══════════════════════════════════════════════════════════════════════════

test('admin salva endereco do imovel e retorna dados com estrutura correta', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', [
        'rua'    => 'Avenida Paulista',
        'numero' => '1578',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'cep'    => '01310-200',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.imovel_id'))->toBe($estado['imovel_id'])
        ->and($resp->json('dados.rua'))->toBe('Avenida Paulista')
        ->and($resp->json('dados.cidade'))->toBe('São Paulo')
        ->and($resp->json('dados.estado'))->toBe('SP')
        ->and($resp->json('dados.cep'))->toBe('01310-200');

    $lat = $resp->json('dados.latitude');
    $lng = $resp->json('dados.longitude');
    expect($lat === null || is_string($lat))->toBeTrue();
    expect($lng === null || is_string($lng))->toBeTrue();
})->group('fase4', 'imoveis', 'endereco');

test('segundo POST no endereco sobrescreve o anterior sem duplicar', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', [
        'rua'    => 'Rua das Flores',
        'numero' => '99',
        'cidade' => 'Campinas',
        'estado' => 'SP',
        'cep'    => '13010-050',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.rua'))->toBe('Rua das Flores')
        ->and($resp->json('dados.numero'))->toBe('99')
        ->and($resp->json('dados.cidade'))->toBe('Campinas');
})->group('fase4', 'imoveis', 'endereco');

test('campo invalido no endereco retorna 422 com erro no campo correto', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $base = [
        'rua' => 'Rua Valida', 'numero' => '1',
        'cidade' => 'Cidade', 'estado' => 'SP', 'cep' => '01310-200',
    ];
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco',
        array_merge($base, [$campo => $valor])
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'rua ausente'        => ['rua', ''],
    'rua muito curta'    => ['rua', 'a'],
    'numero ausente'     => ['numero', ''],
    'cidade ausente'     => ['cidade', ''],
    'estado ausente'     => ['estado', ''],
    'estado tamanho 1'   => ['estado', 'S'],
    'estado tamanho 3'   => ['estado', 'SPA'],
    'cep ausente'        => ['cep', ''],
    'cep sem mascara'    => ['cep', '01310200'],
    'cep formato errado' => ['cep', '1310-200'],
])->group('fase4', 'imoveis', 'endereco', 'validacao');

test('body vazio no endereco retorna 422 com multiplos erros', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros'))->toBeArray()
        ->and(count($resp->erros()))->toBeGreaterThan(1);
})->group('fase4', 'imoveis', 'endereco', 'validacao');

test('salvar endereco sem token retorna 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', [
        'rua' => 'Rua', 'numero' => '1', 'cidade' => 'Cidade', 'estado' => 'SP', 'cep' => '01310-200',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'endereco', 'autorizacao');

test('salvar endereco em imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/00000000-0000-4000-a000-000000000000/endereco', [
        'rua' => 'Rua', 'numero' => '1', 'cidade' => 'Cidade', 'estado' => 'SP', 'cep' => '01310-200',
    ]);

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase4', 'imoveis', 'endereco');

// ── GET endereco ──────────────────────────────────────────────────────────

test('admin busca endereco do imovel', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.imovel_id'))->toBe($estado['imovel_id'])
        ->and($resp->json('dados.rua'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.cidade'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.cep'))->toBeString()->not->toBeEmpty();
})->group('fase4', 'imoveis', 'endereco');

test('buscar endereco de imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/00000000-0000-4000-a000-000000000000/endereco');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase4', 'imoveis', 'endereco');

test('buscar endereco sem token retorna 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'endereco', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/imoveis/{id}/comodos — criar cômodo
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria comodo no imovel e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos', [
        'tipo'      => 'Sala de Estar',
        'descricao' => 'Sala ampla com varanda',
    ]);

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('Sala de Estar')
        ->and($resp->json('dados.descricao'))->toBe('Sala ampla com varanda')
        ->and($resp->json('dados.imovel_id'))->toBe($estado['imovel_id']);

    $estado['comodo_id'] = $resp->json('dados.id');
})->group('fase4', 'imoveis', 'comodos', 'criar');

test('campo tipo invalido no comodo retorna 422', function (
    string $valor
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos', [
        'tipo' => $valor,
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.tipo'))->toBeString()->not->toBeEmpty();
})->with([
    'tipo ausente'     => [''],
    'tipo muito curto' => ['a'],
])->group('fase4', 'imoveis', 'comodos', 'criar', 'validacao');

test('criar comodo em imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/00000000-0000-4000-a000-000000000000/comodos', [
        'tipo' => 'Quarto',
    ]);

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase4', 'imoveis', 'comodos', 'criar');

test('criar comodo sem token retorna 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos', ['tipo' => 'Quarto']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'comodos', 'criar', 'autorizacao');

test('locatario nao pode criar comodo e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos', [
        'tipo' => 'Quarto',
    ]);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase4', 'imoveis', 'comodos', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/imoveis/{id}/comodos — listar cômodos
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista comodos do imovel', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()
        ->and(count($resp->json('dados')))->toBeGreaterThan(0);

    expect($resp->json('dados.0.imovel_id'))->toBe($estado['imovel_id']);
})->group('fase4', 'imoveis', 'comodos', 'listar');

test('vistoriador pode listar comodos e recebe 200', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray();
})->group('fase4', 'imoveis', 'comodos', 'listar', 'autorizacao');

test('locatario nao pode listar comodos e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos');

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase4', 'imoveis', 'comodos', 'listar', 'autorizacao');

test('listar comodos sem token retorna 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'comodos', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/imoveis/{id}/comodos/{comodo_id} — atualizar cômodo
// ═══════════════════════════════════════════════════════════════════════════

test('admin atualiza tipo e descricao do comodo', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/' . $estado['comodo_id'],
        ['tipo' => 'Sala de Jantar', 'descricao' => 'Ambiente integrado']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.tipo'))->toBe('Sala de Jantar')
        ->and($resp->json('dados.descricao'))->toBe('Ambiente integrado');
})->group('fase4', 'imoveis', 'comodos', 'atualizar');

test('atualizar comodo com tipo invalido retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/' . $estado['comodo_id'],
        ['tipo' => 'a']
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.tipo'))->toBeString();
})->group('fase4', 'imoveis', 'comodos', 'atualizar', 'validacao');

test('atualizar comodo inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/00000000-0000-4000-a000-000000000000',
        ['tipo' => 'Quarto']
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase4', 'imoveis', 'comodos', 'atualizar');

test('atualizar comodo sem token retorna 401', function () use (&$estado): void {
    $resp = api()->put(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/' . $estado['comodo_id'],
        ['tipo' => 'Teste']
    );

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'comodos', 'atualizar', 'autorizacao');

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
    $ids  = array_column($resp->json('dados') ?? [], 'id');
    expect(in_array($estado['comodo_id'], $ids, strict: true))->toBeFalse();
})->depends('admin exclui comodo e retorna 204')->group('fase4', 'imoveis', 'comodos', 'deletar');

test('excluir comodo inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/00000000-0000-4000-a000-000000000000'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase4', 'imoveis', 'comodos', 'deletar');

test('excluir comodo sem token retorna 401', function () use (&$estado): void {
    $temp   = apiComToken($estado['admin_token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos', [
        'tipo' => 'Temp',
    ]);
    $tempId = $temp->json('dados.id');

    $resp = api()->delete('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/' . $tempId);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();

    apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos/' . $tempId);
})->group('fase4', 'imoveis', 'comodos', 'deletar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// DELETE /api/v1/imoveis/{id} — excluir imóvel
// ═══════════════════════════════════════════════════════════════════════════

test('nao pode excluir imovel com status locado e recebe 422', function () use (&$estado): void {
    $temp   = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo' => 'Temp Locado', 'tamanho' => '40m²',
    ]);
    $tempId = $temp->json('dados.id');
    apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $tempId, ['status' => 'locado']);

    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $tempId);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();

    apiComToken($estado['admin_token'])->put('/api/v1/imoveis/' . $tempId, ['status' => 'disponivel']);
    apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $tempId);
})->group('fase4', 'imoveis', 'deletar', 'regra-negocio');

test('excluir imovel inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/00000000-0000-4000-a000-000000000000');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase4', 'imoveis', 'deletar');

test('excluir imovel sem token retorna 401', function () use (&$estado): void {
    $resp = api()->delete('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase4', 'imoveis', 'deletar', 'autorizacao');

test('ao excluir imovel o endereco tambem e soft-deletado', function () use (&$estado): void {
    // Cria imóvel com endereço
    $criarResp = apiComToken($estado['admin_token'])->post('/api/v1/imoveis', [
        'tipo'     => 'Studio',
        'tamanho'  => '30m²',
        'endereco' => [
            'rua'    => 'Rua do Teste',
            'numero' => '10',
            'cidade' => 'São Paulo',
            'estado' => 'SP',
            'cep'    => '01310-200',
        ],
    ]);

    expect($criarResp->status)->toBe(201);
    $tempId = $criarResp->json('dados.id');

    // Confirma que o endereço está acessível antes do delete
    $antes = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $tempId . '/endereco');
    expect($antes->status)->toBe(200);

    // Deleta o imóvel
    $deleteResp = apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $tempId);
    expect($deleteResp->status)->toBe(204);

    // Endereço deve estar inacessível via API (soft-deleted, ativo = 0)
    $depois = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $tempId . '/endereco');
    expect($depois->status)->toBe(404);
})->group('fase4', 'imoveis', 'deletar', 'endereco');

test('admin exclui imovel disponivel e retorna 204', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/imoveis/' . $estado['imovel_id']);

    expect($resp->status)->toBe(204);
    $estado['imovel_id_deletado'] = $estado['imovel_id'];
    $estado['imovel_id']          = '';
})->group('fase4', 'imoveis', 'deletar');

test('imovel excluido nao aparece na listagem', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/imoveis/' . $estado['imovel_id_deletado']);

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->depends('admin exclui imovel disponivel e retorna 204')->group('fase4', 'imoveis', 'deletar');
