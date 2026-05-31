<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'admin_token'        => '',
    'usuario_id'         => '',
    'usuario_id_deletado' => '',
    'locatario_id'       => '',
    'locatario_token'    => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
    foreach (['usuario_id', 'locatario_id'] as $campo) {
        if (!empty($estado[$campo])) {
            apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado[$campo]);
        }
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/usuarios — criação
// ═══════════════════════════════════════════════════════════════════════════

test('admin cria usuario valido e retorna 201 com UUID', function () use (&$estado): void {
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

test('body vazio retorna 422 com todos os campos obrigatorios', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', []);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros'))->toBeArray()
        ->and($resp->json('erros.nome'))->toBeString()
        ->and($resp->json('erros.email'))->toBeString()
        ->and($resp->json('erros.senha'))->toBeString()
        ->and($resp->json('erros.role'))->toBeString();
})->group('fase3', 'usuarios', 'criar', 'validacao');

test('campo nome invalido retorna 422 com erro no campo nome', function (
    string $valor
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => $valor,
        'email' => 'valido@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.nome'))->toBeString()->not->toBeEmpty();
})->with([
    'nome ausente'    => [''],
    'nome muito curto' => ['ab'],
])->group('fase3', 'usuarios', 'criar', 'validacao');

test('campo email invalido retorna 422 com erro no campo email', function (
    string $email
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Nome Valido',
        'email' => $email,
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.email'))->toBeString()->not->toBeEmpty();
})->with([
    'sem arroba'     => ['naoemail'],
    'sem dominio'    => ['user@'],
    'com espaco'     => ['user @email.com'],
])->group('fase3', 'usuarios', 'criar', 'validacao');

test('campo senha invalido retorna 422 com erro no campo senha', function (
    string $senha
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Nome Valido',
        'email' => 'valido@teste.com',
        'senha' => $senha,
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.senha'))->toBeString()->not->toBeEmpty();
})->with([
    'senha ausente'       => [''],
    'senha muito curta'   => ['123'],
    'senha 7 caracteres'  => ['1234567'],
])->group('fase3', 'usuarios', 'criar', 'validacao');

test('campo role invalido retorna 422 com erro no campo role', function (
    string $role
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Nome Valido',
        'email' => 'valido@teste.com',
        'senha' => 'Teste@1234',
        'role'  => $role,
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.role'))->toBeString()->not->toBeEmpty();
})->with([
    'role ausente'    => [''],
    'role invalida'   => ['superadmin'],
    'role numerica'   => ['1'],
])->group('fase3', 'usuarios', 'criar', 'validacao');

test('email duplicado retorna 422 com mensagem clara', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Duplicado',
        'email' => 'admin@onecheck.com.br',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString()
        ->and(mb_strtolower($resp->json('erro')))->toContain('e-mail');
})->group('fase3', 'usuarios', 'criar', 'regra-negocio');

test('senha nao retorna na resposta de criacao', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome'  => 'Sem Senha Na Resp',
        'email' => 'sem_senha_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);

    assertSemCamposSensiveis($resp);

    if ($resp->status === 201) {
        apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $resp->json('dados.id'));
    }
})->group('fase3', 'usuarios', 'criar', 'seguranca');

test('criar usuario sem token retorna 401', function (): void {
    $resp = api()->post('/api/v1/usuarios', [
        'nome' => 'Qualquer', 'email' => 'q@q.com', 'senha' => 'Teste@1234', 'role' => 'locatario',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase3', 'usuarios', 'criar', 'autorizacao');

test('locatario nao pode criar usuario e recebe 403', function () use (&$estado): void {
    $locatario = criarUsuarioELogar($estado['admin_token'], [
        'nome'  => 'Loc Criar',
        'email' => 'loc_criar_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];

    $resp = apiComToken($locatario['token'])->post('/api/v1/usuarios', [
        'nome' => 'Novo', 'email' => 'novo@teste.com', 'senha' => 'Teste@1234', 'role' => 'locatario',
    ]);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'criar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/usuarios — listagem
// ═══════════════════════════════════════════════════════════════════════════

test('admin lista usuarios retorna paginacao completa', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados'))->toBeArray()
        ->and($resp->json('paginacao.total'))->toBeInt()->toBeGreaterThan(0)
        ->and($resp->json('paginacao.pagina'))->toBeInt()
        ->and($resp->json('paginacao.itensPorPagina'))->toBeInt()
        ->and($resp->json('paginacao.totalPaginas'))->toBeInt();

    assertSemCamposSensiveis($resp);
})->group('fase3', 'usuarios', 'listar');

test('filtro por role retorna apenas usuarios com aquela role', function (
    string $role
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios', ['role' => $role]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue();

    foreach ($resp->json('dados') as $usuario) {
        expect($usuario['role'])->toBe($role);
    }
})->with([
    'filtro admin'       => ['admin'],
    'filtro vistoriador' => ['vistoriador'],
    'filtro locatario'   => ['locatario'],
])->group('fase3', 'usuarios', 'listar');

test('paginacao com por_pagina=1 retorna no maximo 1 item', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios', [
        'pagina'    => 1,
        'por_pagina' => 1,
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('paginacao.itensPorPagina'))->toBe(1)
        ->and(count($resp->json('dados')))->toBeLessThanOrEqual(1);
})->group('fase3', 'usuarios', 'listar');

test('listar usuarios sem token retorna 401', function (): void {
    $resp = api()->get('/api/v1/usuarios');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase3', 'usuarios', 'listar', 'autorizacao');

test('locatario nao pode listar usuarios e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->get('/api/v1/usuarios');

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'listar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/usuarios/{id} — busca por ID
// ═══════════════════════════════════════════════════════════════════════════

test('admin busca usuario por id valido', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios/' . $estado['usuario_id']);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.id'))->toBe($estado['usuario_id'])
        ->and($resp->json('dados.nome'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.email'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('dados.role'))->toBeString();

    assertSemCamposSensiveis($resp);
})->group('fase3', 'usuarios', 'buscar');

test('buscar usuario com id inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios/00000000-0000-4000-a000-000000000000');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase3', 'usuarios', 'buscar');

test('buscar usuario por id sem token retorna 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/usuarios/' . $estado['usuario_id']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase3', 'usuarios', 'buscar', 'autorizacao');

test('locatario nao pode buscar outro usuario por id e recebe 403', function () use (&$estado): void {
    $adminId = loginAdmin()['usuario']['id'];
    $resp    = apiComToken($estado['locatario_token'])->get('/api/v1/usuarios/' . $adminId);

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'buscar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/usuarios/{id} — atualização
// ═══════════════════════════════════════════════════════════════════════════

test('admin atualiza nome do usuario', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/' . $estado['usuario_id'], [
        'nome' => 'Nome Atualizado',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.nome'))->toBe('Nome Atualizado')
        ->and($resp->json('dados.id'))->toBe($estado['usuario_id']);

    assertSemCamposSensiveis($resp);
})->group('fase3', 'usuarios', 'atualizar');

test('admin atualiza role do usuario', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/' . $estado['usuario_id'], [
        'role' => 'locatario',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.role'))->toBe('locatario');
})->group('fase3', 'usuarios', 'atualizar');

test('campo invalido na atualizacao retorna 422', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/' . $estado['usuario_id'], [
        $campo => $valor,
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'nome muito curto' => ['nome', 'ab'],
    'email malformado' => ['email', 'nao-eh-email'],
    'role invalida'    => ['role', 'superadmin'],
])->group('fase3', 'usuarios', 'atualizar', 'validacao');

test('atualizar email ja em uso retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/' . $estado['usuario_id'], [
        'email' => 'admin@onecheck.com.br',
    ]);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'atualizar', 'regra-negocio');

test('atualizar usuario inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->put('/api/v1/usuarios/00000000-0000-4000-a000-000000000000', [
        'nome' => 'Qualquer',
    ]);

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase3', 'usuarios', 'atualizar');

test('atualizar usuario sem token retorna 401', function () use (&$estado): void {
    $resp = api()->put('/api/v1/usuarios/' . $estado['usuario_id'], ['nome' => 'X']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase3', 'usuarios', 'atualizar', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// DELETE /api/v1/usuarios/{id} — soft delete
// ═══════════════════════════════════════════════════════════════════════════

test('admin nao pode deletar a propria conta e recebe 422', function () use (&$estado): void {
    $adminId = loginAdmin()['usuario']['id'];
    $resp    = apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $adminId);

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'deletar', 'regra-negocio');

test('deletar usuario inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/00000000-0000-4000-a000-000000000000');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->group('fase3', 'usuarios', 'deletar');

test('deletar usuario sem token retorna 401', function () use (&$estado): void {
    $resp = api()->delete('/api/v1/usuarios/' . $estado['usuario_id']);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase3', 'usuarios', 'deletar', 'autorizacao');

test('admin deleta usuario e retorna 204', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $estado['usuario_id']);
    expect($resp->status)->toBe(204);
    $estado['usuario_id_deletado'] = $estado['usuario_id'];
    $estado['usuario_id']          = '';
})->group('fase3', 'usuarios', 'deletar');

test('usuario deletado nao aparece mais na listagem', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get('/api/v1/usuarios/' . $estado['usuario_id_deletado']);
    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString();
})->depends('admin deleta usuario e retorna 204')->group('fase3', 'usuarios', 'deletar');

test('usuario deletado nao consegue fazer login', function () use (&$estado): void {
    $email = 'del_login_' . uniqid() . '@teste.com';
    $cria  = apiComToken($estado['admin_token'])->post('/api/v1/usuarios', [
        'nome' => 'Para Deletar', 'email' => $email, 'senha' => 'Teste@1234', 'role' => 'locatario',
    ]);
    $id = $cria->json('dados.id');
    apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $id);

    $loginResp = api()->post('/api/v1/auth/login', ['email' => $email, 'senha' => 'Teste@1234']);

    expect($loginResp->status)->toBe(401)
        ->and($loginResp->json('sucesso'))->toBeFalse()
        ->and($loginResp->json('trace'))->toBeNull();
})->group('fase3', 'usuarios', 'deletar', 'seguranca');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/usuarios/me — perfil próprio
// ═══════════════════════════════════════════════════════════════════════════

test('qualquer role autenticada acessa GET /me com dados proprios', function (
    string $role
) use (&$estado): void {
    $email    = "me_{$role}_" . uniqid() . '@teste.com';
    $usuario  = criarUsuarioELogar($estado['admin_token'], [
        'nome'  => "Me {$role}",
        'email' => $email,
        'senha' => 'Teste@1234',
        'role'  => $role,
    ]);

    $resp = apiComToken($usuario['token'])->get('/api/v1/usuarios/me');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.email'))->toBe($email)
        ->and($resp->json('dados.role'))->toBe($role);

    assertSemCamposSensiveis($resp);

    apiComToken($estado['admin_token'])->delete('/api/v1/usuarios/' . $usuario['id']);
})->with([
    'role admin'       => ['admin'],
    'role vistoriador' => ['vistoriador'],
    'role locatario'   => ['locatario'],
])->group('fase3', 'usuarios', 'me');

test('GET /me sem token retorna 401', function (): void {
    $resp = api()->get('/api/v1/usuarios/me');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase3', 'usuarios', 'me', 'autorizacao');

// ═══════════════════════════════════════════════════════════════════════════
// PUT /api/v1/usuarios/me/senha — alterar própria senha
// ═══════════════════════════════════════════════════════════════════════════

test('usuario altera propria senha com dados validos', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->put('/api/v1/usuarios/me/senha', [
        'senha_atual' => 'Teste@1234',
        'nova_senha'  => 'Nova@5678',
    ]);

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.mensagem'))->toBeString();
})->group('fase3', 'usuarios', 'senha');

test('login com nova senha funciona apos alteracao', function () use (&$estado): void {
    $meResp = apiComToken($estado['locatario_token'])->get('/api/v1/usuarios/me');
    $email  = $meResp->json('dados.email');

    $loginResp = api()->post('/api/v1/auth/login', ['email' => $email, 'senha' => 'Nova@5678']);

    expect($loginResp->status)->toBe(200)
        ->and($loginResp->json('sucesso'))->toBeTrue();
})->depends('usuario altera propria senha com dados validos')->group('fase3', 'usuarios', 'senha');

test('login com senha antiga falha apos alteracao', function () use (&$estado): void {
    $meResp = apiComToken($estado['locatario_token'])->get('/api/v1/usuarios/me');
    $email  = $meResp->json('dados.email');

    $loginResp = api()->post('/api/v1/auth/login', ['email' => $email, 'senha' => 'Teste@1234']);

    expect($loginResp->status)->toBe(401)
        ->and($loginResp->json('sucesso'))->toBeFalse();
})->depends('usuario altera propria senha com dados validos')->group('fase3', 'usuarios', 'senha');

test('campo invalido na alteracao de senha retorna 422', function (
    string $campo,
    mixed $valor
) use (&$estado): void {
    $base = ['senha_atual' => 'Nova@5678', 'nova_senha' => 'Valida@1234'];
    $resp = apiComToken($estado['locatario_token'])->put('/api/v1/usuarios/me/senha',
        array_merge($base, [$campo => $valor])
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json("erros.{$campo}"))->toBeString()->not->toBeEmpty();
})->with([
    'senha_atual ausente'    => ['senha_atual', ''],
    'nova_senha ausente'     => ['nova_senha', ''],
    'nova_senha muito curta' => ['nova_senha', '1234567'],
])->group('fase3', 'usuarios', 'senha', 'validacao');

test('senha_atual incorreta retorna 401', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->put('/api/v1/usuarios/me/senha', [
        'senha_atual' => 'SenhaErrada@999',
        'nova_senha'  => 'Qualquer@1234',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase3', 'usuarios', 'senha');

test('alterar senha sem token retorna 401', function (): void {
    $resp = api()->put('/api/v1/usuarios/me/senha', [
        'senha_atual' => 'Qualquer', 'nova_senha' => 'Qualquer@Nova',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase3', 'usuarios', 'senha', 'autorizacao');
