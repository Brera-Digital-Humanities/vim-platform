<?php namespace Quivi\Profile\Classes;

use Closure;
use Response;
use RuntimeException;
use Winter\User\Models\Settings as UserSettings;
use Winter\User\Models\User;

class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $this->bearerToken($request);

        if (!$token) {
            return Response::json(['error' => 'Token missing.'], 401);
        }

        try {
            $payload = (new JwtService())->decode($token);
            $user = User::find($payload['sub']);
        } catch (RuntimeException $ex) {
            return Response::json(['error' => $ex->getMessage()], 401);
        }

        if (!$user || $user->is_guest) {
            return Response::json(['error' => 'User not found.'], 401);
        }

        if (UserSettings::get('require_activation', true) && !$user->is_activated) {
            return Response::json(['error' => 'User is not activated.'], 403);
        }

        $request->attributes->set('api_user', $user);
        $request->attributes->set('jwt_payload', $payload);

        return $next($request);
    }

    protected function bearerToken($request): ?string
    {
        $header = $request->headers->get('Authorization')
            ?: $request->server->get('HTTP_AUTHORIZATION')
            ?: $request->server->get('REDIRECT_HTTP_AUTHORIZATION');

        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return $request->input('token') ?: null;
    }
}
