<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$testPort    = (int) (getenv('TEST_PORT') ?: 8001);
$testHost    = "localhost:{$testPort}";
$serverUrl   = "http://{$testHost}";
$pidFile     = sys_get_temp_dir() . "/one_check_test_{$testPort}.pid";

require_once $projectRoot . '/vendor/autoload.php';

// ── Carregar .env.test ────────────────────────────────────────────────────────

$envFile = $projectRoot . '/.env.test';
if (!file_exists($envFile)) {
    $exemplo = $projectRoot . '/.env.test.example';
    fwrite(STDERR, "\n  ERRO: .env.test não encontrado.\n");
    if (file_exists($exemplo)) {
        fwrite(STDERR, "  Execute: cp .env.test.example .env.test\n\n");
    }
    exit(1);
}

$dotenv = Dotenv\Dotenv::createImmutable($projectRoot, '.env.test');
$dotenv->load();

$dbHost = $_ENV['DB_HOST']     ?? 'db';
$dbPort = $_ENV['DB_PORT']     ?? '3306';
$dbUser = $_ENV['DB_USERNAME'] ?? 'appuser';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? 'appdb_test';

// ── Banner ────────────────────────────────────────────────────────────────────
echo "\n";
echo "  ┌─ One Check API — Suite de Testes ──────────────────────────────┐\n";
echo "  │\n";

// ── Criar e resetar banco de dados de teste ───────────────────────────────────

// Criar banco usando root (se disponível) ou o próprio usuário
$rootUser = $_ENV['DB_ROOT_USERNAME'] ?? null;
$rootPass = $_ENV['DB_ROOT_PASSWORD'] ?? null;
$criarUser = ($rootUser && $rootPass) ? $rootUser : $dbUser;
$criarPass = ($rootUser && $rootPass) ? $rootPass : $dbPass;

try {
    $pdoAdmin = new PDO(
        "mysql:host={$dbHost};port={$dbPort}",
        $criarUser,
        $criarPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdoAdmin->exec(
        "CREATE DATABASE IF NOT EXISTS `{$dbName}`
         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    // Garantir que o usuário da aplicação tem acesso ao banco de teste
    if ($rootUser && $rootPass) {
        $pdoAdmin->exec(
            "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'%'; FLUSH PRIVILEGES"
        );
    }
} catch (PDOException $e) {
    echo "  │  ERRO ao conectar ao MySQL ({$dbHost}:{$dbPort}): " . $e->getMessage() . "\n";
    echo "  └────────────────────────────────────────────────────────────────┘\n\n";
    exit(1);
}

$pdoTeste = new PDO(
    "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Dropar todas as tabelas (garante estado limpo a cada execução)
$pdoTeste->exec('SET FOREIGN_KEY_CHECKS = 0');
$tabelas = $pdoTeste->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tabelas as $tabela) {
    $pdoTeste->exec("DROP TABLE IF EXISTS `{$tabela}`");
}
$pdoTeste->exec('SET FOREIGN_KEY_CHECKS = 1');

// Rodar migrations em ordem numérica
$arquivosSql = glob($projectRoot . '/database/migrations/*.sql');
sort($arquivosSql);
foreach ($arquivosSql as $arquivo) {
    try {
        $pdoTeste->exec(file_get_contents($arquivo));
    } catch (PDOException $e) {
        echo "  │  ERRO na migration '" . basename($arquivo) . "': " . $e->getMessage() . "\n";
        echo "  └────────────────────────────────────────────────────────────────┘\n\n";
        exit(1);
    }
}

echo "  │  Banco '{$dbName}': " . count($arquivosSql) . " migration(s) aplicada(s)\n";

// Rodar seed — forçar o uso do banco de teste e do storage local
// (garante que os testes automatizados nunca dependam do MinIO ou S3 real)
putenv("DB_HOST={$dbHost}");
putenv("DB_PORT={$dbPort}");
putenv("DB_DATABASE={$dbName}");
putenv("DB_USERNAME={$dbUser}");
putenv("DB_PASSWORD={$dbPass}");
putenv('STORAGE_DRIVER=local');
putenv('STORAGE_LOCAL_PATH=storage/uploads');
$_ENV['DB_HOST']          = $dbHost;
$_ENV['DB_PORT']          = $dbPort;
$_ENV['DB_DATABASE']      = $dbName;
$_ENV['DB_USERNAME']      = $dbUser;
$_ENV['DB_PASSWORD']      = $dbPass;
$_ENV['STORAGE_DRIVER']   = 'local';
$_ENV['STORAGE_LOCAL_PATH'] = 'storage/uploads';

// Descartar singleton de conexão para que o seed use as vars corretas
if (class_exists('App\Database\Conexao')) {
    $ref  = new ReflectionClass('App\Database\Conexao');
    $prop = $ref->getProperty('instancia');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
}

$seedFiles = glob($projectRoot . '/database/seeds/*.php');
sort($seedFiles);
foreach ($seedFiles as $seedFile) {
    require $seedFile;
}
echo "  │  Seed: OK\n";

// Descartar singleton novamente para que os testes comecem com conexão limpa
if (class_exists('App\Database\Conexao')) {
    $ref  = new ReflectionClass('App\Database\Conexao');
    $prop = $ref->getProperty('instancia');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
}

// ── Servidor PHP built-in ─────────────────────────────────────────────────────

// Verificar se já existe um servidor no port de teste
$jaCorrendo = @file_get_contents("{$serverUrl}/api/v1/health");
if ($jaCorrendo && str_contains($jaCorrendo, '"status":"ok"')) {
    echo "  │  Servidor: já em execução em {$serverUrl}\n";
} else {
    // Construir linha de env vars para o processo filho
    $jwtSecret = $_ENV['JWT_SECRET'] ?? 'test-jwt-secret';
    $appUrl    = $_ENV['APP_URL']    ?? $serverUrl;

    $envLine = sprintf(
        'APP_ENV=test DB_HOST=%s DB_PORT=%s DB_DATABASE=%s DB_USERNAME=%s DB_PASSWORD=%s ' .
        'JWT_SECRET=%s JWT_EXPIRY=900 JWT_REFRESH_EXPIRY=604800 ' .
        'STORAGE_DRIVER=local STORAGE_LOCAL_PATH=storage/uploads APP_URL=%s',
        $dbHost, $dbPort, $dbName, $dbUser, $dbPass,
        $jwtSecret, $serverUrl
    );

    $cmd      = "{$envLine} php -S {$testHost} -t {$projectRoot}/public {$projectRoot}/public/index.php";
    $processo = popen("{$cmd} > /dev/null 2>&1 & echo \$!", 'r');
    $pid      = trim(fgets($processo));
    pclose($processo);

    if (!$pid || !is_numeric($pid)) {
        echo "  │  ERRO: não foi possível iniciar o servidor PHP em {$serverUrl}\n";
        echo "  └────────────────────────────────────────────────────────────────┘\n\n";
        exit(1);
    }

    file_put_contents($pidFile, $pid);

    // Aguardar o servidor responder (máx. 5 segundos)
    $pronto     = false;
    $tentativas = 0;
    while ($tentativas < 25 && !$pronto) {
        usleep(200_000); // 200ms
        $check  = @file_get_contents("{$serverUrl}/api/v1/health");
        $pronto = $check && str_contains($check, '"status":"ok"');
        $tentativas++;
    }

    if (!$pronto) {
        echo "  │  ERRO: servidor não respondeu em {$serverUrl} após 5s.\n";
        echo "  └────────────────────────────────────────────────────────────────┘\n\n";
        exit(1);
    }

    echo "  │  Servidor: PID {$pid} em {$serverUrl}\n";

    register_shutdown_function(static function () use ($pidFile): void {
        if (file_exists($pidFile)) {
            $pid = trim(file_get_contents($pidFile));
            if ($pid && is_numeric($pid)) {
                exec("kill {$pid} 2>/dev/null");
            }
            @unlink($pidFile);
        }
        echo "\n  │  Servidor de teste encerrado.\n";
        echo "  └────────────────────────────────────────────────────────────────┘\n\n";
    });
}

echo "  │\n";
echo "  └────────────────────────────────────────────────────────────────┘\n\n";

define('TEST_BASE_URL', $serverUrl);
define('TEST_DB_NAME',  $dbName);
