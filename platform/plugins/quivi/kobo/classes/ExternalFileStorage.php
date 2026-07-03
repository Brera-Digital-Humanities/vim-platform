<?php namespace Quivi\Kobo\Classes;

use Aws\S3\S3Client;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Winter\Storm\Exception\ApplicationException;
use Winter\User\Models\User;

class ExternalFileStorage
{
    protected const PROVIDER = 'infomaniak_s3';
    protected const DISK = 's3';

    protected ?S3Client $client = null;

    public function initUpload(User $user, array $input): array
    {
        $fieldName = $this->validatedFieldName($input['field_name'] ?? '');
        $submissionUuid = $this->validatedSubmissionUuid($input['submission_uuid'] ?? '');
        $filename = $this->cleanFilename($input['filename'] ?? 'file.bin');
        $mimeType = $this->cleanMimeType($input['mime_type'] ?? '');
        $size = max(0, (int) ($input['size_bytes'] ?? $input['size'] ?? 0));
        $kind = $this->cleanKind($input['kind'] ?? null, $mimeType, $filename);
        $fileId = 'file_' . bin2hex(random_bytes(16));
        $key = $this->objectKey($user, $submissionUuid, $fieldName, $fileId, $filename);
        $transport = $this->uploadTransport();
        $method = $this->shouldUseMultipart($size, (bool) ($input['multipart'] ?? false), $transport)
            ? 'multipart'
            : 'single';

        $base = [
            'v' => 1,
            'file_id' => $fileId,
            'field' => $fieldName,
            'status' => 'pending',
            'provider' => self::PROVIDER,
            'disk' => self::DISK,
            'bucket' => $this->bucket(),
            'key' => $key,
            'name' => $filename,
            'size_bytes' => $size,
            'mime_type' => $mimeType,
            'kind' => $kind,
            'submission_uuid' => $submissionUuid,
            'upload_method' => $method,
            'upload_transport' => $transport,
        ];

        if ($method === 'multipart') {
            $result = $this->client()->createMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'ContentType' => $mimeType,
                'Metadata' => $this->metadata($user, $base),
            ]);

            return [
                'method' => 'multipart',
                'transport' => $transport,
                'upload_id' => (string) $result->get('UploadId'),
                'part_size' => $this->partSizeBytes($transport),
                'file' => $base,
            ];
        }

        if ($transport === 'proxy') {
            return [
                'method' => 'single',
                'transport' => 'proxy',
                'file' => $base,
            ];
        }

        $command = $this->client()->getCommand('PutObject', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ContentType' => $mimeType,
        ]);

        return [
            'method' => 'single',
            'transport' => 'direct',
            'upload_url' => (string) $this->client()->createPresignedRequest(
                $command,
                '+' . $this->uploadUrlTtlMinutes() . ' minutes'
            )->getUri(),
            'headers' => [
                'Content-Type' => $mimeType,
            ],
            'file' => $base,
        ];
    }

    public function proxySingleUpload(User $user, array $input, ?UploadedFile $upload): array
    {
        $file = $this->validatedFilePayload($input['file'] ?? []);
        $key = $this->validatedOwnedKey($user, $file['key'] ?? '');
        $path = $this->uploadedFilePath($upload);

        $this->client()->putObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'SourceFile' => $path,
            'ContentType' => $this->cleanMimeType($file['mime_type'] ?? ''),
            'Metadata' => $this->metadata($user, $file),
        ]);

        return $this->completeUpload($user, [
            'file' => $file,
            'key' => $key,
        ]);
    }

    public function proxyPartUpload(User $user, array $input, ?UploadedFile $upload): array
    {
        $key = $this->validatedOwnedKey($user, $input['key'] ?? '');
        $uploadId = trim((string) ($input['upload_id'] ?? ''));
        $partNumber = (int) ($input['part_number'] ?? 0);
        $path = $this->uploadedFilePath($upload);

        if ($uploadId === '' || $partNumber < 1 || $partNumber > 10000) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_part'));
        }

        $result = $this->client()->uploadPart([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
            'SourceFile' => $path,
        ]);

        return [
            'part_number' => $partNumber,
            'etag' => (string) $result->get('ETag'),
        ];
    }

    public function partUrl(User $user, array $input): array
    {
        $key = $this->validatedOwnedKey($user, $input['key'] ?? '');
        $uploadId = trim((string) ($input['upload_id'] ?? ''));
        $partNumber = (int) ($input['part_number'] ?? 0);

        if ($uploadId === '' || $partNumber < 1 || $partNumber > 10000) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_part'));
        }

        $command = $this->client()->getCommand('UploadPart', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        return [
            'upload_url' => (string) $this->client()->createPresignedRequest(
                $command,
                '+' . $this->uploadUrlTtlMinutes() . ' minutes'
            )->getUri(),
        ];
    }

    public function completeUpload(User $user, array $input): array
    {
        $file = $this->validatedFilePayload($input['file'] ?? []);
        $key = $this->validatedOwnedKey($user, $file['key'] ?? ($input['key'] ?? ''));
        $uploadId = trim((string) ($input['upload_id'] ?? ''));

        if ($uploadId !== '') {
            $parts = $this->validatedParts($input['parts'] ?? []);

            $this->client()->completeMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'UploadId' => $uploadId,
                'MultipartUpload' => [
                    'Parts' => $parts,
                ],
            ]);
        }

        $head = $this->client()->headObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ]);

        $file['key'] = $key;
        $file['status'] = 'uploaded';
        $file['etag'] = trim((string) $head->get('ETag'), '"');
        $file['size_bytes'] = (int) ($head->get('ContentLength') ?: ($file['size_bytes'] ?? 0));
        $file['mime_type'] = (string) ($head->get('ContentType') ?: ($file['mime_type'] ?? 'application/octet-stream'));
        $file['uploaded_at'] = Carbon::now()->toIso8601String();

        return ['file' => $file];
    }

    public function abortUpload(User $user, array $input): array
    {
        $key = $this->validatedOwnedKey($user, $input['key'] ?? '');
        $uploadId = trim((string) ($input['upload_id'] ?? ''));

        if ($uploadId !== '') {
            $this->client()->abortMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'UploadId' => $uploadId,
            ]);
        }

        return ['aborted' => true];
    }

    public function temporaryDownloadUrl(array $file): string
    {
        $key = trim((string) ($file['key'] ?? ''));

        if ($key === '') {
            throw new ApplicationException(trans('quivi.kobo::lang.review.errors.external_file_key_missing'));
        }

        return Storage::disk(self::DISK)->temporaryUrl(
            $key,
            Carbon::now()->addMinutes($this->downloadUrlTtlMinutes()),
            [
                'ResponseContentType' => $this->cleanMimeType($file['mime_type'] ?? ''),
                'ResponseContentDisposition' => 'inline; filename="' . addcslashes($this->cleanFilename($file['name'] ?? 'file.bin'), '"\\') . '"',
            ]
        );
    }

    protected function client(): S3Client
    {
        if ($this->client) {
            return $this->client;
        }

        $config = config('filesystems.disks.' . self::DISK, []);

        foreach (['key', 'secret', 'bucket', 'endpoint'] as $required) {
            if (empty($config[$required])) {
                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_not_configured'));
            }
        }

        return $this->client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint' => $config['endpoint'],
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? true),
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);
    }

    protected function bucket(): string
    {
        $bucket = config('filesystems.disks.' . self::DISK . '.bucket');

        if (!$bucket) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_not_configured'));
        }

        return (string) $bucket;
    }

    protected function objectKey(User $user, string $submissionUuid, string $fieldName, string $fileId, string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!preg_match('/^[a-z0-9]{1,12}$/', $extension)) {
            $extension = 'bin';
        }

        return sprintf(
            'kobo-external/user-%d/%s/%s/%s/original.%s',
            (int) $user->id,
            $submissionUuid,
            $fieldName,
            $fileId,
            $extension
        );
    }

    protected function validatedOwnedKey(User $user, mixed $key): string
    {
        $key = trim((string) $key);
        $prefix = 'kobo-external/user-' . (int) $user->id . '/';

        if ($key === '' || !str_starts_with($key, $prefix) || str_contains($key, '..')) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_key'));
        }

        return $key;
    }

    protected function validatedFieldName(mixed $value): string
    {
        $fieldName = trim((string) $value);

        if (!preg_match('/^xfile_[A-Za-z0-9_]+$/', $fieldName)) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_field'));
        }

        return $fieldName;
    }

    protected function validatedSubmissionUuid(mixed $value): string
    {
        $uuid = trim((string) $value);
        $uuid = preg_replace('/^uuid:/i', '', $uuid);

        if (!preg_match('/^[A-Za-z0-9._:-]{8,80}$/', $uuid) || str_contains($uuid, '..')) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_submission'));
        }

        return $uuid;
    }

    protected function validatedFilePayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_file'));
        }

        foreach (['file_id', 'field', 'key', 'name'] as $required) {
            if (empty($payload[$required])) {
                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_file'));
            }
        }

        return $payload;
    }

    protected function validatedParts(mixed $parts): array
    {
        if (!is_array($parts) || !$parts) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_parts'));
        }

        $normalized = [];
        foreach ($parts as $part) {
            if (!is_array($part)) {
                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_parts'));
            }

            $partNumber = (int) ($part['part_number'] ?? $part['PartNumber'] ?? 0);
            $etag = trim((string) ($part['etag'] ?? $part['ETag'] ?? ''));

            if ($partNumber < 1 || $partNumber > 10000 || $etag === '') {
                throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_invalid_parts'));
            }

            $normalized[] = [
                'PartNumber' => $partNumber,
                'ETag' => $etag,
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['PartNumber'] <=> $b['PartNumber']);

        return $normalized;
    }

    protected function cleanFilename(mixed $value): string
    {
        $filename = basename(str_replace('\\', '/', trim((string) $value)));
        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', $filename);
        $filename = trim((string) $filename, " .\t\n\r\0\x0B");

        return $filename !== '' ? mb_substr($filename, 0, 180) : 'file.bin';
    }

    protected function cleanMimeType(mixed $value): string
    {
        $mime = trim((string) $value);

        return preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $mime)
            ? strtolower($mime)
            : 'application/octet-stream';
    }

    protected function cleanKind(mixed $value, string $mimeType, string $filename): string
    {
        $kind = trim((string) $value);
        if (in_array($kind, ['audio', 'image', 'video', 'file'], true)) {
            return $kind;
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
            'mp3', 'm4a', 'ogg', 'opus', 'wav', 'webm' => 'audio',
            'mp4', 'm4v', 'mov' => 'video',
            default => 'file',
        };
    }

    protected function metadata(User $user, array $file): array
    {
        return [
            'user-id' => (string) $user->id,
            'file-id' => (string) $file['file_id'],
            'field' => (string) $file['field'],
            'submission-uuid' => (string) $file['submission_uuid'],
            'original-name' => $this->asciiMetadata((string) $file['name']),
        ];
    }

    protected function asciiMetadata(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return substr(preg_replace('/[^\x20-\x7E]+/', '_', $ascii ?: $value), 0, 180);
    }

    protected function uploadedFilePath(?UploadedFile $upload): string
    {
        if (!$upload || !$upload->isValid() || !is_file($upload->getRealPath())) {
            throw new ApplicationException(trans('quivi.kobo::lang.api.errors.external_upload_missing_body'));
        }

        return $upload->getRealPath();
    }

    protected function shouldUseMultipart(int $size, bool $requested, string $transport): bool
    {
        return $requested || $size >= $this->multipartThresholdBytes($transport);
    }

    protected function multipartThresholdBytes(string $transport): int
    {
        if ($transport === 'proxy') {
            return max(5, (int) env('VIM_S3_PROXY_MULTIPART_THRESHOLD_MB', 8)) * 1024 * 1024;
        }

        return max(5, (int) env('VIM_S3_MULTIPART_THRESHOLD_MB', 100)) * 1024 * 1024;
    }

    protected function partSizeBytes(string $transport): int
    {
        if ($transport === 'proxy') {
            return max(5, (int) env('VIM_S3_PROXY_PART_SIZE_MB', 8)) * 1024 * 1024;
        }

        return max(5, (int) env('VIM_S3_MULTIPART_PART_SIZE_MB', 64)) * 1024 * 1024;
    }

    protected function uploadTransport(): string
    {
        $transport = strtolower(trim((string) env('VIM_S3_UPLOAD_TRANSPORT', 'proxy')));

        return in_array($transport, ['direct', 'proxy'], true) ? $transport : 'proxy';
    }

    protected function uploadUrlTtlMinutes(): int
    {
        return max(1, (int) env('VIM_S3_UPLOAD_URL_TTL_MINUTES', 30));
    }

    protected function downloadUrlTtlMinutes(): int
    {
        return max(1, (int) env('VIM_S3_DOWNLOAD_URL_TTL_MINUTES', 15));
    }
}
