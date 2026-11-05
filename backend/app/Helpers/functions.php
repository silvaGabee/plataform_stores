<?php

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        static $configs = [];
        $parts = explode('.', $key);
        $file = $parts[0];
        if (!isset($configs[$file])) {
            $path = PLATAFORM_BACKEND . "/config/{$file}.php";
            $configs[$file] = file_exists($path) ? require $path : [];
        }
        $value = $configs[$file];
        for ($i = 1; $i < count($parts); $i++) {
            $value = $value[$parts[$i]] ?? $default;
        }
        return $value ?? $default;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = getenv($key);
        if ($value === false) return $default;
        if (in_array(strtolower($value), ['true', '1', 'on'])) return true;
        if (in_array(strtolower($value), ['false', '0', 'off'])) return false;
        return $value;
    }
}

if (!function_exists('dd')) {
    function dd(...$vars) {
        foreach ($vars as $v) var_dump($v);
        exit(1);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', strtolower($text));
        $text = trim($text, '-');
        return $text ?: 'loja';
    }
}

if (!function_exists('json_response')) {
    function json_response($data, int $code = 200): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $code = 302): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: {$url}", true, $code);
        exit;
    }
}

if (!function_exists('log_message')) {
    /** Escreve uma linha em storage/logs/app-AAAA-MM-DD.log. Nunca lança. */
    function log_message(string $level, string $message, array $context = []): void {
        $dir = PLATAFORM_ROOT . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return;
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ': ' . $message;
        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('log_exception')) {
    /**
     * Registra a exceção completa e devolve um id curto.
     *
     * O id é a ponte entre o que o usuário vê ("ref: a1b2c3d4") e o stack trace
     * no log: permite esconder o detalhe da exceção na resposta — que é o que
     * vazava nome de tabela e trecho de SQL — sem tornar o erro impossível de
     * investigar depois.
     */
    function log_exception(\Throwable $e, array $context = []): string {
        $id = bin2hex(random_bytes(4));
        log_message('error', sprintf(
            '[%s] %s: %s @ %s:%d',
            $id,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ), $context);
        log_message('error', '[' . $id . '] trace: ' . $e->getTraceAsString());

        return $id;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    /** Campo oculto com o token, para formulários HTML. */
    function csrf_field(): string {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_meta')) {
    /** <meta> lido pelo interceptador de fetch (assets/js/csrf.js). */
    function csrf_meta(): string {
        return '<meta name="csrf-token" content="'
            . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('user_can')) {
    /**
     * O usuário logado tem esta permissão nesta loja?
     * Use nas views para mostrar ou esconder ações — a decisão real é do Guard,
     * antes do controller rodar.
     */
    function user_can(string $permissao, int $storeId): bool {
        return \App\Auth\Permissions::can($permissao, $storeId);
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $path = ltrim($path, '/');
        $base = '';
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
            // No Windows, dirname('/index.php') devolve '\' (e não '/'): sem
            // normalizar de novo, o `php -S` gerava links como "host\/login".
            $scriptDir = str_replace('\\', '/', dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])));
            if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '') {
                $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                $scheme = $https ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? '';
                if ($host !== '') {
                    $base = $scheme . '://' . $host . rtrim($scriptDir, '/');
                }
            }
        }
        if ($base === '') {
            $base = rtrim((string) config('app.url', 'http://localhost/plataform_stores/public'), '/');
        }
        return $path !== '' ? "{$base}/{$path}" : $base;
    }
}

if (!function_exists('asset')) {
    /**
     * URL de um arquivo em assets/, com ?v=<mtime>.
     *
     * A versão no fim é o que permite servir o arquivo com cache de um ano
     * (ver frontend/public/static.php): o conteúdo de uma URL nunca muda,
     * porque editar o arquivo muda o mtime e portanto a URL. Sem isso, ou o
     * cache é curto, ou uma alteração no CSS demora a chegar no navegador de
     * quem já visitou.
     */
    function asset(string $path): string {
        $rel = ltrim($path, '/');
        $url = base_url('assets/' . $rel);
        $arquivo = PLATAFORM_ROOT . '/frontend/public/assets/' . $rel;
        $mtime = is_file($arquivo) ? @filemtime($arquivo) : false;

        return $mtime !== false ? $url . '?v=' . $mtime : $url;
    }
}

/** Ícone «+» para botões de ação primária (mesmo padrão de «Novo funcionário»). */
if (!function_exists('btn_icon_plus')) {
    function btn_icon_plus(int $size = 18): string
    {
        $s = max(12, min(24, (int) $size));

        return '<svg class="btn-icon-svg" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
            . '<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    }
}

if (!function_exists('favicon_link_tag')) {
    /**
     * Tag <link rel="icon"> completa, com o tipo MIME certo.
     * Passe a loja para usar o ícone dela; sem argumento, usa o da plataforma.
     */
    function favicon_link_tag(?array $store = null): string
    {
        $url = $store !== null ? store_brand_icon_url($store) : favicon_url();

        // O tipo sai da extensão do arquivo. Os layouts declaravam
        // type="image/x-icon" fixo, mas o ícone da loja é enviado pelo lojista
        // e costuma ser PNG: o navegador recebia um PNG rotulado como ICO,
        // recusava, e desenhava um quadrado de ícone inválido na aba.
        $caminho = parse_url($url, PHP_URL_PATH) ?: '';
        $tipos = [
            'ico' => 'image/x-icon',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];
        $tipo = $tipos[strtolower(pathinfo($caminho, PATHINFO_EXTENSION))] ?? null;

        // Uma tag só: "shortcut icon" é herança do IE e, duplicada, só dava
        // ao navegador uma segunda chance de errar.
        return '<link rel="icon" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
            . ($tipo !== null ? ' type="' . $tipo . '"' : '')
            . ' sizes="any">';
    }
}

if (!function_exists('favicon_url')) {
    function favicon_url(): string
    {
        // A versão já vem do asset(). Esta função acrescentava a sua própria e,
        // depois que asset() passou a versionar, a URL saía com DOIS "?v=" —
        // malformada, o favicon não carregava e o navegador desenhava um
        // quadrado de imagem quebrada no cabeçalho de todas as páginas.
        return asset('favicon.ico');
    }
}

/** Normaliza chave de ícone (inclui aliases do catálogo antigo). */
if (!function_exists('vitrine_category_icon_normalize_key')) {
    function vitrine_category_icon_normalize_key(string $key): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
        if ($safe === '') {
            return 'acessorios';
        }
        $legacy = [
            'legging' => 'feminino',
            'garrafa' => 'suplementos',
            'suplemento' => 'suplementos',
            'mochila' => 'acessorios',
            'bermuda' => 'shorts',
            'blusa' => 'camiseta',
            'sacola' => 'acessorios',
            'presente' => 'ofertas',
            'luvas' => 'treino',
            'corda' => 'treino',
            'bike' => 'corrida',
            'carrinho' => 'ofertas',
        ];

        return $legacy[$safe] ?? $safe;
    }
}

/** Catálogo de ícones para categorias da vitrine (SVG brancos locais; o CSS escurece no tema claro). */
if (!function_exists('vitrine_category_icon_catalog')) {
    function vitrine_category_icon_catalog(): array
    {
        // Todos os ícones são servidos pelo próprio projeto. Antes a maioria
        // vinha de api.iconify.design: sem internet, a vitrine ficava sem ícone.
        $icones = [
            ['lancamentos', 'Lançamentos'],
            ['ofertas', 'Ofertas'],
            ['feminino', 'Feminino'],
            ['masculino', 'Masculino'],
            ['masculino_simbolo', 'Masculino (símbolo)'],
            ['infantil', 'Infantil'],
            ['camiseta', 'Camisetas'],
            ['camisa', 'Camisas'],
            ['calca', 'Calças'],
            ['shorts', 'Shorts e bermudas'],
            ['saia', 'Saias'],
            ['vestido', 'Vestido'],
            ['casaco', 'Casaco'],
            ['moletom', 'Moletons'],
            ['tenis', 'Tênis'],
            ['caminhada', 'Caminhada'],
            ['acessorios', 'Acessórios'],
            ['bolsa', 'Bolsas'],
            ['bone', 'Bonés'],
            ['oculos', 'Óculos'],
            ['relogio', 'Relógios'],
            ['meias', 'Meias'],
            ['premium', 'Premium'],
            ['plus_size', 'Plus Size'],
            ['treino', 'Treino / Academia'],
            ['corrida', 'Corrida'],
            ['yoga', 'Yoga / Pilates'],
            ['suplementos', 'Suplementos / Shaker'],
            ['nike', 'Nike'],
            ['adidas', 'Adidas'],
            ['puma', 'Puma'],
            ['new_balance', 'New Balance'],
            ['fila', 'Fila'],
        ];

        return array_map(static fn (array $i): array => [
            'key' => $i[0],
            'label' => $i[1],
            'url' => asset('images/category-icons/' . $i[0] . '.svg'),
        ], $icones);
    }
}

if (!function_exists('vitrine_category_icon_is_valid')) {
    function vitrine_category_icon_is_valid(string $key): bool
    {
        $normalized = vitrine_category_icon_normalize_key($key);
        foreach (vitrine_category_icon_catalog() as $icon) {
            if ($icon['key'] === $normalized) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vitrine_category_icon_url')) {
    function vitrine_category_icon_url(string $key): string
    {
        $normalized = vitrine_category_icon_normalize_key($key);
        foreach (vitrine_category_icon_catalog() as $icon) {
            if ($icon['key'] === $normalized) {
                return $icon['url'];
            }
        }

        return asset('images/category-icons/acessorios.svg');
    }
}

/** Catálogo de variações de produto (tipo + valores permitidos). */
if (!function_exists('product_variant_type_catalog')) {
    function product_variant_type_catalog(): array
    {
        return [
            'tamanho' => [
                'label' => 'Tamanho',
                'values' => ['P', 'M', 'G', 'GG', 'EGG', 'EXGG'],
            ],
            'numeracao' => [
                'label' => 'Numeração',
                'values' => ['34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'],
            ],
            'cor' => [
                'label' => 'Cores',
                'values' => ['Preto', 'Branco', 'Azul', 'Vermelho', 'Verde', 'Cinza', 'Rosa', 'Amarelo'],
            ],
        ];
    }
}

if (!function_exists('product_variant_type_is_valid')) {
    function product_variant_type_is_valid(string $type): bool
    {
        return isset(product_variant_type_catalog()[$type])
            || $type === 'combinacao'
            || $type === '_meta';
    }
}

if (!function_exists('product_variant_value_is_valid')) {
    function product_variant_value_is_valid(string $type, string $value): bool
    {
        $catalog = product_variant_type_catalog();
        if (!isset($catalog[$type])) {
            return false;
        }
        $value = trim($value);

        return in_array($value, $catalog[$type]['values'], true);
    }
}

/** Cor, tamanho ou numeração personalizada (além do catálogo padrão). */
if (!function_exists('product_variant_custom_value_is_valid')) {
    function product_variant_custom_value_is_valid(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 48) {
            return false;
        }
        if (str_contains($value, '|')) {
            return false;
        }

        return true;
    }
}

if (!function_exists('product_variant_matrix_color_is_valid')) {
    function product_variant_matrix_color_is_valid(string $color): bool
    {
        return product_variant_value_is_valid('cor', $color)
            || product_variant_custom_value_is_valid($color);
    }
}

if (!function_exists('product_variant_matrix_size_is_valid')) {
    function product_variant_matrix_size_is_valid(string $size, string $axis): bool
    {
        return in_array($axis, ['tamanho', 'numeracao'], true)
            && (product_variant_value_is_valid($axis, $size) || product_variant_custom_value_is_valid($size));
    }
}

if (!function_exists('product_variant_type_label')) {
    function product_variant_type_label(string $type): string
    {
        if ($type === 'combinacao') {
            return 'Combinação';
        }
        $catalog = product_variant_type_catalog();

        return $catalog[$type]['label'] ?? $type;
    }
}

if (!function_exists('product_variant_combination_key')) {
    function product_variant_combination_key(string $color, string $size): string
    {
        return trim($color) . '|' . trim($size);
    }
}

if (!function_exists('product_variants_matrix_to_rows')) {
    /** @param mixed $matrix */
    function product_variants_matrix_to_rows($matrix): array
    {
        if (!is_array($matrix)) {
            return [];
        }
        $axis = trim((string) ($matrix['axis'] ?? ''));
        if (!in_array($axis, ['tamanho', 'numeracao'], true)) {
            return [];
        }
        $colors = [];
        foreach ($matrix['colors'] ?? [] as $c) {
            $c = trim((string) $c);
            if ($c !== '' && product_variant_matrix_color_is_valid($c)) {
                $colors[$c] = true;
            }
        }
        $colors = array_keys($colors);
        $sizes = product_variants_matrix_normalize_sizes(
            is_array($matrix['sizes'] ?? null) ? $matrix['sizes'] : [],
            $axis
        );
        if ($colors === [] || $sizes === []) {
            return [];
        }
        $colorMeta = product_variant_matrix_color_meta_normalize($matrix['color_meta'] ?? null, $colors);
        $stockMap = is_array($matrix['stock'] ?? null) ? $matrix['stock'] : [];
        $out = [
            [
                'variant_type' => '_meta',
                'variant_value' => 'axis:' . $axis,
                'stock_quantity' => 0,
            ],
        ];
        foreach ($colorMeta as $colorName => $hex) {
            $out[] = [
                'variant_type' => '_meta',
                'variant_value' => 'color_hex:' . $colorName . '|' . $hex,
                'stock_quantity' => 0,
            ];
        }
        foreach ($sizes as $size) {
            $rowStock = is_array($stockMap[$size] ?? null) ? $stockMap[$size] : [];
            foreach ($colors as $color) {
                $qty = max(0, (int) ($rowStock[$color] ?? 0));
                $out[] = [
                    'variant_type' => 'combinacao',
                    'variant_value' => product_variant_combination_key($color, $size),
                    'stock_quantity' => $qty,
                ];
            }
        }

        return $out;
    }
}

if (!function_exists('product_variants_rows_to_matrix')) {
    /** @param list<array> $rows */
    function product_variants_rows_to_matrix(array $rows): ?array
    {
        $axis = null;
        $colors = [];
        $sizes = [];
        $stock = [];
        $colorMeta = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) ($row['variant_type'] ?? '');
            $value = trim((string) ($row['variant_value'] ?? ''));
            if ($type === '_meta' && str_starts_with($value, 'axis:')) {
                $a = substr($value, 5);
                if (in_array($a, ['tamanho', 'numeracao'], true)) {
                    $axis = $a;
                }
                continue;
            }
            if ($type === '_meta' && str_starts_with($value, 'color_hex:') && str_contains($value, '|')) {
                $payload = substr($value, strlen('color_hex:'));
                [$colorName, $hex] = explode('|', $payload, 2);
                $colorName = trim($colorName);
                $hexNorm = product_variant_hex_normalize(trim($hex));
                if ($colorName !== '' && $hexNorm !== null && product_variant_matrix_color_is_valid($colorName)) {
                    $colorMeta[$colorName] = $hexNorm;
                }
                continue;
            }
            if ($type !== 'combinacao' || !str_contains($value, '|')) {
                continue;
            }
            [$color, $size] = explode('|', $value, 2);
            $color = trim($color);
            $size = trim($size);
            if ($color === '' || $size === '') {
                continue;
            }
            $colors[$color] = true;
            $sizes[$size] = true;
            if (!isset($stock[$size])) {
                $stock[$size] = [];
            }
            $stock[$size][$color] = max(0, (int) ($row['stock_quantity'] ?? 0));
        }
        if ($axis === null || $colors === [] || $stock === []) {
            return null;
        }
        $catalog = product_variant_type_catalog();
        $sizeOrder = $catalog[$axis]['values'] ?? [];
        $colorOrder = $catalog['cor']['values'] ?? [];

        $colorsFromStock = [];
        foreach ($stock as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $colorName) {
                if ($colorName !== '' && product_variant_matrix_color_is_valid($colorName)) {
                    $colorsFromStock[$colorName] = true;
                }
            }
        }
        $sortedColors = array_values(array_intersect($colorOrder, array_keys($colorsFromStock)));
        foreach (array_keys($colorsFromStock) as $c) {
            if (!in_array($c, $sortedColors, true)) {
                $sortedColors[] = $c;
            }
        }

        $sortedSizes = array_keys($stock);
        usort($sortedSizes, static function (string $a, string $b) use ($sizeOrder): int {
            $ia = array_search($a, $sizeOrder, true);
            $ib = array_search($b, $sizeOrder, true);
            $ia = $ia === false ? PHP_INT_MAX : $ia;
            $ib = $ib === false ? PHP_INT_MAX : $ib;

            return $ia <=> $ib ?: strcmp($a, $b);
        });

        $matrixColors = $sortedColors;
        $normalizedMeta = product_variant_matrix_color_meta_normalize($colorMeta, $matrixColors);

        return [
            'axis' => $axis,
            'axis_label' => product_variant_type_label($axis),
            'colors' => $matrixColors,
            'sizes' => array_values($sortedSizes),
            'stock' => $stock,
            'color_meta' => $normalizedMeta,
        ];
    }
}

if (!function_exists('product_variants_matrix_normalize_sizes')) {
    /** @param list<string> $sizes */
    function product_variants_matrix_normalize_sizes(array $sizes, string $axis): array
    {
        $seen = [];
        $out = [];
        foreach ($sizes as $s) {
            $s = trim((string) $s);
            if ($s === '' || isset($seen[$s])) {
                continue;
            }
            if ($axis !== '' && !product_variant_matrix_size_is_valid($s, $axis)) {
                continue;
            }
            $seen[$s] = true;
            $out[] = $s;
        }
        $catalog = product_variant_type_catalog();
        $order = $catalog[$axis]['values'] ?? [];
        usort($out, static function (string $a, string $b) use ($order): int {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            $ia = $ia === false ? PHP_INT_MAX : $ia;
            $ib = $ib === false ? PHP_INT_MAX : $ib;

            return $ia <=> $ib ?: strcmp($a, $b);
        });

        return $out;
    }
}

if (!function_exists('normalize_product_variants_input')) {
    /** @param mixed $raw */
    function normalize_product_variants_input($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        if (isset($raw['axis'], $raw['colors'], $raw['sizes'])) {
            return product_variants_matrix_to_rows($raw);
        }
        $seen = [];
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = trim((string) ($row['variant_type'] ?? ''));
            $value = trim((string) ($row['variant_value'] ?? ''));
            $stock = max(0, (int) ($row['stock_quantity'] ?? 0));
            if ($type === '_meta') {
                if (preg_match('/^axis:(tamanho|numeracao)$/', $value)) {
                    $key = '_meta|' . $value;
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $out[] = [
                            'variant_type' => '_meta',
                            'variant_value' => $value,
                            'stock_quantity' => 0,
                        ];
                    }
                    continue;
                }
                if (preg_match('/^color_hex:(.+)\|(#?[0-9a-fA-F]{3,8})$/', $value, $m)) {
                    $colorName = trim($m[1]);
                    $hexNorm = product_variant_hex_normalize($m[2]);
                    if ($colorName !== '' && $hexNorm !== null && product_variant_matrix_color_is_valid($colorName)) {
                        $key = '_meta|color_hex:' . mb_strtolower($colorName);
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $out[] = [
                                'variant_type' => '_meta',
                                'variant_value' => 'color_hex:' . $colorName . '|' . $hexNorm,
                                'stock_quantity' => 0,
                            ];
                        }
                    }
                }
                continue;
            }
            if ($type === 'combinacao' && str_contains($value, '|')) {
                [$color, $size] = explode('|', $value, 2);
                if (!product_variant_matrix_color_is_valid(trim($color))) {
                    continue;
                }
                $key = 'combinacao|' . mb_strtolower($value);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = [
                    'variant_type' => 'combinacao',
                    'variant_value' => trim($color) . '|' . trim($size),
                    'stock_quantity' => $stock,
                ];
                continue;
            }
            if (!product_variant_type_is_valid($type) || !product_variant_value_is_valid($type, $value)) {
                continue;
            }
            $key = $type . '|' . mb_strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'variant_type' => $type,
                'variant_value' => $value,
                'stock_quantity' => $stock,
            ];
        }

        return $out;
    }
}

if (!function_exists('product_cover_image')) {
    function product_cover_image(array $product): ?array
    {
        $images = $product['images'] ?? [];
        if ($images === []) {
            return null;
        }
        foreach ($images as $img) {
            if (!empty($img['is_cover'])) {
                return $img;
            }
        }

        return $images[0];
    }
}

if (!function_exists('product_variants_grouped')) {
    /** @return list<array{type: string, label: string, items: list<array>}> */
    function product_variants_grouped(array $product): array
    {
        $matrix = $product['variants_matrix'] ?? product_variants_rows_to_matrix($product['variants'] ?? []);
        if ($matrix !== null) {
            return [];
        }
        $groups = [];
        foreach ($product['variants'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) ($row['variant_type'] ?? '');
            if ($type === '' || $type === '_meta' || $type === 'combinacao') {
                continue;
            }
            if (!isset($groups[$type])) {
                $groups[$type] = [
                    'type' => $type,
                    'label' => (string) ($row['variant_type_label'] ?? product_variant_type_label($type)),
                    'items' => [],
                ];
            }
            $groups[$type]['items'][] = $row;
        }

        return array_values($groups);
    }
}

if (!function_exists('product_display_name')) {
    /** Nome para exibição quando o cadastro está sem título. */
    function product_display_name(array $product): string
    {
        $name = trim((string) ($product['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $id = (int) ($product['id'] ?? 0);
        $matrix = $product['variants_matrix'] ?? product_variants_rows_to_matrix($product['variants'] ?? []);
        if (is_array($matrix) && !empty($matrix['colors'])) {
            $colors = [];
            foreach ($matrix['colors'] as $c) {
                $c = trim((string) $c);
                if ($c !== '') {
                    $colors[] = $c;
                }
            }
            if ($colors !== []) {
                $preview = array_slice($colors, 0, 4);
                $label = implode(', ', $preview);
                if (count($colors) > count($preview)) {
                    $label .= '…';
                }
                $axis = trim((string) ($matrix['axis_label'] ?? 'Tamanho'));

                return 'Sem nome — ' . $label . ' (' . $axis . ')' . ($id > 0 ? ' #' . $id : '');
            }
        }
        $desc = trim(strip_tags((string) ($product['description'] ?? '')));
        if ($desc !== '') {
            return mb_strlen($desc) > 80 ? mb_substr($desc, 0, 77) . '…' : $desc;
        }

        return 'Produto sem nome' . ($id > 0 ? ' #' . $id : '');
    }
}

/*
 * Estoque por variação: a lógica mudou-se para App\Domain\Product\VariantStock.
 * As funções abaixo ficam como atalho para as views e controllers que já as
 * chamavam.
 */

if (!function_exists('product_has_variants')) {
    function product_has_variants(array $product): bool
    {
        return \App\Domain\Product\VariantStock::hasVariants($product);
    }
}

if (!function_exists('product_variants_total_stock')) {
    /** @param list<array> $variants */
    function product_variants_total_stock(array $variants): int
    {
        return \App\Domain\Product\VariantStock::totalStock($variants);
    }
}

if (!function_exists('product_variant_stock_for_combination')) {
    function product_variant_stock_for_combination(array $product, string $color, string $size): int
    {
        $matrix = $product['variants_matrix'] ?? product_variants_rows_to_matrix($product['variants'] ?? []);
        if ($matrix === null) {
            return 0;
        }
        $size = trim($size);
        $color = trim($color);

        return max(0, (int) ($matrix['stock'][$size][$color] ?? 0));
    }
}

if (!function_exists('store_cart_line_storage_key')) {
    /** Chave no carrinho (session / sessionStorage): productId ou productId#variantKey */
    function store_cart_line_storage_key(int $productId, ?string $variantKey): string
    {
        $vk = $variantKey !== null ? trim($variantKey) : '';
        if ($vk === '') {
            return (string) $productId;
        }

        return $productId . '#' . $vk;
    }
}

if (!function_exists('store_cart_normalize_lines')) {
    /**
     * @param array<string|int, mixed> $cart
     *
     * @return list<array{product_id: int, quantity: int, variant_key: ?string}>
     */
    function store_cart_normalize_lines(array $cart): array
    {
        $lines = [];
        foreach ($cart as $key => $val) {
            if (is_array($val) && isset($val['product_id'])) {
                $qty = (int) ($val['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $vk = isset($val['variant_key']) ? trim((string) $val['variant_key']) : '';
                $lines[] = [
                    'product_id' => (int) $val['product_id'],
                    'quantity' => $qty,
                    'variant_key' => $vk !== '' ? $vk : null,
                ];
                continue;
            }
            $qty = is_numeric($val) ? (int) $val : 0;
            if ($qty <= 0) {
                continue;
            }
            $key = (string) $key;
            if (str_contains($key, '#')) {
                [$pid, $vk] = explode('#', $key, 2);
                $vk = trim($vk);
                $lines[] = [
                    'product_id' => (int) $pid,
                    'quantity' => $qty,
                    'variant_key' => $vk !== '' ? $vk : null,
                ];
            } else {
                $lines[] = [
                    'product_id' => (int) $key,
                    'quantity' => $qty,
                    'variant_key' => null,
                ];
            }
        }

        return $lines;
    }
}

if (!function_exists('product_sale_available_stock')) {
    function product_sale_available_stock(array $product, ?string $variantKey): int
    {
        return \App\Domain\Product\VariantStock::availableForSale($product, $variantKey);
    }
}

if (!function_exists('product_variant_key_label')) {
    function product_variant_key_label(array $product, ?string $variantKey): string
    {
        $vk = $variantKey !== null ? trim($variantKey) : '';
        if ($vk === '') {
            return '';
        }
        if (str_contains($vk, '|') && !str_contains($vk, ':')) {
            $parts = explode('|', $vk, 2);
            if (count($parts) === 2) {
                $axis = $product['variants_matrix']['axis_label'] ?? 'Tamanho';

                return $parts[0] . ' · ' . $axis . ' ' . $parts[1];
            }
        }
        if (str_contains($vk, ':')) {
            [$type, $val] = explode(':', $vk, 2);

            return product_variant_type_label($type) . ': ' . $val;
        }

        return $vk;
    }
}

if (!function_exists('product_apply_sale_stock_decrement')) {
    /**
     * Baixa o estoque da venda. Devolve false quando NAO foi possivel — saldo
     * insuficiente ou variacao inexistente. Quem chama deve tratar como falha
     * da venda e desfazer a transacao.
     */
    function product_apply_sale_stock_decrement(
        array $product,
        int $quantity,
        ?string $variantKey,
        \App\Repositories\ProductVariantRepository $variantRepo,
        \App\Repositories\ProductRepository $productRepo
    ): bool {
        return \App\Domain\Product\VariantStock::applySaleDecrement(
            $product,
            $quantity,
            $variantKey,
            $variantRepo,
            $productRepo
        );
    }
}
if (!function_exists('product_variant_default_color_hex_map')) {
    /** @return array<string, string> */
    function product_variant_default_color_hex_map(): array
    {
        return [
            'Preto' => '#171717',
            'Branco' => '#f8fafc',
            'Azul' => '#2563eb',
            'Vermelho' => '#dc2626',
            'Verde' => '#16a34a',
            'Cinza' => '#94a3b8',
            'Rosa' => '#ec4899',
            'Amarelo' => '#eab308',
        ];
    }
}

if (!function_exists('product_variant_hex_normalize')) {
    function product_variant_hex_normalize(string $hex): ?string
    {
        $hex = trim($hex);
        if ($hex === '') {
            return null;
        }
        if ($hex[0] !== '#') {
            $hex = '#' . $hex;
        }
        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
            return null;
        }
        if (strlen($hex) === 4) {
            $r = $hex[1];
            $g = $hex[2];
            $b = $hex[3];
            $hex = '#' . $r . $r . $g . $g . $b . $b;
        }

        return strtoupper($hex);
    }
}

if (!function_exists('product_variant_color_hex')) {
    function product_variant_color_hex(string $colorName): ?string
    {
        $map = product_variant_default_color_hex_map();

        return $map[trim($colorName)] ?? null;
    }
}

if (!function_exists('product_variant_matrix_color_hex')) {
    /** @param array<string, mixed> $matrix */
    function product_variant_matrix_color_hex(array $matrix, string $colorName): ?string
    {
        $colorName = trim($colorName);
        $meta = $matrix['color_meta'] ?? [];
        if (is_array($meta) && isset($meta[$colorName])) {
            $fromMeta = product_variant_hex_normalize((string) $meta[$colorName]);
            if ($fromMeta !== null) {
                return $fromMeta;
            }
        }

        return product_variant_color_hex($colorName);
    }
}

if (!function_exists('product_variant_matrix_color_meta_normalize')) {
    /**
     * @param mixed $raw
     * @param list<string> $colors
     * @return array<string, string>
     */
    function product_variant_matrix_color_meta_normalize($raw, array $colors): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $allowed = [];
        foreach ($colors as $c) {
            $c = trim((string) $c);
            if ($c !== '' && product_variant_matrix_color_is_valid($c)) {
                $allowed[$c] = true;
            }
        }
        $out = [];
        foreach ($raw as $name => $hex) {
            $name = trim((string) $name);
            if ($name === '' || !isset($allowed[$name])) {
                continue;
            }
            $hexNorm = product_variant_hex_normalize((string) $hex);
            if ($hexNorm !== null) {
                $out[$name] = $hexNorm;
            }
        }

        return $out;
    }
}

/** URL do ícone da loja na vitrine (aba e cabeçalho); sem imagem própria usa o ícone da plataforma. */
if (!function_exists('hex_color_is_dark')) {
    /**
     * A cor (#RRGGBB) é escura? Pela luminância relativa do WCAG 2.x:
     *
     *     L = 0,2126·R + 0,7152·G + 0,0722·B   (canais linearizados)
     *
     * Escura quando texto branco tem mais contraste sobre ela do que texto
     * preto — o limiar dessa troca é L ≈ 0,179.
     */
    function hex_color_is_dark(string $hex): bool
    {
        if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return false;
        }
        $lin = static function (string $h): float {
            $c = hexdec($h) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $lin($m[1]) + 0.7152 * $lin($m[2]) + 0.0722 * $lin($m[3]);

        return $l < 0.179;
    }
}

if (!function_exists('store_brand_icon_url')) {
    function store_brand_icon_url(?array $store): string
    {
        $path = isset($store['store_icon_path']) ? trim((string) $store['store_icon_path']) : '';
        if ($path === '' || strpos($path, '..') !== false) {
            return favicon_url();
        }
        $full = PLATAFORM_ROOT . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (!is_file($full)) {
            return favicon_url();
        }
        $v = (string) @filemtime($full);

        return base_url('uploads/' . str_replace('\\', '/', $path)) . '?v=' . rawurlencode($v !== '' && $v !== '0' ? $v : (string) time());
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = '') {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('logged_in')) {
    function logged_in(): bool {
        return !empty($_SESSION['logged_user_id']);
    }
}

if (!function_exists('logout')) {
    function logout(): void {
        unset(
            $_SESSION['logged_user_id'],
            $_SESSION['logged_store_id'],
            $_SESSION['user_id'],
            $_SESSION['store_slug'],
            // O carrinho é da pessoa, não do navegador: sem isto ele sobrevive
            // ao logout e aparece para quem usar a máquina em seguida.
            $_SESSION['cart']
        );
        // Novo id: o identificador usado enquanto havia sessão autenticada não
        // deve continuar válido. Mantém $_SESSION (as mensagens flash gravadas
        // logo depois desta chamada continuam funcionando) e apaga o registro antigo.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

if (!function_exists('login_user')) {
    /**
     * Marca a sessão como autenticada.
     *
     * O session_regenerate_id() é o que impede fixação de sessão: sem ele, um
     * id capturado antes do login continua valendo depois — quem plantou o
     * cookie passa a estar logado como a vítima.
     */
    function login_user(array $user): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        // Token novo a cada login: o que valia para o visitante anônimo não
        // deve continuar valendo para a sessão autenticada.
        unset($_SESSION['_csrf']);
        \App\Auth\Permissions::limparCache();
        $_SESSION['logged_user_id'] = (int) $user['id'];
        $_SESSION['user_id'] = (int) $user['id'];
    }
}

/** Salva um arquivo de upload de imagem de produto. Retorna path relativo (ex: products/abc.jpg) ou null. */
if (!function_exists('upload_product_image')) {
    function upload_product_image(array $file): ?string {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/pjpeg'];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        if (!$mime) {
            $mime = $file['type'] ?? '';
        }
        $name = $file['name'] ?? '';
        $ext = 'jpg';
        if (in_array($mime, $allowed, true)) {
            if ($mime === 'image/png') $ext = 'png';
            elseif ($mime === 'image/gif') $ext = 'gif';
            elseif ($mime === 'image/webp') $ext = 'webp';
        } elseif (preg_match('/\.(jpe?g|png|gif|webp)$/i', $name, $m)) {
            $ext = strtolower($m[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
        } else {
            return null;
        }
        $dir = PLATAFORM_ROOT . '/frontend/public/uploads/products';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $filename = 'p_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return null;
        }
        return 'products/' . $filename;
    }
}

/** Salva imagem a partir de data URL (data:image/jpeg;base64,...). Retorna path relativo ou null. */
if (!function_exists('save_product_image_from_base64')) {
    function save_product_image_from_base64(string $dataUrl): ?string {
        if (strpos($dataUrl, 'data:image/') !== 0 || strpos($dataUrl, ';base64,') === false) {
            return null;
        }
        $parts = explode(';base64,', $dataUrl, 2);
        $header = $parts[0];
        $data = base64_decode($parts[1] ?? '', true);
        if ($data === false || $data === '') {
            return null;
        }
        $ext = 'jpg';
        if (strpos($header, 'image/png') !== false) $ext = 'png';
        elseif (strpos($header, 'image/gif') !== false) $ext = 'gif';
        elseif (strpos($header, 'image/webp') !== false) $ext = 'webp';
        $dir = PLATAFORM_ROOT . '/frontend/public/uploads/products';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $filename = 'p_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return null;
        }
        if (file_put_contents($path, $data) === false) {
            return null;
        }
        return 'products/' . $filename;
    }
}

/** Salva banner da vitrine (uma imagem por loja). Retorna path relativo (ex.: store-banners/1/banner_xxx.jpg) ou null. */
if (!function_exists('upload_store_banner')) {
    function upload_store_banner(int $storeId, array $file): ?string
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/pjpeg'];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        if ($mime === '') {
            $mime = $file['type'] ?? '';
        }
        $name = $file['name'] ?? '';
        $ext = 'jpg';
        if (in_array($mime, $allowed, true)) {
            if ($mime === 'image/png') {
                $ext = 'png';
            } elseif ($mime === 'image/gif') {
                $ext = 'gif';
            } elseif ($mime === 'image/webp') {
                $ext = 'webp';
            }
        } elseif (preg_match('/\.(jpe?g|png|gif|webp)$/i', $name, $m)) {
            $ext = strtolower($m[1]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
        } else {
            return null;
        }
        $storeId = max(1, $storeId);
        $dir = PLATAFORM_ROOT . '/frontend/public/uploads/store-banners/' . $storeId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return null;
        }
        $filename = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return null;
        }
        return 'store-banners/' . $storeId . '/' . $filename;
    }
}

/** Remove arquivo de banner salvo em uploads/ (path relativo guardado em stores.banner_path). */
if (!function_exists('delete_store_banner_file')) {
    function delete_store_banner_file(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        if (strpos($relativePath, '..') !== false) {
            return;
        }
        $baseDir = PLATAFORM_ROOT . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        $path = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** Imagem pequena da loja (aba do navegador e marca ao lado do nome). Path: store-icons/{id}/loja_*.ext */
if (!function_exists('upload_store_icon')) {
    function upload_store_icon(int $storeId, array $file): ?string
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }
        $allowed = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/pjpeg',
            'image/x-icon', 'image/vnd.microsoft.icon',
        ];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        if ($mime === '') {
            $mime = $file['type'] ?? '';
        }
        $name = $file['name'] ?? '';
        $ext = 'png';
        if (in_array($mime, $allowed, true)) {
            if ($mime === 'image/jpeg' || $mime === 'image/pjpeg') {
                $ext = 'jpg';
            } elseif ($mime === 'image/png') {
                $ext = 'png';
            } elseif ($mime === 'image/gif') {
                $ext = 'gif';
            } elseif ($mime === 'image/webp') {
                $ext = 'webp';
            } elseif ($mime === 'image/x-icon' || $mime === 'image/vnd.microsoft.icon') {
                $ext = 'ico';
            }
        } elseif (preg_match('/\.(jpe?g|png|gif|webp|ico)$/i', $name, $m)) {
            $ext = strtolower($m[1]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
        } else {
            return null;
        }
        $storeId = max(1, $storeId);
        $dir = PLATAFORM_ROOT . '/frontend/public/uploads/store-icons/' . $storeId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return null;
        }
        $filename = 'loja_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return null;
        }
        return 'store-icons/' . $storeId . '/' . $filename;
    }
}

if (!function_exists('delete_store_icon_file')) {
    function delete_store_icon_file(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        if (strpos($relativePath, '..') !== false) {
            return;
        }
        $baseDir = PLATAFORM_ROOT . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        $path = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/*
 * As três funções abaixo eram a autorização do sistema: cada controller
 * chamava uma delas à mão, e quem esquecesse deixava a rota aberta. Agora são
 * invólucros finos sobre App\Auth\Permissions, mantidos porque as views e os
 * controllers ainda as usam. A decisão que vale é a do Guard, aplicada antes de
 * qualquer controller rodar.
 */

if (!function_exists('is_gerente_store')) {
    /** O usuário logado é gerente desta loja? Gerente de outra loja: false. */
    function is_gerente_store(int $storeId): bool {
        return \App\Auth\Permissions::cargoNaLoja((int) $storeId) === \App\Auth\Permissions::GERENTE;
    }
}

if (!function_exists('can_access_store_panel')) {
    /** O usuário logado pode abrir o painel desta loja (gerente ou funcionário)? */
    function can_access_store_panel(int $storeId): bool {
        return \App\Auth\Permissions::can('store.panel.view', (int) $storeId);
    }
}

if (!function_exists('is_funcionario_panel_readonly')) {
    /**
     * Funcionário desta loja — ou seja, tem painel mas não gerencia catálogo.
     * As views usam isto para esconder botões; o bloqueio real é da matriz.
     */
    function is_funcionario_panel_readonly(int $storeId): bool {
        return \App\Auth\Permissions::cargoNaLoja((int) $storeId) === \App\Auth\Permissions::FUNCIONARIO;
    }
}

/*
 * Cartão: a lógica mudou-se para App\Payment\CardValidator. As funções abaixo
 * ficam como atalho para o código que já as chamava.
 */

if (!function_exists('card_digits_only')) {
    function card_digits_only(string $value): string {
        return \App\Payment\CardValidator::digitsOnly($value);
    }
}

if (!function_exists('card_luhn_valid')) {
    function card_luhn_valid(string $digits): bool {
        return \App\Payment\CardValidator::luhnValid($digits);
    }
}

if (!function_exists('card_detect_brand')) {
    function card_detect_brand(string $digits): string {
        return \App\Payment\CardValidator::detectBrand($digits);
    }
}

if (!function_exists('card_validate_checkout_input')) {
    /**
     * @param array<string, mixed>|null $card
     * @return array{holder: string, last4: string, brand: string}|null
     */
    function card_validate_checkout_input(?array $card): ?array {
        return \App\Payment\CardValidator::validate($card);
    }
}

/*
 * Preço efetivo de venda: o promocional, quando o gerente definiu um válido
 * (maior que zero e menor que o preço normal); senão, o preço normal. Toda
 * conta de venda (carrinho, pedido, PDV, vitrine) passa por aqui.
 */
if (!function_exists('product_effective_price')) {
    function product_effective_price(array $product): float
    {
        $venda = (float) ($product['sale_price'] ?? 0);
        $promo = isset($product['promo_price']) && $product['promo_price'] !== null ? (float) $product['promo_price'] : 0.0;

        return ($promo > 0 && $promo < $venda) ? $promo : $venda;
    }
}

if (!function_exists('product_on_sale')) {
    function product_on_sale(array $product): bool
    {
        return product_effective_price($product) < (float) ($product['sale_price'] ?? 0);
    }
}

if (!function_exists('product_discount_percent')) {
    /** Percentual de desconto da promoção, arredondado (ex.: 25 para "-25%"). */
    function product_discount_percent(array $product): int
    {
        $venda = (float) ($product['sale_price'] ?? 0);
        if ($venda <= 0 || !product_on_sale($product)) {
            return 0;
        }

        return (int) round((1 - product_effective_price($product) / $venda) * 100);
    }
}

if (!function_exists('ui_icon')) {
    /**
     * Ícone SVG em linha (traço, 24×24, cor do texto). Um conjunto só para o
     * site inteiro, no lugar de emojis e símbolos de texto.
     */
    function ui_icon(string $name, int $size = 18, string $class = ''): string
    {
        static $paths = [
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
            'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
            'cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
            'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/>',
            'arrow-left' => '<path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>',
            'check' => '<path d="M20 6L9 17l-5-5"/>',
            'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
            'tag' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/>',
            'history' => '<path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/>',
            'percent' => '<path d="M19 5L5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
            'qr' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>',
            'card' => '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>',
            'truck' => '<rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
            'store' => '<path d="M3 9l1-5h16l1 5"/><path d="M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0V9z"/><path d="M5 13v8h14v-8"/>',
            'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
            'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
            'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'trash' => '<path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
            'package' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'whatsapp' => '<path d="M3.5 20.5l1.3-4.2A8.5 8.5 0 1 1 8 19.6z"/><path d="M9.2 8.6c.2-.4.5-.4.8-.4h.5c.2 0 .4.1.5.4l.6 1.5c.1.2 0 .5-.1.6l-.5.6c.6 1.1 1.5 2 2.6 2.6l.6-.5c.2-.2.4-.2.6-.1l1.5.6c.3.1.4.3.4.5v.5c0 .3 0 .6-.4.8-.6.4-1.6.6-2.7.1a9 9 0 0 1-4.3-4.3c-.5-1.1-.3-2.1.1-2.7z"/>',
            'heart' => '<path d="M12 21s-7.5-4.6-9.6-9.2C1 8.6 2.9 5 6.4 5c2.1 0 3.6 1.2 4.6 2.6C12 6.2 13.5 5 15.6 5 19.1 5 21 8.6 19.6 11.8 17.5 16.4 12 21 12 21z"/>',
        ];
        $inner = $paths[$name] ?? $paths['alert'];
        $cls = trim('ui-icon ' . $class);

        return '<svg class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
    }
}

if (!function_exists('order_payment_view')) {
    /**
     * Situação de pagamento de um pedido para o cliente: status, prazo e, se
     * houver PIX em aberto, o copia-e-cola e a imagem do QR para retomar.
     */
    function order_payment_view(array $order, int $storeId): array
    {
        $payments = (new \App\Repositories\PaymentRepository())->findByOrder((int) $order['id']);
        $aberto = null;
        $confirmado = null;
        foreach ($payments as $p) {
            if ($p['status'] === 'pendente') {
                $aberto = $p;
            }
            if ($p['status'] === 'confirmado') {
                $confirmado = $p;
            }
        }
        $pix = null;
        if ($aberto && $aberto['method'] === 'pix' && $order['status'] === 'pendente') {
            $svc = new \App\Services\PixService(new \App\Repositories\StorePixConfigRepository());
            $copia = $svc->buildCopyPaste($storeId, (float) $aberto['amount'], 'Pedido ' . $order['id']);
            $img = (string) ($aberto['pix_qr_code'] ?? '');
            if ($img === '' && $copia) {
                $img = \App\Support\QrCode::toDataUri($copia);
            }
            $pix = ['copia_cola' => $copia, 'qr_code' => $img !== '' ? $img : null];
        }
        $p = $confirmado ?? $aberto;

        return [
            'order' => [
                'id' => (int) $order['id'],
                'status' => $order['status'],
                'total' => (float) $order['total'],
                'expires_at' => $order['expires_at'] ?? null,
                'delivery_type' => $order['delivery_type'] ?? 'retirada',
            ],
            'payment' => $p ? [
                'id' => (int) $p['id'],
                'method' => $p['method'],
                'status' => $p['status'],
                'amount' => (float) $p['amount'],
                'installments' => (int) ($p['installments'] ?? 1),
            ] : null,
            'pix' => $pix,
            'server_now' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('storefront_sort_options')) {
    /** Ordenações da vitrine (chave da URL => rótulo). */
    function storefront_sort_options(): array
    {
        return [
            'mais-vendidos' => 'Mais vendidos',
            'novidades' => 'Novidades',
            'promocoes' => 'Promoções',
            'menor-preco' => 'Menor preço',
            'maior-preco' => 'Maior preço',
            'mais-avaliados' => 'Mais bem avaliados',
            'a-z' => 'Nome (A–Z)',
        ];
    }
}

if (!function_exists('store_whatsapp_url')) {
    /**
     * Link wa.me do número da loja, com mensagem opcional. Número brasileiro
     * sem DDI (10 ou 11 dígitos) ganha o 55. Sem número válido: null.
     */
    function store_whatsapp_url(array $store, ?string $mensagem = null): ?string
    {
        $d = preg_replace('/\D/', '', (string) ($store['phone'] ?? '')) ?? '';
        if (strlen($d) === 10 || strlen($d) === 11) {
            $d = '55' . $d;
        }
        if (strlen($d) < 12 || strlen($d) > 13) {
            return null;
        }
        $url = 'https://wa.me/' . $d;

        return $mensagem !== null && $mensagem !== '' ? $url . '?text=' . rawurlencode($mensagem) : $url;
    }
}

if (!function_exists('storefront_review_summary')) {
    /**
     * Média e total de avaliações de cada produto da loja. Cache por
     * requisição: a vitrine chama uma vez por card.
     *
     * @return array<int, array{media: float, total: int}>
     */
    function storefront_review_summary(int $storeId): array
    {
        static $cache = [];
        if (!isset($cache[$storeId])) {
            try {
                $cache[$storeId] = (new \App\Repositories\ReviewRepository())->summaryByStore($storeId);
            } catch (\Throwable $e) {
                $cache[$storeId] = []; // banco sem a migration 0023: vitrine segue sem estrelas
            }
        }

        return $cache[$storeId];
    }
}

if (!function_exists('storefront_favorite_ids')) {
    /**
     * Produtos que o cliente logado favoritou nesta loja (vazio sem login).
     *
     * @return array<int, true>
     */
    function storefront_favorite_ids(int $storeId): array
    {
        static $cache = [];
        $userId = logged_in() ? (int) ($_SESSION['logged_user_id'] ?? 0) : 0;
        if ($userId < 1) {
            return [];
        }
        $chave = $userId . ':' . $storeId;
        if (!isset($cache[$chave])) {
            try {
                $cache[$chave] = array_fill_keys((new \App\Repositories\FavoriteRepository())->productIds($userId, $storeId), true);
            } catch (\Throwable $e) {
                $cache[$chave] = [];
            }
        }

        return $cache[$chave];
    }
}

if (!function_exists('ui_stars')) {
    /** Estrelas de 0 a 5 (com meia estrela), só visuais; o texto acessível vai no rótulo. */
    function ui_stars(float $nota, int $tamanho = 14): string
    {
        $nota = max(0.0, min(5.0, $nota));
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $preenchido = $nota >= $i ? 'full' : ($nota >= $i - 0.5 ? 'half' : 'empty');
            // A meia estrela usa o gradiente #ui-star-half, definido uma vez no layout da loja.
            $html .= '<svg class="ui-star ui-star--' . $preenchido . '" width="' . $tamanho . '" height="' . $tamanho . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
                . '<path d="M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96L12 2.5z"/></svg>';
        }

        return '<span class="ui-stars" aria-hidden="true">' . $html . '</span>';
    }
}

if (!function_exists('storefront_current_sort')) {
    function storefront_current_sort(): string
    {
        $o = (string) ($_GET['ordem'] ?? '');

        return array_key_exists($o, storefront_sort_options()) ? $o : 'mais-vendidos';
    }
}

if (!function_exists('storefront_products')) {
    /**
     * Prepara a lista da vitrine: desconta as reservas (o que está sendo pago
     * por PIX não aparece como disponível) e ordena. Antes a vitrine era
     * sempre alfabética.
     */
    function storefront_products(array $products, int $storeId, ?string $ordem = null): array
    {
        $ordem = $ordem ?? storefront_current_sort();
        $products = array_map([\App\Services\StockReservation::class, 'applyTo'], $products);
        $vendas = $ordem === 'mais-vendidos' ? (new \App\Repositories\ProductRepository())->salesCountByProduct($storeId) : [];
        $notas = $ordem === 'mais-avaliados' ? storefront_review_summary($storeId) : [];
        usort($products, static function (array $a, array $b) use ($ordem, $vendas, $notas): int {
            // Esgotado sempre por último, em qualquer ordenação.
            $ea = (int) ($a['stock_quantity'] ?? 0) > 0 ? 0 : 1;
            $eb = (int) ($b['stock_quantity'] ?? 0) > 0 ? 0 : 1;
            if ($ea !== $eb) {
                return $ea <=> $eb;
            }
            switch ($ordem) {
                case 'novidades':
                    $c = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')) ?: ((int) $b['id'] <=> (int) $a['id']);
                    break;
                case 'promocoes':
                    $c = product_discount_percent($b) <=> product_discount_percent($a);
                    break;
                case 'menor-preco':
                    $c = product_effective_price($a) <=> product_effective_price($b);
                    break;
                case 'maior-preco':
                    $c = product_effective_price($b) <=> product_effective_price($a);
                    break;
                case 'mais-avaliados':
                    // Média primeiro; com a mesma média, quem tem mais avaliações.
                    // Sem avaliação fica depois de qualquer avaliado.
                    $na = $notas[(int) $a['id']] ?? ['media' => -1.0, 'total' => 0];
                    $nb = $notas[(int) $b['id']] ?? ['media' => -1.0, 'total' => 0];
                    $c = ($nb['media'] <=> $na['media']) ?: ($nb['total'] <=> $na['total']);
                    break;
                case 'a-z':
                    $c = 0;
                    break;
                default:
                    $c = ($vendas[(int) $b['id']] ?? 0) <=> ($vendas[(int) $a['id']] ?? 0);
            }

            return $c !== 0 ? $c : strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $products;
    }
}
