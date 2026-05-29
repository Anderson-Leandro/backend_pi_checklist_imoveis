<?php

declare(strict_types=1);

namespace App\Helpers;

class Requisicao
{
    private static array|null $corpo = null;

    /**
     * Retorna o corpo da requisição decodificado como array associativo.
     * O resultado é cacheado para evitar leituras múltiplas do php://input.
     *
     * @return array<string, mixed>
     */
    public static function corpo(): array
    {
        if (self::$corpo === null) {
            $conteudoBruto = file_get_contents('php://input');
            self::$corpo   = !empty($conteudoBruto)
                ? (json_decode($conteudoBruto, associative: true) ?? [])
                : [];
        }

        return self::$corpo;
    }

    /**
     * Retorna os parâmetros de query string ($_GET).
     *
     * @return array<string, string>
     */
    public static function query(): array
    {
        return $_GET;
    }

    /**
     * Retorna os arquivos enviados na requisição ($_FILES).
     *
     * @return array<string, mixed>
     */
    public static function arquivos(): array
    {
        return $_FILES;
    }

    /**
     * Retorna o IP real do cliente, respeitando proxies.
     */
    public static function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    /**
     * Retorna o valor de um cabeçalho HTTP pelo nome (insensível a maiúsculas).
     */
    public static function cabecalho(string $nome): string|null
    {
        $chave = 'HTTP_' . strtoupper(str_replace('-', '_', $nome));
        return $_SERVER[$chave] ?? null;
    }
}
