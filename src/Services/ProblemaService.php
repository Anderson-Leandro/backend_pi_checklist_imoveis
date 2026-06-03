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
use App\Models\AtualizacaoProblemaModel;
use App\Models\ComodoModel;
use App\Models\ContratoModel;
use App\Models\RegistroProblemaModel;
use App\Services\Storage\StorageInterface;

class ProblemaService
{
    private const TAMANHO_MAXIMO_FOTO  = 10 * 1024 * 1024;
    private const TIPOS_FOTO_PERMITIDOS = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly RegistroProblemaModel  $problemaModel,
        private readonly AtualizacaoProblemaModel $atualizacaoModel,
        private readonly ContratoModel          $contratoModel,
        private readonly ComodoModel            $comodoModel,
        private readonly StorageInterface       $storage,
        private readonly LogService             $logService,
        private readonly NotificationService    $notificationService
    ) {}

    /**
     * Abre um novo problema vinculado a um contrato (locatário).
     *
     * @param  array{comodo_id: string, titulo: string, descricao: string} $dados
     * @param  array{name: string, tmp_name: string, size: int, type: string, error: int}|null $arquivo
     * @return array{id: string, contrato_id: string, comodo_id: string, titulo: string, descricao: string, foto_url: string|null, status: string, created_at: string}
     * @throws ValidacaoException      Se os dados forem inválidos ou a foto for inválida
     * @throws NaoEncontradoException  Se o contrato ou cômodo não existir
     * @throws AcessoNegadoException   Se o usuário não for o locatário do contrato
     * @throws RegraDeNegocioException Se o contrato não estiver ativo
     */
    public function criar(string $contratoId, array $dados, array|null $arquivo = null): array
    {
        $erros = Validator::validar($dados, [
            'comodo_id' => 'obrigatorio|uuid',
            'titulo'    => 'obrigatorio|min:3|max:150',
            'descricao' => 'obrigatorio|min:5',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $contrato = $this->contratoModel->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        if ($contrato['status'] !== 'ativo') {
            throw new RegraDeNegocioException('Problemas só podem ser registrados em contratos ativos');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($usuario['role'] === 'locatario' && $contrato['locatario_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Você não tem permissão para registrar problemas neste contrato');
        }

        $comodo = $this->comodoModel->buscarPorId($dados['comodo_id'], $contrato['imovel_id']);
        if ($comodo === null) {
            throw new NaoEncontradoException('Cômodo');
        }

        $fotoChave = null;
        if ($arquivo !== null && isset($arquivo['error']) && $arquivo['error'] !== UPLOAD_ERR_NO_FILE) {
            $fotoChave = $this->processarFoto($arquivo);
        }

        $novoId = Uuid::gerar();

        try {
            $this->problemaModel->inserir([
                'id'          => $novoId,
                'contrato_id' => $contratoId,
                'comodo_id'   => $dados['comodo_id'],
                'titulo'      => $dados['titulo'],
                'descricao'   => $dados['descricao'],
                'foto_url'    => $fotoChave,
            ]);
        } catch (\Throwable $e) {
            if ($fotoChave !== null) {
                $this->storage->delete($fotoChave);
            }
            throw $e;
        }

        $problema = $this->resolverUrlFotoProblema($this->problemaModel->buscarPorId($novoId));

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'registro_problema',
            entidadeId: $novoId,
            payload:    [
                'contrato_id' => $contratoId,
                'comodo_id'   => $dados['comodo_id'],
                'titulo'      => $dados['titulo'],
            ],
        );

        $this->notificationService->notificarAdmins(
            assunto:  'Novo problema registrado — ' . $dados['titulo'],
            mensagem: "Um novo problema foi registrado no contrato {$contratoId}.\n\n"
                    . "Cômodo: {$comodo['tipo']}\n"
                    . "Título: {$dados['titulo']}\n"
                    . "Descrição: {$dados['descricao']}\n\n"
                    . 'Acesse o painel para visualizar os detalhes.',
        );

        return $problema;
    }

    /**
     * Lista os problemas de um contrato (admin vê todos; locatário vê apenas os seus).
     *
     * @return list<array{id: string, contrato_id: string, comodo_id: string, titulo: string, descricao: string, foto_url: string|null, status: string, created_at: string}>
     * @throws NaoEncontradoException Se o contrato não existir
     * @throws AcessoNegadoException  Se o locatário tentar acessar contrato de outro
     */
    public function listarPorContrato(string $contratoId): array
    {
        $contrato = $this->contratoModel->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($usuario['role'] === 'locatario' && $contrato['locatario_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Você não tem acesso a este contrato');
        }

        return array_map(
            [$this, 'resolverUrlFotoProblema'],
            $this->problemaModel->listarPorContrato($contratoId)
        );
    }

    /**
     * Busca um problema pelo ID (admin vê qualquer; locatário vê apenas os seus).
     *
     * @return array{id: string, contrato_id: string, comodo_id: string, titulo: string, descricao: string, foto_url: string|null, status: string, created_at: string}
     * @throws NaoEncontradoException Se o problema ou o contrato não existir
     * @throws AcessoNegadoException  Se o locatário tentar acessar problema de outro contrato
     */
    public function buscarPorId(string $id): array
    {
        $problema = $this->problemaModel->buscarPorId($id);
        if ($problema === null) {
            throw new NaoEncontradoException('Problema');
        }

        $contrato = $this->contratoModel->buscarPorId($problema['contrato_id']);
        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($usuario['role'] === 'locatario' && $contrato['locatario_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Você não tem acesso a este problema');
        }

        return $this->resolverUrlFotoProblema($problema);
    }

    /**
     * Atualiza o status de um problema (admin).
     *
     * @param  array{status: string} $dados
     * @return array{id: string, contrato_id: string, comodo_id: string, titulo: string, descricao: string, foto_url: string|null, status: string, created_at: string}
     * @throws ValidacaoException     Se o status for inválido
     * @throws NaoEncontradoException Se o problema não existir
     */
    public function atualizarStatus(string $id, array $dados): array
    {
        $erros = Validator::validar($dados, [
            'status' => 'obrigatorio|enum:aberto,em_andamento,resolvido',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $problema = $this->problemaModel->buscarPorId($id);
        if ($problema === null) {
            throw new NaoEncontradoException('Problema');
        }

        $this->problemaModel->atualizarStatus($id, $dados['status']);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'registro_problema',
            entidadeId: $id,
            payload:    ['status_anterior' => $problema['status'], 'status_novo' => $dados['status']],
        );

        return $this->resolverUrlFotoProblema($this->problemaModel->buscarPorId($id));
    }

    /**
     * Adiciona uma atualização a um problema com foto opcional (admin).
     *
     * @param  array{descricao: string} $dados
     * @param  array{name: string, tmp_name: string, size: int, type: string, error: int}|null $arquivo
     * @return array{id: string, problema_id: string, autor_id: string, descricao: string, foto_url: string|null, created_at: string}
     * @throws ValidacaoException      Se os dados forem inválidos ou a foto for inválida
     * @throws NaoEncontradoException  Se o problema não existir
     */
    public function criarAtualizacao(string $problemaId, array $dados, array|null $arquivo = null): array
    {
        $erros = Validator::validar($dados, [
            'descricao' => 'obrigatorio|min:5',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $problema = $this->problemaModel->buscarPorId($problemaId);
        if ($problema === null) {
            throw new NaoEncontradoException('Problema');
        }

        $fotoChave = null;
        if ($arquivo !== null && isset($arquivo['error']) && $arquivo['error'] !== UPLOAD_ERR_NO_FILE) {
            $fotoChave = $this->processarFoto($arquivo);
        }

        $autor   = UsuarioAutenticado::obterOuFalhar();
        $novoId  = Uuid::gerar();

        try {
            $this->atualizacaoModel->inserir([
                'id'          => $novoId,
                'problema_id' => $problemaId,
                'autor_id'    => $autor['id'],
                'descricao'   => $dados['descricao'],
                'foto_url'    => $fotoChave,
            ]);
        } catch (\Throwable $e) {
            if ($fotoChave !== null) {
                $this->storage->delete($fotoChave);
            }
            throw $e;
        }

        $atualizacao = $this->resolverUrlFotoAtualizacao($this->atualizacaoModel->buscarPorId($novoId));

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'atualizacao_problema',
            entidadeId: $novoId,
            payload:    ['problema_id' => $problemaId, 'autor_id' => $autor['id']],
        );

        $contrato = $this->contratoModel->buscarPorId($problema['contrato_id']);
        if ($contrato !== null) {
            $this->notificationService->enviar(
                usuarioId: $contrato['locatario_id'],
                assunto:   'Atualização no seu problema — ' . $problema['titulo'],
                mensagem:  "Seu problema recebeu uma atualização.\n\n"
                         . "Problema: {$problema['titulo']}\n"
                         . "Atualização: {$dados['descricao']}\n\n"
                         . 'Acesse o painel para visualizar os detalhes.',
            );
        }

        return $atualizacao;
    }

    /**
     * Lista as atualizações de um problema em ordem cronológica (admin e locatário).
     *
     * @return list<array{id: string, problema_id: string, autor_id: string, descricao: string, foto_url: string|null, created_at: string}>
     * @throws NaoEncontradoException Se o problema não existir
     * @throws AcessoNegadoException  Se o locatário não for do contrato relacionado
     */
    public function listarAtualizacoes(string $problemaId): array
    {
        $problema = $this->problemaModel->buscarPorId($problemaId);
        if ($problema === null) {
            throw new NaoEncontradoException('Problema');
        }

        $contrato = $this->contratoModel->buscarPorId($problema['contrato_id']);
        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($usuario['role'] === 'locatario' && $contrato['locatario_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Você não tem acesso a este problema');
        }

        return array_map(
            [$this, 'resolverUrlFotoAtualizacao'],
            $this->atualizacaoModel->listarPorProblema($problemaId)
        );
    }

    private function resolverUrlFotoProblema(array|null $problema): array|null
    {
        if ($problema === null) {
            return null;
        }

        if ($problema['foto_url'] !== null) {
            $problema['foto_url'] = $this->storage->resolverUrl($problema['foto_url']);
        }

        return $problema;
    }

    private function resolverUrlFotoAtualizacao(array $atualizacao): array
    {
        if ($atualizacao['foto_url'] !== null) {
            $atualizacao['foto_url'] = $this->storage->resolverUrl($atualizacao['foto_url']);
        }

        return $atualizacao;
    }

    private function processarFoto(array $arquivo): string
    {
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new ValidacaoException(['foto' => 'Erro no upload do arquivo']);
        }

        if ($arquivo['size'] > self::TAMANHO_MAXIMO_FOTO) {
            throw new ValidacaoException(['foto' => 'A foto não pode exceder 10 MB']);
        }

        $tipoReal = mime_content_type($arquivo['tmp_name']);
        if (!in_array($tipoReal, self::TIPOS_FOTO_PERMITIDOS, strict: true)) {
            throw new ValidacaoException(['foto' => 'Apenas imagens JPG e PNG são permitidas']);
        }

        return $this->storage->store($arquivo, 'fotos/problemas');
    }
}
