<?php

declare(strict_types=1);

namespace App\Services\Storage;

interface StorageInterface
{
    /**
     * Armazena um arquivo e retorna a URL pública de acesso.
     *
     * @param  array{name: string, tmp_name: string, size: int, type: string, error: int} $arquivo
     * @param  string $subpasta Subpasta de destino dentro do diretório de uploads
     * @return string URL pública do arquivo salvo
     */
    public function store(array $arquivo, string $subpasta): string;
}
