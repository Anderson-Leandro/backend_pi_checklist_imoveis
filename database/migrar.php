<?php

declare(strict_types=1);

/**
 * Script de execução de migrations e seeds — One Check API
 *
 * Uso:
 *   php database/migrar.php              # Apenas migrations
 *   php database/migrar.php --com-seed   # Migrations + seed inicial
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Conexao;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$executarSeed = in_array('--com-seed', $argv ?? [], strict: true);

$conexao = Conexao::obter();

// ─── Migrations ──────────────────────────────────────────────────────────────

$diretorioMigrations = __DIR__ . '/migrations';
$arquivosSql         = glob($diretorioMigrations . '/*.sql');

if (empty($arquivosSql)) {
    echo "[!] Nenhuma migration encontrada em {$diretorioMigrations}.\n";
} else {
    sort($arquivosSql);

    echo "=== Executando migrations ===\n";

    foreach ($arquivosSql as $arquivoSql) {
        $nomeArquivo = basename($arquivoSql);
        echo "[→] {$nomeArquivo}... ";

        $sql = file_get_contents($arquivoSql);

        if ($sql === false) {
            echo "ERRO ao ler o arquivo.\n";
            exit(1);
        }

        try {
            $conexao->exec($sql);
            echo "OK\n";
        } catch (PDOException $excecao) {
            echo "FALHOU\n";
            echo "    Erro: " . $excecao->getMessage() . "\n";
            exit(1);
        }
    }

    echo "\n[✓] Migrations concluídas.\n";
}

// ─── Seeds (opcional) ────────────────────────────────────────────────────────

if (!$executarSeed) {
    echo "\nDica: Use --com-seed para também executar os seeds.\n";
    exit(0);
}

$diretorioSeeds = __DIR__ . '/seeds';
$arquivosSeed   = glob($diretorioSeeds . '/*.php');

if (empty($arquivosSeed)) {
    echo "[!] Nenhum seed encontrado em {$diretorioSeeds}.\n";
    exit(0);
}

sort($arquivosSeed);

echo "\n=== Executando seeds ===\n";

foreach ($arquivosSeed as $arquivoSeed) {
    $nomeArquivo = basename($arquivoSeed);
    echo "[→] {$nomeArquivo}\n";
    require $arquivoSeed;
}

echo "\n[✓] Processo concluído.\n";
