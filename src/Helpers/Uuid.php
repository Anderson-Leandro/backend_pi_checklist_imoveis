<?php

declare(strict_types=1);

namespace App\Helpers;

use Ramsey\Uuid\Uuid as RamseyUuid;

class Uuid
{
    /**
     * Gera um novo UUID v4.
     */
    public static function gerar(): string
    {
        return RamseyUuid::uuid4()->toString();
    }

    /**
     * Verifica se o valor informado é um UUID v4 válido.
     */
    public static function valido(string $uuid): bool
    {
        return RamseyUuid::isValid($uuid);
    }
}
