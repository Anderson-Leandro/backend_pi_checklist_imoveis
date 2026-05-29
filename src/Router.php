<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\NaoEncontradoException;

class Router
{
    /**
     * Mapa de rotas registradas por método HTTP.
     *
     * @var array<string, list<array{
     *     padrao: string,
     *     parametros: list<string>,
     *     callback: callable,
     *     middlewares: list<callable>
     * }>>
     */
    private array $rotas = [];

    /**
     * Registra uma rota GET.
     *
     * @param list<callable> $middlewares
     */
    public function get(string $caminho, callable $callback, array $middlewares = []): void
    {
        $this->registrar('GET', $caminho, $callback, $middlewares);
    }

    /**
     * Registra uma rota POST.
     *
     * @param list<callable> $middlewares
     */
    public function post(string $caminho, callable $callback, array $middlewares = []): void
    {
        $this->registrar('POST', $caminho, $callback, $middlewares);
    }

    /**
     * Registra uma rota PUT.
     *
     * @param list<callable> $middlewares
     */
    public function put(string $caminho, callable $callback, array $middlewares = []): void
    {
        $this->registrar('PUT', $caminho, $callback, $middlewares);
    }

    /**
     * Registra uma rota PATCH.
     *
     * @param list<callable> $middlewares
     */
    public function patch(string $caminho, callable $callback, array $middlewares = []): void
    {
        $this->registrar('PATCH', $caminho, $callback, $middlewares);
    }

    /**
     * Registra uma rota DELETE.
     *
     * @param list<callable> $middlewares
     */
    public function delete(string $caminho, callable $callback, array $middlewares = []): void
    {
        $this->registrar('DELETE', $caminho, $callback, $middlewares);
    }

    /**
     * Registra uma rota para o método HTTP informado.
     *
     * @param list<callable> $middlewares
     */
    private function registrar(string $metodo, string $caminho, callable $callback, array $middlewares): void
    {
        [$padrao, $parametros] = $this->compilarRota($caminho);

        $this->rotas[$metodo][] = [
            'padrao'      => $padrao,
            'parametros'  => $parametros,
            'callback'    => $callback,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * Converte uma rota com parâmetros dinâmicos em regex e extrai os nomes dos parâmetros.
     * Exemplo: '/api/v1/usuarios/{id}' → regex + ['id']
     *
     * @return array{string, list<string>}
     */
    private function compilarRota(string $caminho): array
    {
        $parametros = [];

        $padrao = preg_replace_callback(
            '/\{(\w+)\}/',
            function (array $correspondencia) use (&$parametros): string {
                $parametros[] = $correspondencia[1];
                return '([^/]+)';
            },
            $caminho
        );

        return ['#^' . $padrao . '$#', $parametros];
    }

    /**
     * Despacha a requisição para o callback da rota correspondente,
     * executando os middlewares registrados na rota em ordem.
     *
     * @throws NaoEncontradoException Se nenhuma rota corresponder à URI e método
     */
    public function despachar(string $uri, string $metodo): void
    {
        $rotasDoMetodo = $this->rotas[$metodo] ?? [];

        foreach ($rotasDoMetodo as $rota) {
            if (!preg_match($rota['padrao'], $uri, $correspondencias)) {
                continue;
            }

            array_shift($correspondencias);

            $parametrosDaRota = count($rota['parametros']) > 0
                ? array_combine($rota['parametros'], $correspondencias)
                : [];

            $this->executarMiddlewares(
                $rota['middlewares'],
                function () use ($rota, $parametrosDaRota): void {
                    ($rota['callback'])($parametrosDaRota);
                }
            );

            return;
        }

        throw new NaoEncontradoException('Rota', 'a');
    }

    /**
     * Executa uma lista de middlewares em cadeia (pipeline).
     * Cada middleware recebe o próximo callable e decide se o invoca.
     * Middlewares devem ter a assinatura: function(callable $proximo): void
     *
     * @param list<callable> $middlewares
     */
    private function executarMiddlewares(array $middlewares, callable $destino): void
    {
        $cadeia = array_reduce(
            array_reverse($middlewares),
            fn(callable $proximo, callable $middleware): callable => fn() => $middleware($proximo),
            $destino
        );

        $cadeia();
    }
}
