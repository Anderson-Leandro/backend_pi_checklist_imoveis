<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Requisicao;
use App\Helpers\Response;
use App\Services\LogService;

class LogController
{
    public function __construct(
        private readonly LogService $logService
    ) {}

    /**
     * Lista logs de operação com filtros opcionais.
     * Restrito ao role admin.
     *
     * Query params: entidade, usuario_id, de (YYYY-MM-DD), ate (YYYY-MM-DD), pagina, por_pagina
     */
    public function listar(array $parametros): void
    {
        $query          = Requisicao::query();
        $pagina         = (int) ($query['pagina'] ?? 1);
        $itensPorPagina = (int) ($query['por_pagina'] ?? 20);
        $entidade       = isset($query['entidade']) && $query['entidade'] !== ''
            ? $query['entidade']
            : null;
        $usuarioId      = isset($query['usuario_id']) && $query['usuario_id'] !== ''
            ? $query['usuario_id']
            : null;
        $de             = isset($query['de']) && $query['de'] !== ''
            ? $query['de']
            : null;
        $ate            = isset($query['ate']) && $query['ate'] !== ''
            ? $query['ate']
            : null;

        $resultado = $this->logService->listar($pagina, $itensPorPagina, $entidade, $usuarioId, $de, $ate);

        Response::paginado(
            itens:          $resultado['itens'],
            total:          $resultado['total'],
            pagina:         $resultado['pagina'],
            itensPorPagina: $resultado['itensPorPagina']
        );
    }
}
