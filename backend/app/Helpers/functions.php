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

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $path = ltrim($path, '/');
        $base = '';
        if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
            $scriptDir = dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']));
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
    function asset(string $path): string {
        return base_url('assets/' . ltrim($path, '/'));
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

if (!function_exists('favicon_url')) {
    /** URL do favicon com versão (mtime) para contornar cache agressivo do navegador. */
    function favicon_url(): string
    {
        $file = PLATAFORM_ROOT . '/frontend/public/assets/favicon.ico';
        $v = is_readable($file) ? (string) @filemtime($file) : '1';

        return asset('favicon.ico') . '?v=' . rawurlencode($v);
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
            'camisa' => 'feminino',
            'calca' => 'masculino',
            'legging' => 'feminino',
            'shorts' => 'feminino',
            'garrafa' => 'suplementos',
            'suplemento' => 'suplementos',
            'mochila' => 'acessorios',
            'sacola' => 'acessorios',
            'presente' => 'ofertas',
            'relogio' => 'premium',
            'bone' => 'acessorios',
            'luvas' => 'treino',
            'corda' => 'treino',
            'bike' => 'corrida',
            'carrinho' => 'ofertas',
        ];

        return $legacy[$safe] ?? $safe;
    }
}

/** Catálogo de ícones para categorias da vitrine (Iconify API, ícones brancos para círculo azul). */
if (!function_exists('vitrine_category_icon_catalog')) {
    function vitrine_category_icon_catalog(): array
    {
        return [
            [
                'key' => 'feminino',
                'label' => 'Feminino / Moda fitness',
                'url' => 'https://api.iconify.design/lucide:shirt.svg?color=white&width=34',
            ],
            [
                'key' => 'masculino',
                'label' => 'Masculino',
                'url' => 'https://api.iconify.design/lucide:user-round.svg?color=white&width=34',
            ],
            [
                'key' => 'masculino_simbolo',
                'label' => 'Masculino símbolo',
                'url' => asset('images/category-icons/masculino_simbolo.svg'),
            ],
            [
                'key' => 'treino',
                'label' => 'Treino / Academia',
                'url' => 'https://api.iconify.design/lucide:dumbbell.svg?color=white&width=34',
            ],
            [
                'key' => 'corrida',
                'label' => 'Corrida',
                'url' => 'https://api.iconify.design/lucide:footprints.svg?color=white&width=34',
            ],
            [
                'key' => 'caminhada',
                'label' => 'Caminhada',
                'url' => asset('images/category-icons/caminhada.svg'),
            ],
            [
                'key' => 'vestido',
                'label' => 'Vestido',
                'url' => asset('images/category-icons/vestido.svg'),
            ],
            [
                'key' => 'casaco',
                'label' => 'Casaco',
                'url' => asset('images/category-icons/casaco.svg'),
            ],
            [
                'key' => 'tenis',
                'label' => 'Ténis',
                'url' => 'https://api.iconify.design/game-icons:running-shoe.svg?color=white&width=34',
            ],
            [
                'key' => 'acessorios',
                'label' => 'Acessórios',
                'url' => 'https://api.iconify.design/lucide:backpack.svg?color=white&width=34',
            ],
            [
                'key' => 'yoga',
                'label' => 'Yoga / Pilates',
                'url' => 'https://api.iconify.design/lucide:flower.svg?color=white&width=34',
            ],
            [
                'key' => 'suplementos',
                'label' => 'Suplementos / Shaker',
                'url' => 'https://api.iconify.design/mdi:bottle-soda-classic-outline.svg?color=white&width=34',
            ],
            [
                'key' => 'ofertas',
                'label' => 'Ofertas',
                'url' => 'https://api.iconify.design/lucide:badge-percent.svg?color=white&width=34',
            ],
            [
                'key' => 'lancamentos',
                'label' => 'Lançamentos',
                'url' => 'https://api.iconify.design/lucide:sparkles.svg?color=white&width=34',
            ],
            [
                'key' => 'premium',
                'label' => 'Premium',
                'url' => 'https://api.iconify.design/lucide:gem.svg?color=white&width=34',
            ],
            [
                'key' => 'plus_size',
                'label' => 'Plus Size',
                'url' => 'https://api.iconify.design/lucide:heart.svg?color=white&width=34',
            ],
            [
                'key' => 'nike',
                'label' => 'Nike',
                'url' => asset('images/category-icons/nike.svg'),
            ],
            [
                'key' => 'adidas',
                'label' => 'Adidas',
                'url' => asset('images/category-icons/adidas.svg'),
            ],
            [
                'key' => 'puma',
                'label' => 'Puma',
                'url' => asset('images/category-icons/puma.svg'),
            ],
            [
                'key' => 'new_balance',
                'label' => 'New Balance',
                'url' => asset('images/category-icons/new_balance.svg'),
            ],
            [
                'key' => 'fila',
                'label' => 'Fila',
                'url' => asset('images/category-icons/fila.svg'),
            ],
        ];
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

        return vitrine_category_icon_catalog()[5]['url'];
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

if (!function_exists('product_has_variants')) {
    function product_has_variants(array $product): bool
    {
        if (!empty($product['variants_matrix']) && is_array($product['variants_matrix'])) {
            return !empty($product['variants_matrix']['colors']);
        }
        $matrix = product_variants_rows_to_matrix($product['variants'] ?? []);

        return $matrix !== null || (!empty($product['variants']) && is_array($product['variants']));
    }
}

if (!function_exists('product_variants_total_stock')) {
    /** @param list<array> $variants */
    function product_variants_total_stock(array $variants): int
    {
        $sum = 0;
        foreach ($variants as $row) {
            if (!is_array($row) || ($row['variant_type'] ?? '') === '_meta') {
                continue;
            }
            $sum += max(0, (int) ($row['stock_quantity'] ?? 0));
        }

        return $sum;
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
        if (!product_has_variants($product)) {
            return max(0, (int) ($product['stock_quantity'] ?? 0));
        }
        $vk = $variantKey !== null ? trim($variantKey) : '';
        if ($vk === '') {
            return 0;
        }
        if (str_contains($vk, '|') && !str_contains($vk, ':')) {
            $parts = explode('|', $vk, 2);
            if (count($parts) === 2) {
                return product_variant_stock_for_combination($product, $parts[0], $parts[1]);
            }
        }
        if (str_contains($vk, ':')) {
            [$type, $val] = explode(':', $vk, 2);
            foreach ($product['variants'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (($row['variant_type'] ?? '') === $type && ($row['variant_value'] ?? '') === $val) {
                    return max(0, (int) ($row['stock_quantity'] ?? 0));
                }
            }
        }
        foreach ($product['variants'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) ($row['variant_type'] ?? '');
            if ($type === '' || $type === '_meta') {
                continue;
            }
            if ((string) ($row['variant_value'] ?? '') === $vk) {
                return max(0, (int) ($row['stock_quantity'] ?? 0));
            }
        }

        return 0;
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
     * Baixa estoque da variação vendida e recalcula o total do produto.
     *
     * Devolve false quando a baixa NÃO pôde ser feita — saldo insuficiente ou
     * variação que não existe. Antes esta função devolvia void e seguia adiante
     * de qualquer jeito: o estoque era cortado em zero e o pedido saía como se
     * a mercadoria existisse. Quem chama deve tratar o false como falha da
     * venda e desfazer a transação.
     */
    function product_apply_sale_stock_decrement(
        array $product,
        int $quantity,
        ?string $variantKey,
        \App\Repositories\ProductVariantRepository $variantRepo,
        \App\Repositories\ProductRepository $productRepo
    ): bool {
        $productId = (int) ($product['id'] ?? 0);
        if ($productId < 1 || $quantity < 1) {
            return false;
        }
        $qty = $quantity;

        if (!product_has_variants($product)) {
            return $productRepo->decrementStock($productId, $qty);
        }

        $vk = $variantKey !== null ? trim($variantKey) : '';
        if ($vk === '') {
            return false;
        }

        $type = null;
        $value = null;
        if (str_contains($vk, '|') && !str_contains($vk, ':')) {
            $type = 'combinacao';
            $value = $vk;
        } elseif (str_contains($vk, ':')) {
            [$type, $value] = explode(':', $vk, 2);
            $type = trim($type);
            $value = trim($value);
        } else {
            foreach ($product['variants'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rowType = (string) ($row['variant_type'] ?? '');
                if ($rowType === '' || $rowType === '_meta') {
                    continue;
                }
                if ((string) ($row['variant_value'] ?? '') === $vk) {
                    $type = $rowType;
                    $value = $vk;
                    break;
                }
            }
        }

        if ($type === null || $value === null || $type === '' || $value === '') {
            // Chave de variação que não casa com nenhuma linha: não dá para
            // saber o que baixar, então a venda não pode prosseguir.
            return false;
        }
        if (!$variantRepo->decrementStock($productId, $type, $value, $qty)) {
            return false;
        }

        // products.stock_quantity é o total derivado das variações — recalculado
        // a partir do banco, já com a baixa aplicada.
        $productRepo->updateStock($productId, $variantRepo->totalStock($productId));

        return true;
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
        $storeId = $user['store_id'] ?? null;
        $_SESSION['logged_user_id'] = (int) $user['id'];
        $_SESSION['logged_store_id'] = $storeId !== null && $storeId !== '' ? (int) $storeId : null;
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

if (!function_exists('is_gerente_store')) {
    /** Verifica se o usuário logado é gerente da loja informada. Gerente de outra loja retorna false. */
    function is_gerente_store(int $storeId): bool {
        $storeId = (int) $storeId;
        $userId = $_SESSION['logged_user_id'] ?? null;
        if (!$userId) {
            return false;
        }
        $userRepo = new \App\Repositories\UserRepository();
        $user = $userRepo->find((int) $userId);
        if (!$user || $user['store_id'] === null || $user['store_id'] === '') {
            return false;
        }
        $userStoreId = (int) $user['store_id'];
        if ($userStoreId !== $storeId) {
            return false;
        }
        return ($user['user_type'] ?? '') === 'gerente';
    }
}

if (!function_exists('can_access_store_panel')) {
    /**
     * Verifica se o usuário logado pode acessar o painel desta loja:
     * deve ser gerente OU funcionário e o store_id do usuário deve ser o da loja informada.
     */
    function can_access_store_panel(int $storeId): bool {
        $storeId = (int) $storeId;
        $userId = $_SESSION['logged_user_id'] ?? null;
        if (!$userId) {
            return false;
        }
        $userRepo = new \App\Repositories\UserRepository();
        $user = $userRepo->find((int) $userId);
        if (!$user || $user['store_id'] === null || $user['store_id'] === '') {
            return false;
        }
        $userStoreId = (int) $user['store_id'];
        if ($userStoreId !== $storeId) {
            return false;
        }
        $type = strtolower((string) ($user['user_type'] ?? ''));
        return $type === 'gerente' || $type === 'funcionario';
    }
}

if (!function_exists('is_funcionario_panel_readonly')) {
    /** Funcionário da loja: acesso ao painel só leitura (gerente tem acesso total). */
    function is_funcionario_panel_readonly(int $storeId): bool {
        if (is_gerente_store((int) $storeId)) {
            return false;
        }
        $userId = $_SESSION['logged_user_id'] ?? null;
        if (!$userId) {
            return false;
        }
        $user = (new \App\Repositories\UserRepository())->find((int) $userId);
        if (!$user || (int) ($user['store_id'] ?? 0) !== (int) $storeId) {
            return false;
        }
        return strtolower((string) ($user['user_type'] ?? '')) === 'funcionario';
    }
}

if (!function_exists('card_digits_only')) {
    function card_digits_only(string $value): string {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}

if (!function_exists('card_luhn_valid')) {
    function card_luhn_valid(string $digits): bool {
        $len = strlen($digits);
        if ($len < 13 || $len > 19) {
            return false;
        }
        $sum = 0;
        $parity = $len % 2;
        for ($i = 0; $i < $len; $i++) {
            $d = (int) $digits[$i];
            if ($i % 2 === $parity) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }
        return $sum % 10 === 0;
    }
}

if (!function_exists('card_detect_brand')) {
    function card_detect_brand(string $digits): string {
        if (preg_match('/^4/', $digits)) {
            return 'visa';
        }
        if (preg_match('/^(5[1-5]|2[2-7])/', $digits)) {
            return 'mastercard';
        }
        if (preg_match('/^3[47]/', $digits)) {
            return 'amex';
        }
        if (preg_match('/^(636368|438935|504175|451416|636297|5067|4576|4011)/', $digits)) {
            return 'elo';
        }
        if (preg_match('/^(606282|3841)/', $digits)) {
            return 'hipercard';
        }
        return 'card';
    }
}

if (!function_exists('card_validate_checkout_input')) {
    /**
     * @param array<string, mixed>|null $card
     * @return array{holder: string, last4: string, brand: string}|null
     */
    function card_validate_checkout_input(?array $card): ?array {
        if (!$card || !is_array($card)) {
            return null;
        }
        $holder = trim((string) ($card['holder'] ?? ''));
        $number = card_digits_only((string) ($card['number'] ?? ''));
        $expiry = trim((string) ($card['expiry'] ?? ''));
        $cvv = card_digits_only((string) ($card['cvv'] ?? ''));

        if (strlen($holder) < 3) {
            throw new \InvalidArgumentException('Informe o nome impresso no cartão.');
        }
        if (!card_luhn_valid($number)) {
            throw new \InvalidArgumentException('Número do cartão inválido.');
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $m)) {
            throw new \InvalidArgumentException('Validade inválida. Use MM/AA.');
        }
        $month = (int) $m[1];
        $year = 2000 + (int) $m[2];
        $now = new \DateTimeImmutable('first day of this month');
        $exp = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month));
        if (!$exp || $exp < $now) {
            throw new \InvalidArgumentException('Cartão expirado.');
        }
        $brand = card_detect_brand($number);
        $cvvLen = $brand === 'amex' ? 4 : 3;
        if (strlen($cvv) !== $cvvLen) {
            throw new \InvalidArgumentException('CVV inválido.');
        }

        return [
            'holder' => $holder,
            'last4'  => substr($number, -4),
            'brand'  => $brand,
        ];
    }
}
