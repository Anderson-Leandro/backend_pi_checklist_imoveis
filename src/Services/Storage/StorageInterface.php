<?php

declare(strict_types=1);

namespace App\Services\Storage;

interface StorageInterface
{
    /**
     * Armazena um arquivo e retorna a chave de armazenamento (ex: fotos/checklists/uuid.jpg).
     * Use resolverUrl() para obter a URL acessível pelo cliente.
     *
     * @param  array{name: string, tmp_name: string, size: int, type: string, error: int} $arquivo
     * @param  string $subpasta Subpasta de destino (ex: 'fotos/checklists')
     * @return string Chave de armazenamento (não é URL pública)
     */
    public function store(array $arquivo, string $subpasta): string;

    /**
     * Armazena um arquivo sob uma chave explícita, sem geração de UUID.
     * Usar apenas em contextos controlados (seeds, testes) — nunca com dados de usuário.
     *
     * @param  array{name: string, tmp_name: string, size: int, type: string, error: int} $arquivo
     * @param  string $chave Chave de armazenamento completa (ex: 'fotos/checklists/uuid.jpg')
     */
    public function storeWithKey(array $arquivo, string $chave): void;

    /**
     * Remove um arquivo pela sua chave de armazenamento.
     * Implementações devem ignorar silenciosamente se a chave não existir.
     */
    public function delete(string $chave): void;

    /**
     * Verifica se um arquivo existe pela sua chave de armazenamento.
     */
    public function exists(string $chave): bool;

    /**
     * Retorna a URL acessível pelo cliente para uma chave de armazenamento.
     * Local: URL pública via APP_URL.
     * S3: presigned URL com TTL configurável via S3_PRESIGNED_TTL.
     */
    public function resolverUrl(string $chave): string;
}
