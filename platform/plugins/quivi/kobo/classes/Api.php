<?php namespace Quivi\Kobo\Classes;

use CURLFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Winter\Storm\Exception\ApplicationException;
use Winter\Storm\Network\Http;

class Api
{
    public const DEFAULT_BASE_URL = 'https://eu.kobotoolbox.org';
    public const API_PREFIX = '/api/v2';
    protected const RETRYABLE_STATUS_CODES = [0, 408, 429, 500, 502, 503, 504];

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
        $configToken = env('KOBO_API_TOKEN') ?: env('VIM_KOBO_TOKEN') ?: null;
        $configBaseUrl = env('KOBO_BASE_URL') ?: env('VIM_KOBO_BASE') ?: self::DEFAULT_BASE_URL;

        $this->token = $token ?: $configToken;
        $this->baseUrl = rtrim($baseUrl ?: $configBaseUrl, '/');
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

    public function findSubmissionByUuid(string $assetUid, string $uuid): ?array
    {
        $uuid = preg_replace('/^uuid:/i', '', trim($uuid));
        if ($uuid === '') {
            return null;
        }

        $queries = [
            ['_uuid' => $uuid],
            ['_uuid' => 'uuid:' . $uuid],
            ['meta/instanceID' => 'uuid:' . $uuid],
        ];

        for ($attempt = 0; $attempt < 3; $attempt++) {
            foreach ($queries as $query) {
                try {
                    $page = $this->submissions($assetUid, [
                        'query' => json_encode($query),
                        'limit' => 1,
                    ]);
                } catch (\Throwable $ex) {
                    continue;
                }

                $results = $page['results'] ?? [];
                if (!is_array($results) || !$results) {
                    continue;
                }

                $record = reset($results);
                if (is_array($record)) {
                    return $record;
                }
            }

            if ($attempt < 2) {
                usleep(500000);
            }
        }

        return null;
    }

    public function allSubmissions(string $assetUid, array $query = []): array
    {
        $url = $this->buildUrl('assets/' . rawurlencode($assetUid) . '/data/', $query);
        $submissions = [];

        do {
            $page = $this->request('GET', $url);
            $results = $page['results'] ?? [];

            if (!is_array($results)) {
                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.missing_paginated_results'));
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

    public function download(string $path, array $query = []): array
    {
        $lastResponse = null;
        $lastException = null;
        $attempts = $this->retryAttempts('GET');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::make($this->buildDownloadUrl($path, $query), 'GET', function (Http $http) {
                    $http->timeout($this->timeout);
                    $http->header('Accept', '*/*');

                    if ($this->token) {
                        $http->header('Authorization', 'Token ' . $this->token);
                    }

                    $this->configureSsl($http);
                })->send();
            } catch (\Throwable $ex) {
                $lastException = $ex;

                if ($this->shouldRetryException('GET', $attempt, $attempts)) {
                    $this->waitBeforeRetry($attempt);
                    continue;
                }

                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.media_request_failed', [
                    'status' => 0,
                    'body' => $ex->getMessage(),
                ]));
            }

            if ($response->ok) {
                return [
                    'body' => $response->body,
                    'headers' => $response->headers,
                    'status' => $response->code,
                ];
            }

            $lastResponse = $response;

            if ($this->shouldRetryResponse('GET', $response, $attempt, $attempts)) {
                $this->waitBeforeRetry($attempt);
                continue;
            }

            break;
        }

        if ($lastResponse) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.media_request_failed', [
                'status' => $lastResponse->code,
                'body' => $lastResponse->body,
            ]));
        }

        throw new ApplicationException(trans('quivi.kobo::lang.api.errors.media_request_failed', [
            'status' => 0,
            'body' => $lastException ? $lastException->getMessage() : 'Unknown error',
        ]));
    }

    public function submitOpenRosa(Request $request): array
    {
        if (!$this->token) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.token_missing'));
        }

        $files = [];
        $tempFiles = [];

        $xmlFile = $request->file('xml_submission_file');
        if ($xmlFile instanceof UploadedFile) {
            $files['xml_submission_file'] = $this->uploadedFileToCurlFile($xmlFile, 'submission.xml');
        } else {
            $xml = trim((string) $request->input('xml_submission_file'));
            if ($xml === '') {
                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.xml_submission_required'));
            }

            $tmp = tempnam(sys_get_temp_dir(), 'kobo_submission_');
            if ($tmp === false || file_put_contents($tmp, $xml) === false) {
                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.xml_submission_prepare_failed'));
            }

            $tempFiles[] = $tmp;
            $files['xml_submission_file'] = new CURLFile($tmp, 'text/xml', 'submission.xml');
        }

        foreach ($request->allFiles() as $key => $file) {
            if ($key === 'xml_submission_file') {
                continue;
            }

            $this->appendUploadedFiles($files, $key, $file);
        }

        try {
            $response = $this->sendOpenRosaMultipart($files);
        } finally {
            foreach ($tempFiles as $tmp) {
                @unlink($tmp);
            }
        }

        return [
            'ok' => $response['ok'],
            'status' => $response['status'],
            'body' => $response['body'],
            'headers' => $response['headers'],
        ];
    }

    protected function sendOpenRosaMultipart(array $files): array
    {
        $curl = curl_init($this->submissionUrl());

        if ($curl === false) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.submission_request_init_failed'));
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $files,
            CURLOPT_HTTPHEADER => [
                'Accept: application/xml, text/xml, application/json, */*',
                'Authorization: Token ' . $this->token,
                'X-OpenRosa-Version: 1.0',
                'Expect:',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => (int) env('KOBO_SUBMISSION_TIMEOUT', $this->timeout),
            CURLOPT_TIMEOUT => (int) env('KOBO_SUBMISSION_TIMEOUT', $this->timeout),
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'VIM WinterCMS',
        ]);

        $raw = curl_exec($curl);

        if ($raw === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.submission_curl_failed', [
                'error' => $error,
            ]));
        }

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        return [
            'ok' => $status >= 200 && $status <= 299,
            'status' => $status,
            'body' => $body,
            'headers' => $this->parseHeaders($rawHeaders),
        ];
    }

    protected function request(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $body = $this->requestRaw($method, $path, $query, $payload);

        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.invalid_json', [
                'error' => json_last_error_msg(),
            ]));
        }

        return $decoded ?: [];
    }

    protected function requestRaw(string $method, string $path, array $query = [], ?array $payload = null): string
    {
        $method = strtoupper($method);
        $encodedPayload = null;
        $lastResponse = null;
        $lastException = null;
        $attempts = $this->retryAttempts($method);

        if ($payload !== null) {
            $encodedPayload = json_encode($payload);

            if ($encodedPayload === false) {
                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.payload_encode_failed', [
                    'error' => json_last_error_msg(),
                ]));
            }
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::make($this->buildUrl($path, $query), $method, function (Http $http) use ($encodedPayload) {
                    $http->timeout($this->timeout);
                    $http->header('Accept', 'application/json');

                    if ($this->token) {
                        $http->header('Authorization', 'Token ' . $this->token);
                    }

                    $this->configureSsl($http);

                    if ($encodedPayload !== null) {
                        $http->header('Content-Type', 'application/json');
                        $http->setOption(CURLOPT_POSTFIELDS, $encodedPayload);
                    }
                })->send();
            } catch (\Throwable $ex) {
                $lastException = $ex;

                if ($this->shouldRetryException($method, $attempt, $attempts)) {
                    $this->waitBeforeRetry($attempt);
                    continue;
                }

                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.request_failed', [
                    'status' => 0,
                    'body' => $ex->getMessage(),
                ]));
            }

            if ($response->ok) {
                return $response->body;
            }

            $lastResponse = $response;

            if ($this->shouldRetryResponse($method, $response, $attempt, $attempts)) {
                $this->waitBeforeRetry($attempt);
                continue;
            }

            break;
        }

        if ($lastResponse) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.request_failed', [
                'status' => $lastResponse->code,
                'body' => $lastResponse->body,
            ]));
        }

        throw new ApplicationException(trans('quivi.kobo::lang.api.errors.request_failed', [
            'status' => 0,
            'body' => $lastException ? $lastException->getMessage() : 'Unknown error',
        ]));
    }

    protected function retryAttempts(string $method): int
    {
        if (!$this->isRetryableMethod($method)) {
            return 1;
        }

        return max(1, (int) env('KOBO_API_ATTEMPTS', 3));
    }

    protected function isRetryableMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD'], true);
    }

    protected function shouldRetryResponse(string $method, $response, int $attempt, int $attempts): bool
    {
        if ($attempt >= $attempts || !$this->isRetryableMethod($method)) {
            return false;
        }

        return in_array((int) ($response->code ?? 0), self::RETRYABLE_STATUS_CODES, true);
    }

    protected function shouldRetryException(string $method, int $attempt, int $attempts): bool
    {
        return $attempt < $attempts && $this->isRetryableMethod($method);
    }

    protected function waitBeforeRetry(int $attempt): void
    {
        $baseDelay = max(0, (int) env('KOBO_API_RETRY_DELAY_MS', 400));

        if ($baseDelay <= 0) {
            return;
        }

        $maxDelay = max($baseDelay, (int) env('KOBO_API_RETRY_MAX_DELAY_MS', 2000));
        $delay = min($maxDelay, $baseDelay * (2 ** max(0, $attempt - 1)));

        usleep($delay * 1000);
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

    protected function buildDownloadUrl(string $path, array $query = []): string
    {
        if (preg_match('#^https?://#i', $path)) {
            $url = $path;
        } elseif (str_starts_with($path, '/')) {
            $url = $this->baseUrl . $path;
        } else {
            return $this->buildUrl($path, $query);
        }

        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    protected function submissionUrl(): string
    {
        return rtrim((string) env('KOBO_SUBMISSION_URL', $this->baseUrl . '/submission'), '/');
    }

    protected function configureSsl(Http $http): void
    {
        if (!$this->verifySsl) {
            return;
        }

        $http->setOption(CURLOPT_SSL_VERIFYPEER, true);
        $http->setOption(CURLOPT_SSL_VERIFYHOST, 2);
    }

    protected function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $blocks = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($rawHeaders));
        $latest = $blocks ? end($blocks) : '';

        foreach (preg_split("/\r\n|\n|\r/", (string) $latest) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }

        return $headers;
    }

    protected function appendUploadedFiles(array &$files, string $key, mixed $file): void
    {
        if ($file instanceof UploadedFile) {
            $files[$key] = $this->uploadedFileToCurlFile($file);
            return;
        }

        if (is_array($file)) {
            foreach ($file as $index => $child) {
                $this->appendUploadedFiles($files, $key . '[' . $index . ']', $child);
            }
        }
    }

    protected function uploadedFileToCurlFile(UploadedFile $file, ?string $fallbackName = null): CURLFile
    {
        return new CURLFile(
            $file->getPathname(),
            $file->getMimeType() ?: 'application/octet-stream',
            $file->getClientOriginalName() ?: $fallbackName ?: basename($file->getPathname())
        );
    }
}
