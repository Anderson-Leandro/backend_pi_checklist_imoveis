<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\RegraDeNegocioException;
use App\Helpers\Uuid;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;

class S3StorageService implements StorageInterface
{
    private readonly S3Client $client;
    private readonly string   $bucket;
    private readonly int      $presignedTtl;
    private readonly string   $endpointInterno;
    private readonly string   $endpointPublico;

    public function __construct()
    {
        $endpointInterno = $this->env('AWS_ENDPOINT', '');
        $endpointPublico = $this->env('S3_PRESIGNED_BASE_URL', '');

        $config = [
            'version'     => 'latest',
            'region'      => $this->env('AWS_DEFAULT_REGION', 'us-east-1'),
            'credentials' => [
                'key'    => $this->env('AWS_ACCESS_KEY_ID', ''),
                'secret' => $this->env('AWS_SECRET_ACCESS_KEY', ''),
            ],
        ];

        if ($endpointInterno !== '') {
            $config['endpoint']                = $endpointInterno;
            $config['use_path_style_endpoint'] = filter_var(
                $this->env('AWS_USE_PATH_STYLE_ENDPOINT', 'false'),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        $this->client          = new S3Client($config);
        $this->bucket          = $this->env('AWS_BUCKET', '');
        $this->presignedTtl    = (int) $this->env('S3_PRESIGNED_TTL', '3600');
        $this->endpointInterno = rtrim($endpointInterno, '/');
        $this->endpointPublico = rtrim($endpointPublico, '/');
    }

    /**
     * Faz upload do arquivo para o S3 e retorna a chave de armazenamento.
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

        $this->enviarParaS3($arquivo['tmp_name'], $chave);

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

        $this->enviarParaS3($arquivo['tmp_name'], $chave);
    }

    public function delete(string $chave): void
    {
        // Compatibilidade: chave legada de outro storage — nada a deletar no S3
        if (str_starts_with($chave, 'http://') || str_starts_with($chave, 'https://')) {
            return;
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $chave,
            ]);
        } catch (AwsException) {
            // Objeto não existe ou já foi removido — ignorar silenciosamente
        }
    }

    public function exists(string $chave): bool
    {
        if (str_starts_with($chave, 'http://') || str_starts_with($chave, 'https://')) {
            return false;
        }

        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $chave,
            ]);
            return true;
        } catch (AwsException) {
            return false;
        }
    }

    /**
     * Gera uma presigned URL com TTL configurável.
     * Em ambientes Docker/MinIO substitui o endpoint interno pelo público
     * para que o cliente (browser/app) consiga acessar a URL.
     */
    public function resolverUrl(string $chave): string
    {
        // Compatibilidade: dados legados podem ter URL completa de outro storage
        if (str_starts_with($chave, 'http://') || str_starts_with($chave, 'https://')) {
            return $chave;
        }

        $comando = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $chave,
        ]);

        $request = $this->client->createPresignedRequest($comando, "+{$this->presignedTtl} seconds");
        $url     = (string) $request->getUri();

        // Substitui endpoint interno (ex: http://minio:9000) pelo público (ex: http://localhost:9000)
        // necessário em Docker onde o PHP alcança o MinIO internamente mas o cliente não
        if ($this->endpointInterno !== '' && $this->endpointPublico !== '') {
            $url = str_replace($this->endpointInterno, $this->endpointPublico, $url);
        }

        return $url;
    }

    private function env(string $chave, string $padrao): string
    {
        $valor = getenv($chave);
        if ($valor !== false && $valor !== '') {
            return $valor;
        }
        return (string) ($_ENV[$chave] ?? $padrao);
    }

    private function enviarParaS3(string $tmpName, string $chave): void
    {
        $contentType = mime_content_type($tmpName) ?: 'application/octet-stream';

        $this->client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $chave,
            'SourceFile'  => $tmpName,
            'ContentType' => $contentType,
        ]);
    }
}
