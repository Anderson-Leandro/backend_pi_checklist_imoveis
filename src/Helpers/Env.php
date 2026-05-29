<?php

declare(strict_types=1);

namespace App\Helpers;

class Env
{
    public static function get(string $chave, string $padrao = ''): string
    {
        return $_ENV[$chave] ?? (getenv($chave) ?: $padrao);
    }

    public static function int(string $chave, int $padrao = 0): int
    {
        $valor = self::get($chave);
        return $valor !== '' ? (int) $valor : $padrao;
    }
}
