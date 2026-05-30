<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AcessoNegadoException;
use App\Exceptions\NaoEncontradoException;
use App\Exceptions\RegraDeNegocioException;
use App\Exceptions\ValidacaoException;
use App\Helpers\UsuarioAutenticado;
use App\Helpers\Uuid;
use App\Helpers\Validator;
use App\Models\ComodoModel;
use App\Models\EnderecoModel;
use App\Models\ImovelModel;

class ImovelService
{
    public function __construct(
        private readonly ImovelModel     $imovelModel,
        private readonly EnderecoModel   $enderecoModel,
        private readonly ComodoModel     $comodoModel,
        private readonly GeocodingService $geocodingService,
        private readonly LogService      $logService
    ) {}

    // ──── Imóveis ────────────────────────────────────────────────────────────

    /**
     * Cria um novo imóvel.
     *
     * @param  array{tipo: string, tamanho: string, garagem: bool, garagem_vagas: int} $dados
     * @return array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string}
     * @throws ValidacaoException Se os dados forem inválidos
     */
    public function criar(array $dados): array
    {
        $erros = Validator::validar($dados, [
            'tipo'    => 'obrigatorio|min:2|max:80',
            'tamanho' => 'obrigatorio|min:1|max:80',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $novoId = Uuid::gerar();
        $this->imovelModel->inserir([
            'id'            => $novoId,
            'tipo'          => $dados['tipo'],
            'tamanho'       => $dados['tamanho'],
            'garagem'       => isset($dados['garagem']) && $dados['garagem'] ? 1 : 0,
            'garagem_vagas' => (int) ($dados['garagem_vagas'] ?? 0),
        ]);

        $imovel = $this->imovelModel->buscarPorId($novoId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'imovel',
            entidadeId: $novoId,
            payload:    ['tipo' => $dados['tipo'], 'tamanho' => $dados['tamanho']],
        );

        return $imovel;
    }

    /**
     * Lista imóveis ativos com paginação e filtro opcional por status.
     *
     * @return array{itens: list<array>, total: int, pagina: int, itensPorPagina: int}
     */
    public function listar(int $pagina, int $itensPorPagina, string|null $status): array
    {
        $pagina         = max(1, $pagina);
        $itensPorPagina = max(1, min(100, $itensPorPagina));

        $itens = $this->imovelModel->listar($pagina, $itensPorPagina, $status);
        $total = $this->imovelModel->contar($status);

        return compact('itens', 'total', 'pagina', 'itensPorPagina');
    }

    /**
     * Busca um imóvel pelo ID.
     * Admin acessa qualquer imóvel; locatário apenas o vinculado ao seu contrato ativo.
     *
     * @return array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string}
     * @throws NaoEncontradoException  Se o imóvel não existir
     * @throws AcessoNegadoException   Se o locatário não tiver contrato ativo para este imóvel
     */
    public function buscarPorId(string $id): array
    {
        $imovel = $this->imovelModel->buscarPorId($id);

        if ($imovel === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($usuario['role'] === 'locatario') {
            if (!$this->imovelModel->locatarioPossuiContratoAtivo($id, $usuario['id'])) {
                throw new AcessoNegadoException('Você não tem acesso a este imóvel');
            }
        }

        return $imovel;
    }

    /**
     * Atualiza dados de um imóvel.
     *
     * @param  array{tipo?: string, tamanho?: string, garagem?: bool, garagem_vagas?: int, status?: string} $dados
     * @return array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string}
     * @throws NaoEncontradoException  Se o imóvel não existir
     * @throws ValidacaoException      Se os dados forem inválidos
     */
    public function atualizar(string $id, array $dados): array
    {
        if ($this->imovelModel->buscarPorId($id) === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        $regras = [];
        if (isset($dados['tipo']))    $regras['tipo']    = 'min:2|max:80';
        if (isset($dados['tamanho'])) $regras['tamanho'] = 'min:1|max:80';
        if (isset($dados['status']))  $regras['status']  = 'enum:disponivel,locado,em_vistoria';

        if (!empty($regras)) {
            $erros = Validator::validar($dados, $regras);
            if (!empty($erros)) {
                throw new ValidacaoException($erros);
            }
        }

        $camposPermitidos = ['tipo', 'tamanho', 'status'];
        $campos = array_filter(
            array_intersect_key($dados, array_flip($camposPermitidos)),
            fn($v) => $v !== null && $v !== ''
        );

        if (isset($dados['garagem'])) {
            $campos['garagem'] = $dados['garagem'] ? 1 : 0;
        }
        if (isset($dados['garagem_vagas'])) {
            $campos['garagem_vagas'] = (int) $dados['garagem_vagas'];
        }

        if (!empty($campos)) {
            $this->imovelModel->atualizar($id, $campos);
        }

        $imovelAtualizado = $this->imovelModel->buscarPorId($id);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'imovel',
            entidadeId: $id,
            payload:    $campos,
        );

        return $imovelAtualizado;
    }

    /**
     * Desativa um imóvel via soft delete.
     *
     * @throws NaoEncontradoException  Se o imóvel não existir
     * @throws RegraDeNegocioException Se o imóvel tiver contrato ativo
     */
    public function excluir(string $id): void
    {
        $imovel = $this->imovelModel->buscarPorId($id);

        if ($imovel === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        if ($imovel['status'] === 'locado') {
            throw new RegraDeNegocioException('Não é possível excluir um imóvel com contrato ativo (status: locado)');
        }

        $this->imovelModel->desativar($id);

        $this->logService->registrar(
            acao:       'DELETE',
            entidade:   'imovel',
            entidadeId: $id,
            payload:    ['tipo' => $imovel['tipo'], 'status' => $imovel['status']],
        );
    }

    // ──── Endereço ───────────────────────────────────────────────────────────

    /**
     * Cria ou atualiza o endereço de um imóvel, preenchendo lat/lng via geocodificação.
     *
     * @param  array{rua: string, numero: string, cidade: string, estado: string, cep: string, complemento?: string, bloco?: string, andar?: string} $dados
     * @return array{id: string, imovel_id: string, rua: string, numero: string, complemento: string|null, bloco: string|null, andar: string|null, cidade: string, estado: string, cep: string, latitude: string|null, longitude: string|null}
     * @throws NaoEncontradoException Se o imóvel não existir
     * @throws ValidacaoException     Se os dados forem inválidos
     */
    public function salvarEndereco(string $imovelId, array $dados): array
    {
        if ($this->imovelModel->buscarPorId($imovelId) === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        $erros = Validator::validar($dados, [
            'rua'    => 'obrigatorio|min:2|max:200',
            'numero' => 'obrigatorio|max:20',
            'cidade' => 'obrigatorio|min:2|max:100',
            'estado' => 'obrigatorio|tamanho:2',
            'cep'    => 'obrigatorio|cep',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $coordenadas = $this->geocodingService->buscarCoordenadas(
            "{$dados['rua']}, {$dados['numero']}, {$dados['cidade']}, {$dados['estado']}, Brasil"
        );

        $enderecoExistente = $this->enderecoModel->buscarPorImovelId($imovelId);

        if ($enderecoExistente !== null) {
            $campos = [
                'rua'         => $dados['rua'],
                'numero'      => $dados['numero'],
                'complemento' => $dados['complemento'] ?? null,
                'bloco'       => $dados['bloco']       ?? null,
                'andar'       => $dados['andar']       ?? null,
                'cidade'      => $dados['cidade'],
                'estado'      => strtoupper($dados['estado']),
                'cep'         => $dados['cep'],
                'latitude'    => $coordenadas['latitude']  ?? null,
                'longitude'   => $coordenadas['longitude'] ?? null,
            ];

            $this->enderecoModel->atualizar($imovelId, $campos);

            $this->logService->registrar(
                acao:       'UPDATE',
                entidade:   'endereco',
                entidadeId: $enderecoExistente['id'],
                payload:    ['imovel_id' => $imovelId, 'cep' => $dados['cep']],
            );
        } else {
            $novoId = Uuid::gerar();
            $this->enderecoModel->inserir([
                'id'          => $novoId,
                'imovel_id'   => $imovelId,
                'rua'         => $dados['rua'],
                'numero'      => $dados['numero'],
                'complemento' => $dados['complemento'] ?? null,
                'bloco'       => $dados['bloco']       ?? null,
                'andar'       => $dados['andar']       ?? null,
                'cidade'      => $dados['cidade'],
                'estado'      => strtoupper($dados['estado']),
                'cep'         => $dados['cep'],
                'latitude'    => $coordenadas['latitude']  ?? null,
                'longitude'   => $coordenadas['longitude'] ?? null,
            ]);

            $this->logService->registrar(
                acao:       'CREATE',
                entidade:   'endereco',
                entidadeId: $novoId,
                payload:    ['imovel_id' => $imovelId, 'cep' => $dados['cep']],
            );
        }

        return $this->enderecoModel->buscarPorImovelId($imovelId);
    }

    /**
     * Retorna o endereço de um imóvel.
     *
     * @return array{id: string, imovel_id: string, rua: string, numero: string, complemento: string|null, bloco: string|null, andar: string|null, cidade: string, estado: string, cep: string, latitude: string|null, longitude: string|null}
     * @throws NaoEncontradoException  Se o imóvel ou endereço não existir
     * @throws AcessoNegadoException   Se o locatário não tiver contrato ativo para este imóvel
     */
    public function buscarEndereco(string $imovelId): array
    {
        // Reutiliza a verificação de acesso do imóvel
        $this->buscarPorId($imovelId);

        $endereco = $this->enderecoModel->buscarPorImovelId($imovelId);

        if ($endereco === null) {
            throw new NaoEncontradoException('Endereço');
        }

        return $endereco;
    }

    // ──── Cômodos ────────────────────────────────────────────────────────────

    /**
     * Cria um cômodo vinculado a um imóvel.
     *
     * @param  array{tipo: string, descricao?: string} $dados
     * @return array{id: string, imovel_id: string, tipo: string, descricao: string|null}
     * @throws NaoEncontradoException Se o imóvel não existir
     * @throws ValidacaoException     Se os dados forem inválidos
     */
    public function criarComodo(string $imovelId, array $dados): array
    {
        if ($this->imovelModel->buscarPorId($imovelId) === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        $erros = Validator::validar($dados, [
            'tipo' => 'obrigatorio|min:2|max:80',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $novoId = Uuid::gerar();
        $this->comodoModel->inserir([
            'id'        => $novoId,
            'imovel_id' => $imovelId,
            'tipo'      => $dados['tipo'],
            'descricao' => $dados['descricao'] ?? null,
        ]);

        $comodo = $this->comodoModel->buscarPorId($novoId, $imovelId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'comodo',
            entidadeId: $novoId,
            payload:    ['imovel_id' => $imovelId, 'tipo' => $dados['tipo']],
        );

        return $comodo;
    }

    /**
     * Lista os cômodos de um imóvel.
     *
     * @return list<array{id: string, imovel_id: string, tipo: string, descricao: string|null}>
     * @throws NaoEncontradoException Se o imóvel não existir
     */
    public function listarComodos(string $imovelId): array
    {
        if ($this->imovelModel->buscarPorId($imovelId) === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        return $this->comodoModel->listarPorImovel($imovelId);
    }

    /**
     * Atualiza um cômodo de um imóvel.
     *
     * @param  array{tipo?: string, descricao?: string} $dados
     * @return array{id: string, imovel_id: string, tipo: string, descricao: string|null}
     * @throws NaoEncontradoException Se o imóvel ou cômodo não existir
     * @throws ValidacaoException     Se os dados forem inválidos
     */
    public function atualizarComodo(string $imovelId, string $comodoId, array $dados): array
    {
        if ($this->imovelModel->buscarPorId($imovelId) === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        if ($this->comodoModel->buscarPorId($comodoId, $imovelId) === null) {
            throw new NaoEncontradoException('Cômodo');
        }

        if (isset($dados['tipo'])) {
            $erros = Validator::validar($dados, ['tipo' => 'min:2|max:80']);
            if (!empty($erros)) {
                throw new ValidacaoException($erros);
            }
        }

        $camposPermitidos = ['tipo', 'descricao'];
        $campos = array_intersect_key($dados, array_flip($camposPermitidos));

        if (!empty($campos)) {
            $this->comodoModel->atualizar($comodoId, $campos);
        }

        $comodoAtualizado = $this->comodoModel->buscarPorId($comodoId, $imovelId);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'comodo',
            entidadeId: $comodoId,
            payload:    $campos,
        );

        return $comodoAtualizado;
    }

    /**
     * Remove um cômodo de um imóvel.
     *
     * @throws NaoEncontradoException Se o imóvel ou cômodo não existir
     */
    public function excluirComodo(string $imovelId, string $comodoId): void
    {
        if ($this->imovelModel->buscarPorId($imovelId) === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        $comodo = $this->comodoModel->buscarPorId($comodoId, $imovelId);

        if ($comodo === null) {
            throw new NaoEncontradoException('Cômodo');
        }

        $this->comodoModel->deletar($comodoId);

        $this->logService->registrar(
            acao:       'DELETE',
            entidade:   'comodo',
            entidadeId: $comodoId,
            payload:    ['imovel_id' => $imovelId, 'tipo' => $comodo['tipo']],
        );
    }
}
