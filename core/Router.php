<?php
/**
 * URL-Routing
 */

namespace Core;

class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private string $basePath = '';

    /**
     * Basis-Pfad setzen
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = rtrim($path, '/');
    }

    /**
     * Route hinzufuegen
     */
    public function add(string $method, string $path, callable $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    /**
     * GET Route
     */
    public function get(string $path, callable $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    /**
     * POST Route
     */
    public function post(string $path, callable $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    /**
     * PUT Route
     */
    public function put(string $path, callable $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    /**
     * DELETE Route
     */
    public function delete(string $path, callable $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Globale Middleware hinzufuegen
     */
    public function middleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Route verarbeiten
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getUri();

        // Globale Middlewares ausfuehren
        foreach ($this->middlewares as $middleware) {
            $result = $middleware();
            if ($result === false) {
                return;
            }
        }

        // Route suchen
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            $params = $this->matchRoute($route['path'], $uri);
            if ($params !== false) {
                // Route-spezifische Middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $result = $middleware();
                    if ($result === false) {
                        return;
                    }
                }

                // Handler ausfuehren
                call_user_func_array($route['handler'], $params);
                return;
            }
        }

        // 404 - Route nicht gefunden
        Response::error('Seite nicht gefunden', 404);
    }

    /**
     * URI ohne Query-String
     */
    private function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rawurldecode($uri);

        // Basis-Pfad entfernen
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * Route matchen
     */
    private function matchRoute(string $pattern, string $uri): array|false
    {
        // Exakte Uebereinstimmung
        if ($pattern === $uri) {
            return [];
        }

        // Pattern mit Platzhaltern
        $patternParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        if (count($patternParts) !== count($uriParts)) {
            return false;
        }

        $params = [];
        foreach ($patternParts as $i => $part) {
            // Parameter {name}
            if (preg_match('/^\{([a-zA-Z_]+)\}$/', $part, $matches)) {
                $params[$matches[1]] = $uriParts[$i];
            }
            // Optionaler Parameter {name?}
            elseif (preg_match('/^\{([a-zA-Z_]+)\?\}$/', $part, $matches)) {
                $params[$matches[1]] = $uriParts[$i] ?? null;
            }
            // Keine Uebereinstimmung
            elseif ($part !== $uriParts[$i]) {
                return false;
            }
        }

        return $params;
    }

    /**
     * URL generieren
     */
    public function url(string $path, array $params = []): string
    {
        $url = $this->basePath . '/' . ltrim($path, '/');

        // Parameter ersetzen
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }

        return $url;
    }

    /**
     * Redirect
     */
    public static function redirect(string $url, int $status = 302): void
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    /**
     * Zurueck zur vorherigen Seite
     */
    public static function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        self::redirect($referer);
    }

    /**
     * Aktuelle URL
     */
    public static function currentUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Ist AJAX-Request?
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Request-Methode
     */
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Query-Parameter
     */
    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * POST-Parameter
     */
    public static function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Alle Eingaben
     */
    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * JSON-Body lesen
     */
    public static function json(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
}
