<?php

declare(strict_types=1);

// ── Estado compartilhado ──────────────────────────────────────────────────────
$estado = [
    'admin_token'              => '',
    'vistoriador_id'           => '',
    'vistoriador_token'        => '',
    'locatario_id'             => '',
    'locatario_token'          => '',
    'locatario2_id'            => '',
    'locatario2_token'         => '',
    'imovel_id'                => '',
    'comodo_id'                => '',
    'item_vistoria_id'         => '',
    'contrato_id'              => '',
    'checklist_aceitar_id'     => '',
    'checklist_rejeitar_id'    => '',
    'checklist_rejeitar2_id'   => '',
    'checklist_download_id'    => '',
];

// ── Setup ─────────────────────────────────────────────────────────────────────
beforeAll(function () use (&$estado): void {
    $admin = loginAdmin();
    $estado['admin_token'] = $admin['token'];

    $vistoriador = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Vistoriador F7',
        'email' => 'vist_f7_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'vistoriador',
    ]);
    $estado['vistoriador_id']    = $vistoriador['id'];
    $estado['vistoriador_token'] = $vistoriador['token'];

    $locatario = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F7',
        'email' => 'loc_f7_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario_id']    = $locatario['id'];
    $estado['locatario_token'] = $locatario['token'];

    $locatario2 = criarUsuarioELogar($admin['token'], [
        'nome'  => 'Locatario F7 B',
        'email' => 'loc_f7b_' . uniqid() . '@teste.com',
        'senha' => 'Teste@1234',
        'role'  => 'locatario',
    ]);
    $estado['locatario2_id']    = $locatario2['id'];
    $estado['locatario2_token'] = $locatario2['token'];

    // Imóvel + endereço + cômodo
    $imovel = apiComToken($admin['token'])->post('/api/v1/imoveis', [
        'tipo' => 'Apartamento', 'tamanho' => '80m²',
    ]);
    $estado['imovel_id'] = $imovel->json('dados.id');

    apiComToken($admin['token'])->post('/api/v1/imoveis/' . $estado['imovel_id'] . '/endereco', [
        'rua'    => 'Rua das Flores',
        'numero' => '100',
        'cidade' => 'São Paulo',
        'estado' => 'SP',
        'cep'    => '01310-100',
    ]);

    $comodo = apiComToken($admin['token'])->post(
        '/api/v1/imoveis/' . $estado['imovel_id'] . '/comodos',
        ['tipo' => 'Sala', 'descricao' => 'Sala principal']
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

    // ── Criar e preparar checklists para os testes ──────────────────────────────

    // Checklist para ACEITAR
    $chkAceitar = apiComToken($admin['token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial']
    );
    $estado['checklist_aceitar_id'] = $chkAceitar->json('dados.id');
    apiComToken($vistoriador['token'])->post(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/itens',
        ['comodo_id' => $estado['comodo_id'], 'item_vistoria_id' => $estado['item_vistoria_id'], 'estado' => 'bom']
    );
    apiComToken($admin['token'])->patch(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/enviar-para-aceite'
    );

    // Checklist para REJEITAR (com motivo)
    $chkRejeitar = apiComToken($admin['token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'encerramento']
    );
    $estado['checklist_rejeitar_id'] = $chkRejeitar->json('dados.id');
    apiComToken($vistoriador['token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/itens',
        ['comodo_id' => $estado['comodo_id'], 'item_vistoria_id' => $estado['item_vistoria_id'], 'estado' => 'ruim']
    );
    apiComToken($admin['token'])->patch(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/enviar-para-aceite'
    );

    // Checklist para REJEITAR (testes de validação de motivo)
    $chkRejeitar2 = apiComToken($admin['token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial']
    );
    $estado['checklist_rejeitar2_id'] = $chkRejeitar2->json('dados.id');
    apiComToken($vistoriador['token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar2_id'] . '/itens',
        ['comodo_id' => $estado['comodo_id'], 'item_vistoria_id' => $estado['item_vistoria_id'], 'estado' => 'regular']
    );
    apiComToken($admin['token'])->patch(
        '/api/v1/checklists/' . $estado['checklist_rejeitar2_id'] . '/enviar-para-aceite'
    );

    // Checklist para DOWNLOAD (em qualquer status)
    $chkDownload = apiComToken($admin['token'])->post(
        '/api/v1/contratos/' . $estado['contrato_id'] . '/checklists',
        ['vistoriador_id' => $estado['vistoriador_id'], 'tipo' => 'inicial']
    );
    $estado['checklist_download_id'] = $chkDownload->json('dados.id');
    apiComToken($vistoriador['token'])->post(
        '/api/v1/checklists/' . $estado['checklist_download_id'] . '/itens',
        ['comodo_id' => $estado['comodo_id'], 'item_vistoria_id' => $estado['item_vistoria_id'], 'estado' => 'otimo']
    );
});

// ── Teardown ──────────────────────────────────────────────────────────────────
afterAll(function () use (&$estado): void {
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
// POST /api/v1/checklists/{id}/aceitar — locatário aceita checklist
// ═══════════════════════════════════════════════════════════════════════════

test('locatario aceita checklist em pendente_aceite e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/aceitar'
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.checklist_id'))->toBe($estado['checklist_aceitar_id'])
        ->and($resp->json('dados.locatario_id'))->toBe($estado['locatario_id'])
        ->and($resp->json('dados.status'))->toBe('aceito')
        ->and($resp->json('dados.motivo_rejeicao'))->toBeNull();
})->group('fase7', 'aceite', 'aceitar');

test('status do checklist muda para aceito apos aceitar', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.status'))->toBe('aceito');
})->group('fase7', 'aceite', 'aceitar');

test('aceitar checklist ja aceito retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/aceitar'
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'aceitar', 'regra-negocio');

test('aceitar checklist em preenchimento retorna 422', function () use (&$estado): void {
    // checklist_download_id está em em_preenchimento
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_download_id'] . '/aceitar'
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'aceitar', 'regra-negocio');

test('locatario2 nao pode aceitar checklist do contrato alheio e recebe 403', function () use (&$estado): void {
    // Envia o segundo checklist para aceite mas tenta aceitar com locatario2
    $resp = apiComToken($estado['locatario2_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/aceitar'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'aceitar', 'autorizacao');

test('admin nao pode aceitar checklist e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/aceitar'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'aceitar', 'autorizacao');

test('vistoriador nao pode aceitar checklist e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/aceitar'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'aceitar', 'autorizacao');

test('sem token nao pode aceitar checklist e recebe 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/aceitar');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase7', 'aceite', 'aceitar', 'autorizacao');

test('aceitar checklist inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/00000000-0000-4000-a000-000000000000/aceitar'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'aceitar');

// ═══════════════════════════════════════════════════════════════════════════
// POST /api/v1/checklists/{id}/rejeitar — locatário rejeita checklist
// ═══════════════════════════════════════════════════════════════════════════

test('rejeitar checklist sem motivo retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/rejeitar',
        []
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.motivo'))->toBeString()->not->toBeEmpty();
})->group('fase7', 'aceite', 'rejeitar', 'validacao');

test('rejeitar checklist com motivo vazio retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/rejeitar',
        ['motivo' => '']
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erros.motivo'))->toBeString()->not->toBeEmpty();
})->group('fase7', 'aceite', 'rejeitar', 'validacao');

test('locatario rejeita checklist com motivo e retorna 200', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/rejeitar',
        ['motivo' => 'Itens não condizem com a realidade do imóvel']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and(uuidValido($resp->json('dados.id')))->toBeTrue()
        ->and($resp->json('dados.checklist_id'))->toBe($estado['checklist_rejeitar_id'])
        ->and($resp->json('dados.locatario_id'))->toBe($estado['locatario_id'])
        ->and($resp->json('dados.status'))->toBe('rejeitado')
        ->and($resp->json('dados.motivo_rejeicao'))->toBe('Itens não condizem com a realidade do imóvel');
})->group('fase7', 'aceite', 'rejeitar');

test('status do checklist muda para pendente_revisao apos rejeitar', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id']
    );

    expect($resp->status)->toBe(200)
        ->and($resp->json('dados.status'))->toBe('pendente_revisao');
})->group('fase7', 'aceite', 'rejeitar');

test('rejeitar checklist ja rejeitado retorna 422', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar_id'] . '/rejeitar',
        ['motivo' => 'Tentativa repetida']
    );

    expect($resp->status)->toBe(422)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'rejeitar', 'regra-negocio');

test('locatario2 nao pode rejeitar checklist do contrato alheio e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario2_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar2_id'] . '/rejeitar',
        ['motivo' => 'Tentativa não autorizada']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'rejeitar', 'autorizacao');

test('admin nao pode rejeitar checklist e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->post(
        '/api/v1/checklists/' . $estado['checklist_rejeitar2_id'] . '/rejeitar',
        ['motivo' => 'Admin tentando rejeitar']
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'rejeitar', 'autorizacao');

test('sem token nao pode rejeitar checklist e recebe 401', function () use (&$estado): void {
    $resp = api()->post('/api/v1/checklists/' . $estado['checklist_rejeitar2_id'] . '/rejeitar', [
        'motivo' => 'Sem autenticacao',
    ]);

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase7', 'aceite', 'rejeitar', 'autorizacao');

test('rejeitar checklist inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->post(
        '/api/v1/checklists/00000000-0000-4000-a000-000000000000/rejeitar',
        ['motivo' => 'Checklist inexistente']
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'aceite', 'rejeitar');

// ═══════════════════════════════════════════════════════════════════════════
// GET /api/v1/checklists/{id}/download — gerar PDF
// ═══════════════════════════════════════════════════════════════════════════

test('admin baixa PDF do checklist e recebe application/pdf', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->download(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/download'
    );

    expect($resp['status'])->toBe(200)
        ->and($resp['content_type'])->toContain('application/pdf')
        ->and($resp['body_length'])->toBeGreaterThan(100);
})->group('fase7', 'pdf', 'download');

test('locatario baixa PDF do proprio checklist e recebe application/pdf', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario_token'])->download(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/download'
    );

    expect($resp['status'])->toBe(200)
        ->and($resp['content_type'])->toContain('application/pdf')
        ->and($resp['body_length'])->toBeGreaterThan(100);
})->group('fase7', 'pdf', 'download');

test('PDF contem assinatura de arquivo PDF valido', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->download(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/download'
    );

    expect($resp['status'])->toBe(200)
        ->and(str_starts_with($resp['body'], '%PDF-'))->toBeTrue();
})->group('fase7', 'pdf', 'download');

test('locatario2 nao pode baixar PDF de checklist alheio e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['locatario2_token'])->get(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/download'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'pdf', 'download', 'autorizacao');

test('vistoriador nao pode baixar PDF e recebe 403', function () use (&$estado): void {
    $resp = apiComToken($estado['vistoriador_token'])->get(
        '/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/download'
    );

    expect($resp->status)->toBe(403)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'pdf', 'download', 'autorizacao');

test('sem token nao pode baixar PDF e recebe 401', function () use (&$estado): void {
    $resp = api()->get('/api/v1/checklists/' . $estado['checklist_aceitar_id'] . '/download');

    expect($resp->status)->toBe(401)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('trace'))->toBeNull();
})->group('fase7', 'pdf', 'download', 'autorizacao');

test('download de checklist inexistente retorna 404', function () use (&$estado): void {
    $resp = apiComToken($estado['admin_token'])->get(
        '/api/v1/checklists/00000000-0000-4000-a000-000000000000/download'
    );

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase7', 'pdf', 'download');
