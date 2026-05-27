<?php namespace Quivi\Profile\Classes;

use RuntimeException;
use Winter\User\Models\User;

class JwtService
{
    protected const ALGORITHM = 'HS256';

    public function issueForUser(User $user, ?int $ttl = null): array
    {
        $issuedAt = time();
        $ttl = $ttl ?: (int) env('JWT_TTL', 525600);
        $expiresAt = $issuedAt + ($ttl * 60);

        $payload = [
            'iss' => config('app.url'),
            'sub' => (string) $user->getKey(),
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
        ];

        return [
            'token' => $this->encode($payload),
            'token_type' => 'bearer',
            'expires_in' => $expiresAt - $issuedAt,
        ];
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->jsonDecode($this->base64UrlDecode($encodedHeader));
        $payload = $this->jsonDecode($this->base64UrlDecode($encodedPayload));

        if (($header['alg'] ?? null) !== self::ALGORITHM) {
            throw new RuntimeException('Unsupported token algorithm.');
        }

        $expectedSignature = $this->sign($encodedHeader . '.' . $encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $now = time();

        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            throw new RuntimeException('Token is not valid yet.');
        }

        if (isset($payload['exp']) && $payload['exp'] <= $now) {
            throw new RuntimeException('Token has expired.');
        }

        if (empty($payload['sub'])) {
            throw new RuntimeException('Token subject is missing.');
        }

        return $payload;
    }

    protected function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => self::ALGORITHM,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $encodedSignature = $this->base64UrlEncode($this->sign($encodedHeader . '.' . $encodedPayload));

        return $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;
    }

    protected function sign(string $value): string
    {
        return hash_hmac('sha256', $value, $this->secret(), true);
    }

    protected function secret(): string
    {
        $secret = (string) env('JWT_SECRET', env('APP_KEY'));

        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET is not configured.');
        }

        return $secret;
    }

    protected function jsonDecode(string $value): array
    {
        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid token JSON.');
        }

        return $decoded;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Invalid token encoding.');
        }

        return $decoded;
    }
}
