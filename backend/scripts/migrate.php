<?php
/**
 * Aplicador de migrations.
 *
 *   php backend/scripts/migrate.php              aplica as pendentes
 *   php backend/scripts/migrate.php --status     lista o que falta, sem aplicar
 *   php backend/scripts/migrate.php --baseline   marca todas como aplicadas
 *
 * A ordem é a alfabética dos nomes — por isso o prefixo numérico (0001_, 0002_).
 * O que já rodou fica registado em `schema_migrations`, então cada arquivo é
 * executado uma única vez, mesmo que não seja idempotente por si só.
 *
 * `--baseline` serve para um banco que já existe e já recebeu as alterações à
 * mão: marca tudo como aplicado sem executar nada. Sem isso, a primeira execução
 * tentaria recriar colunas que já estão lá.
 *
 * Atenção: DDL provoca commit implícito no MySQL/MariaDB. Não há como desfazer
 * uma migration pela metade — por isso o runner para no primeiro erro e diz
 * exatamente onde parou, em vez de seguir e deixar o banco num estado incerto.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

use App\Database\Backup;
use App\Database\Database;

$args = array_slice($argv, 1);
$semBackup = in_array('--no-backup', $args, true);
$args = array_values(array_filter($args, static fn (string $a): bool => $a !== '--no-backup'));
$modo = $args[0] ?? '';
if (!in_array($modo, ['', '--status', '--baseline'], true)) {
    fwrite(STDERR, "Uso: php backend/scripts/migrate.php [--status|--baseline] [--no-backup]\n");
    exit(64);
}

$dir = PLATAFORM_BACKEND . '/database/migrations';
$arquivos = glob($dir . '/*.sql') ?: [];
sort($arquivos, SORT_STRING);
if ($arquivos === []) {
    echo "Nenhuma migration encontrada em {$dir}\n";
    exit(0);
}

try {
    $pdo = Database::getConnection();
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro de conexão: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(191) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$aplicadas = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
$aplicadas = array_flip($aplicadas);

$pendentes = [];
foreach ($arquivos as $caminho) {
    $versao = basename($caminho);
    if (!isset($aplicadas[$versao])) {
        $pendentes[] = $caminho;
    }
}

$marcar = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');

if ($modo === '--status') {
    echo 'Aplicadas: ' . count($aplicadas) . ' | Pendentes: ' . count($pendentes) . PHP_EOL;
    foreach ($arquivos as $caminho) {
        $versao = basename($caminho);
        echo (isset($aplicadas[$versao]) ? '  [x] ' : '  [ ] ') . $versao . PHP_EOL;
    }
    exit(0);
}

if ($modo === '--baseline') {
    if ($pendentes === []) {
        echo "Nada a marcar: todas as migrations já estão registadas.\n";
        exit(0);
    }
    foreach ($pendentes as $caminho) {
        $marcar->execute([basename($caminho)]);
        echo '  marcada (sem executar)  ' . basename($caminho) . PHP_EOL;
    }
    echo PHP_EOL . count($pendentes) . " migration(s) marcada(s) como aplicada(s).\n";
    exit(0);
}

if ($pendentes === []) {
    echo "Banco já está atualizado (" . count($aplicadas) . " migrations aplicadas).\n";
    exit(0);
}

echo count($pendentes) . " migration(s) pendente(s):\n\n";

// Backup antes de qualquer DDL. DDL provoca commit implícito no MySQL/MariaDB,
// então não há rollback possível se uma migration falhar no meio — o dump é a
// única forma de voltar atrás. Só é dispensado quando o banco está vazio (nada
// a perder) ou quando alguém pede explicitamente com --no-backup.
$temDados = false;
foreach (['stores', 'users', 'products', 'orders'] as $t) {
    try {
        if ((int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn() > 0) {
            $temDados = true;
            break;
        }
    } catch (Throwable $e) {
        // Tabela ainda não existe: instalação nova, sem dados a proteger.
    }
}
if ($temDados && !$semBackup) {
    try {
        $arquivo = Backup::dump('pre-migrate');
        echo '  backup  ' . basename($arquivo) . PHP_EOL . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Backup falhou: ' . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, 'Nada foi aplicado. Resolva o backup, ou use --no-backup se souber o que está fazendo.' . PHP_EOL);
        exit(1);
    }
}

$executadas = 0;
foreach ($pendentes as $caminho) {
    $versao = basename($caminho);
    $sql = file_get_contents($caminho);
    if ($sql === false) {
        fwrite(STDERR, "  ERRO  não foi possível ler {$versao}\n");
        exit(1);
    }
    try {
        $pdo->exec($sql);
        $marcar->execute([$versao]);
        $executadas++;
        echo '  ok    ' . $versao . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, PHP_EOL . "  ERRO  {$versao}" . PHP_EOL);
        fwrite(STDERR, '        ' . $e->getMessage() . PHP_EOL . PHP_EOL);
        fwrite(STDERR, "Parou aqui. {$executadas} migration(s) aplicada(s) antes da falha." . PHP_EOL);
        fwrite(STDERR, "Corrija o arquivo e rode de novo — as anteriores não repetem." . PHP_EOL);
        exit(1);
    }
}

echo PHP_EOL . $executadas . " migration(s) aplicada(s).\n";
exit(0);
