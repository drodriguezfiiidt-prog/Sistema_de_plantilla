<?php

declare(strict_types=1);

namespace GestioBio\Support;

use RuntimeException;

/**
 * Renderizado de plantillas PHP con layout compartido.
 */
final class View
{
    /**
     * @var array<string, mixed>|null
     */
    private static ?array $shared = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = []): void
    {
        $content = self::capture($template, $data);
        $html = self::capture('layout', $data + ['content' => $content]);
        self::send($html);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function partial(string $template, array $data = []): void
    {
        self::send(self::capture($template, $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function capture(string $template, array $data = []): string
    {
        $file = base_path('resources/views/' . $template . '.php');
        if (!is_file($file)) {
            throw new RuntimeException('No se encontro la plantilla: ' . $template);
        }

        return self::renderFile($file, self::shared() + $data);
    }

    public static function send(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public static function json(mixed $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(int $status, string $title, string $message): void
    {
        self::send(self::capture('error', [
            'page_title' => $title,
            'status' => $status,
            'title' => $title,
            'message' => $message,
        ]), $status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        if (self::$shared === null) {
            self::$shared = [
                'brand_name' => (string) cfg('app.brand_name', 'GestioBioV3'),
                'brand_subtitle' => (string) cfg('app.brand_subtitle', 'Soporte Postventa'),
                'company' => (string) cfg('app.company', 'Biopago C.A.'),
                'currency' => (string) cfg('app.currency', 'USD'),
                'current_user' => Auth::user(),
                'flashes' => Flash::consume(),
                'csrf_token' => Csrf::token(),
            ];
        }

        return self::$shared;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
