<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ValidacaoException extends RuntimeException
{
    public function __construct(
        public readonly array $erros
    ) {
        parent::__construct('Dados inválidos', 422);
    }
}
