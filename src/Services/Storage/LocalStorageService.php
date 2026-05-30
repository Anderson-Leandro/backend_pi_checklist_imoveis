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
        $this->basePath = rtrim(getenv('STORAGE_LOCAL_PATH') ?: 'storage/uploads', '/');
        $this->baseUrl  = rtrim(getenv('APP_URL') ?: '', '/');
    }

    /**
     * Salva o arquivo no disco local e retorna a URL pública.
     *
     * @param  array{name: string, tmp_name: string, size: int, type: string, error: int} $arquivo
     * @param  string $subpasta Subpasta de destino (ex: 'fotos/checklists')
     * @return string URL pública do arquivo salvo
     */
    public function store(array $arquivo, string $subpasta): string
    {
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new RegraDeNegocioException('Erro no upload do arquivo');
        }

        $extensao  = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $nomeUnico = Uuid::gerar() . '.' . $extensao;
        $destino   = $this->basePath . '/' . trim($subpasta, '/');

        if (!is_dir($destino) && !mkdir($destino, 0755, true)) {
            throw new RegraDeNegocioException('Não foi possível criar o diretório de armazenamento');
        }

        $caminhoCompleto = $destino . '/' . $nomeUnico;

        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new RegraDeNegocioException('Falha ao salvar o arquivo');
        }

        return $this->baseUrl . '/storage/uploads/' . trim($subpasta, '/') . '/' . $nomeUnico;
    }
}
