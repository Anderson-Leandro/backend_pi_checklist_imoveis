<?php

declare(strict_types=1);

// ── Estado compartilhado entre testes do arquivo ──────────────────────────────
$estado = [
    'admin_token'    => '',
    'usuario_id'     => '',
    'locatario_id'   => '',
    'locatario_token' => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin               = loginAdmin();
    $estado['admin_token'] = $admin['token'];
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    // Limpar usuários criados nos testes
    foreach (['usuario_id', 'locatario_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado[$campo]);
        }
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/usuarios — criação
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria usuario com dados validos e retorna 201', function () use (&$estado): void {
    $email = 'vistoriador_' . uniqid() . '@teste.com';

    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Vistoriador Teste',
        'email' => $email,
        'senha' => 'Teste@1234',
        'role'  => 'vistoriador',
    ]);

    expect($resp->status)->toBe(201)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.nome'))->toBe('Vistoriador Teste')
        ->and($resp->json('dados.email'))->toBe($email)
        ->and($resp->json('dados.role'))->toBe('vistoriador')
        ->and($resp->json('dados.created_at'))->toBeString()->not->toBeEmpty();

    assertSemCamposSensiveis($resp);

    $estado['usuario_id'] = $resp->json('dados.id');
})->group('fase3', 'usuarios', 'criar');

test('criacao gera UUID valido automaticamente', function () use (&$estado): void {
    expect(uuidValido($estado['usuario_id']))->toBeTrue();
})->depends('admin cria usuario com dados validos e retorna 201')->group('fase3', 'usuarios', 'criar');

test('email duplicado retorna 422 com mensagem clara', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Outro Nome',
        'email' => 'admin@onecheck.com.br', // e-mail já existente do seed
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString()
        ->and(strtolower($resp->json('erro')))->toContain('e-mail');
})->group('fase3', 'usuarios', 'criar');

test('criar usuario sem nome retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'email' => 'sem_nome@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.nome'))->toBeString()->not->toBeEmpty();
})->group('fase3', 'usuarios', 'criar', 'validacao');

test('criar usuario com email invalido retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Nome Válido',
        'email' => 'nao-eh-email',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.email'))->toBeString()->not->toBeEmpty();
})->group('fase3', 'usuarios', 'criar', 'validacao');

test('criar usuario com senha menor que 8 caracteres retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Nome Válido',
        'email' => 'curta@teste.com',
        'senha' => '123',
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.senha'))->toBeString()->not->toBeEmpty();
})->group('fase3', 'usuarios', 'criar', 'validacao');

test('criar usuario com role invalida retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Nome Válido',
        'email' => 'role@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'superadmin',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.role'))->toBeString()->not->toBeEmpty();
})->group('fase3', 'usuarios', 'criar', 'validacao');

test('senha nunca retorna na resposta de criacao', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Sem Senha Na Resposta',
        'email' => 'sem_senha_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    assertSemCamposSensiveis($resp);

    // Cleanup imediato
    if ($resp->status === 201) {
        apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $resp->json('dados.id'));
    }
})->group('fase3', 'usuarios', 'criar', 'seguranca');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/usuarios — listagem
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista usuarios com paginacao', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()
        ->and($resp->json('paginacao.total'))->toBeInt()->toBeGreaterThan(0)
        ->and($resp->json('paginacao.pagina'))->toBe(1)
        ->and($resp->json('paginacao.itensPorPagina'))->toBeInt()
        ->and($resp->json('paginacao.totalPaginas'))->toBeInt();

    assertSemCamposSensiveis($resp);
})->group('fase3', 'usuarios', 'listar');

test('filtro por role retorna apenas usuarios com aquela role', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios', ['role' => 'admin']);

    expect($resp->status)->toBe(200);

    foreach ($resp->json('dados') as $usuario) {
        expect($usuario['role'])->toBe('admin');
    }
})->group('fase3', 'usuarios', 'listar');

test('paginacao funciona com por_pagina personalizado', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios', [
        'pagina'    => 1,
        'por_pagina' => 1,
    ]);

    expect($resp->status)->toBe(200)
        ->and(count($resp->json('dados')))->toBeLessThanOrEqual(1)
        ->and($resp->json('paginacao.itensPorPagina'))->toBe(1);
})->group('fase3', 'usuarios', 'listar');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/usuarios/{id} — busca por ID
// ═══════════════════════════════════════════════════════════════════════════

test('admin busca usuario por id valido', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios/' . $estado['usuario_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['usuario_id'])
        ->and($resp->json('dados.nome'))->toBe('Vistoriador Teste')
        ->and($resp->json('dados.role'))->toBe('vistoriador');

    assertSemCamposSensiveis($resp);
})->group('fase3', 'usuarios', 'buscar');

test('buscar usuario com id inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios/00000000-0000-4000-a000-000000000000');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'buscar');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/usuarios/{id} — atualização
// ═══════════════════════════════════════════════════════════════════════════

test('admin atualiza nome do usuario', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/' . $estado['usuario_id'], [
        'nome' => 'Vistoriador Atualizado',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.nome'))->toBe('Vistoriador Atualizado')
        ->and($resp->json('dados.id'))->toBe($estado['usuario_id']);

    assertSemCamposSensiveis($resp);
})->group('fase3', 'usuarios', 'atualizar');

test('admin atualiza role do usuario', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/' . $estado['usuario_id'], [
        'role' => 'locatario',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.role'))->toBe('locatario');
})->group('fase3', 'usuarios', 'atualizar');

test('atualizar usuario com email ja em uso retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/' . $estado['usuario_id'], [
        'email' => 'admin@onecheck.com.br', // e-mail do admin
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'atualizar');

test('atualizar usuario inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/00000000-0000-4000-a000-000000000000', [
        'nome' => 'Qualquer',
    ]);

    expect($resp->status)->toBe(404);
})->group('fase3', 'usuarios', 'atualizar');

// ═══════════════════════════════════════════════════════════════════════════
// DELETE /api/v1/usuarios/{id} — soft delete
// ═══════════════════════════════════════════════════════════════════════════

test('admin nao pode deletar a propria conta', function () use (&$estado): void {
    $admin = loginAdmin();
    $adminId = $admin['usuario']['id'];

    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $adminId);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'deletar');

test('admin deleta usuario existente e retorna 204', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado['usuario_id']);

    expect($resp->status)->toBe(204);
})->group('fase3', 'usuarios', 'deletar');

test('usuario deletado nao aparece mais na listagem', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios/' . $estado['usuario_id']);

    expect($resp->status)->toBe(404);

    $estado['usuario_id'] = ''; // Evitar dupla tentativa no afterAll
})->depends('admin deleta usuario existente e retorna 204')->group('fase3', 'usuarios', 'deletar');

test('usuario deletado nao consegue fazer login', function () use (&$estado): void {
    // Criar e imediatamente deletar
    $email = 'del_' . uniqid() . '@teste.com';
    $cria  = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Para Deletar',
        'email' => $email,
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    $id = $cria->json('dados.id');
    apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $id);

    $loginResp = api()->post('/api/v1/auth/login', [
        'email' => $email,
        'senha' => 'Teste@1234',
    ]);

    expect($loginResp->status)->toBe(401);
})->group('fase3', 'usuarios', 'deletar', 'seguranca');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/usuarios/me — perfil próprio
// ═══════════════════════════════════════════════════════════════════════════

test('qualquer role acessa GET /me com dados proprios', function () use (&$estado): void {
    // Criar locatário para este teste
    $email = 'loc_' . uniqid() . '@teste.com';
    $loc   = criarUsuarioELogar($estado['admin_token'], [
        'nome'  => 'Locatário Me',
        'email' => $email,
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    $estado['locatario_id']    = $loc['id'];
    $estado['locatario_token'] = $loc['token'];

    $resp = apiComToken($loc['token'])->get('/api/v1/usuarios/me');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.email'))->toBe($email)
        ->and($resp->json('dados.role'))->toBe('locatario');

    assertSemCamposSensiveis($resp);
})->group('fase3', 'usuarios', 'me');

test('admin acessa GET /me e recebe dados do proprio admin', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios/me');

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.email'))->toBe('admin@onecheck.com.br')
        ->and($resp->json('dados.role'))->toBe('admin');

    assertSemCamposSensiveis($resp);
})->group('fase3', 'usuarios', 'me');

test('GET /me sem token retorna 401', function (): void {
    $resp = api()->get('/api/v1/usuarios/me');
    expect($resp->status)->toBe(401);
})->group('fase3', 'usuarios', 'me');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/usuarios/me/senha — alterar própria senha
// ═══════════════════════════════════════════════════════════════════════════

test('usuario altera a propria senha com sucesso', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->put('/api/v1/usuarios/me/senha', [
        'senha_atual' => 'Teste@1234',
        'nova_senha'  => 'Nova@5678',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue();
})->group('fase3', 'usuarios', 'senha');

test('login com nova senha funciona apos alteracao', function () use (&$estado): void {
    $meResp = apiComToken($estado['locatario_token'])->get('/api/v1/usuarios/me');
    $email  = $meResp->json('dados.email');

    $loginResp = api()->post('/api/v1/auth/login', [
        'email' => $email,
        'senha' => 'Nova@5678',
    ]);

    expect($loginResp->status)->toBe(200);
})->depends('usuario altera a propria senha com sucesso')->group('fase3', 'usuarios', 'senha');

test('login com senha antiga falha apos alteracao', function () use (&$estado): void {
    $meResp = apiComToken($estado['locatario_token'])->get('/api/v1/usuarios/me');
    $email  = $meResp->json('dados.email');

    $loginResp = api()->post('/api/v1/auth/login', [
        'email' => $email,
        'senha' => 'Teste@1234', // senha antiga
    ]);

    expect($loginResp->status)->toBe(401);
})->depends('usuario altera a propria senha com sucesso')->group('fase3', 'usuarios', 'senha');

test('alterar senha com senha_atual incorreta retorna 401', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->put('/api/v1/usuarios/me/senha', [
        'senha_atual' => 'SenhaErrada@999',
        'nova_senha'  => 'Nova@Qualquer',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'senha');

test('alterar senha com nova_senha menor que 8 chars retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->put('/api/v1/usuarios/me/senha', [
        'senha_atual' => 'Nova@5678',
        'nova_senha'  => '123',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('erros.nova_senha'))->toBeString()->not->toBeEmpty();
})->group('fase3', 'usuarios', 'senha', 'validacao');

test('alterar senha sem token retorna 401', function (): void {
    $resp = api()->put('/api/v1/usuarios/me/senha', [
        'senha_atual' => 'qualquer',
        'nova_senha'  => 'qualquerNova@1',
    ]);

    expect($resp->status)->toBe(401);
})->group('fase3', 'usuarios', 'senha');

// ═══════════════════════════════════════════════════════════════════════════
// Autorização — rotas restritas a admin
// ═══════════════════════════════════════════════════════════════════════════

test('locatario nao pode listar usuarios e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/usuarios');

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'autorizacao');

test('locatario nao pode criar usuario e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Qualquer',
        'email' => 'novo@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(403);
})->group('fase3', 'usuarios', 'autorizacao');

test('locatario nao pode buscar outro usuario por id e recebe 403', function () use (&$estado): void {
    $adminId = loginAdmin()['usuario']['id'];

    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/usuarios/' . $adminId);

    expect($resp->status)->toBe(403);
})->group('fase3', 'usuarios', 'autorizacao');

test('locatario nao pode deletar usuario e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->delete('/api/v1/usuarios/' . $estado['locatario_id']);

    expect($resp->status)->toBe(403);
})->group('fase3', 'usuarios', 'autorizacao');

test('requisicoes sem token em rotas admin retornam 401', function () use (&$estado): void {
    $endpoints = [
        fn() => api()->get('/api/v1/usuarios'),
        fn() => api()->post('/api/v1/usuarios', []),
        fn() => api()->get('/api/v1/usuarios/' . $estado['locatario_id']),
        fn() => api()->put('/api/v1/usuarios/' . $estado['locatario_id'], []),
        fn() => api()->delete('/api/v1/usuarios/' . $estado['locatario_id']),
    ];

    foreach ($endpoints as $chamar) {
        $resp = $chamar();
        expect($resp->status)->toBe(401);
    }
})->group('fase3', 'usuarios', 'autorizacao');
