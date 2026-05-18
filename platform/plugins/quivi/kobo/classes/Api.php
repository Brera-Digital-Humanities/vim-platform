<?php namespace Quivi\Kobo\Classes;

use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Network\Http;

class Api
{
    public const DEFAULT_BASE_URL = 'https://eu.kobotoolbox.org';
    public const API_PREFIX = '/api/v2';

    protected string $baseUrl;

    protected ?string $token;

    protected int $timeout;

    protected bool $verifySsl;

    public function __construct(
        ?string $token = null,
        ?string $baseUrl = null,
        int $timeout = 30,
        bool $verifySsl = true
    ) {
        $this->token = $token ?: env('KOBO_API_TOKEN') ?: null;
        $this->baseUrl = rtrim($baseUrl ?: env('KOBO_BASE_URL', self::DEFAULT_BASE_URL), '/');
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
    }

    public static function make(
        ?string $token = null,
        ?string $baseUrl = null,
        int $timeout = 30,
        bool $verifySsl = true
    ): self {
        return new self($token, $baseUrl, $timeout, $verifySsl);
    }

    public function docs(): string
    {
        return $this->requestRaw('GET', 'docs/');
    }

    public function schema(): array
    {
        return $this->get('schema/', ['format' => 'json']);
    }

    public function assets(array $query = []): array
    {
        return $this->get('assets/', $query);
    }

    public function surveys(array $query = []): array
    {
        return $this->assets(array_replace(['asset_type' => 'survey'], $query));
    }

    public function asset(string $assetUid): array
    {
        return $this->get('assets/' . rawurlencode($assetUid) . '/');
    }

    public function submissions(string $assetUid, array $query = []): array
    {
        return $this->get('assets/' . rawurlencode($assetUid) . '/data/', $query);
    }

    public function submission(string $assetUid, int|string $submissionId): array
    {
        return $this->get(
            'assets/' . rawurlencode($assetUid) . '/data/' . rawurlencode((string) $submissionId) . '/'
        );
    }

    public function allSubmissions(string $assetUid, array $query = []): array
    {
        $url = $this->buildUrl('assets/' . rawurlencode($assetUid) . '/data/', $query);
        $submissions = [];

        do {
            $page = $this->request('GET', $url);
            $results = $page['results'] ?? [];

            if (!is_array($results)) {
                throw new ApplicationException('Unexpected Kobo response: missing paginated results.');
            }

            $submissions = array_merge($submissions, $results);
            $url = $page['next'] ?? null;
        } while (!empty($url));

        return $submissions;
    }

    public function exports(string $assetUid, array $query = []): array
    {
        return $this->get('assets/' . rawurlencode($assetUid) . '/exports/', $query);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('POST', $path, $query, $payload);
    }

    public function patch(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('PATCH', $path, $query, $payload);
    }

    public function put(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('PUT', $path, $query, $payload);
    }

    public function delete(string $path, array $query = []): array
    {
        return $this->request('DELETE', $path, $query);
    }

    protected function request(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $body = $this->requestRaw($method, $path, $query, $payload);

        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApplicationException('Kobo returned invalid JSON: ' . json_last_error_msg());
        }

        return $decoded ?: [];
    }

    protected function requestRaw(string $method, string $path, array $query = [], ?array $payload = null): string
    {
        $response = Http::make($this->buildUrl($path, $query), strtoupper($method), function (Http $http) use ($payload) {
            $http->timeout($this->timeout);
            $http->header('Accept', 'application/json');

            if ($this->token) {
                $http->header('Authorization', 'Token ' . $this->token);
            }

            if ($this->verifySsl) {
                $http->verifySSL();
            }

            if ($payload !== null) {
                $encodedPayload = json_encode($payload);

                if ($encodedPayload === false) {
                    throw new ApplicationException('Unable to encode Kobo request payload: ' . json_last_error_msg());
                }

                $http->header('Content-Type', 'application/json');
                $http->setOption(CURLOPT_POSTFIELDS, $encodedPayload);
            }
        })->send();

        if (!$response->ok) {
            throw new ApplicationException(sprintf(
                'Kobo API request failed with HTTP %d: %s',
                $response->code,
                $response->body
            ));
        }

        return $response->body;
    }

    protected function buildUrl(string $path, array $query = []): string
    {
        if (preg_match('#^https?://#i', $path)) {
            $url = $path;
        } elseif (
            str_starts_with($path, self::API_PREFIX . '/')
            || str_starts_with($path, '/' . ltrim(self::API_PREFIX, '/') . '/')
        ) {
            $url = $this->baseUrl . '/' . ltrim($path, '/');
        } else {
            $url = $this->baseUrl . self::API_PREFIX . '/' . ltrim($path, '/');
        }

        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }
}
