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
use App\Models\ChecklistItemModel;
use App\Models\ChecklistModel;
use App\Models\ComodoModel;
use App\Models\ContratoModel;
use App\Models\FotoChecklistModel;
use App\Models\ItemVistoriaModel;
use App\Services\Storage\StorageInterface;

class ChecklistService
{
    private const TAMANHO_MAXIMO_FOTO = 10 * 1024 * 1024; // 10 MB
    private const TIPOS_FOTO_PERMITIDOS = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly ChecklistModel     $checklistModel,
        private readonly ChecklistItemModel $checklistItemModel,
        private readonly FotoChecklistModel $fotoChecklistModel,
        private readonly ContratoModel      $contratoModel,
        private readonly ComodoModel        $comodoModel,
        private readonly ItemVistoriaModel  $itemVistoriaModel,
        private readonly StorageInterface   $storage,
        private readonly LogService         $logService
    ) {}

    /**
     * Cria um novo checklist vinculado a um contrato (admin).
     *
     * @param  array{vistoriador_id: string, tipo: string} $dados
     * @return array{id: string, contrato_id: string, vistoriador_id: string, tipo: string, status: string, data_vistoria: string|null, created_at: string}
     * @throws ValidacaoException      Se os dados forem inválidos
     * @throws NaoEncontradoException  Se o contrato ou vistoriador não existir
     * @throws RegraDeNegocioException Se o contrato não estiver ativo
     */
    public function criar(string $contratoId, array $dados): array
    {
        $erros = Validator::validar($dados, [
            'vistoriador_id' => 'obrigatorio|uuid',
            'tipo'           => 'obrigatorio|enum:inicial,encerramento',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $contrato = $this->contratoModel->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        if ($contrato['status'] !== 'ativo') {
            throw new RegraDeNegocioException('Checklists só podem ser criados em contratos ativos');
        }

        $novoId = Uuid::gerar();

        $this->checklistModel->inserir([
            'id'             => $novoId,
            'contrato_id'    => $contratoId,
            'vistoriador_id' => $dados['vistoriador_id'],
            'tipo'           => $dados['tipo'],
        ]);

        $checklist = $this->checklistModel->buscarPorId($novoId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'checklist',
            entidadeId: $novoId,
            payload:    [
                'contrato_id'    => $contratoId,
                'vistoriador_id' => $dados['vistoriador_id'],
                'tipo'           => $dados['tipo'],
            ],
        );

        return $checklist;
    }

    /**
     * Lista os checklists de um contrato.
     * Locatário só pode listar checklists do próprio contrato.
     *
     * @return list<array{id: string, contrato_id: string, vistoriador_id: string, tipo: string, status: string, data_vistoria: string|null, created_at: string}>
     * @throws NaoEncontradoException Se o contrato não existir
     * @throws AcessoNegadoException  Se locatário tentar acessar contrato de outro
     */
    public function listarPorContrato(string $contratoId): array
    {
        $contrato = $this->contratoModel->buscarPorId($contratoId);
        if ($contrato === null) {
            throw new NaoEncontradoException('Contrato');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($usuario['role'] === 'locatario' && $contrato['locatario_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Você não tem acesso aos checklists deste contrato');
        }

        return $this->checklistModel->listarPorContrato($contratoId);
    }

    /**
     * Busca um checklist pelo ID com seus itens e fotos.
     * Controla acesso por role: admin vê qualquer, vistoriador vê os seus, locatário vê os do seu contrato.
     *
     * @return array{id: string, contrato_id: string, vistoriador_id: string, tipo: string, status: string, data_vistoria: string|null, created_at: string, itens: list<array>}
     * @throws NaoEncontradoException Se o checklist não existir
     * @throws AcessoNegadoException  Se o usuário não tiver acesso
     */
    public function buscarPorId(string $id): array
    {
        $checklist = $this->checklistModel->buscarPorId($id);

        if ($checklist === null) {
            throw new NaoEncontradoException('Checklist');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($usuario['role'] === 'vistoriador' && $checklist['vistoriador_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Você não tem acesso a este checklist');
        }

        if ($usuario['role'] === 'locatario') {
            $contrato = $this->contratoModel->buscarPorId($checklist['contrato_id']);
            if ($contrato === null || $contrato['locatario_id'] !== $usuario['id']) {
                throw new AcessoNegadoException('Você não tem acesso a este checklist');
            }
        }

        $checklist['itens'] = $this->checklistItemModel->listarPorChecklist($id);

        return $checklist;
    }

    /**
     * Envia o checklist para aceite do locatário (admin).
     * Altera o status para 'pendente_aceite'.
     *
     * @return array{id: string, contrato_id: string, vistoriador_id: string, tipo: string, status: string, data_vistoria: string|null, created_at: string}
     * @throws NaoEncontradoException  Se o checklist não existir
     * @throws RegraDeNegocioException Se o checklist não estiver em preenchimento
     */
    public function enviarParaAceite(string $id): array
    {
        $checklist = $this->checklistModel->buscarPorId($id);

        if ($checklist === null) {
            throw new NaoEncontradoException('Checklist');
        }

        if ($checklist['status'] !== 'em_preenchimento') {
            throw new RegraDeNegocioException(
                "Não é possível enviar para aceite um checklist com status '{$checklist['status']}'"
            );
        }

        if ($this->checklistItemModel->contarPorChecklist($id) === 0) {
            throw new RegraDeNegocioException('O checklist deve ter ao menos um item preenchido antes de ser enviado para aceite');
        }

        $this->checklistModel->atualizarStatus($id, 'pendente_aceite');

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'checklist',
            entidadeId: $id,
            payload:    ['status_anterior' => 'em_preenchimento', 'novo_status' => 'pendente_aceite'],
        );

        return $this->checklistModel->buscarPorId($id);
    }

    /**
     * Submete o checklist pelo vistoriador, finalizando o preenchimento.
     * Valida que há itens preenchidos; o status permanece 'em_preenchimento'
     * até o admin enviar para aceite.
     *
     * @return array{id: string, contrato_id: string, vistoriador_id: string, tipo: string, status: string, data_vistoria: string|null, created_at: string}
     * @throws NaoEncontradoException  Se o checklist não existir
     * @throws AcessoNegadoException   Se o vistoriador não for o responsável
     * @throws RegraDeNegocioException Se o checklist não estiver em preenchimento ou sem itens
     */
    public function submeter(string $id): array
    {
        $checklist = $this->checklistModel->buscarPorId($id);

        if ($checklist === null) {
            throw new NaoEncontradoException('Checklist');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($checklist['vistoriador_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Apenas o vistoriador responsável pode submeter este checklist');
        }

        if ($checklist['status'] !== 'em_preenchimento') {
            throw new RegraDeNegocioException(
                "Não é possível submeter um checklist com status '{$checklist['status']}'"
            );
        }

        if ($this->checklistItemModel->contarPorChecklist($id) === 0) {
            throw new RegraDeNegocioException('O checklist deve ter ao menos um item preenchido antes de ser submetido');
        }

        $dataVistoria = date('Y-m-d H:i:s');
        $this->checklistModel->atualizarDataVistoria($id, $dataVistoria);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'checklist',
            entidadeId: $id,
            payload:    ['acao' => 'submeter', 'data_vistoria' => $dataVistoria],
        );

        return $this->checklistModel->buscarPorId($id);
    }

    /**
     * Adiciona um item preenchido ao checklist (vistoriador).
     *
     * @param  array{comodo_id: string, item_vistoria_id: string, estado: string, observacao?: string|null} $dados
     * @return array{id: string, checklist_id: string, comodo_id: string, item_vistoria_id: string, estado: string, observacao: string|null, created_at: string, fotos: list<array>}
     * @throws ValidacaoException      Se os dados forem inválidos
     * @throws NaoEncontradoException  Se o checklist, cômodo ou item não existir
     * @throws AcessoNegadoException   Se o vistoriador não for o responsável
     * @throws RegraDeNegocioException Se o checklist não estiver em preenchimento
     */
    public function adicionarItem(string $checklistId, array $dados): array
    {
        $erros = Validator::validar($dados, [
            'comodo_id'        => 'obrigatorio|uuid',
            'item_vistoria_id' => 'obrigatorio|uuid',
            'estado'           => 'obrigatorio|enum:otimo,bom,regular,ruim',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $checklist = $this->checklistModel->buscarPorId($checklistId);
        if ($checklist === null) {
            throw new NaoEncontradoException('Checklist');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($checklist['vistoriador_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Apenas o vistoriador responsável pode preencher este checklist');
        }

        if ($checklist['status'] !== 'em_preenchimento') {
            throw new RegraDeNegocioException(
                "Não é possível adicionar itens a um checklist com status '{$checklist['status']}'"
            );
        }

        $contrato = $this->contratoModel->buscarPorId($checklist['contrato_id']);
        if ($this->comodoModel->buscarPorId($dados['comodo_id'], $contrato['imovel_id']) === null) {
            throw new NaoEncontradoException('Cômodo');
        }

        if ($this->itemVistoriaModel->buscarPorId($dados['item_vistoria_id']) === null) {
            throw new NaoEncontradoException('Item de vistoria');
        }

        $novoId = Uuid::gerar();

        $this->checklistItemModel->inserir([
            'id'               => $novoId,
            'checklist_id'     => $checklistId,
            'comodo_id'        => $dados['comodo_id'],
            'item_vistoria_id' => $dados['item_vistoria_id'],
            'estado'           => $dados['estado'],
            'observacao'       => $dados['observacao'] ?? null,
        ]);

        $item = $this->checklistItemModel->buscarPorId($novoId);
        $item['fotos'] = [];

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'checklist_item',
            entidadeId: $novoId,
            payload:    [
                'checklist_id'     => $checklistId,
                'comodo_id'        => $dados['comodo_id'],
                'item_vistoria_id' => $dados['item_vistoria_id'],
                'estado'           => $dados['estado'],
            ],
        );

        return $item;
    }

    /**
     * Atualiza um item de checklist (vistoriador).
     *
     * @param  array{estado: string, observacao?: string|null} $dados
     * @return array{id: string, checklist_id: string, comodo_id: string, item_vistoria_id: string, estado: string, observacao: string|null, created_at: string, fotos: list<array>}
     * @throws ValidacaoException      Se os dados forem inválidos
     * @throws NaoEncontradoException  Se o checklist ou item não existir
     * @throws AcessoNegadoException   Se o vistoriador não for o responsável
     * @throws RegraDeNegocioException Se o checklist não estiver em preenchimento ou item não pertencer a ele
     */
    public function atualizarItem(string $checklistId, string $itemId, array $dados): array
    {
        $erros = Validator::validar($dados, [
            'estado' => 'obrigatorio|enum:otimo,bom,regular,ruim',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $checklist = $this->checklistModel->buscarPorId($checklistId);
        if ($checklist === null) {
            throw new NaoEncontradoException('Checklist');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($checklist['vistoriador_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Apenas o vistoriador responsável pode editar itens deste checklist');
        }

        if ($checklist['status'] !== 'em_preenchimento') {
            throw new RegraDeNegocioException(
                "Não é possível editar itens de um checklist com status '{$checklist['status']}'"
            );
        }

        $item = $this->checklistItemModel->buscarPorId($itemId);
        if ($item === null) {
            throw new NaoEncontradoException('Item de checklist');
        }

        if ($item['checklist_id'] !== $checklistId) {
            throw new RegraDeNegocioException('Este item não pertence ao checklist informado');
        }

        $this->checklistItemModel->atualizar($itemId, [
            'estado'     => $dados['estado'],
            'observacao' => $dados['observacao'] ?? null,
        ]);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'checklist_item',
            entidadeId: $itemId,
            payload:    ['estado' => $dados['estado']],
        );

        $itemAtualizado          = $this->checklistItemModel->buscarPorId($itemId);
        $itemAtualizado['fotos'] = $this->fotoChecklistModel->listarPorItem($itemId);

        return $itemAtualizado;
    }

    /**
     * Faz upload de uma foto para um item de checklist (vistoriador).
     *
     * @return array{id: string, checklist_item_id: string, url: string, created_at: string}
     * @throws ValidacaoException      Se o arquivo for inválido (tipo ou tamanho)
     * @throws NaoEncontradoException  Se o checklist ou item não existir
     * @throws AcessoNegadoException   Se o vistoriador não for o responsável
     * @throws RegraDeNegocioException Se o checklist não estiver em preenchimento ou item não pertencer a ele
     */
    public function uploadFoto(string $checklistId, string $itemId, array $arquivo): array
    {
        $checklist = $this->checklistModel->buscarPorId($checklistId);
        if ($checklist === null) {
            throw new NaoEncontradoException('Checklist');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($checklist['vistoriador_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Apenas o vistoriador responsável pode adicionar fotos a este checklist');
        }

        if ($checklist['status'] !== 'em_preenchimento') {
            throw new RegraDeNegocioException(
                "Não é possível adicionar fotos a um checklist com status '{$checklist['status']}'"
            );
        }

        $item = $this->checklistItemModel->buscarPorId($itemId);
        if ($item === null) {
            throw new NaoEncontradoException('Item de checklist');
        }

        if ($item['checklist_id'] !== $checklistId) {
            throw new RegraDeNegocioException('Este item não pertence ao checklist informado');
        }

        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new ValidacaoException(['foto' => 'Erro no upload do arquivo']);
        }

        if ($arquivo['size'] > self::TAMANHO_MAXIMO_FOTO) {
            throw new ValidacaoException(['foto' => 'O arquivo deve ter no máximo 10MB']);
        }

        $tipoReal = mime_content_type($arquivo['tmp_name']);
        if (!in_array($tipoReal, self::TIPOS_FOTO_PERMITIDOS, strict: true)) {
            throw new ValidacaoException(['foto' => 'Apenas arquivos JPG e PNG são permitidos']);
        }

        $url    = $this->storage->store($arquivo, 'fotos/checklists');
        $novoId = Uuid::gerar();

        $this->fotoChecklistModel->inserir([
            'id'               => $novoId,
            'checklist_item_id' => $itemId,
            'url'              => $url,
        ]);

        $foto = $this->fotoChecklistModel->buscarPorId($novoId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'foto_checklist',
            entidadeId: $novoId,
            payload:    ['checklist_item_id' => $itemId, 'url' => $url],
        );

        return $foto;
    }

    /**
     * Exclui uma foto de um item de checklist (vistoriador).
     *
     * @throws NaoEncontradoException  Se o checklist, item ou foto não existir
     * @throws AcessoNegadoException   Se o vistoriador não for o responsável
     * @throws RegraDeNegocioException Se o checklist não estiver em preenchimento ou vínculos inválidos
     */
    public function excluirFoto(string $checklistId, string $itemId, string $fotoId): void
    {
        $checklist = $this->checklistModel->buscarPorId($checklistId);
        if ($checklist === null) {
            throw new NaoEncontradoException('Checklist');
        }

        $usuario = UsuarioAutenticado::obterOuFalhar();

        if ($checklist['vistoriador_id'] !== $usuario['id']) {
            throw new AcessoNegadoException('Apenas o vistoriador responsável pode remover fotos deste checklist');
        }

        if ($checklist['status'] !== 'em_preenchimento') {
            throw new RegraDeNegocioException(
                "Não é possível remover fotos de um checklist com status '{$checklist['status']}'"
            );
        }

        $item = $this->checklistItemModel->buscarPorId($itemId);
        if ($item === null) {
            throw new NaoEncontradoException('Item de checklist');
        }

        if ($item['checklist_id'] !== $checklistId) {
            throw new RegraDeNegocioException('Este item não pertence ao checklist informado');
        }

        $foto = $this->fotoChecklistModel->buscarPorId($fotoId);
        if ($foto === null) {
            throw new NaoEncontradoException('Foto');
        }

        if ($foto['checklist_item_id'] !== $itemId) {
            throw new RegraDeNegocioException('Esta foto não pertence ao item informado');
        }

        $this->fotoChecklistModel->excluir($fotoId);

        $this->logService->registrar(
            acao:       'DELETE',
            entidade:   'foto_checklist',
            entidadeId: $fotoId,
            payload:    ['checklist_item_id' => $itemId],
        );
    }
}
