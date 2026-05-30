<?php

declare(strict_types=1);

/**
 * Script de execução de migrations e seeds — One Check API
 *
 * Uso:
 *   php database/migrar.php              # Apenas migrations pendentes
 *   php database/migrar.php --com-seed   # Migrations + seed inicial
 *   php database/migrar.php --status     # Lista o estado de cada migration
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Conexao;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$executarSeed   = in_array('--com-seed', $argv ?? [], strict: true);
$mostrarStatus  = in_array('--status',   $argv ?? [], strict: true);

$conexao = Conexao::obter();

// ─── Tabela de controle de migrations ────────────────────────────────────────

$tabelaExistia = (bool) $conexao->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '_migrations'"
)->fetchColumn();

$conexao->exec(
    'CREATE TABLE IF NOT EXISTS _migrations (
        nome        VARCHAR(255) NOT NULL,
        aplicado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

// Ao criar a tabela de controle pela primeira vez, detecta e registra as
// migrations que já foram aplicadas antes do sistema de rastreamento existir.
if (!$tabelaExistia) {
    $tabelasExistentes = $conexao->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN, 0);
    $tabelasExistentes = array_flip($tabelasExistentes);

    $migracoesPreviasAplicadas = [];

    // 001: tabelas base criadas
    if (isset($tabelasExistentes['usuario'])) {
        $migracoesPreviasAplicadas[] = '001_criar_tabelas.sql';
    }
    // 002: tabela refresh_tokens
    if (isset($tabelasExistentes['refresh_tokens'])) {
        $migracoesPreviasAplicadas[] = '002_refresh_tokens_e_ajustes.sql';
    }
    // 003: coluna ativo em usuario
    $colunas003 = $conexao->query("SHOW COLUMNS FROM usuario LIKE 'ativo'")->fetchAll();
    if (!empty($colunas003)) {
        $migracoesPreviasAplicadas[] = '003_adicionar_ativo_usuario.sql';
    }

    foreach ($migracoesPreviasAplicadas as $nome) {
        $stmt = $conexao->prepare('INSERT IGNORE INTO _migrations (nome) VALUES (:nome)');
        $stmt->execute([':nome' => $nome]);
    }

    if (!empty($migracoesPreviasAplicadas)) {
        echo "[i] " . count($migracoesPreviasAplicadas) . " migration(s) anterior(es) detectada(s) e registrada(s).\n";
    }
}

$jaAplicadas = $conexao->query('SELECT nome FROM _migrations')
    ->fetchAll(PDO::FETCH_COLUMN, 0);
$jaAplicadas = array_flip($jaAplicadas);

// ─── Migrations ──────────────────────────────────────────────────────────────

$diretorioMigrations = __DIR__ . '/migrations';
$arquivosSql         = glob($diretorioMigrations . '/*.sql');

if (empty($arquivosSql)) {
    echo "[!] Nenhuma migration encontrada em {$diretorioMigrations}.\n";
} else {
    sort($arquivosSql);

    if ($mostrarStatus) {
        echo "=== Status das migrations ===\n";
        foreach ($arquivosSql as $arquivo) {
            $nome   = basename($arquivo);
            $status = isset($jaAplicadas[$nome]) ? '✓ aplicada' : '○ pendente';
            echo "  [{$status}] {$nome}\n";
        }
        exit(0);
    }

    echo "=== Executando migrations ===\n";

    $pendentes = 0;
    foreach ($arquivosSql as $arquivoSql) {
        $nomeArquivo = basename($arquivoSql);

        if (isset($jaAplicadas[$nomeArquivo])) {
            echo "[✓] {$nomeArquivo}... já aplicada\n";
            continue;
        }

        echo "[→] {$nomeArquivo}... ";

        $sql = file_get_contents($arquivoSql);

        if ($sql === false) {
            echo "ERRO ao ler o arquivo.\n";
            exit(1);
        }

        try {
            $conexao->exec($sql);

            $stmt = $conexao->prepare('INSERT INTO _migrations (nome) VALUES (:nome)');
            $stmt->execute([':nome' => $nomeArquivo]);

            echo "OK\n";
            $pendentes++;
        } catch (PDOException $excecao) {
            echo "FALHOU\n";
            echo "    Erro: " . $excecao->getMessage() . "\n";
            exit(1);
        }
    }

    if ($pendentes === 0) {
        echo "\n[✓] Banco já está atualizado. Nenhuma migration pendente.\n";
    } else {
        echo "\n[✓] {$pendentes} migration(s) aplicada(s) com sucesso.\n";
    }
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
