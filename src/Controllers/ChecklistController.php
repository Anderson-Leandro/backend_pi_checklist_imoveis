<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Services\ChecklistService;

class ChecklistController
{
    public function __construct(
        private readonly ChecklistService $checklistService
    ) {}

    public function criar(array $parametros): void
    {
        $checklist = $this->checklistService->criar($parametros['id'], Requisicao::corpo());
        Response::sucesso($checklist, 201);
    }

    public function listarPorContrato(array $parametros): void
    {
        $checklists = $this->checklistService->listarPorContrato($parametros['id']);
        Response::sucesso($checklists);
    }

    public function buscarPorId(array $parametros): void
    {
        $checklist = $this->checklistService->buscarPorId($parametros['id']);
        Response::sucesso($checklist);
    }

    public function enviarParaAceite(array $parametros): void
    {
        $checklist = $this->checklistService->enviarParaAceite($parametros['id']);
        Response::sucesso($checklist);
    }

    public function submeter(array $parametros): void
    {
        $checklist = $this->checklistService->submeter($parametros['id']);
        Response::sucesso($checklist);
    }

    public function adicionarItem(array $parametros): void
    {
        $item = $this->checklistService->adicionarItem($parametros['id'], Requisicao::corpo());
        Response::sucesso($item, 201);
    }

    public function atualizarItem(array $parametros): void
    {
        $item = $this->checklistService->atualizarItem(
            $parametros['id'],
            $parametros['item_id'],
            Requisicao::corpo()
        );
        Response::sucesso($item);
    }

    public function uploadFoto(array $parametros): void
    {
        $arquivos = Requisicao::arquivos();
        $arquivo  = $arquivos['foto'] ?? null;

        if ($arquivo === null) {
            Response::erro(['foto' => 'O campo foto é obrigatório'], 422);
            return;
        }

        $foto = $this->checklistService->uploadFoto(
            $parametros['id'],
            $parametros['item_id'],
            $arquivo
        );
        Response::sucesso($foto, 201);
    }

    public function excluirFoto(array $parametros): void
    {
        $this->checklistService->excluirFoto(
            $parametros['id'],
            $parametros['item_id'],
            $parametros['foto_id']
        );
        Response::sucesso(null, 204);
    }

    public function aceitar(array $parametros): void
    {
        $aceite = $this->checklistService->aceitar($parametros['id']);
        Response::sucesso($aceite);
    }

    public function rejeitar(array $parametros): void
    {
        $aceite = $this->checklistService->rejeitar($parametros['id'], Requisicao::corpo());
        Response::sucesso($aceite);
    }

    public function downloadPdf(array $parametros): void
    {
        $pdfBytes = $this->checklistService->gerarPdf($parametros['id']);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="checklist-' . $parametros['id'] . '.pdf"');
        header('Content-Length: ' . strlen($pdfBytes));
        echo $pdfBytes;
        exit;
    }
}
