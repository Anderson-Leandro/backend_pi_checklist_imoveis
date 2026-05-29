<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTime;

class Validator
{
    /**
     * Valida um array de dados com base em regras declarativas separadas por pipe.
     *
     * Exemplo de uso:
     *   Validator::validar($dados, [
     *       'email' => 'obrigatorio|email',
     *       'nome'  => 'obrigatorio|min:3|max:150',
     *       'role'  => 'obrigatorio|enum:admin,vistoriador,locatario',
     *   ]);
     *
     * @param  array<string, mixed>  $dados
     * @param  array<string, string> $regras
     * @return array<string, string> Mapa de campo => mensagem de erro (vazio se tudo válido)
     */
    public static function validar(array $dados, array $regras): array
    {
        $erros = [];

        foreach ($regras as $campo => $regrasCampo) {
            $listaDeRegras = explode('|', $regrasCampo);
            $valor         = $dados[$campo] ?? null;

            foreach ($listaDeRegras as $regra) {
                $erro = self::aplicarRegra($campo, $valor, $regra);

                if ($erro !== null) {
                    $erros[$campo] = $erro;
                    break; // Para no primeiro erro do campo
                }
            }
        }

        return $erros;
    }

    /**
     * Aplica uma regra individual a um valor e retorna a mensagem de erro ou null.
     */
    private static function aplicarRegra(string $campo, mixed $valor, string $regra): string|null
    {
        [$nomeRegra, $parametro] = array_pad(explode(':', $regra, 2), 2, null);

        return match ($nomeRegra) {
            'obrigatorio' => self::validarObrigatorio($campo, $valor),
            'email'       => self::validarEmail($campo, $valor),
            'min'         => self::validarMin($campo, $valor, (int) $parametro),
            'max'         => self::validarMax($campo, $valor, (int) $parametro),
            'enum'        => self::validarEnum($campo, $valor, $parametro ?? ''),
            'uuid'        => self::validarUuid($campo, $valor),
            'data'        => self::validarData($campo, $valor),
            'inteiro'     => self::validarInteiro($campo, $valor),
            'booleano'    => self::validarBooleano($campo, $valor),
            'url'         => self::validarUrl($campo, $valor),
            'cep'         => self::validarCep($campo, $valor),
            'tamanho'     => self::validarTamanho($campo, $valor, (int) $parametro),
            default       => null,
        };
    }

    private static function validarObrigatorio(string $campo, mixed $valor): string|null
    {
        if ($valor === null || $valor === '') {
            return "O campo {$campo} é obrigatório.";
        }

        return null;
    }

    private static function validarEmail(string $campo, mixed $valor): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            return "O campo {$campo} deve ser um e-mail válido.";
        }

        return null;
    }

    private static function validarMin(string $campo, mixed $valor, int $minimo): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            if ((float) $valor < $minimo) {
                return "O campo {$campo} deve ser no mínimo {$minimo}.";
            }
        } elseif (mb_strlen((string) $valor) < $minimo) {
            return "O campo {$campo} deve ter no mínimo {$minimo} caracteres.";
        }

        return null;
    }

    private static function validarMax(string $campo, mixed $valor, int $maximo): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            if ((float) $valor > $maximo) {
                return "O campo {$campo} deve ser no máximo {$maximo}.";
            }
        } elseif (mb_strlen((string) $valor) > $maximo) {
            return "O campo {$campo} deve ter no máximo {$maximo} caracteres.";
        }

        return null;
    }

    private static function validarEnum(string $campo, mixed $valor, string $opcoes): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $listaDeOpcoes = explode(',', $opcoes);

        if (!in_array($valor, $listaDeOpcoes, strict: true)) {
            $opcoesFormatadas = implode(', ', $listaDeOpcoes);
            return "O campo {$campo} deve ser um dos valores: {$opcoesFormatadas}.";
        }

        return null;
    }

    private static function validarUuid(string $campo, mixed $valor): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $padraoUuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        if (!preg_match($padraoUuid, (string) $valor)) {
            return "O campo {$campo} deve ser um UUID v4 válido.";
        }

        return null;
    }

    private static function validarData(string $campo, mixed $valor): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $valorString = (string) $valor;
        $data        = DateTime::createFromFormat('Y-m-d', $valorString);

        if (!$data || $data->format('Y-m-d') !== $valorString) {
            return "O campo {$campo} deve ser uma data válida no formato YYYY-MM-DD.";
        }

        return null;
    }

    private static function validarInteiro(string $campo, mixed $valor): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (filter_var($valor, FILTER_VALIDATE_INT) === false) {
            return "O campo {$campo} deve ser um número inteiro.";
        }

        return null;
    }

    private static function validarBooleano(string $campo, mixed $valor): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $valoresValidos = [true, false, 1, 0, '1', '0', 'true', 'false'];

        if (!in_array($valor, $valoresValidos, strict: true)) {
            return "O campo {$campo} deve ser um valor booleano (true, false, 1 ou 0).";
        }

        return null;
    }

    private static function validarUrl(string $campo, mixed $valor): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!filter_var($valor, FILTER_VALIDATE_URL)) {
            return "O campo {$campo} deve ser uma URL válida.";
        }

        return null;
    }

    private static function validarCep(string $campo, mixed $valor): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!preg_match('/^\d{5}-\d{3}$/', (string) $valor)) {
            return "O campo {$campo} deve estar no formato 00000-000.";
        }

        return null;
    }

    private static function validarTamanho(string $campo, mixed $valor, int $tamanho): string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (mb_strlen((string) $valor) !== $tamanho) {
            return "O campo {$campo} deve ter exatamente {$tamanho} caracteres.";
        }

        return null;
    }
}
