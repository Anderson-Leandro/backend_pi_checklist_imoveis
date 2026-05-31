<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\DashboardService;

class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function obter(array $parametros): void
    {
        $estatisticas = $this->dashboardService->obterEstatisticas();
        Response::sucesso($estatisticas);
    }
}
