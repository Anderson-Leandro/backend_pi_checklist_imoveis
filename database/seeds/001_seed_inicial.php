<?php

declare(strict_types=1);

/**
 * Seed inicial — One Check API
 * Popula: 1 usuário administrador e os itens de vistoria padrão.
 *
 * Execução: php database/seeds/001_seed_inicial.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexao;
use App\Helpers\Uuid;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$conexao = Conexao::obter();

// ─── Administrador padrão ────────────────────────────────────────────────────

$emailAdmin = 'admin@onecheck.com.br';
$senhaAdmin = 'Admin@1234'; // Deve ser alterada no primeiro acesso

$stmtBuscarAdmin = $conexao->prepare(
    'SELECT id FROM usuario WHERE email = :email LIMIT 1'
);
$stmtBuscarAdmin->execute([':email' => $emailAdmin]);
$adminExistente = $stmtBuscarAdmin->fetch();

if (!$adminExistente) {
    $idAdmin     = Uuid::gerar();
    $hashDaSenha = password_hash($senhaAdmin, PASSWORD_BCRYPT);

    $stmtInserirAdmin = $conexao->prepare(
        'INSERT INTO usuario (id, nome, email, senha_hash, role)
         VALUES (:id, :nome, :email, :senha_hash, :role)'
    );

    $stmtInserirAdmin->execute([
        ':id'         => $idAdmin,
        ':nome'       => 'Administrador',
        ':email'      => $emailAdmin,
        ':senha_hash' => $hashDaSenha,
        ':role'       => 'admin',
    ]);

    echo "[✓] Administrador criado — e-mail: {$emailAdmin} | senha: {$senhaAdmin}\n";
    echo "[!] ATENÇÃO: Altere a senha padrão imediatamente após o primeiro acesso.\n";
} else {
    echo "[~] Administrador já existe, pulando.\n";
}

// ─── Itens de vistoria padrão ────────────────────────────────────────────────

$itensDeVistoria = [
    ['nome' => 'Pintura',       'categoria' => 'Acabamento'],
    ['nome' => 'Piso',          'categoria' => 'Acabamento'],
    ['nome' => 'Rodapé',        'categoria' => 'Acabamento'],
    ['nome' => 'Teto',          'categoria' => 'Estrutura'],
    ['nome' => 'Janelas',       'categoria' => 'Esquadrias'],
    ['nome' => 'Portas',        'categoria' => 'Esquadrias'],
    ['nome' => 'Tomadas',       'categoria' => 'Elétrica'],
    ['nome' => 'Interruptores', 'categoria' => 'Elétrica'],
    ['nome' => 'Iluminação',    'categoria' => 'Elétrica'],
    ['nome' => 'Torneiras',     'categoria' => 'Hidráulica'],
];

$stmtBuscarItem  = $conexao->prepare(
    'SELECT id FROM item_vistoria WHERE nome = :nome LIMIT 1'
);
$stmtInserirItem = $conexao->prepare(
    'INSERT INTO item_vistoria (id, nome, categoria) VALUES (:id, :nome, :categoria)'
);

foreach ($itensDeVistoria as $item) {
    $stmtBuscarItem->execute([':nome' => $item['nome']]);
    $itemExistente = $stmtBuscarItem->fetch();

    if (!$itemExistente) {
        $stmtInserirItem->execute([
            ':id'        => Uuid::gerar(),
            ':nome'      => $item['nome'],
            ':categoria' => $item['categoria'],
        ]);
        echo "[✓] Item de vistoria criado: {$item['nome']} ({$item['categoria']})\n";
    } else {
        echo "[~] Item já existe: {$item['nome']}, pulando.\n";
    }
}

echo "\n[✓] Seed inicial concluído.\n";
