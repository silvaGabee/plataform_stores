<?php
/**
 * Gera as miniaturas WebP das fotos de produto que ainda não têm uma.
 *
 *   php backend/scripts/thumbs.php          só as que faltam
 *   php backend/scripts/thumbs.php --todas  refaz todas
 *
 * As fotos novas já ganham miniatura no envio (product_image_make_thumb).
 * Este script cobre as que foram enviadas antes. Precisa da extensão GD
 * (no XAMPP: tirar o ";" de ";extension=gd" no php.ini e reiniciar o Apache).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "A extensão GD com suporte a WebP não está ativa neste PHP.\n"
        . "No XAMPP, abra php.ini, troque ';extension=gd' por 'extension=gd' e rode de novo.\n");
    exit(1);
}

$todas = in_array('--todas', $argv, true);
$pasta = PLATAFORM_ROOT . '/frontend/public/uploads/products';
$feitas = 0;
$puladas = 0;
$falhas = 0;
$antes = 0;
$depois = 0;

foreach (glob($pasta . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $arquivo) {
    $rel = 'products/' . basename($arquivo);
    $thumb = PLATAFORM_ROOT . '/frontend/public/uploads/' . product_image_thumb_path($rel);
    if (!$todas && is_file($thumb)) {
        $puladas++;
        continue;
    }
    if (product_image_make_thumb($rel) === null) {
        $falhas++;
        fwrite(STDERR, 'Não deu para gerar: ' . $rel . PHP_EOL);
        continue;
    }
    $feitas++;
    $antes += (int) filesize($arquivo);
    $depois += (int) filesize($thumb);
}

echo "Miniaturas geradas: {$feitas}. Já existiam: {$puladas}. Falhas: {$falhas}." . PHP_EOL;
if ($feitas > 0) {
    printf("Peso das fotos processadas: %.1f MB → %.1f MB nas miniaturas.\n", $antes / 1048576, $depois / 1048576);
}
