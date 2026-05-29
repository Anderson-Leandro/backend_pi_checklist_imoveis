<?php

declare(strict_types=1);

namespace App\Helpers;

class Response
{
    /**
     * Retorna uma resposta de sucesso com dados opcionais.
     *
     * @param mixed $dados
     */
    public static function sucesso(mixed $dados = null, int $codigo = 200): void
    {
        http_response_code($codigo);
        echo json_encode(
            ['sucesso' => true, 'dados' => $dados],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    /**
     * Retorna uma resposta de erro.
     * Se $mensagem for array, envia como "erros" (validação por campo).
     * Se for string, envia como "erro".
     *
     * @param string|array<string, string> $mensagem
     */
    public static function erro(string|array $mensagem, int $codigo = 400): void
    {
        http_response_code($codigo);

        $corpo = is_array($mensagem)
            ? ['sucesso' => false, 'erros' => $mensagem]
            : ['sucesso' => false, 'erro' => $mensagem];

        echo json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Retorna uma resposta paginada.
     *
     * @param array<int, mixed> $itens
     */
    public static function paginado(
        array $itens,
        int $total,
        int $pagina,
        int $itensPorPagina
    ): void {
        http_response_code(200);
        echo json_encode(
            [
                'sucesso' => true,
                'dados'   => $itens,
                'paginacao' => [
                    'total'          => $total,
                    'pagina'         => $pagina,
                    'itensPorPagina' => $itensPorPagina,
                    'totalPaginas'   => (int) ceil($total / max(1, $itensPorPagina)),
                ],
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}
