<?php namespace Quivi\Kobo\Classes;

use Backend;
use Quivi\Kobo\Models\Submission;
use Winter\Storm\Exception\ApplicationException;

class SubmissionPreview
{
    protected const META_KEYS = [
        '_attachments',
        '_duration',
        '_edited',
        '_geolocation',
        '_id',
        '_notes',
        '_status',
        '_submission_time',
        '_submitted_by',
        '_tags',
        '_uuid',
        '_validation_status',
        '_version__',
        '_xform_id_string',
        'formhub/uuid',
        'meta/instanceID',
    ];

    protected Api $api;

    public function __construct(?Api $api = null)
    {
        $this->api = $api ?: Api::make();
    }

    public static function make(?Api $api = null): self
    {
        return new self($api);
    }

    public function build(Submission $submission): array
    {
        if (!$submission->asset_uid) {
            return $this->error(trans('quivi.kobo::lang.review.errors.asset_uid_missing'));
        }

        if (!$submission->kobo_id && !$submission->kobo_uuid) {
            return $this->error(trans('quivi.kobo::lang.review.errors.kobo_identifiers_missing'));
        }

        try {
            $asset = $this->safeAsset($submission->asset_uid);
            $data = $this->loadSubmissionData($submission);
            $fields = $this->buildFieldMap($asset);
            $flatData = $this->flattenSubmission($data);

            return [
                'ok' => true,
                'answers' => $this->buildAnswers($flatData, $fields),
                'attachments' => $this->buildAttachments($submission, $data, $flatData, $fields),
                'metadata' => $this->buildMetadata($data),
                'raw' => $data,
            ];
        } catch (\Throwable $ex) {
            return $this->error($ex->getMessage());
        }
    }

    public function downloadAttachment(Submission $submission, int $index): array
    {
        if (!$submission->asset_uid) {
            throw new ApplicationException(trans('quivi.kobo::lang.review.errors.download_asset_uid_missing'));
        }

        $data = $this->loadSubmissionData($submission);
        $attachments = $this->extractAttachments($data);

        if (!array_key_exists($index, $attachments)) {
            throw new ApplicationException(trans('quivi.kobo::lang.review.errors.attachment_not_found'));
        }

        $attachment = $attachments[$index];
        $url = $attachment['download_url'] ?? $attachment['downloadUrl'] ?? null;

        if (!$url) {
            throw new ApplicationException(trans('quivi.kobo::lang.review.errors.attachment_download_url_missing'));
        }

        $download = $this->api->download($url);
        $filename = $this->attachmentFilename($attachment, $index);
        $contentType = $this->attachmentMime($attachment, $filename, $download['headers'] ?? []);

        return [
            'body' => $download['body'],
            'headers' => [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline; filename="' . addcslashes($filename, '"\\') . '"',
                'Cache-Control' => 'private, max-age=300',
            ],
        ];
    }

    protected function loadSubmissionData(Submission $submission): array
    {
        if ($submission->kobo_id) {
            return $this->api->submission($submission->asset_uid, $submission->kobo_id);
        }

        $data = $this->api->findSubmissionByUuid($submission->asset_uid, (string) $submission->kobo_uuid);
        if (!$data) {
            throw new ApplicationException(trans('quivi.kobo::lang.review.errors.submission_not_found_by_uuid', [
                'uuid' => $submission->kobo_uuid,
            ]));
        }

        return $data;
    }

    protected function safeAsset(string $assetUid): array
    {
        try {
            return $this->api->asset($assetUid);
        } catch (\Throwable $ex) {
            return [];
        }
    }

    protected function buildFieldMap(array $asset): array
    {
        $content = $asset['content'] ?? [];
        $choices = $this->buildChoices($content['choices'] ?? []);
        $fields = [];
        $groups = [];

        foreach (($content['survey'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = trim((string) ($row['type'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $baseType = strtolower(strtok($type, ' ') ?: $type);

            if (in_array($baseType, ['end_group', 'end_repeat'], true)) {
                array_pop($groups);
                continue;
            }

            if ($name === '') {
                continue;
            }

            if (in_array($baseType, ['begin_group', 'begin_repeat'], true)) {
                $groups[] = $name;
                continue;
            }

            $path = trim(implode('/', array_merge($groups, [$name])), '/');
            $listName = trim((string) ($row['select_from_list_name'] ?? '')) ?: null;

            if (!$listName && preg_match('/^select_(one|multiple)\s+([^\s]+)/i', $type, $matches)) {
                $listName = $matches[2];
            }

            $label = $this->readLocalizedText($row['label'] ?? null);
            $field = [
                'name' => $name,
                'path' => $path,
                'label' => $label ?: $name,
                'has_label' => $label !== '',
                'type' => $baseType,
                'raw_type' => $type,
                'choices' => $listName ? ($choices[$listName] ?? []) : [],
            ];

            foreach ($this->fieldAliases($name, $path) as $alias) {
                $fields[$alias] = $field;
            }
        }

        return $fields;
    }

    protected function buildChoices(array $rows): array
    {
        $choices = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $listName = trim((string) ($row['list_name'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($listName === '' || $name === '') {
                continue;
            }

            $choices[$listName][$name] = $this->readLocalizedText($row['label'] ?? null) ?: $name;
        }

        return $choices;
    }

    protected function buildAnswers(array $flatData, array $fields): array
    {
        $answers = [];

        foreach ($flatData as $key => $value) {
            if ($this->isMetaKey($key) || $this->isEmptyValue($value)) {
                continue;
            }

            $field = $this->findField($key, $fields);

            if ($this->shouldHideAnswer($field)) {
                continue;
            }

            $answers[] = [
                'key' => $key,
                'label' => $field['label'] ?? $key,
                'type' => $field['raw_type'] ?? null,
                'value' => $this->formatValue($value, $field),
                'raw_value' => $value,
            ];
        }

        return $answers;
    }

    protected function shouldHideAnswer(?array $field): bool
    {
        if (!$field || ($field['has_label'] ?? false)) {
            return false;
        }

        return in_array($field['type'] ?? '', ['calculate', 'hidden', 'note'], true);
    }

    protected function buildAttachments(Submission $submission, array $data, array $flatData, array $fields): array
    {
        $attachments = [];

        foreach ($this->extractAttachments($data) as $index => $attachment) {
            $filename = $this->attachmentFilename($attachment, $index);
            $fieldKey = $this->findAttachmentField($filename, $flatData);
            $field = $fieldKey ? $this->findField($fieldKey, $fields) : null;

            $attachments[] = [
                'index' => $index,
                'filename' => $filename,
                'label' => $field['label'] ?? $filename,
                'mime' => $this->attachmentMime($attachment, $filename),
                'kind' => $this->attachmentKind($attachment, $filename),
                'url' => Backend::url('quivi/kobo/submissions/media/' . $submission->id . '/' . $index),
                'size' => $attachment['size'] ?? $attachment['filesize'] ?? null,
            ];
        }

        return $attachments;
    }

    protected function buildMetadata(array $data): array
    {
        $metadata = [];

        foreach (self::META_KEYS as $key) {
            if ($key === '_attachments' || !array_key_exists($key, $data)) {
                continue;
            }

            $metadata[] = [
                'key' => $key,
                'value' => $this->formatScalar($data[$key]),
            ];
        }

        return $metadata;
    }

    protected function extractAttachments(array $data): array
    {
        $attachments = $data['_attachments'] ?? [];
        return is_array($attachments) ? array_values($attachments) : [];
    }

    protected function flattenSubmission(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '/' . $key;

            if (is_array($value) && $this->isAssoc($value)) {
                $flat += $this->flattenSubmission($value, $path);
                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }

    protected function findField(string $key, array $fields): ?array
    {
        foreach ($this->fieldAliases(basename(str_replace('\\', '/', $key)), $key) as $alias) {
            if (isset($fields[$alias])) {
                return $fields[$alias];
            }
        }

        return null;
    }

    protected function fieldAliases(string $name, string $path): array
    {
        $aliases = [
            $name,
            $path,
            trim($path, '/'),
            basename(str_replace('\\', '/', $path)),
        ];

        return array_values(array_unique(array_filter($aliases, static fn ($alias) => $alias !== '')));
    }

    protected function formatValue(mixed $value, ?array $field): string
    {
        if (!$field) {
            return $this->formatScalar($value);
        }

        $valueKey = is_scalar($value) || $value === null ? (string) $value : null;

        if (($field['type'] ?? '') === 'select_one' && $valueKey !== null && isset($field['choices'][$valueKey])) {
            return $field['choices'][$valueKey];
        }

        if (($field['type'] ?? '') === 'select_multiple' && is_string($value)) {
            $labels = [];
            foreach (preg_split('/\s+/', trim($value)) ?: [] as $choice) {
                if ($choice === '') {
                    continue;
                }
                $labels[] = $field['choices'][(string) $choice] ?? $choice;
            }

            return implode(', ', $labels);
        }

        return $this->formatScalar($value);
    }

    protected function formatScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value
                ? trans('quivi.kobo::lang.review.values.boolean_yes')
                : trans('quivi.kobo::lang.review.values.boolean_no');
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '' : $encoded;
    }

    protected function readLocalizedText(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        if ($this->isListArray($value)) {
            foreach ($this->preferredLanguageIndexes() as $index) {
                if (!array_key_exists($index, $value)) {
                    continue;
                }

                $label = $this->readLocalizedText($value[$index]);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        foreach ($this->preferredLanguageCodes() as $languageCode) {
            foreach ($value as $key => $translation) {
                if (!$this->matchesLanguageCode((string) $key, $languageCode)) {
                    continue;
                }

                $label = $this->readLocalizedText($translation);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        foreach ($value as $translation) {
            $label = $this->readLocalizedText($translation);
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }

    protected function preferredLanguageIndexes(): array
    {
        return match ($this->currentPreviewLanguage()) {
            'it' => [0, 1, 2],
            'ar' => [2, 1, 0],
            default => [1, 0, 2],
        };
    }

    protected function preferredLanguageCodes(): array
    {
        return match ($this->currentPreviewLanguage()) {
            'it' => ['it', 'en', 'ar'],
            'ar' => ['ar', 'en', 'it'],
            default => ['en', 'it', 'ar'],
        };
    }

    protected function currentPreviewLanguage(): string
    {
        $locale = strtolower(str_replace('_', '-', (string) \App::getLocale()));

        if (str_starts_with($locale, 'it')) {
            return 'it';
        }

        if (str_starts_with($locale, 'ar')) {
            return 'ar';
        }

        return 'en';
    }

    protected function matchesLanguageCode(string $key, string $languageCode): bool
    {
        $normalized = mb_strtolower(trim($key));
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];

        if ($normalized === $languageCode) {
            return true;
        }

        $aliases = match ($languageCode) {
            'it' => ['it', 'ita', 'italian', 'italiano'],
            'ar' => ['ar', 'ara', 'arab', 'arabic', 'العربية', 'عربي'],
            default => ['en', 'eng', 'english'],
        };

        foreach ($tokens as $token) {
            if (in_array($token, $aliases, true)) {
                return true;
            }
        }

        return $languageCode === 'ar' && str_contains($normalized, 'عرب');
    }

    protected function findAttachmentField(string $filename, array $flatData): ?string
    {
        $basename = basename($filename);

        foreach ($flatData as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            foreach (preg_split('/\s+/', trim($value)) ?: [] as $candidate) {
                if (basename($candidate) === $basename) {
                    return $key;
                }
            }
        }

        return null;
    }

    protected function attachmentFilename(array $attachment, int $index): string
    {
        $filename = $attachment['filename']
            ?? $attachment['name']
            ?? $attachment['media_file_basename']
            ?? null;

        if (!$filename && !empty($attachment['download_url'])) {
            $path = (string) parse_url((string) $attachment['download_url'], PHP_URL_PATH);
            $filename = basename($path);
        }

        return $filename ?: 'attachment-' . ($index + 1);
    }

    protected function attachmentMime(array $attachment, string $filename, array $headers = []): string
    {
        $mime = $attachment['mimetype']
            ?? $attachment['mime_type']
            ?? $headers['Content-Type']
            ?? $headers['content-type']
            ?? null;

        if ($mime) {
            return strtok((string) $mime, ';') ?: 'application/octet-stream';
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'ogg', 'opus' => 'audio/ogg',
            'wav' => 'audio/wav',
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };
    }

    protected function attachmentKind(array $attachment, string $filename): string
    {
        $mime = $this->attachmentMime($attachment, $filename);

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return 'file';
    }

    protected function isMetaKey(string $key): bool
    {
        if (in_array($key, self::META_KEYS, true)) {
            return true;
        }

        return str_starts_with($key, '_') || str_starts_with($key, '__');
    }

    protected function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    protected function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    protected function isListArray(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    protected function error(string $message): array
    {
        return [
            'ok' => false,
            'error' => $message,
            'answers' => [],
            'attachments' => [],
            'metadata' => [],
            'raw' => [],
        ];
    }
}
