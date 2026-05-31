<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NaoEncontradoException;
use App\Models\ChecklistModel;
use App\Models\ContratoModel;
use App\Models\EnderecoModel;
use App\Models\ImovelModel;

class PublicService
{
    public function __construct(
        private readonly ImovelModel   $imovelModel,
        private readonly EnderecoModel $enderecoModel,
        private readonly ContratoModel $contratoModel,
        private readonly ChecklistModel $checklistModel
    ) {}

    /**
     * Lista todos os imóveis ativos com seus endereços (para consumidores externos).
     *
     * @return list<array{id: string, tipo: string, tamanho: string, garagem: int, garagem_vagas: int, status: string, created_at: string, endereco: array|null}>
     */
    public function listarImoveis(): array
    {
        $imoveis = $this->imovelModel->listarTodos();

        return array_map(function (array $imovel): array {
            $imovel['endereco'] = $this->enderecoModel->buscarPorImovelId($imovel['id']);
            return $imovel;
        }, $imoveis);
    }

    /**
     * Retorna o status do checklist ativo do contrato vinculado ao imóvel.
     *
     * @return array{imovel_id: string, contrato_id: string|null, checklist_status: string|null, checklist_tipo: string|null}
     * @throws NaoEncontradoException Se o imóvel não existir
     */
    public function obterStatusChecklist(string $imovelId): array
    {
        $imovel = $this->imovelModel->buscarPorId($imovelId);
        if ($imovel === null) {
            throw new NaoEncontradoException('Imóvel');
        }

        $contrato = $this->contratoModel->buscarAtivoPorImovel($imovelId);

        if ($contrato === null) {
            return [
                'imovel_id'        => $imovelId,
                'contrato_id'      => null,
                'checklist_status' => null,
                'checklist_tipo'   => null,
            ];
        }

        $checklist = $this->checklistModel->buscarUltimoPorContrato($contrato['id']);

        return [
            'imovel_id'        => $imovelId,
            'contrato_id'      => $contrato['id'],
            'checklist_status' => $checklist['status']  ?? null,
            'checklist_tipo'   => $checklist['tipo']    ?? null,
        ];
    }
}
