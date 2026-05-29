<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AcessoNegadoException;
use App\Exceptions\NaoAutorizadoException;
use App\Helpers\Env;
use App\Helpers\Requisicao;
use App\Helpers\UsuarioAutenticado;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

class AuthMiddleware
{
    public function verificar(callable $proximo): void
    {
        $cabecalhoAutorizacao = Requisicao::cabecalho('Authorization');

        if ($cabecalhoAutorizacao === null || !str_starts_with($cabecalhoAutorizacao, 'Bearer ')) {
            throw new NaoAutorizadoException('Token de autenticação não informado');
        }

        $token = substr($cabecalhoAutorizacao, 7);

        try {
            $payload = JWT::decode($token, new Key(Env::get('JWT_SECRET'), 'HS256'));

            if (($payload->type ?? '') !== 'access') {
                throw new NaoAutorizadoException('Tipo de token inválido');
            }

            UsuarioAutenticado::definir([
                'id'    => $payload->sub,
                'email' => $payload->email,
                'role'  => $payload->role,
            ]);
        } catch (ExpiredException) {
            throw new NaoAutorizadoException('Token expirado');
        } catch (SignatureInvalidException) {
            throw new NaoAutorizadoException('Assinatura do token inválida');
        } catch (NaoAutorizadoException $excecao) {
            throw $excecao;
        } catch (\Throwable) {
            throw new NaoAutorizadoException('Token inválido');
        }

        $proximo();
    }
}
