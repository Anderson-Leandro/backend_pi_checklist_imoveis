<?php

declare(strict_types=1);

namespace Tests\Support;

class ApiResponse
{
    public function __construct(
        public readonly int   $status,
        private readonly array $corpo,
    ) {}

    public function json(string|null $chave = null): mixed
    {
        if ($chave === null) {
            return $this->corpo;
        }

        $partes = explode('.', $chave);
        $valor  = $this->corpo;

        foreach ($partes as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return null;
            }
            $valor = $valor[$parte];
        }

        return $valor;
    }

    public function sucesso(): bool
    {
        return $this->json('sucesso') === true;
    }

    public function erros(): array
    {
        return $this->json('erros') ?? [];
    }
}
