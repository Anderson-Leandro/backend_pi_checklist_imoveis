<?php

declare(strict_types=1);

use Tests\Support\ApiClient;
use Tests\Support\ApiResponse;

/*
|--------------------------------------------------------------------------
| Helpers globais disponíveis em todos os testes
|--------------------------------------------------------------------------
*/

/** Retorna um ApiClient anônimo (sem token de autenticação) */
function api(): ApiClient
{
    return ApiClient::criar();
}

/** Retorna um ApiClient autenticado com o token informado */
function apiComToken(string $token): ApiClient
{
    return ApiClient::criar()->comToken($token);
}

/**
 * Faz login como admin padrão do seed e retorna os tokens + dados do usuário.
 *
 * @return array{token: string, refresh: string, usuario: array}
 * @throws RuntimeException Se o login falhar
 */
function loginAdmin(): array
{
    $resp = api()->post('/api/v1/auth/login', [
        'email' => 'admin@onecheck.com.br',
        'senha' => 'Admin@1234',
    ]);

    if ($resp->status !== 200) {
        throw new RuntimeException(
            'loginAdmin() falhou (HTTP ' . $resp->status . '): ' . json_encode($resp->json())
        );
    }

    return [
        'token'   => $resp->json('dados.access_token'),
        'refresh' => $resp->json('dados.refresh_token'),
        'usuario' => $resp->json('dados.usuario'),
    ];
}

/**
 * Cria um usuário via API e faz login com ele.
 * O usuário criado deve ser deletado no afterAll do teste que o criou.
 *
 * @param  string $adminToken  Token do admin para criar o usuário
 * @param  array{nome: string, email: string, senha: string, role: string} $dados
 * @return array{id: string, token: string, refresh: string, usuario: array}
 */
function criarUsuarioELogar(string $adminToken, array $dados): array
{
    $resp = apiComToken($adminToken)->post('/api/v1/usuarios', $dados);

    if ($resp->status !== 201) {
        throw new RuntimeException(
            'criarUsuarioELogar() falhou ao criar (HTTP ' . $resp->status . '): '
            . json_encode($resp->json())
        );
    }

    $loginResp = api()->post('/api/v1/auth/login', [
        'email' => $dados['email'],
        'senha' => $dados['senha'],
    ]);

    return [
        'id'      => $resp->json('dados.id'),
        'token'   => $loginResp->json('dados.access_token'),
        'refresh' => $loginResp->json('dados.refresh_token'),
        'usuario' => $resp->json('dados'),
    ];
}

/**
 * Verifica se o valor é um UUID v4 válido.
 */
function uuidValido(mixed $valor): bool
{
    if (!is_string($valor)) {
        return false;
    }

    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $valor
    );
}

/**
 * Garante que nenhum campo sensível está exposto em qualquer nível da resposta.
 */
function assertSemCamposSensiveis(ApiResponse $resp): void
{
    $camposSensiveis = ['senha_hash', 'mfa_secret', 'senha', 'password'];

    foreach ($camposSensiveis as $campo) {
        expect(arrayContemChave($resp->json() ?? [], $campo))->toBeFalse(
            "A resposta não deve expor o campo sensível '{$campo}'"
        );
    }
}

/**
 * Busca recursivamente uma chave em um array aninhado.
 */
function arrayContemChave(array $array, string $chave): bool
{
    foreach ($array as $k => $v) {
        if ($k === $chave) {
            return true;
        }
        if (is_array($v) && arrayContemChave($v, $chave)) {
            return true;
        }
    }

    return false;
}
