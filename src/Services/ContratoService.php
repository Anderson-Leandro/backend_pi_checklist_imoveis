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
use App\Models\ContratoModel;
use App\Models\ImovelModel;
use App\Models\UsuarioModel;

class ContratoService
{
    public function __construct(
        private readonly ContratoModel $contratoModel,
        private readonly ImovelModel   $imovelModel,
        private readonly UsuarioModel  $usuarioModel,
        private readonly LogService    $logService
    ) {}

    /**
     * Cria um novo contrato vinculando imóvel e locatário.
     * Atualiza o status do imóvel para 'locado' em transação.
     *
     * @param  array{imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string} $dados
     * @return array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string, status: string, created_at: string}
     * @throws ValidacaoException      Se os dados forem inválidos
     * @throws NaoEncontradoException  Se o imóvel ou locatário não existir
     * @throws RegraDeNegocioException Se o imóvel já tiver contrato ativo ou o locatário tiver role errada
     */
    public function criar(array $dados): array
    {
        $erros = Validator::validar($dados, [
            'imovel_id'    => 'obrigatorio|uuid',
            'locatario_id' => 'obrigatorio|uuid',
            'data_inicio'  => 'obrigatorio|data',
            'data_fim'     => 'obrigatorio|data',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        if ($dados['data_fim'] <= $dados['data_inicio']) {
            throw new ValidacaoException(['data_fim' => 'A data de fim deve ser posterior à data de início.']);
        }

        $imovel = $this->imovelModel->buscarPorId($dados['imovel_id']);
        if ($imovel === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        if ($imovel['status'] === 'locado' || $this->contratoModel->imovelPossuiContratoAtivo($dados['imovel_id'])) {
            throw new RegraDeNegocioException('Este imóvel já possui um contrato ativo');
        }

        $locatario = $this->usuarioModel->buscarPorId($dados['locatario_id']);
        if ($locatario === null) {
            throw new NaoEncontradoException('Locatário');
        }

        if ($locatario['role'] !== 'locatario') {
            throw new RegraDeNegocioException('O usuário informado não possui a role de locatário');
        }

        $novoId  = Uuid::gerar();
        $conexao = \App\Database\Conexao::obter();
        $conexao->beginTransaction();

        try {
            $this->contratoModel->inserir([
                'id'           => $novoId,
                'imovel_id'    => $dados['imovel_id'],
                'locatario_id' => $dados['locatario_id'],
                'data_inicio'  => $dados['data_inicio'],
                'data_fim'     => $dados['data_fim'],
            ]);

            $this->imovelModel->atualizar($dados['imovel_id'], ['status' => 'locado']);

            $conexao->commit();
        } catch (\Throwable $excecao) {
            $conexao->rollBack();
            throw $excecao;
        }

        $contrato = $this->contratoModel->buscarPorId($novoId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'contrato',
            entidadeId: $novoId,
            payload:    [
                'imovel_id'    => $dados['imovel_id'],
                'locatario_id' => $dados['locatario_id'],
                'data_inicio'  => $dados['data_inicio'],
                'data_fim'     => $dados['data_fim'],
            ],
        );

        return $contrato;
    }

    /**
     * Lista contratos com paginação e filtros opcionais.
     * Locatário vê apenas os próprios contratos.
     *
     * @return array{itens: list<array>, total: int, pagina: int, itensPorPagina: int}
     */
    public function listar(int $pagina, int $itensPorPagina, string|null $status, string|null $imovelId): array
    {
        $pagina         = max(1, $pagina);
        $itensPorPagina = max(1, min(100, $itensPorPagina));

        $usuario     = UsuarioAutenticado::obterOuFalhar();
        $locatarioId = $usuario['role'] === 'locatario' ? $usuario['id'] : null;

        $itens = $this->contratoModel->listar($pagina, $itensPorPagina, $status, $imovelId, $locatarioId);
        $total = $this->contratoModel->contar($status, $imovelId, $locatarioId);

        return compact('itens', 'total', 'pagina', 'itensPorPagina');
    }

    /**
     * Busca um contrato pelo ID.
     * Locatário só pode acessar contratos onde é o locatário.
     *
     * @return array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string, status: string, created_at: string}
     * @throws NaoEncontradoException Se o contrato não existir
     * @throws AcessoNegadoException  Se o locatário tentar acessar contrato de outro
     */
    public function buscarPorId(string $id): array
    {
        $contrato = $this->contratoModel->buscarPorId($id);

        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($usuario['role'] === 'locatario' && $contrato['locatario_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Você não tem acesso a este contrato');
        }

        return $contrato;
    }

    /**
     * Encerra um contrato ativo e libera o imóvel.
     * Usa transação para garantir consistência entre contrato e imóvel.
     *
     * @return array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string, status: string, created_at: string}
     * @throws NaoEncontradoException  Se o contrato não existir
     * @throws RegraDeNegocioException Se o contrato não estiver ativo
     */
    public function encerrar(string $id): array
    {
        $contrato = $this->contratoModel->buscarPorId($id);

        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        if ($contrato['status'] !== 'ativo') {
            throw new RegraDeNegocioException(
                "Não é possível encerrar um contrato com status '{$contrato['status']}'"
            );
        }

        return $this->alterarStatusComAtualizacaoDeImovel($contrato, 'encerrado');
    }

    /**
     * Cancela um contrato ativo e libera o imóvel.
     *
     * @return array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string, status: string, created_at: string}
     * @throws NaoEncontradoException  Se o contrato não existir
     * @throws RegraDeNegocioException Se o contrato não estiver ativo
     */
    public function cancelar(string $id): array
    {
        $contrato = $this->contratoModel->buscarPorId($id);

        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        if ($contrato['status'] !== 'ativo') {
            throw new RegraDeNegocioException(
                "Não é possível cancelar um contrato com status '{$contrato['status']}'"
            );
        }

        return $this->alterarStatusComAtualizacaoDeImovel($contrato, 'cancelado');
    }

    /**
     * Altera o status do contrato e libera o imóvel em transação atômica.
     *
     * @param  array{id: string, imovel_id: string, status: string} $contrato
     * @return array{id: string, imovel_id: string, locatario_id: string, data_inicio: string, data_fim: string, status: string, created_at: string}
     */
    private function alterarStatusComAtualizacaoDeImovel(array $contrato, string $novoStatus): array
    {
        $conexao = \App\Database\Conexao::obter();
        $conexao->beginTransaction();

        try {
            $this->contratoModel->atualizarStatus($contrato['id'], $novoStatus);
            $this->imovelModel->atualizar($contrato['imovel_id'], ['status' => 'disponivel']);
            $conexao->commit();
        } catch (\Throwable $excecao) {
            $conexao->rollBack();
            throw $excecao;
        }

        $contratoAtualizado = $this->contratoModel->buscarPorId($contrato['id']);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'contrato',
            entidadeId: $contrato['id'],
            payload:    ['status_anterior' => $contrato['status'], 'novo_status' => $novoStatus],
        );

        return $contratoAtualizado;
    }
}
