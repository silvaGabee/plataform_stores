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

if (!function_exists('favicon_url')) {
    /** URL do favicon com versão (mtime) para contornar cache agressivo do navegador. */
    function favicon_url(): string
    {
        $file = PLATAFORM_ROOT . '/frontend/public/assets/favicon.ico';
        $v = is_readable($file) ? (string) @filemtime($file) : '1';

        return asset('favicon.ico') . '?v=' . rawurlencode($v);
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
        unset($_SESSION['logged_user_id'], $_SESSION['logged_store_id'], $_SESSION['user_id']);
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
