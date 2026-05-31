<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'admin_token'         => '',
    'vistoriador_id'      => '',
    'vistoriador_token'   => '',
    'locatario_id'        => '',
    'locatario_token'     => '',
    'outro_locatario_id'  => '',
    'outro_locatario_token' => '',
    'imovel_id'           => '',
    'comodo_id'           => '',
    'contrato_id'         => '',
    'outro_imovel_id'     => '',
    'outro_comodo_id'     => '',
    'outro_contrato_id'   => '',
    'problema_id'         => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];

    $vistoriador = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Vistoriador F9',
        'email' => 'vist_f9_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'vistoriador',
    ]);
    $estado['vistoriador_id']    = $vistoriador['id'];
    $estado['vistoriador_token'] = $vistoriador['token'];

    $locatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F9',
        'email' => 'loc_f9_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];

    $outroLocatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Outro Locatario F9',
        'email' => 'outroloc_f9_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['outro_locatario_id']    = $outroLocatario['id'];
    $estado['outro_locatario_token'] = $outroLocatario['token'];

    // Imóvel principal com cômodo para o locatário
    $imovel = apiComToken($admin['token'])->post('/api/v1/imoveis', [
        'tipo' => 'Apartamento', 'tamanho' => '70m²',
    ]);
    $estado['imovel_id'] = $imovel->json('dados.id');

    $comodo = apiComToken($admin['token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos', [
        'tipo'      => 'Sala',
        'descricao' => 'Sala de estar',
    ]);
    $estado['comodo_id'] = $comodo->json('dados.id');

    $contrato = apiComToken($admin['token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['imovel_id'],
        'locatario_id' => $estado['locatario_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);
    $estado['contrato_id'] = $contrato->json('dados.id');

    // Segundo imóvel com cômodo para o outro locatário
    $outroImovel = apiComToken($admin['token'])->post('/api/v1/imoveis', [
        'tipo' => 'Casa', 'tamanho' => '90m²',
    ]);
    $estado['outro_imovel_id'] = $outroImovel->json('dados.id');

    $outroComodo = apiComToken($admin['token'])->post('/api/v1/imoveis/' . $estado['outro_imovel_id'] . '/comodos', [
        'tipo' => 'Quarto',
    ]);
    $estado['outro_comodo_id'] = $outroComodo->json('dados.id');

    $outroContrato = apiComToken($admin['token'])->post('/api/v1/contratos', [
        'imovel_id'    => $estado['outro_imovel_id'],
        'locatario_id' => $estado['outro_locatario_id'],
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2027-01-01',
    ]);
    $estado['outro_contrato_id'] = $outroContrato->json('dados.id');
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    foreach (['vistoriador_id', 'locatario_id', 'outro_locatario_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado[$campo]);
        }
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/contratos/{id}/problemas — criar problema
// ═══════════════════════════════════════════════════════════════════════════

test('locatario cria problema valido e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas',
        [
            'comodo_id' => $estado['comodo_id'],
            'titulo'    => 'Vazamento na torneira',
            'descricao' => 'A torneira da sala está vazando constantemente.',
        ]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.contrato_id'))->toBe($estado['contrato_id'])
        ->and($resp->json('dados.comodo_id'))->toBe($estado['comodo_id'])
        ->and($resp->json('dados.titulo'))->toBe('Vazamento na torneira')
        ->and($resp->json('dados.descricao'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.status'))->toBe('aberto')
        ->and($resp->json('dados.foto_url'))->toBeNull()
        ->and($resp->json('dados.created_at'))->toBeString()->not->toBeEmpty();

    $estado['problema_id'] = $resp->json('dados.id');
})->group('fase9', 'problemas', 'criar');

test('admin cria problema no contrato e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas',
        [
            'comodo_id' => $estado['comodo_id'],
            'titulo'    => 'Problema criado pelo admin',
            'descricao' => 'Descrição do problema criado pelo administrador do sistema.',
        ]
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.status'))->toBe('aberto');
})->group('fase9', 'problemas', 'criar');

test('body vazio ao criar problema retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas',
        []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.comodo_id'))->toBeString()
        ->and($resp->json('erros.titulo'))->toBeString()
        ->and($resp->json('erros.descricao'))->toBeString();
})->group('fase9', 'problemas', 'criar', 'validacao');

test('campo obrigatorio faltando ao criar problema retorna 422', function (
    string $campoAusente
) use (&$estado): void {
    $base = [
        'comodo_id' => $estado['comodo_id'],
        'titulo'    => 'Problema de teste',
        'descricao' => 'Descrição do problema de teste para validação de campos.',
    ];
    unset($base[$campoAusente]);

    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas',
        $base
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campoAusente}"))->toBeString()->not->toBeEmpty();
})->with([
    'comodo_id ausente' => ['comodo_id'],
    'titulo ausente'    => ['titulo'],
    'descricao ausente' => ['descricao'],
])->group('fase9', 'problemas', 'criar', 'validacao');

test('comodo_id invalido ao criar problema retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas',
        [
            'comodo_id' => 'uuid-invalido',
            'titulo'    => 'Problema',
            'descricao' => 'Descrição válida do problema para teste.',
        ]
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.comodo_id'))->toBeString()->not->toBeEmpty();
})->group('fase9', 'problemas', 'criar', 'validacao');

test('comodo nao pertencente ao imovel retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas',
        [
            'comodo_id' => $estado['outro_comodo_id'],
            'titulo'    => 'Tentativa de cômodo errado',
            'descricao' => 'Tentativa de usar cômodo de outro imóvel no registro.',
        ]
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'criar');

test('locatario nao pode criar problema em contrato de outro locatario e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['outro_locatario_token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas',
        [
            'comodo_id' => $estado['comodo_id'],
            'titulo'    => 'Tentativa de acesso indevido',
            'descricao' => 'O outro locatário não deveria conseguir criar problema neste contrato.',
        ]
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'criar', 'autorizacao');

test('criar problema em contrato inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/contratos/00000000-0000-4000-a000-000000000000/problemas',
        [
            'comodo_id' => $estado['comodo_id'],
            'titulo'    => 'Problema em contrato inexistente',
            'descricao' => 'Tentativa de criar problema em contrato que não existe no banco.',
        ]
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'criar');

test('sem token nao pode criar problema e recebe 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/contratos/' . $estado['contrato_id'] . '/problemas', []);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase9', 'problemas', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/contratos/{id}/problemas — listar problemas do contrato
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista problemas do contrato e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()->not->toBeEmpty()
        ->and(uuidValido($resp->json('dados.0.id')))->toBeTrue()
        ->and($resp->json('dados.0.titulo'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.0.status'))->toBeString();
})->group('fase9', 'problemas', 'listar');

test('locatario lista seus problemas e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()->not->toBeEmpty();
})->group('fase9', 'problemas', 'listar');

test('locatario nao pode listar problemas de outro contrato e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['outro_locatario_token'])->get(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/problemas'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'listar', 'autorizacao');

test('listar problemas de contrato inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/contratos/00000000-0000-4000-a000-000000000000/problemas'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'listar');

test('sem token nao pode listar problemas e recebe 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/contratos/' . $estado['contrato_id'] . '/problemas');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase9', 'problemas', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/problemas/{id} — buscar problema por ID
// ═══════════════════════════════════════════════════════════════════════════

test('admin busca problema por id e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/problemas/' . $estado['problema_id']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['problema_id'])
        ->and($resp->json('dados.contrato_id'))->toBe($estado['contrato_id'])
        ->and($resp->json('dados.status'))->toBe('aberto');
})->group('fase9', 'problemas', 'buscar');

test('locatario busca seu proprio problema e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get(
        '/api/v1/problemas/' . $estado['problema_id']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['problema_id']);
})->group('fase9', 'problemas', 'buscar');

test('outro locatario nao pode ver problema alheio e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['outro_locatario_token'])->get(
        '/api/v1/problemas/' . $estado['problema_id']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'buscar', 'autorizacao');

test('problema inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/problemas/00000000-0000-4000-a000-000000000000'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'buscar');

test('sem token nao pode buscar problema e recebe 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/problemas/' . $estado['problema_id']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase9', 'problemas', 'buscar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PATCH /api/v1/problemas/{id}/status — alterar status do problema
// ═══════════════════════════════════════════════════════════════════════════

test('admin altera status do problema para em_andamento e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/problemas/' . $estado['problema_id'] . '/status',
        ['status' => 'em_andamento']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['problema_id'])
        ->and($resp->json('dados.status'))->toBe('em_andamento');
})->group('fase9', 'problemas', 'status');

test('admin altera status para resolvido e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/problemas/' . $estado['problema_id'] . '/status',
        ['status' => 'resolvido']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.status'))->toBe('resolvido');
})->group('fase9', 'problemas', 'status');

test('admin restaura status para aberto e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/problemas/' . $estado['problema_id'] . '/status',
        ['status' => 'aberto']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.status'))->toBe('aberto');
})->group('fase9', 'problemas', 'status');

test('status invalido ao alterar retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/problemas/' . $estado['problema_id'] . '/status',
        ['status' => 'invalido']
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.status'))->toBeString()->not->toBeEmpty();
})->group('fase9', 'problemas', 'status', 'validacao');

test('status ausente ao alterar retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/problemas/' . $estado['problema_id'] . '/status',
        []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.status'))->toBeString();
})->group('fase9', 'problemas', 'status', 'validacao');

test('problema inexistente ao alterar status retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->patch(
        '/api/v1/problemas/00000000-0000-4000-a000-000000000000/status',
        ['status' => 'resolvido']
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'status');

test('locatario nao pode alterar status do problema e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->patch(
        '/api/v1/problemas/' . $estado['problema_id'] . '/status',
        ['status' => 'resolvido']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'problemas', 'status', 'autorizacao');

test('sem token nao pode alterar status e recebe 401', function () use (&$estado): void {
    $resp = api()->patch('/api/v1/problemas/' . $estado['problema_id'] . '/status', ['status' => 'resolvido']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase9', 'problemas', 'status', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/problemas/{id}/atualizacoes — criar atualização
// ═══════════════════════════════════════════════════════════════════════════

test('admin adiciona atualizacao ao problema e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes',
        ['descricao' => 'Encaminhamos um técnico para verificar o vazamento amanhã.']
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.problema_id'))->toBe($estado['problema_id'])
        ->and($resp->json('dados.autor_id'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.descricao'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.foto_url'))->toBeNull()
        ->and($resp->json('dados.created_at'))->toBeString()->not->toBeEmpty();
})->group('fase9', 'atualizacoes', 'criar');

test('admin adiciona segunda atualizacao e retorna 201', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes',
        ['descricao' => 'O técnico compareceu e identificou o problema na vedação.']
    );

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.descricao'))->toBeString()->not->toBeEmpty();
})->group('fase9', 'atualizacoes', 'criar');

test('descricao ausente ao criar atualizacao retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes',
        []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.descricao'))->toBeString()->not->toBeEmpty();
})->group('fase9', 'atualizacoes', 'criar', 'validacao');

test('problema inexistente ao criar atualizacao retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/problemas/00000000-0000-4000-a000-000000000000/atualizacoes',
        ['descricao' => 'Atualização para problema que não existe no sistema.']
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'atualizacoes', 'criar');

test('locatario nao pode adicionar atualizacao e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes',
        ['descricao' => 'Tentativa do locatário de adicionar atualização.']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'atualizacoes', 'criar', 'autorizacao');

test('vistoriador nao pode adicionar atualizacao e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes',
        ['descricao' => 'Tentativa do vistoriador de adicionar atualização.']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'atualizacoes', 'criar', 'autorizacao');

test('sem token nao pode adicionar atualizacao e recebe 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes', []);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase9', 'atualizacoes', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/problemas/{id}/atualizacoes — listar atualizações
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista atualizacoes em ordem cronologica e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()->not->toBeEmpty()
        ->and(count($resp->json('dados')))->toBeGreaterThanOrEqual(2)
        ->and(uuidValido($resp->json('dados.0.id')))->toBeTrue()
        ->and($resp->json('dados.0.problema_id'))->toBe($estado['problema_id'])
        ->and($resp->json('dados.0.descricao'))->toBeString()->not->toBeEmpty();
})->group('fase9', 'atualizacoes', 'listar');

test('locatario lista atualizacoes do seu problema e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()->not->toBeEmpty();
})->group('fase9', 'atualizacoes', 'listar');

test('outro locatario nao pode listar atualizacoes de problema alheio e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['outro_locatario_token'])->get(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'atualizacoes', 'listar', 'autorizacao');

test('problema inexistente ao listar atualizacoes retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/problemas/00000000-0000-4000-a000-000000000000/atualizacoes'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase9', 'atualizacoes', 'listar');

test('sem token nao pode listar atualizacoes e recebe 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase9', 'atualizacoes', 'listar', 'autorizacao');

test('atualizacoes retornam em ordem cronologica ascendente', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/problemas/' . $estado['problema_id'] . '/atualizacoes'
    );

    $atualizacoes = $resp->json('dados');
    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($atualizacoes)->toBeArray();

    if (count($atualizacoes) >= 2) {
        expect(strtotime($atualizacoes[0]['created_at']))->toBeLessThanOrEqual(
            strtotime($atualizacoes[1]['created_at'])
        );
    }
})->group('fase9', 'atualizacoes', 'listar');
