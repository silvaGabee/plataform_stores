<?php

namespace App;

class Router
{
    private string $basePath;
    private string $method;
    private string $path;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        $this->path = $uri === '' ? '/' : '/' . trim($uri, '/');
        if ($this->basePath !== '' && strpos($this->path, $this->basePath) === 0) {
            $this->path = substr($this->path, strlen($this->basePath)) ?: '/';
        }
    }

    public function match(string $pattern): ?array
    {
        $pattern = $this->basePath === '' ? $pattern : preg_replace('#^' . preg_quote($this->basePath, '#') . '#', '', $pattern);
        $parts = explode(' ', $pattern, 2);
        $method = $parts[0];
        $route = $parts[1] ?? '/';
        if ($method !== $this->method) return null;
        $regex = preg_quote($route, '#');
        // Aceita {slug}, {id} e também {storeId}: o padrão anterior só casava
        // nomes em minúsculas, então um parâmetro em camelCase fazia a rota
        // nunca corresponder — falha silenciosa, sem erro nenhum.
        $regex = preg_replace('#\\\\\\{[A-Za-z_][A-Za-z0-9_]*\\\\}#', '([^/]+)', $regex);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $this->path, $m)) {
            array_shift($m);
            return $m;
        }
        return null;
    }

    /**
     * Nomes dos parâmetros do padrão, na ordem em que aparecem.
     * 'POST /api/loja/{slug}/products/{id}' => ['slug', 'id']
     *
     * @return list<string>
     */
    public static function paramNames(string $pattern): array
    {
        return preg_match_all('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', $pattern, $m) ? $m[1] : [];
    }

    /**
     * Junta nomes e valores capturados.
     *
     * @param list<string> $params
     * @return array<string, string>
     */
    public static function namedParams(string $pattern, array $params): array
    {
        $nomes = self::paramNames($pattern);
        $out = [];
        foreach ($nomes as $i => $nome) {
            if (array_key_exists($i, $params)) {
                $out[$nome] = (string) $params[$i];
            }
        }

        return $out;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
