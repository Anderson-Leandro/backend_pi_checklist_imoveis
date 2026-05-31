<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ItemVistoriaModel;

class ItemVistoriaService
{
    public function __construct(
        private readonly ItemVistoriaModel $itemVistoriaModel
    ) {}

    /**
     * Lista todos os itens de vistoria padrão cadastrados.
     *
     * @return list<array{id: string, nome: string, categoria: string}>
     */
    public function listar(): array
    {
        return $this->itemVistoriaModel->listar();
    }
}
