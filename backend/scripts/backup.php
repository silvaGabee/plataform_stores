<?php
/**
 * Dump do banco para storage/backups/.
 *
 *   php backend/scripts/backup.php
 *   php backend/scripts/backup.php meu-rotulo
 *
 * Restaurar:
 *   mysql -u root plataform_stores < storage/backups/<arquivo>.sql
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Database\Backup;

try {
    $caminho = Backup::dump(isset($argv[1]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $argv[1]) : null);
} catch (Throwable $e) {
    fwrite(STDERR, 'Falha no backup: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Backup gravado: ' . $caminho . PHP_EOL;
echo 'Tamanho: ' . number_format(filesize($caminho) / 1024, 1) . ' KB' . PHP_EOL;
