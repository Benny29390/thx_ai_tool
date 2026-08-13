<?php
/**
 * HTTP-Responses und Views
 */

namespace Core;

class Response
{
    /**
     * View rendern
     */
    public static function view(string $view, array $data = [], ?string $layout = 'main'): void
    {
        // Daten als Variablen verfuegbar machen
        extract($data);

        // CSRF-Token immer verfuegbar
        $csrfToken = Session::getCsrfToken();

        // Flash-Messages
        $flashMessages = Session::getFlash();

        // Aktueller Benutzer
        $currentUser = Auth::user();

        // View-Pfad
        $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            self::error("View '{$view}' nicht gefunden", 500);
            return;
        }

        // View in Buffer rendern
        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        // Mit Layout ausgeben
        if ($layout) {
            $layoutFile = VIEWS_PATH . '/layouts/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                include $layoutFile;
            } else {
                echo $content;
            }
        } else {
            echo $content;
        }
    }

    /**
     * JSON-Response
     */
    public static function json(array $data, int $status = 200): void
    {
        // Output Buffer leeren um PHP-Warnungen zu entfernen
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Erfolgs-JSON
     */
    public static function success($data = null, string $message = 'Erfolg'): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Fehler-JSON
     */
    public static function error(string $message, int $status = 400, $errors = null): void
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        // Bei HTML-Requests Fehlerseite anzeigen
        if (!Router::isAjax() && strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') === false) {
            http_response_code($status);
            self::view('errors/error', [
                'status' => $status,
                'message' => $message
            ], 'auth');
            exit;
        }

        self::json($response, $status);
    }

    /**
     * Download
     */
    public static function download(string $content, string $filename, string $contentType = 'application/octet-stream'): void
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        echo $content;
        exit;
    }

    /**
     * Datei-Download
     */
    public static function downloadFile(string $path, ?string $filename = null): void
    {
        if (!file_exists($path)) {
            self::error('Datei nicht gefunden', 404);
            return;
        }

        $filename = $filename ?? basename($path);
        $contentType = mime_content_type($path) ?: 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($path);
        exit;
    }

    /**
     * HTML ausgeben
     */
    public static function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    /**
     * Plain Text ausgeben
     */
    public static function text(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $content;
        exit;
    }

    /**
     * Redirect
     */
    public static function redirect(string $url, int $status = 302): void
    {
        Router::redirect($url, $status);
    }

    /**
     * Zurueck
     */
    public static function back(): void
    {
        Router::back();
    }

    /**
     * 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Nicht autorisiert'): void
    {
        self::error($message, 401);
    }

    /**
     * 403 Forbidden
     */
    public static function forbidden(string $message = 'Zugriff verweigert'): void
    {
        self::error($message, 403);
    }

    /**
     * 404 Not Found
     */
    public static function notFound(string $message = 'Nicht gefunden'): void
    {
        self::error($message, 404);
    }

    /**
     * 500 Internal Server Error
     */
    public static function serverError(string $message = 'Interner Serverfehler'): void
    {
        self::error($message, 500);
    }

    /**
     * CORS-Header setzen
     */
    public static function cors(string $origin = '*', array $methods = ['GET', 'POST', 'PUT', 'DELETE']): void
    {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');

        // Preflight-Request beantworten
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Cache-Header setzen
     */
    public static function cache(int $seconds): void
    {
        header('Cache-Control: public, max-age=' . $seconds);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
    }

    /**
     * Kein Cache
     */
    public static function noCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
