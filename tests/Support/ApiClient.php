<?php

declare(strict_types=1);

namespace Tests\Support;

class ApiClient
{
    private static string $baseUrl = TEST_BASE_URL;
    private string|null $token = null;

    public static function criar(): self
    {
        return new self();
    }

    public function comToken(string $token): self
    {
        $clone        = clone $this;
        $clone->token = $token;
        return $clone;
    }

    public function get(string $caminho, array $query = []): ApiResponse
    {
        $url = self::$baseUrl . $caminho;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $this->requisicao('GET', $url, []);
    }

    public function post(string $caminho, array $corpo = []): ApiResponse
    {
        return $this->requisicao('POST', self::$baseUrl . $caminho, $corpo);
    }

    public function put(string $caminho, array $corpo = []): ApiResponse
    {
        return $this->requisicao('PUT', self::$baseUrl . $caminho, $corpo);
    }

    public function patch(string $caminho, array $corpo = []): ApiResponse
    {
        return $this->requisicao('PATCH', self::$baseUrl . $caminho, $corpo);
    }

    public function delete(string $caminho, array $corpo = []): ApiResponse
    {
        return $this->requisicao('DELETE', self::$baseUrl . $caminho, $corpo);
    }

    /**
     * Envia uma requisição POST com multipart/form-data (para upload de arquivo).
     *
     * @param string $caminho Caminho da rota
     * @param array<string, string> $campos   Campos de texto (name => value)
     * @param array<string, string> $arquivos Arquivos (field_name => caminho_absoluto)
     */
    public function postArquivo(string $caminho, array $campos = [], array $arquivos = []): ApiResponse
    {
        $cabecalhos = ['Accept: application/json'];

        if ($this->token !== null) {
            $cabecalhos[] = "Authorization: Bearer {$this->token}";
        }

        $postData = $campos;
        foreach ($arquivos as $campo => $caminhoDisco) {
            $postData[$campo] = new \CURLFile($caminhoDisco);
        }

        $ch = curl_init(self::$baseUrl . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $cabecalhos,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => $postData,
        ]);

        $resposta   = curl_exec($ch);
        $status     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $json = json_decode(substr($resposta, $headerSize), true) ?? [];

        return new ApiResponse($status, $json);
    }

    private function requisicao(string $metodo, string $url, array $corpo): ApiResponse
    {
        $cabecalhos = ['Content-Type: application/json', 'Accept: application/json'];

        if ($this->token !== null) {
            $cabecalhos[] = "Authorization: Bearer {$this->token}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $cabecalhos,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        if ($metodo !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, empty($corpo) ? '{}' : json_encode($corpo));
        }

        $resposta   = curl_exec($ch);
        $status     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $json = json_decode(substr($resposta, $headerSize), true) ?? [];

        return new ApiResponse($status, $json);
    }
}
