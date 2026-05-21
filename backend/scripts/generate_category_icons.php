<?php
$dir = dirname(__DIR__, 2) . '/frontend/public/assets/images/category-icons';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$icons = [
    'camisa' => '<path d="M7 4l-2 3H3v13h18V7h-2L15 4H7z"/><path d="M9 7h6v13"/>',
    'calca' => '<path d="M6 3h12v5l-1 14h-4l-1-14V8H7V3z"/><path d="M9 8h6"/>',
    'tenis' => '<path d="M4 14h16l-2 6H6l-2-6z"/><path d="M6 14l1-4h10l1 4"/><path d="M8 10h8"/>',
    'garrafa' => '<path d="M10 3h4v3h1l1 14H8L9 6h1V3z"/><path d="M10 10h4"/>',
    'acessorios' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
    'mochila' => '<path d="M8 7V5a4 4 0 018 0v2"/><path d="M5 7h14l-1 13H6L5 7z"/><path d="M9 11h6"/>',
    'sacola' => '<path d="M7 8V6a5 5 0 0110 0v2"/><path d="M6 8h12l-1 12H7L6 8z"/>',
    'presente' => '<path d="M4 10h16v11H4z"/><path d="M12 10v11"/><path d="M12 6c-2 0-3 1-3 2h6c0-1-1-2-3-2z"/><path d="M4 10h16V8H4z"/>',
    'suplemento' => '<path d="M9 4h6v3h1v13H8V7h1V4z"/><path d="M10 10h4"/>',
    'relogio' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    'bone' => '<path d="M4 14c0-4 3.5-7 8-7s8 3 8 7"/><path d="M6 14h12l-1 5H7l-1-5z"/>',
    'legging' => '<path d="M8 3h8v6l-1 12h-2l-1-12V9H9v12H7L8 3z"/>',
    'shorts' => '<path d="M6 6h12v3l-2 11h-3l-1-11V9H9v11H6L6 6z"/>',
    'luvas' => '<path d="M5 12V9a3 3 0 016 0v8H7V9"/><path d="M13 12V9a3 3 0 016 0v8h-4v-8"/>',
    'corda' => '<path d="M7 5c4 0 5 3 5 7s-1 7-5 7"/><path d="M17 5c-4 0-5 3-5 7s1 7 5 7"/>',
    'bike' => '<circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path d="M9 17h6M6 14l3-7h5l2 4"/>',
    'carrinho' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2 12h10l2-8H7"/>',
];
$wrap = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">%s</svg>';
foreach ($icons as $key => $paths) {
    file_put_contents($dir . '/' . $key . '.svg', sprintf($wrap, $paths));
}
echo "OK\n";
