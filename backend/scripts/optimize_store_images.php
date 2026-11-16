<?php
/**
 * Reduz o banner e o ícone que as lojas já enviaram.
 *
 *   php backend/scripts/optimize_store_images.php            mostra o que faria
 *   php backend/scripts/optimize_store_images.php --aplicar  reduz e atualiza o banco
 *
 * Os envios novos já passam por store_image_optimize(). Banner vira WebP com
 * até 1920 px; ícone vira PNG com até 128 px. GIF animado não é tocado (perderia
 * a animação): o script só informa o tamanho, para o lojista trocar.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

if (!function_exists('imagecreatefromstring')) {
    fwrite(STDERR, "A extensão GD não está ativa neste PHP.\n");
    exit(1);
}

$aplicar = in_array('--aplicar', $argv, true);
$uploads = PLATAFORM_ROOT . '/frontend/public/uploads/';
$pdo = \App\Database\Database::getConnection();
$kb = static fn (string $rel): int => is_file($uploads . $rel) ? (int) round(filesize($uploads . $rel) / 1024) : 0;

$campos = [
    'banner_path' => [1920, 1080, 'webp'],
    'store_icon_path' => [128, 128, 'png'],
];
foreach ($pdo->query('SELECT id, slug, banner_path, store_icon_path FROM stores')->fetchAll(PDO::FETCH_ASSOC) as $loja) {
    foreach ($campos as $coluna => [$w, $h, $formato]) {
        $rel = trim((string) ($loja[$coluna] ?? ''));
        if ($rel === '' || strpos($rel, '..') !== false || !is_file($uploads . $rel)) {
            continue;
        }
        $antes = $kb($rel);
        if (image_is_animated_gif($uploads . $rel)) {
            echo "{$loja['slug']} · {$coluna}: GIF animado de {$antes} KB, mantido (peça ao lojista uma imagem parada).\n";
            continue;
        }
        if (!$aplicar) {
            echo "{$loja['slug']} · {$coluna}: {$rel} ({$antes} KB) seria conferido e reduzido se couber.\n";
            continue;
        }
        $novo = store_image_optimize($rel, $w, $h, $formato);
        clearstatcache();
        // Mesmo formato (ícone PNG → PNG): o caminho não muda, mas o arquivo sim.
        if ($novo === $rel && $kb($rel) >= $antes) {
            echo "{$loja['slug']} · {$coluna}: já estava enxuto ({$antes} KB).\n";
            continue;
        }
        $pdo->prepare("UPDATE stores SET {$coluna} = ? WHERE id = ?")->execute([$novo, (int) $loja['id']]);
        echo "{$loja['slug']} · {$coluna}: {$antes} KB → " . $kb($novo) . " KB ({$novo}).\n";
    }
}
if (!$aplicar) {
    echo "Rode com --aplicar para reduzir.\n";
}
