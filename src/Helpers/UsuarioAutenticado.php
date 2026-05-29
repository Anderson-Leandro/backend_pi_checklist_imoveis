<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\NaoAutorizadoException;

class UsuarioAutenticado
{
    private static array|null $dados = null;

    private function __construct() {}

    public static function definir(array $dados): void
    {
        self::$dados = $dados;
    }

    public static function obter(): array|null
    {
        return self::$dados;
    }

    public static function obterOuFalhar(): array
    {
        if (self::$dados === null) {
            throw new NaoAutorizadoException();
        }
        return self::$dados;
    }

    public static function limpar(): void
    {
        self::$dados = null;
    }
}
