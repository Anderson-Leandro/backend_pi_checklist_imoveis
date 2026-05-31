<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\PublicService;

class PublicController
{
    public function __construct(
        private readonly PublicService $publicService
    ) {}

    public function listarImoveis(array $parametros): void
    {
        $imoveis = $this->publicService->listarImoveis();
        Response::sucesso($imoveis);
    }

    public function obterStatusChecklist(array $parametros): void
    {
        $status = $this->publicService->obterStatusChecklist($parametros['id']);
        Response::sucesso($status);
    }
}
