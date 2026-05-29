<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════
// Fase 1 — Fundação: roteamento, health check e respostas padrão
// ═══════════════════════════════════════════════════════════════════════════

test('health check retorna status ok com versao e horario', function (): void {
    $resp = api()->get('/api/v1/health');

    expect($resp->status)->toBe(200)
        ->and($resp->json('sucesso'))->toBeTrue()
        ->and($resp->json('dados.status'))->toBe('ok')
        ->and($resp->json('dados.versao'))->toBe('v1')
        ->and($resp->json('dados.horario'))->toBeString()->not->toBeEmpty();

    assertSemCamposSensiveis($resp);
})->group('fase1', 'sistema');

test('health check responde com Content-Type application/json', function (): void {
    $ch = curl_init('http://localhost:8001/api/v1/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
    ]);
    $raw        = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr($raw, 0, $headerSize);
    expect(str_contains(strtolower($headers), 'content-type: application/json'))->toBeTrue();
})->group('fase1', 'sistema');

test('rota inexistente retorna 404 em JSON com campo erro', function (): void {
    $resp = api()->get('/api/v1/rota-que-nao-existe-jamais');

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse()
        ->and($resp->json('erro'))->toBeString()->not->toBeEmpty()
        ->and($resp->json('erros'))->toBeNull();
})->group('fase1', 'roteamento');

test('metodo nao suportado na rota retorna 404', function (): void {
    $resp = api()->post('/api/v1/health', []);

    expect($resp->status)->toBe(404)
        ->and($resp->json('sucesso'))->toBeFalse();
})->group('fase1', 'roteamento');

test('resposta de erro nao expoe stack trace nem detalhes internos', function (): void {
    $resp = api()->get('/api/v1/rota-invalida');

    expect($resp->json('trace'))->toBeNull()
        ->and($resp->json('file'))->toBeNull()
        ->and($resp->json('line'))->toBeNull()
        ->and($resp->json('exception'))->toBeNull();
})->group('fase1', 'seguranca');
