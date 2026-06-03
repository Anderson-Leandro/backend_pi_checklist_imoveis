<?php

declare(strict_types=1);

/**
 * Seed de demonstração — One Check API
 *
 * Insere um conjunto completo de dados para desenvolvimento e testes:
 *   - 2 usuários (vistoriador + locatário)
 *   - 2 imóveis com endereço e cômodos (apartamento locado + casa disponível)
 *   - 1 contrato ativo (apartamento → locatário)
 *   - 2 agendamentos de vistoria (inicial passado + encerramento futuro)
 *   - 1 checklist inicial aceito com 6 itens preenchidos
 *   - 1 aceite do checklist
 *   - 2 registros de problema (1 em andamento + 1 resolvido com histórico)
 *   - 1 API key para testes de integração
 *
 * Idempotente: pode ser executado múltiplas vezes sem duplicar dados.
 * Pré-requisito: seed 001_seed_inicial.php deve ter sido executado antes.
 *
 * Execução: docker exec php_app php database/seeds/002_seed_demonstracao.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Conexao;
use App\Services\Storage\StorageFactory;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$pdo     = Conexao::obter();
$storage = StorageFactory::criar();

// ─── Helpers ─────────────────────────────────────────────────────────────────

function existe(PDO $pdo, string $tabela, string $id): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM `{$tabela}` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() !== false;
}

function inserir(PDO $pdo, string $tabela, array $dados): void
{
    $colunas    = implode(', ', array_keys($dados));
    $parametros = implode(', ', array_map(fn($c) => ":{$c}", array_keys($dados)));
    $stmt = $pdo->prepare("INSERT INTO `{$tabela}` ({$colunas}) VALUES ({$parametros})");
    $stmt->execute($dados);
}

function idItemVistoria(PDO $pdo, string $nome): string
{
    $stmt = $pdo->prepare('SELECT id FROM item_vistoria WHERE nome = :nome LIMIT 1');
    $stmt->execute([':nome' => $nome]);
    $linha = $stmt->fetch();

    if ($linha === false) {
        throw new RuntimeException(
            "item_vistoria '{$nome}' não encontrado. Execute o seed 001 primeiro."
        );
    }

    return $linha['id'];
}

// ─── IDs fixos (garantem idempotência em múltiplas execuções) ────────────────
// Formato: ENTIDADE-0000-4000-8000-SEQUENCIAL
// Versão 4 (4xxx) + Variante 1 (8xxx) — UUIDs válidos e rastreáveis como seeds.

// Usuários
const ID_VISTORIADOR = '00000001-0000-4000-8000-000000000001';
const ID_LOCATARIO   = '00000001-0000-4000-8000-000000000002';

// Imóveis
const ID_IMOVEL_APTO = '00000002-0000-4000-8000-000000000001';
const ID_IMOVEL_CASA = '00000002-0000-4000-8000-000000000002';

// Endereços
const ID_ENDERECO_APTO = '00000003-0000-4000-8000-000000000001';
const ID_ENDERECO_CASA = '00000003-0000-4000-8000-000000000002';

// Cômodos do Apartamento (6)
const ID_COMODO_APTO_SALA  = '00000004-0000-4000-8000-000000000001';
const ID_COMODO_APTO_QTO1  = '00000004-0000-4000-8000-000000000002';
const ID_COMODO_APTO_QTO2  = '00000004-0000-4000-8000-000000000003';
const ID_COMODO_APTO_COZ   = '00000004-0000-4000-8000-000000000004';
const ID_COMODO_APTO_BAN   = '00000004-0000-4000-8000-000000000005';
const ID_COMODO_APTO_ASERV = '00000004-0000-4000-8000-000000000006';

// Cômodos da Casa (10) — prefixo 11..1a para não colidir com os do apto
const ID_COMODO_CASA_QTO1   = '00000004-0000-4000-8000-000000000011';
const ID_COMODO_CASA_QTO2   = '00000004-0000-4000-8000-000000000012';
const ID_COMODO_CASA_QTO3   = '00000004-0000-4000-8000-000000000013';
const ID_COMODO_CASA_SALA   = '00000004-0000-4000-8000-000000000014';
const ID_COMODO_CASA_JANTAR = '00000004-0000-4000-8000-000000000015';
const ID_COMODO_CASA_COZ    = '00000004-0000-4000-8000-000000000016';
const ID_COMODO_CASA_BANSOC = '00000004-0000-4000-8000-000000000017';
const ID_COMODO_CASA_BANSUI = '00000004-0000-4000-8000-000000000018';
const ID_COMODO_CASA_GARA   = '00000004-0000-4000-8000-000000000019';
const ID_COMODO_CASA_QUIN   = '00000004-0000-4000-8000-00000000001a';

// Contrato
const ID_CONTRATO = '00000005-0000-4000-8000-000000000001';

// Checklist inicial
const ID_CHECKLIST = '00000006-0000-4000-8000-000000000001';

// Itens do checklist (6)
const ID_CK_ITEM_1 = '00000007-0000-4000-8000-000000000001';
const ID_CK_ITEM_2 = '00000007-0000-4000-8000-000000000002';
const ID_CK_ITEM_3 = '00000007-0000-4000-8000-000000000003';
const ID_CK_ITEM_4 = '00000007-0000-4000-8000-000000000004';
const ID_CK_ITEM_5 = '00000007-0000-4000-8000-000000000005';
const ID_CK_ITEM_6 = '00000007-0000-4000-8000-000000000006';

// Aceite do checklist
const ID_ACEITE = '00000008-0000-4000-8000-000000000001';

// Agendamentos de vistoria
const ID_AGEND_INICIAL      = '00000009-0000-4000-8000-000000000001';
const ID_AGEND_ENCERRAMENTO = '00000009-0000-4000-8000-000000000002';

// Problemas
const ID_PROBLEMA_ABERTO    = '0000000a-0000-4000-8000-000000000001';
const ID_PROBLEMA_RESOLVIDO = '0000000a-0000-4000-8000-000000000002';

// Atualizações de problema
const ID_ATUALIZACAO_1 = '0000000b-0000-4000-8000-000000000001';
const ID_ATUALIZACAO_2 = '0000000b-0000-4000-8000-000000000002';
const ID_ATUALIZACAO_3 = '0000000b-0000-4000-8000-000000000003';

// Fotos do checklist (1 por item, mesmo arquivo copiado 6 vezes)
const ID_FOTO_1 = '0000000d-0000-4000-8000-000000000001';
const ID_FOTO_2 = '0000000d-0000-4000-8000-000000000002';
const ID_FOTO_3 = '0000000d-0000-4000-8000-000000000003';
const ID_FOTO_4 = '0000000d-0000-4000-8000-000000000004';
const ID_FOTO_5 = '0000000d-0000-4000-8000-000000000005';
const ID_FOTO_6 = '0000000d-0000-4000-8000-000000000006';

// API Key
const ID_API_KEY    = '0000000c-0000-4000-8000-000000000001';
const VALOR_API_KEY = 'oc-test-e1f2a3b4c5d6e7f8901234567890abcd';

// ─────────────────────────────────────────────────────────────────────────────

echo "\n[→] Iniciando seed de demonstração...\n\n";

// ─── 1. Usuários ─────────────────────────────────────────────────────────────

echo "── Usuários ──────────────────────────────────────────────\n";

if (!existe($pdo, 'usuario', ID_VISTORIADOR)) {
    inserir($pdo, 'usuario', [
        'id'         => ID_VISTORIADOR,
        'nome'       => 'Carlos Eduardo Oliveira',
        'email'      => 'carlos.vistoriador@onecheck.com.br',
        'senha_hash' => password_hash('Vistoria@123', PASSWORD_BCRYPT),
        'role'       => 'vistoriador',
    ]);
    echo "[✓] Vistoriador criado: Carlos Eduardo Oliveira\n";
    echo "    E-mail: carlos.vistoriador@onecheck.com.br | Senha: Vistoria@123\n";
} else {
    echo "[~] Vistoriador já existe, pulando.\n";
}

if (!existe($pdo, 'usuario', ID_LOCATARIO)) {
    inserir($pdo, 'usuario', [
        'id'         => ID_LOCATARIO,
        'nome'       => 'Ana Paula Lima',
        'email'      => 'ana.locataria@onecheck.com.br',
        'senha_hash' => password_hash('Locatario@123', PASSWORD_BCRYPT),
        'role'       => 'locatario',
    ]);
    echo "[✓] Locatário criado: Ana Paula Lima\n";
    echo "    E-mail: ana.locataria@onecheck.com.br | Senha: Locatario@123\n";
} else {
    echo "[~] Locatário já existe, pulando.\n";
}

// ─── 2. Imóvel 1 — Apartamento (será locado) ─────────────────────────────────

echo "\n── Imóvel 1: Apartamento (Mauá, SP) ─────────────────────\n";

if (!existe($pdo, 'imovel', ID_IMOVEL_APTO)) {
    inserir($pdo, 'imovel', [
        'id'            => ID_IMOVEL_APTO,
        'tipo'          => 'Apartamento',
        'tamanho'       => '65m²',
        'garagem'       => 1,
        'garagem_vagas' => 1,
        'status'        => 'locado',
    ]);
    echo "[✓] Imóvel criado: Apartamento 65m², 1 vaga, status=locado\n";
} else {
    echo "[~] Imóvel 1 (Apartamento) já existe, pulando.\n";
}

if (!existe($pdo, 'endereco', ID_ENDERECO_APTO)) {
    inserir($pdo, 'endereco', [
        'id'          => ID_ENDERECO_APTO,
        'imovel_id'   => ID_IMOVEL_APTO,
        'rua'         => 'R. Vicente Grecco',
        'numero'      => '164',
        'complemento' => 'Apto 42',
        'bloco'       => 'B',
        'andar'       => '4º Andar',
        'cidade'      => 'Mauá',
        'estado'      => 'SP',
        'cep'         => '09371-453',
        'latitude'    => -23.6750000,
        'longitude'   => -46.4715560,
    ]);
    echo "[✓] Endereço: R. Vicente Grecco, 164, Apto 42, Bloco B, 4º Andar — Mauá, SP\n";
} else {
    $stmt = $pdo->prepare(
        'UPDATE endereco
            SET rua = :rua, numero = :numero, complemento = :complemento,
                bloco = :bloco, andar = :andar, cidade = :cidade,
                estado = :estado, cep = :cep,
                latitude = :latitude, longitude = :longitude
          WHERE id = :id'
    );
    $stmt->execute([
        'rua'         => 'R. Vicente Grecco',
        'numero'      => '164',
        'complemento' => 'Apto 42',
        'bloco'       => 'B',
        'andar'       => '4º Andar',
        'cidade'      => 'Mauá',
        'estado'      => 'SP',
        'cep'         => '09371-453',
        'latitude'    => -23.6750000,
        'longitude'   => -46.4715560,
        'id'          => ID_ENDERECO_APTO,
    ]);
    echo "[~] Endereço do Apartamento atualizado com dados e coordenadas corretos.\n";
}

$comodosApto = [
    [ID_COMODO_APTO_SALA,  'Sala de Estar',   'Sala espaçosa com piso de porcelanato'],
    [ID_COMODO_APTO_QTO1,  'Quarto Principal', 'Quarto com suíte e armário embutido'],
    [ID_COMODO_APTO_QTO2,  'Quarto 2',         'Quarto de solteiro com janela lateral'],
    [ID_COMODO_APTO_COZ,   'Cozinha',          'Cozinha americana integrada à área de serviço'],
    [ID_COMODO_APTO_BAN,   'Banheiro Social',  'Banheiro com box de vidro'],
    [ID_COMODO_APTO_ASERV, 'Área de Serviço',  'Área coberta com ponto de máquina de lavar'],
];

foreach ($comodosApto as [$id, $tipo, $descricao]) {
    if (!existe($pdo, 'comodo', $id)) {
        inserir($pdo, 'comodo', [
            'id'        => $id,
            'imovel_id' => ID_IMOVEL_APTO,
            'tipo'      => $tipo,
            'descricao' => $descricao,
        ]);
        echo "[✓] Cômodo: {$tipo}\n";
    } else {
        echo "[~] Cômodo '{$tipo}' já existe, pulando.\n";
    }
}

// ─── 3. Imóvel 2 — Casa (disponível para locação) ────────────────────────────

echo "\n── Imóvel 2: Casa (Santo André, SP) ──────────────────────\n";

if (!existe($pdo, 'imovel', ID_IMOVEL_CASA)) {
    inserir($pdo, 'imovel', [
        'id'            => ID_IMOVEL_CASA,
        'tipo'          => 'Casa',
        'tamanho'       => '120m²',
        'garagem'       => 1,
        'garagem_vagas' => 2,
        'status'        => 'disponivel',
    ]);
    echo "[✓] Imóvel criado: Casa 120m², 2 vagas, status=disponivel\n";
} else {
    echo "[~] Imóvel 2 (Casa) já existe, pulando.\n";
}

if (!existe($pdo, 'endereco', ID_ENDERECO_CASA)) {
    inserir($pdo, 'endereco', [
        'id'        => ID_ENDERECO_CASA,
        'imovel_id' => ID_IMOVEL_CASA,
        'rua'       => 'R. Aimorés',
        'numero'    => '8',
        'cidade'    => 'Santo André',
        'estado'    => 'SP',
        'cep'       => '09195-090',
        'latitude'  => -23.6742730,
        'longitude' => -46.5236200,
    ]);
    echo "[✓] Endereço: R. Aimorés, 8 — Santo André, SP\n";
} else {
    $stmt = $pdo->prepare(
        'UPDATE endereco
            SET rua = :rua, numero = :numero, cidade = :cidade,
                estado = :estado, cep = :cep,
                latitude = :latitude, longitude = :longitude
          WHERE id = :id'
    );
    $stmt->execute([
        'rua'       => 'R. Aimorés',
        'numero'    => '8',
        'cidade'    => 'Santo André',
        'estado'    => 'SP',
        'cep'       => '09195-090',
        'latitude'  => -23.6742730,
        'longitude' => -46.5236200,
        'id'        => ID_ENDERECO_CASA,
    ]);
    echo "[~] Endereço da Casa atualizado com dados e coordenadas corretos.\n";
}

$comodosCasa = [
    [ID_COMODO_CASA_QTO1,   'Quarto Principal', 'Suíte master com closet'],
    [ID_COMODO_CASA_QTO2,   'Quarto 2',          'Quarto com armário embutido'],
    [ID_COMODO_CASA_QTO3,   'Quarto 3',          'Quarto reversível / escritório'],
    [ID_COMODO_CASA_SALA,   'Sala de Estar',     'Sala ampla com piso flutuante'],
    [ID_COMODO_CASA_JANTAR, 'Sala de Jantar',    'Integrada à sala de estar'],
    [ID_COMODO_CASA_COZ,    'Cozinha',           'Cozinha completa com armários planejados'],
    [ID_COMODO_CASA_BANSOC, 'Banheiro Social',   'Banheiro completo com box'],
    [ID_COMODO_CASA_BANSUI, 'Banheiro Suíte',    'Banheiro privativo da suíte master'],
    [ID_COMODO_CASA_GARA,   'Garagem',           'Garagem coberta para 2 veículos'],
    [ID_COMODO_CASA_QUIN,   'Quintal',           'Área externa gramada com churrasqueira'],
];

foreach ($comodosCasa as [$id, $tipo, $descricao]) {
    if (!existe($pdo, 'comodo', $id)) {
        inserir($pdo, 'comodo', [
            'id'        => $id,
            'imovel_id' => ID_IMOVEL_CASA,
            'tipo'      => $tipo,
            'descricao' => $descricao,
        ]);
        echo "[✓] Cômodo: {$tipo}\n";
    } else {
        echo "[~] Cômodo '{$tipo}' já existe, pulando.\n";
    }
}

// ─── 4. Contrato ─────────────────────────────────────────────────────────────

echo "\n── Contrato ──────────────────────────────────────────────\n";

if (!existe($pdo, 'contrato', ID_CONTRATO)) {
    inserir($pdo, 'contrato', [
        'id'           => ID_CONTRATO,
        'imovel_id'    => ID_IMOVEL_APTO,
        'locatario_id' => ID_LOCATARIO,
        'data_inicio'  => '2026-01-01',
        'data_fim'     => '2026-12-31',
        'status'       => 'ativo',
    ]);
    echo "[✓] Contrato: Apartamento → Ana Paula Lima (01/01/2026 a 31/12/2026)\n";
} else {
    echo "[~] Contrato já existe, pulando.\n";
}

// ─── 5. Agendamentos de vistoria ─────────────────────────────────────────────

echo "\n── Agendamentos ──────────────────────────────────────────\n";

if (!existe($pdo, 'agendamento_vistoria', ID_AGEND_INICIAL)) {
    inserir($pdo, 'agendamento_vistoria', [
        'id'            => ID_AGEND_INICIAL,
        'contrato_id'   => ID_CONTRATO,
        'tipo'          => 'inicial',
        'data_agendada' => '2026-01-03 09:00:00',
        'observacao'    => 'Vistoria de entrada do imóvel.',
    ]);
    echo "[✓] Agendamento inicial: 03/01/2026 09:00\n";
} else {
    echo "[~] Agendamento inicial já existe, pulando.\n";
}

if (!existe($pdo, 'agendamento_vistoria', ID_AGEND_ENCERRAMENTO)) {
    inserir($pdo, 'agendamento_vistoria', [
        'id'            => ID_AGEND_ENCERRAMENTO,
        'contrato_id'   => ID_CONTRATO,
        'tipo'          => 'encerramento',
        'data_agendada' => '2026-11-28 10:00:00',
        'observacao'    => 'Vistoria de encerramento antes do término do contrato em 31/12/2026.',
    ]);
    echo "[✓] Agendamento encerramento: 28/11/2026 10:00 (futuro)\n";
} else {
    echo "[~] Agendamento de encerramento já existe, pulando.\n";
}

// ─── 6. Checklist inicial ────────────────────────────────────────────────────

echo "\n── Checklist ─────────────────────────────────────────────\n";

if (!existe($pdo, 'checklist', ID_CHECKLIST)) {
    inserir($pdo, 'checklist', [
        'id'             => ID_CHECKLIST,
        'contrato_id'    => ID_CONTRATO,
        'vistoriador_id' => ID_VISTORIADOR,
        'tipo'           => 'inicial',
        'status'         => 'aceito',
        'data_vistoria'  => '2026-01-03 09:00:00',
    ]);
    echo "[✓] Checklist inicial criado (status=aceito, vistoria em 03/01/2026)\n";
} else {
    echo "[~] Checklist já existe, pulando.\n";
}

// ─── 7. Itens do checklist ───────────────────────────────────────────────────

echo "\n── Itens do checklist ────────────────────────────────────\n";

$idPintura   = idItemVistoria($pdo, 'Pintura');
$idPiso      = idItemVistoria($pdo, 'Piso');
$idJanelas   = idItemVistoria($pdo, 'Janelas');
$idTorneiras = idItemVistoria($pdo, 'Torneiras');

$itensChecklist = [
    // [id,          comodo_id,            item_id,      estado,    observacao]
    [ID_CK_ITEM_1, ID_COMODO_APTO_SALA, $idPintura,   'otimo',   null],
    [ID_CK_ITEM_2, ID_COMODO_APTO_SALA, $idPiso,      'bom',     'Pequenas marcas de uso natural'],
    [ID_CK_ITEM_3, ID_COMODO_APTO_QTO1, $idPintura,   'bom',     'Manchas leves próximas à janela'],
    [ID_CK_ITEM_4, ID_COMODO_APTO_QTO1, $idJanelas,   'otimo',   null],
    [ID_CK_ITEM_5, ID_COMODO_APTO_BAN,  $idPintura,   'regular', 'Manchas de umidade no teto'],
    [ID_CK_ITEM_6, ID_COMODO_APTO_BAN,  $idTorneiras, 'bom',     'Funcionando normalmente'],
];

$labelComodo = [
    ID_COMODO_APTO_SALA => 'Sala de Estar',
    ID_COMODO_APTO_QTO1 => 'Quarto Principal',
    ID_COMODO_APTO_BAN  => 'Banheiro Social',
];

foreach ($itensChecklist as [$id, $comodoId, $itemId, $estado, $observacao]) {
    if (!existe($pdo, 'checklist_item', $id)) {
        inserir($pdo, 'checklist_item', [
            'id'               => $id,
            'checklist_id'     => ID_CHECKLIST,
            'comodo_id'        => $comodoId,
            'item_vistoria_id' => $itemId,
            'estado'           => $estado,
            'observacao'       => $observacao,
        ]);
        $label = $labelComodo[$comodoId] ?? $comodoId;
        echo "[✓] Item: {$label} → estado={$estado}" . ($observacao ? " (obs: {$observacao})" : '') . "\n";
    } else {
        echo "[~] Checklist item {$id} já existe, pulando.\n";
    }
}

// ─── 8. Aceite do checklist ──────────────────────────────────────────────────

echo "\n── Aceite ────────────────────────────────────────────────\n";

if (!existe($pdo, 'aceite_checklist', ID_ACEITE)) {
    inserir($pdo, 'aceite_checklist', [
        'id'           => ID_ACEITE,
        'checklist_id' => ID_CHECKLIST,
        'locatario_id' => ID_LOCATARIO,
        'status'       => 'aceito',
    ]);
    echo "[✓] Aceite: Ana Paula Lima aceitou o checklist inicial\n";
} else {
    echo "[~] Aceite já existe, pulando.\n";
}

// ─── 9. Problema em andamento ────────────────────────────────────────────────

echo "\n── Problemas ─────────────────────────────────────────────\n";

if (!existe($pdo, 'registro_problema', ID_PROBLEMA_ABERTO)) {
    inserir($pdo, 'registro_problema', [
        'id'          => ID_PROBLEMA_ABERTO,
        'contrato_id' => ID_CONTRATO,
        'comodo_id'   => ID_COMODO_APTO_BAN,
        'titulo'      => 'Torneira da pia com gotejamento',
        'descricao'   => 'Torneira apresenta gotejamento constante mesmo quando totalmente fechada.',
        'status'      => 'em_andamento',
    ]);
    echo "[✓] Problema criado: 'Torneira da pia com gotejamento' (em_andamento)\n";
} else {
    echo "[~] Problema (em andamento) já existe, pulando.\n";
}

if (!existe($pdo, 'atualizacao_problema', ID_ATUALIZACAO_1)) {
    inserir($pdo, 'atualizacao_problema', [
        'id'          => ID_ATUALIZACAO_1,
        'problema_id' => ID_PROBLEMA_ABERTO,
        'autor_id'    => ID_VISTORIADOR,
        'descricao'   => 'Problema verificado in loco. Vedação do cartucho desgastada. Encanador agendado para 10/01/2026.',
    ]);
    echo "[✓] Atualização: vistoriador registrou diagnóstico e agendamento de encanador\n";
} else {
    echo "[~] Atualização 1 já existe, pulando.\n";
}

// ─── 10. Problema resolvido (ciclo de vida completo) ─────────────────────────

if (!existe($pdo, 'registro_problema', ID_PROBLEMA_RESOLVIDO)) {
    inserir($pdo, 'registro_problema', [
        'id'          => ID_PROBLEMA_RESOLVIDO,
        'contrato_id' => ID_CONTRATO,
        'comodo_id'   => ID_COMODO_APTO_QTO1,
        'titulo'      => 'Janela do quarto com travamento',
        'descricao'   => 'Janela do quarto principal está com dificuldade de fechar. Trinco desalinhado após as chuvas.',
        'status'      => 'resolvido',
    ]);
    echo "[✓] Problema criado: 'Janela do quarto com travamento' (resolvido)\n";
} else {
    echo "[~] Problema (resolvido) já existe, pulando.\n";
}

if (!existe($pdo, 'atualizacao_problema', ID_ATUALIZACAO_2)) {
    inserir($pdo, 'atualizacao_problema', [
        'id'          => ID_ATUALIZACAO_2,
        'problema_id' => ID_PROBLEMA_RESOLVIDO,
        'autor_id'    => ID_LOCATARIO,
        'descricao'   => 'Janela travando ao fechar desde a semana passada. Trinco parece estar desalinhado após as chuvas fortes.',
    ]);
    echo "[✓] Atualização 1: locatário reportou detalhes do problema\n";
} else {
    echo "[~] Atualização 2 já existe, pulando.\n";
}

if (!existe($pdo, 'atualizacao_problema', ID_ATUALIZACAO_3)) {
    inserir($pdo, 'atualizacao_problema', [
        'id'          => ID_ATUALIZACAO_3,
        'problema_id' => ID_PROBLEMA_RESOLVIDO,
        'autor_id'    => ID_VISTORIADOR,
        'descricao'   => 'Trinco ajustado e realinhado. Janela funcionando normalmente. Problema encerrado.',
    ]);
    echo "[✓] Atualização 2: vistoriador registrou resolução e encerrou o problema\n";
} else {
    echo "[~] Atualização 3 já existe, pulando.\n";
}

// ─── 11. Fotos dos itens do checklist ────────────────────────────────────────

echo "\n── Fotos do checklist ────────────────────────────────────\n";

$fotosChecklist = [
    [ID_FOTO_1, ID_CK_ITEM_1],
    [ID_FOTO_2, ID_CK_ITEM_2],
    [ID_FOTO_3, ID_CK_ITEM_3],
    [ID_FOTO_4, ID_CK_ITEM_4],
    [ID_FOTO_5, ID_CK_ITEM_5],
    [ID_FOTO_6, ID_CK_ITEM_6],
];

$assetPlaceholder = __DIR__ . '/assets/placeholder.jpg';

foreach ($fotosChecklist as [$idFoto, $idItem]) {
    if (existe($pdo, 'foto_checklist', $idFoto)) {
        echo "[~] Foto {$idFoto} já existe no banco, pulando.\n";
        continue;
    }

    // Chave determinística — garante idempotência mesmo sem registro no banco
    $chave = 'fotos/checklists/' . $idFoto . '.jpg';

    if (!$storage->exists($chave)) {
        $storage->storeWithKey([
            'name'     => 'placeholder.jpg',
            'tmp_name' => $assetPlaceholder,
            'size'     => filesize($assetPlaceholder),
            'type'     => 'image/jpeg',
            'error'    => UPLOAD_ERR_OK,
        ], $chave);
        echo "[✓] Arquivo enviado ao storage: {$chave}\n";
    } else {
        echo "[~] Arquivo já existe no storage: {$chave}\n";
    }

    inserir($pdo, 'foto_checklist', [
        'id'                => $idFoto,
        'checklist_item_id' => $idItem,
        'url'               => $chave,
    ]);
    echo "[✓] Foto vinculada ao item {$idItem}\n";
}

// ─── 12. API Key para testes ─────────────────────────────────────────────────

echo "\n── API Key ───────────────────────────────────────────────\n";

if (!existe($pdo, 'api_key', ID_API_KEY)) {
    inserir($pdo, 'api_key', [
        'id'       => ID_API_KEY,
        'nome'     => 'Chave de Testes',
        'key_hash' => hash('sha256', VALOR_API_KEY),
        'ativo'    => 1,
    ]);
    echo "[✓] API Key criada: 'Chave de Testes'\n";
    echo "    Valor (header X-Api-Key): " . VALOR_API_KEY . "\n";
} else {
    echo "[~] API Key já existe, pulando.\n";
}

// ─── Resumo ───────────────────────────────────────────────────────────────────

echo "\n[✓] Seed de demonstração concluído.\n";
echo "\n";
echo "════════════════════════════════════════════════════════════\n";
echo " CREDENCIAIS DE ACESSO\n";
echo "════════════════════════════════════════════════════════════\n";
echo " Admin        admin@onecheck.com.br            Admin@1234\n";
echo " Vistoriador  carlos.vistoriador@onecheck.com.br  Vistoria@123\n";
echo " Locatário    ana.locataria@onecheck.com.br       Locatario@123\n";
echo "────────────────────────────────────────────────────────────\n";
echo " API Key (X-Api-Key): " . VALOR_API_KEY . "\n";
echo "════════════════════════════════════════════════════════════\n\n";
