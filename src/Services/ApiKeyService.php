<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NaoEncontradoException;
use App\Exceptions\RegraDeNegocioException;
use App\Exceptions\ValidacaoException;
use App\Helpers\Uuid;
use App\Helpers\Validator;
use App\Models\ApiKeyModel;

class ApiKeyService
{
    public function __construct(
        private readonly ApiKeyModel $apiKeyModel,
        private readonly LogService  $logService
    ) {}

    /**
     * Gera uma nova API key para acesso externo (admin).
     * A chave em texto simples é retornada uma única vez e não pode ser recuperada depois.
     *
     * @param  array{nome: string} $dados
     * @return array{id: string, nome: string, ativo: int, created_at: string, chave: string}
     * @throws ValidacaoException Se o nome for inválido
     */
    public function criar(array $dados): array
    {
        $erros = Validator::validar($dados, [
            'nome' => 'obrigatorio|min:3|max:100',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $chaveSimples = bin2hex(random_bytes(32));
        $keyHash      = hash('sha256', $chaveSimples);
        $novoId       = Uuid::gerar();

        $this->apiKeyModel->inserir([
            'id'       => $novoId,
            'nome'     => $dados['nome'],
            'key_hash' => $keyHash,
        ]);

        $apiKey = $this->apiKeyModel->buscarPorId($novoId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'api_key',
            entidadeId: $novoId,
            payload:    ['nome' => $dados['nome']],
        );

        return array_merge(
            array_diff_key($apiKey, ['key_hash' => '']),
            ['chave' => $chaveSimples]
        );
    }

    /**
     * Lista todas as API keys sem expor os hashes.
     *
     * @return list<array{id: string, nome: string, ativo: int, created_at: string}>
     */
    public function listar(): array
    {
        return $this->apiKeyModel->listar();
    }

    /**
     * Revoga uma API key (admin).
     *
     * @throws NaoEncontradoException  Se a API key não existir
     * @throws RegraDeNegocioException Se a API key já estiver revogada
     */
    public function revogar(string $id): void
    {
        $apiKey = $this->apiKeyModel->buscarPorId($id);
        if ($apiKey === null) {
            throw new NaoEncontradoException('API key');
        }

        if (!$apiKey['ativo']) {
            throw new RegraDeNegocioException('Esta API key já está revogada');
        }

        $this->apiKeyModel->desativar($id);

        $this->logService->registrar(
            acao:       'DELETE',
            entidade:   'api_key',
            entidadeId: $id,
            payload:    ['nome' => $apiKey['nome']],
        );
    }
}
