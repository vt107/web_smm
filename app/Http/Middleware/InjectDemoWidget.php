<?php

namespace App\Http\Middleware;

use App\Support\Demo\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chế độ demo: chèn nút "Demo" (resources/views/demo/widget.blade.php) vào mọi trang HTML,
 * kể cả Filament, mà không phải sửa từng layout.
 */
class InjectDemoWidget
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! DemoMode::enabled() || ! $this->injectable($request, $response)) {
            return $response;
        }

        $content = (string) $response->getContent();
        $position = strripos($content, '</body>');

        if ($position === false) {
            return $response;
        }

        $widget = view('demo.widget')->render();
        $response->setContent(substr($content, 0, $position).$widget.substr($content, $position));

        return $response;
    }

    protected function injectable(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && ! $request->hasHeader('X-Livewire')
            && $response instanceof IlluminateResponse
            && $response->getStatusCode() < 300
            && str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html');
    }
}
