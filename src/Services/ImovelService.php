<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Conexao;
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
        private readonly ImovelModel      $imovelModel,
        private readonly EnderecoModel    $enderecoModel,
        private readonly ComodoModel      $comodoModel,
        private readonly GeocodingService $geocodingService,
        private readonly LogService       $logService
    ) {}

    // ──── Imóveis ────────────────────────────────────────────────────────────

    /**
     * Cria um novo imóvel, opcionalmente com endereço na mesma operação.
     *
     * @param  array{tipo: string, tamanho: string, garagem?: bool, garagem_vagas?: int, endereco?: array} $dados
     * @return array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string, endereco: array|null}
     * @throws ValidacaoException      Se os dados do imóvel ou do endereço forem inválidos
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

        $dadosEndereco = isset($dados['endereco']) && is_array($dados['endereco'])
            ? $dados['endereco']
            : null;

        if ($dadosEndereco !== null) {
            $errosEndereco = $this->validarCamposEndereco($dadosEndereco);
            if (!empty($errosEndereco)) {
                throw new ValidacaoException(['endereco' => $errosEndereco]);
            }
        }

        $novoId = Uuid::gerar();
        $dadosImovel = [
            'id'            => $novoId,
            'tipo'          => $dados['tipo'],
            'tamanho'       => $dados['tamanho'],
            'garagem'       => isset($dados['garagem']) && $dados['garagem'] ? 1 : 0,
            'garagem_vagas' => (int) ($dados['garagem_vagas'] ?? 0),
        ];

        if ($dadosEndereco !== null) {
            $coordenadas  = $this->geocodingService->buscarCoordenadas(
                "{$dadosEndereco['rua']}, {$dadosEndereco['numero']}, {$dadosEndereco['cidade']}, {$dadosEndereco['estado']}, Brasil"
            );
            $novoEnderecoId = Uuid::gerar();
            $registroEndereco = $this->montarRegistroEndereco($novoEnderecoId, $novoId, $dadosEndereco, $coordenadas);

            $conexao = Conexao::obter();
            $conexao->beginTransaction();
            try {
                $this->imovelModel->inserir($dadosImovel);
                $this->enderecoModel->inserir($registroEndereco);
                $conexao->commit();
            } catch (\Throwable $e) {
                $conexao->rollBack();
                throw $e;
            }
        } else {
            $this->imovelModel->inserir($dadosImovel);
        }

        $imovel            = $this->imovelModel->buscarPorId($novoId);
        $imovel['endereco'] = $this->enderecoModel->buscarPorImovelId($novoId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'imovel',
            entidadeId: $novoId,
            payload:    ['tipo' => $dados['tipo'], 'tamanho' => $dados['tamanho']],
        );

        return $imovel;
    }

    /**
     * Lista imóveis ativos com paginação, filtro e endereço embutido.
     *
     * @return array{itens: list<array>, total: int, pagina: int, itensPorPagina: int}
     */
    public function listar(int $pagina, int $itensPorPagina, string|null $status): array
    {
        $pagina         = max(1, $pagina);
        $itensPorPagina = max(1, min(100, $itensPorPagina));

        $itens = $this->imovelModel->listar($pagina, $itensPorPagina, $status);
        $total = $this->imovelModel->contar($status);

        if (!empty($itens)) {
            $ids       = array_column($itens, 'id');
            $enderecos = $this->enderecoModel->buscarPorImovelIds($ids);

            $itens = array_map(function (array $imovel) use ($enderecos): array {
                $imovel['endereco'] = $enderecos[$imovel['id']] ?? null;
                return $imovel;
            }, $itens);
        }

        return compact('itens', 'total', 'pagina', 'itensPorPagina');
    }

    /**
     * Busca um imóvel pelo ID com endereço embutido.
     * Admin acessa qualquer imóvel; locatário apenas o vinculado ao seu contrato ativo.
     *
     * @return array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string, endereco: array|null}
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

        $imovel['endereco'] = $this->enderecoModel->buscarPorImovelId($id);

        return $imovel;
    }

    /**
     * Atualiza dados de um imóvel, opcionalmente com endereço na mesma operação.
     *
     * @param  array{tipo?: string, tamanho?: string, garagem?: bool, garagem_vagas?: int, status?: string, endereco?: array} $dados
     * @return array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string, endereco: array|null}
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

        $dadosEndereco = isset($dados['endereco']) && is_array($dados['endereco'])
            ? $dados['endereco']
            : null;

        if ($dadosEndereco !== null) {
            $errosEndereco = $this->validarCamposEndereco($dadosEndereco);
            if (!empty($errosEndereco)) {
                throw new ValidacaoException(['endereco' => $errosEndereco]);
            }
        }

        $camposPermitidos = ['tipo', 'tamanho', 'status'];
        $camposImovel = array_filter(
            array_intersect_key($dados, array_flip($camposPermitidos)),
            fn($v) => $v !== null && $v !== ''
        );

        if (isset($dados['garagem'])) {
            $camposImovel['garagem'] = $dados['garagem'] ? 1 : 0;
        }
        if (isset($dados['garagem_vagas'])) {
            $camposImovel['garagem_vagas'] = (int) $dados['garagem_vagas'];
        }

        if (!empty($camposImovel) || $dadosEndereco !== null) {
            $conexao = Conexao::obter();
            $conexao->beginTransaction();
            try {
                if (!empty($camposImovel)) {
                    $this->imovelModel->atualizar($id, $camposImovel);
                }

                if ($dadosEndereco !== null) {
                    $coordenadas       = $this->geocodingService->buscarCoordenadas(
                        "{$dadosEndereco['rua']}, {$dadosEndereco['numero']}, {$dadosEndereco['cidade']}, {$dadosEndereco['estado']}, Brasil"
                    );
                    $enderecoExistente = $this->enderecoModel->buscarPorImovelId($id);

                    if ($enderecoExistente !== null) {
                        $this->enderecoModel->atualizar($id, $this->montarCamposEndereco($dadosEndereco, $coordenadas));

                        $this->logService->registrar(
                            acao:       'UPDATE',
                            entidade:   'endereco',
                            entidadeId: $enderecoExistente['id'],
                            payload:    ['imovel_id' => $id, 'cep' => $dadosEndereco['cep']],
                        );
                    } else {
                        $novoEnderecoId = Uuid::gerar();
                        $this->enderecoModel->inserir(
                            $this->montarRegistroEndereco($novoEnderecoId, $id, $dadosEndereco, $coordenadas)
                        );

                        $this->logService->registrar(
                            acao:       'CREATE',
                            entidade:   'endereco',
                            entidadeId: $novoEnderecoId,
                            payload:    ['imovel_id' => $id, 'cep' => $dadosEndereco['cep']],
                        );
                    }
                }

                $conexao->commit();
            } catch (\Throwable $e) {
                $conexao->rollBack();
                throw $e;
            }
        }

        $imovelAtualizado            = $this->imovelModel->buscarPorId($id);
        $imovelAtualizado['endereco'] = $this->enderecoModel->buscarPorImovelId($id);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'imovel',
            entidadeId: $id,
            payload:    $camposImovel ?? [],
        );

        return $imovelAtualizado;
    }

    /**
     * Desativa um imóvel e seu endereço via soft delete em transação.
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

        $conexao = Conexao::obter();
        $conexao->beginTransaction();
        try {
            $this->enderecoModel->desativarPorImovelId($id);
            $this->imovelModel->desativar($id);
            $conexao->commit();
        } catch (\Throwable $e) {
            $conexao->rollBack();
            throw $e;
        }

        $this->logService->registrar(
            acao:       'DELETE',
            entidade:   'imovel',
            entidadeId: $id,
            payload:    ['tipo' => $imovel['tipo'], 'status' => $imovel['status']],
        );
    }

    // ──── Endereço (endpoints dedicados) ────────────────────────────────────

    /**
     * Cria ou atualiza o endereço de um imóvel via endpoint dedicado.
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

        $erros = $this->validarCamposEndereco($dados);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $coordenadas       = $this->geocodingService->buscarCoordenadas(
            "{$dados['rua']}, {$dados['numero']}, {$dados['cidade']}, {$dados['estado']}, Brasil"
        );
        $enderecoExistente = $this->enderecoModel->buscarPorImovelId($imovelId);

        if ($enderecoExistente !== null) {
            $this->enderecoModel->atualizar($imovelId, $this->montarCamposEndereco($dados, $coordenadas));

            $this->logService->registrar(
                acao:       'UPDATE',
                entidade:   'endereco',
                entidadeId: $enderecoExistente['id'],
                payload:    ['imovel_id' => $imovelId, 'cep' => $dados['cep']],
            );
        } else {
            $novoId = Uuid::gerar();
            $this->enderecoModel->inserir($this->montarRegistroEndereco($novoId, $imovelId, $dados, $coordenadas));

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
     * Retorna o endereço de um imóvel via endpoint dedicado.
     *
     * @return array{id: string, imovel_id: string, rua: string, numero: string, complemento: string|null, bloco: string|null, andar: string|null, cidade: string, estado: string, cep: string, latitude: string|null, longitude: string|null}
     * @throws NaoEncontradoException  Se o imóvel ou endereço não existir
     * @throws AcessoNegadoException   Se o locatário não tiver contrato ativo para este imóvel
     */
    public function buscarEndereco(string $imovelId): array
    {
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

    // ──── Helpers privados ───────────────────────────────────────────────────

    /**
     * Valida os campos obrigatórios de um endereço.
     *
     * @param  array<string, mixed> $dados
     * @return array<string, string>
     */
    private function validarCamposEndereco(array $dados): array
    {
        return Validator::validar($dados, [
            'rua'    => 'obrigatorio|min:2|max:200',
            'numero' => 'obrigatorio|max:20',
            'cidade' => 'obrigatorio|min:2|max:100',
            'estado' => 'obrigatorio|tamanho:2',
            'cep'    => 'obrigatorio|cep',
        ]);
    }

    /**
     * Monta o array de campos para UPDATE de endereço.
     *
     * @param  array<string, mixed>                                        $dados
     * @param  array{latitude?: string|null, longitude?: string|null}|null $coordenadas
     * @return array<string, mixed>
     */
    private function montarCamposEndereco(array $dados, array|null $coordenadas): array
    {
        return [
            'rua'         => $dados['rua'],
            'numero'      => $dados['numero'],
            'complemento' => $dados['complemento'] ?? null,
            'bloco'       => $dados['bloco']       ?? null,
            'andar'       => $dados['andar']       ?? null,
            'cidade'      => $dados['cidade'],
            'estado'      => strtoupper($dados['estado']),
            'cep'         => $dados['cep'],
            'latitude'    => $coordenadas !== null ? ($coordenadas['latitude']  ?? null) : null,
            'longitude'   => $coordenadas !== null ? ($coordenadas['longitude'] ?? null) : null,
        ];
    }

    /**
     * Monta o array completo para INSERT de endereço.
     *
     * @param  array<string, mixed>                                        $dados
     * @param  array{latitude?: string|null, longitude?: string|null}|null $coordenadas
     * @return array<string, mixed>
     */
    private function montarRegistroEndereco(string $id, string $imovelId, array $dados, array|null $coordenadas): array
    {
        return [
            'id'        => $id,
            'imovel_id' => $imovelId,
            ...$this->montarCamposEndereco($dados, $coordenadas),
        ];
    }
}
