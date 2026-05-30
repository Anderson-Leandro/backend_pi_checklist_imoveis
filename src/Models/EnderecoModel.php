<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class EnderecoModel
{
    public function __construct(
        private readonly PDO $conexao
    ) {}

    /**
     * Busca o endereço vinculado a um imóvel.
     *
     * @return array{id: string, imovel_id: string, rua: string, numero: string, complemento: string|null, bloco: string|null, andar: string|null, cidade: string, estado: string, cep: string, latitude: string|null, longitude: string|null}|null
     */
    public function buscarPorImovelId(string $imovelId): array|null
    {
        $stmt = $this->conexao->prepare(
            'SELECT id, imovel_id, rua, numero, complemento, bloco, andar,
                    cidade, estado, cep, latitude, longitude
             FROM endereco WHERE imovel_id = :imovel_id LIMIT 1'
        );
        $stmt->execute([':imovel_id' => $imovelId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Insere um novo endereço para o imóvel.
     *
     * @param array{id: string, imovel_id: string, rua: string, numero: string, complemento: string|null, bloco: string|null, andar: string|null, cidade: string, estado: string, cep: string, latitude: string|null, longitude: string|null} $dados
     */
    public function inserir(array $dados): void
    {
        $stmt = $this->conexao->prepare(
            'INSERT INTO endereco (id, imovel_id, rua, numero, complemento, bloco, andar, cidade, estado, cep, latitude, longitude)
             VALUES (:id, :imovel_id, :rua, :numero, :complemento, :bloco, :andar, :cidade, :estado, :cep, :latitude, :longitude)'
        );
        $stmt->execute([
            ':id'          => $dados['id'],
            ':imovel_id'   => $dados['imovel_id'],
            ':rua'         => $dados['rua'],
            ':numero'      => $dados['numero'],
            ':complemento' => $dados['complemento'] ?? null,
            ':bloco'       => $dados['bloco']       ?? null,
            ':andar'       => $dados['andar']       ?? null,
            ':cidade'      => $dados['cidade'],
            ':estado'      => $dados['estado'],
            ':cep'         => $dados['cep'],
            ':latitude'    => $dados['latitude']    ?? null,
            ':longitude'   => $dados['longitude']   ?? null,
        ]);
    }

    /**
     * Atualiza o endereço existente de um imóvel.
     *
     * @param array<string, mixed> $campos
     */
    public function atualizar(string $imovelId, array $campos): void
    {
        if (empty($campos)) {
            return;
        }

        $atribuicoes = implode(', ', array_map(
            fn(string $campo) => "{$campo} = :{$campo}",
            array_keys($campos)
        ));

        $stmt = $this->conexao->prepare(
            "UPDATE endereco SET {$atribuicoes} WHERE imovel_id = :imovel_id"
        );

        $stmt->execute([...$campos, 'imovel_id' => $imovelId]);
    }
}
