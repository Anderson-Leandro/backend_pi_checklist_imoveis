<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class NaoAutorizadoException extends RuntimeException
{
    public function __construct(string $mensagem = 'Não autorizado')
    {
        parent::__construct($mensagem, 401);
    }
}
