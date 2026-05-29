<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AcessoNegadoException extends RuntimeException
{
    public function __construct(string $mensagem = 'Acesso negado')
    {
        parent::__construct($mensagem, 403);
    }
}
