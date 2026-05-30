<?php

declare(strict_types=1);

namespace App\Services;

class GeocodingService
{
    /**
     * Consulta ViaCEP para obter dados do endereço a partir do CEP.
     * Retorna array com logradouro, bairro, cidade, estado ou array vazio em caso de falha.
     *
     * @return array{logradouro: string, bairro: string, cidade: string, estado: string}|array{}
     */
    public function buscarEnderecoPorCep(string $cep): array
    {
        $cepNumerico = preg_replace('/\D/', '', $cep);

        if (strlen($cepNumerico) !== 8) {
            return [];
        }

        $url      = "https://viacep.com.br/ws/{$cepNumerico}/json/";
        $resposta = $this->requisicaoHttp($url);

        if ($resposta === null || isset($resposta['erro'])) {
            return [];
        }

        return [
            'logradouro' => $resposta['logradouro'] ?? '',
            'bairro'     => $resposta['bairro']     ?? '',
            'cidade'     => $resposta['localidade']  ?? '',
            'estado'     => $resposta['uf']          ?? '',
        ];
    }

    /**
     * Consulta Nominatim para obter latitude e longitude a partir de uma string de endereço.
     * Retorna array com latitude e longitude ou null em caso de falha.
     *
     * @return array{latitude: string, longitude: string}|null
     */
    public function buscarCoordenadas(string $enderecoCompleto): array|null
    {
        $query = urlencode($enderecoCompleto);
        $url   = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1";

        $resposta = $this->requisicaoHttp($url, agente: 'OneCheckAPI/1.0');

        if ($resposta === null || empty($resposta)) {
            return null;
        }

        $primeiro = $resposta[0] ?? null;

        if ($primeiro === null || !isset($primeiro['lat'], $primeiro['lon'])) {
            return null;
        }

        return [
            'latitude'  => $primeiro['lat'],
            'longitude' => $primeiro['lon'],
        ];
    }

    /**
     * Realiza uma requisição HTTP GET e retorna o corpo decodificado como array.
     * Retorna null em caso de falha ou timeout.
     */
    private function requisicaoHttp(string $url, string $agente = 'OneCheckAPI/1.0'): array|null
    {
        $contexto = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => "User-Agent: {$agente}\r\nAccept: application/json\r\n",
                'timeout'         => 5,
                'ignore_errors'   => true,
            ],
        ]);

        $corpo = @file_get_contents($url, false, $contexto);

        if ($corpo === false) {
            error_log("[GeocodingService] Falha ao consultar: {$url}");
            return null;
        }

        $dados = json_decode($corpo, associative: true);

        if (!is_array($dados)) {
            return null;
        }

        return $dados;
    }
}
