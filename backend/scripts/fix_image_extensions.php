<?php
/**
 * Corrige fotos de produto salvas com a extensão errada (ex.: AVIF com .png).
 *
 *   php backend/scripts/fix_image_extensions.php            mostra o que faria
 *   php backend/scripts/fix_image_extensions.php --aplicar  renomeia e atualiza o banco
 *
 * O envio passou a decidir a extensão pelo conteúdo (image_extension_from_bytes).
 * Este script conserta as que entraram antes disso: renomeia o arquivo e
 * atualiza product_images.file_path, numa transação por arquivo.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

$aplicar = in_array('--aplicar', $argv, true);
$uploads = PLATAFORM_ROOT . '/frontend/public/uploads/';
$pdo = \App\Database\Database::getConnection();
$achados = 0;
$corrigidos = 0;

foreach (glob($uploads . 'products/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $arquivo) {
    $inicio = (string) @file_get_contents($arquivo, false, null, 0, 4096);
    $real = image_extension_from_bytes($inicio);
    $atual = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
    if ($atual === 'jpeg') {
        $atual = 'jpg';
    }
    if ($real === null || $real === $atual) {
        continue;
    }
    $achados++;
    $relAntigo = 'products/' . basename($arquivo);
    $relNovo = 'products/' . pathinfo($arquivo, PATHINFO_FILENAME) . '.' . $real;
    echo ($aplicar ? 'Corrigindo ' : 'Corrigiria ') . $relAntigo . ' -> ' . $relNovo . PHP_EOL;
    if (!$aplicar) {
        continue;
    }
    if (is_file($uploads . $relNovo)) {
        fwrite(STDERR, '  Já existe ' . $relNovo . ', pulando.' . PHP_EOL);
        continue;
    }
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('UPDATE product_images SET file_path = ? WHERE file_path = ?');
        $st->execute([$relNovo, $relAntigo]);
        if (!rename($arquivo, $uploads . $relNovo)) {
            throw new RuntimeException('não deu para renomear o arquivo');
        }
        $pdo->commit();
        $corrigidos++;
        product_image_make_thumb($relNovo);
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, '  Falhou: ' . $e->getMessage() . PHP_EOL);
    }
}

echo $achados === 0
    ? 'Nenhuma foto com extensão errada.' . PHP_EOL
    : ($aplicar ? "Corrigidas: {$corrigidos} de {$achados}." : "Encontradas: {$achados}. Rode com --aplicar para corrigir.") . PHP_EOL;
