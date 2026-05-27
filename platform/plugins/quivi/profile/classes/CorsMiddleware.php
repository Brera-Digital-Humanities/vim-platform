<?php

namespace Quivi\Profile\Classes;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->matchesPath($request)) {
            return $next($request);
        }

        if ($request->isMethod('OPTIONS')) {
            return $this->preflightResponse($request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    protected function preflightResponse(Request $request): Response
    {
        if (!$this->isOriginAllowed((string) $request->headers->get('Origin'))) {
            return new Response('Origin not allowed.', 403);
        }

        return $this->addCorsHeaders($request, new Response('', 204));
    }

    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = (string) $request->headers->get('Origin');

        if ($origin === '' || !$this->isOriginAllowed($origin)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', $this->appendVary($response, 'Origin'));

        if ($this->supportsCredentials()) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($request->isMethod('OPTIONS')) {
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->envList('CORS_ALLOWED_METHODS', [
                'GET',
                'POST',
                'PUT',
                'PATCH',
                'DELETE',
                'OPTIONS',
            ])));

            $allowedHeaders = $this->envList('CORS_ALLOWED_HEADERS', ['*']);
            $response->headers->set(
                'Access-Control-Allow-Headers',
                in_array('*', $allowedHeaders, true)
                    ? (string) $request->headers->get('Access-Control-Request-Headers', '*')
                    : implode(', ', $allowedHeaders)
            );

            $maxAge = (int) env('CORS_MAX_AGE', 0);
            if ($maxAge > 0) {
                $response->headers->set('Access-Control-Max-Age', (string) $maxAge);
            }
        }

        $exposedHeaders = $this->envList('CORS_EXPOSED_HEADERS', []);
        if ($exposedHeaders) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposedHeaders));
        }

        return $response;
    }

    protected function isOriginAllowed(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        $allowedOrigins = $this->envList('CORS_ALLOWED_ORIGINS', []);

        return in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true);
    }

    protected function matchesPath(Request $request): bool
    {
        foreach ($this->envList('CORS_PATHS', ['api/*']) as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    protected function supportsCredentials(): bool
    {
        return filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN);
    }

    protected function envList(string $key, array $default): array
    {
        $value = env($key);

        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
    }

    protected function appendVary(Response $response, string $header): string
    {
        $vary = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary'))));

        if (!in_array($header, $vary, true)) {
            $vary[] = $header;
        }

        return implode(', ', $vary);
    }
}
