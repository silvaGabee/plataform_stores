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
        $regex = preg_replace('#\\\\\\{[a-z]+\\\\}#', '([^/]+)', $regex);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $this->path, $m)) {
            array_shift($m);
            return $m;
        }
        return null;
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
