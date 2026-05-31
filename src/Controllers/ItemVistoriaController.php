<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\ItemVistoriaService;

class ItemVistoriaController
{
    public function __construct(
        private readonly ItemVistoriaService $itemVistoriaService
    ) {}

    public function listar(array $parametros): void
    {
        $itens = $this->itemVistoriaService->listar();
        Response::sucesso($itens);
    }
}
