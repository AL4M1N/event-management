<?php

namespace App\Helpers;

class Helper
{
    /**
     * Generate base URL for the application
     */
    public static function baseUrl(string $path = ''): string
    {
        $basePath = rtrim($_SERVER['SCRIPT_NAME'], 'index.php');
        return $basePath . trim($path, '/');
    }

    /**
     * Redirect to a specific URL
     */
    public static function redirect(string $path, array $flash = []): void
    {
        if (!empty($flash)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash'] = $flash;
        }
        header('Location: ' . self::baseUrl($path));
        exit;
    }

    /**
     * Send JSON response
     */
    public static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Check if the request is POST
     */
    public static function isPostRequest(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Render a view file
     * @throws RuntimeException if view file is not found or not readable
     */
    public static function view(string $path, array $data = []): void
    {
        $viewPath = realpath(dirname(__DIR__) . '/Views/' . $path);
        
        if (!is_readable($viewPath)) {
            throw new \RuntimeException("View file not found or not readable: {$path}");
        }
        
        extract($data);
        require_once $viewPath;
    }
}
