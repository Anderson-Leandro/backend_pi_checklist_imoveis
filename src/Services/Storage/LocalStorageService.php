<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\RegraDeNegocioException;
use App\Helpers\Uuid;

class LocalStorageService implements StorageInterface
{
    private readonly string $basePath;
    private readonly string $baseUrl;

    public function __construct()
    {
        $storagePath = getenv('STORAGE_LOCAL_PATH') ?: $_ENV['STORAGE_LOCAL_PATH'] ?? 'storage/uploads';
        $storagePath = rtrim($storagePath, '/');

        // Garante caminho absoluto — PHP-FPM pode ter CWD diferente da raiz do projeto
        if (!str_starts_with($storagePath, '/')) {
            $storagePath = dirname(__DIR__, 3) . '/' . $storagePath;
        }

        $this->basePath = $storagePath;
        $this->baseUrl  = rtrim(getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? ''), '/');
    }

    /**
     * Salva o arquivo no disco local e retorna a chave de armazenamento.
     *
     * @param  array{name: string, tmp_name: string, size: int, type: string, error: int} $arquivo
     * @param  string $subpasta Subpasta de destino (ex: 'fotos/checklists')
     * @return string Chave de armazenamento (ex: 'fotos/checklists/uuid.jpg')
     */
    public function store(array $arquivo, string $subpasta): string
    {
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new RegraDeNegocioException('Erro no upload do arquivo');
        }

        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $chave    = trim($subpasta, '/') . '/' . Uuid::gerar() . '.' . $extensao;

        $this->salvarArquivo($arquivo['tmp_name'], $chave);

        return $chave;
    }

    /**
     * @param  array{name: string, tmp_name: string, size: int, type: string, error: int} $arquivo
     */
    public function storeWithKey(array $arquivo, string $chave): void
    {
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new RegraDeNegocioException('Erro no upload do arquivo');
        }

        $this->salvarArquivo($arquivo['tmp_name'], $chave);
    }

    public function delete(string $chave): void
    {
        $caminho = $this->basePath . '/' . ltrim($chave, '/');

        if (file_exists($caminho)) {
            unlink($caminho);
        }
    }

    public function exists(string $chave): bool
    {
        return file_exists($this->basePath . '/' . ltrim($chave, '/'));
    }

    public function resolverUrl(string $chave): string
    {
        // Compatibilidade: dados legados podem ter URL completa armazenada
        if (str_starts_with($chave, 'http://') || str_starts_with($chave, 'https://')) {
            return $chave;
        }

        return $this->baseUrl . '/storage/uploads/' . ltrim($chave, '/');
    }

    private function salvarArquivo(string $tmpName, string $chave): void
    {
        $partes   = explode('/', ltrim($chave, '/'));
        $subpasta = implode('/', array_slice($partes, 0, -1));
        $destino  = $this->basePath . '/' . $subpasta;

        if (!is_dir($destino) && !mkdir($destino, 0755, true)) {
            throw new RegraDeNegocioException('Não foi possível criar o diretório de armazenamento');
        }

        $caminhoCompleto = $this->basePath . '/' . ltrim($chave, '/');

        // move_uploaded_file só funciona em contexto HTTP real; copy() como fallback para seeds/testes
        $sucesso = is_uploaded_file($tmpName)
            ? move_uploaded_file($tmpName, $caminhoCompleto)
            : copy($tmpName, $caminhoCompleto);

        if (!$sucesso) {
            throw new RegraDeNegocioException('Falha ao salvar o arquivo');
        }
    }
}
