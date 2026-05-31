<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgendamentoModel;
use App\Models\ChecklistModel;
use App\Models\ImovelModel;
use App\Models\RegistroProblemaModel;

class DashboardService
{
    public function __construct(
        private readonly ImovelModel          $imovelModel,
        private readonly ChecklistModel       $checklistModel,
        private readonly RegistroProblemaModel $problemaModel,
        private readonly AgendamentoModel     $agendamentoModel
    ) {}

    /**
     * Retorna estatísticas agregadas do sistema para o dashboard do admin.
     *
     * @return array{imoveis_locados: int, checklists_pendentes: int, problemas_abertos: int, vistorias_agendadas: int}
     */
    public function obterEstatisticas(): array
    {
        return [
            'imoveis_locados'      => $this->imovelModel->contar('locado'),
            'checklists_pendentes' => $this->checklistModel->contarPendentes(),
            'problemas_abertos'    => $this->problemaModel->contarAbertos(),
            'vistorias_agendadas'  => $this->agendamentoModel->contarFuturos(),
        ];
    }
}
