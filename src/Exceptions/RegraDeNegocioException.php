<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class RegraDeNegocioException extends RuntimeException
{
    public function __construct(string $mensagem, int $codigo = 422)
    {
        parent::__construct($mensagem, $codigo);
    }
}
