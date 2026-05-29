<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class NaoEncontradoException extends RuntimeException
{
    /**
     * @param string $recurso  Nome do recurso (ex: 'Usuário', 'Rota', 'Imóvel')
     * @param string $genero   'o' para masculino, 'a' para feminino
     */
    public function __construct(string $recurso = 'Recurso', string $genero = 'o')
    {
        parent::__construct("{$recurso} não encontrad{$genero}", 404);
    }
}
