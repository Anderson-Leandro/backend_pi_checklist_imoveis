<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

class Conexao
{
    private static PDO|null $instancia = null;

    private function __construct() {}

    private function __clone() {}

    private static function env(string $chave, string $padrao = ''): string
    {
        return $_ENV[$chave] ?? (getenv($chave) ?: $padrao);
    }

    public static function obter(): PDO
    {
        if (self::$instancia === null) {
            $host    = self::env('DB_HOST',     'db');
            $porta   = self::env('DB_PORT',     '3306');
            $banco   = self::env('DB_DATABASE', 'appdb');
            $usuario = self::env('DB_USERNAME', 'appuser');
            $senha   = self::env('DB_PASSWORD', '');

            $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";

            $opcoes = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            self::$instancia = new PDO($dsn, $usuario, $senha, $opcoes);
        }

        return self::$instancia;
    }
}
