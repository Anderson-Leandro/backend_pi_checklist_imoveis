<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AcessoNegadoException;
use App\Helpers\UsuarioAutenticado;

class RoleMiddleware
{
    private const HIERARQUIA = [
        'locatario'   => 1,
        'vistoriador' => 2,
        'admin'       => 3,
    ];

    public function verificar(string $roleMinima): callable
    {
        return function (callable $proximo) use ($roleMinima): void {
            $usuario = UsuarioAutenticado::obterOuFalhar();

            $nivelUsuario = self::HIERARQUIA[$usuario['role']] ?? 0;
            $nivelMinimo  = self::HIERARQUIA[$roleMinima]      ?? 99;

            if ($nivelUsuario < $nivelMinimo) {
                throw new AcessoNegadoException('Permissão insuficiente para esta ação');
            }

            $proximo();
        };
    }
}
