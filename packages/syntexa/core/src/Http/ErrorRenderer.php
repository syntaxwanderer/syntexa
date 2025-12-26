<?php

declare(strict_types=1);

namespace Syntexa\Core\Http;

use Syntexa\Core\Request;
use Syntexa\Core\Response;

class ErrorRenderer
{
    public static function render(\Throwable $e, ?Request $request = null): Response
    {
        $accept = $request?->getHeader('Accept') ?? '';
        $isHtml = str_contains($accept, 'text/html');
        if ($isHtml) {
            $html = '<!doctype html><html><head><meta charset="utf-8"><title>Error</title>'
                . '<style>body{font-family:system-ui;padding:24px;color:#111;background:#fafafa}'
                . '.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px}'
                . 'code{white-space:pre-wrap;}</style></head><body>'
                . '<div class="card">'
                . '<h1>Internal Server Error</h1>'
                . '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
                . '</div></body></html>';
            return new Response($html, 500, ['Content-Type' => 'text/html; charset=utf-8']);
        }
        return Response::json([
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
        ], 500);
    }
}
