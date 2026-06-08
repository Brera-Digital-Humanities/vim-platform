<?php namespace Quivi\Kobo\Classes;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Quivi\Kobo\Models\Submission;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Winter\Storm\Exception\ApplicationException;
use Winter\User\Models\User;

class SubmissionService
{
    public function start(Request $request): Submission
    {
        $user = $request->attributes->get('api_user');
        if (!$user instanceof User) {
            throw new ApplicationException(trans('quivi.kobo::lang.review.errors.authenticated_user_not_found'));
        }

        $xml = $this->submissionXml($request);
        $assetUid = $this->extractAssetUid($xml) ?: $request->input('kobo_asset_uid') ?: null;
        $koboUuid = $this->extractInstanceUuid($xml);

        $submission = $this->findExisting($request, $user, $koboUuid) ?: new Submission();
        $submission->user_id = $user->id;
        $submission->asset_uid = $submission->asset_uid ?: $assetUid;
        $submission->kobo_uuid = $submission->kobo_uuid ?: $koboUuid;

        if ($submission->status !== Submission::STATUS_DONE) {
            $submission->status = Submission::STATUS_CREATED;
            $submission->error = null;
        }

        $submission->save();

        return $submission;
    }

    public function markDone(Submission $submission, Api $api, array $koboResult): Submission
    {
        $metadata = $this->metadataFromOpenRosaResponse((string) ($koboResult['body'] ?? ''));
        $koboUuid = $metadata['uuid'] ?: $submission->kobo_uuid;
        $koboId = null;

        if ($submission->asset_uid && $koboUuid) {
            $remote = $api->findSubmissionByUuid($submission->asset_uid, $koboUuid);
            if ($remote) {
                $koboId = $remote['_id'] ?? $remote['id'] ?? null;
                $koboUuid = $remote['_uuid'] ?? $remote['meta/instanceID'] ?? $koboUuid;
            }
        }

        $submission->status = Submission::STATUS_DONE;
        $submission->kobo_id = $koboId ?: $submission->kobo_id;
        $submission->kobo_uuid = $this->normalizeUuid($koboUuid) ?: $submission->kobo_uuid;
        $submission->error = null;
        $submission->save();

        return $submission;
    }

    public function markError(Submission $submission, string $error): Submission
    {
        $submission->status = Submission::STATUS_ERROR;
        $submission->error = $this->shortError($error);
        $submission->save();

        return $submission;
    }

    protected function findExisting(Request $request, User $user, ?string $koboUuid): ?Submission
    {
        $submissionId = $request->input('submission_id');
        if ($submissionId) {
            $submission = Submission::where('id', (int) $submissionId)
                ->where('user_id', $user->id)
                ->first();

            if (!$submission) {
                throw new ApplicationException(trans('quivi.kobo::lang.review.errors.submission_not_found'));
            }

            return $submission;
        }

        if ($koboUuid) {
            return Submission::where('kobo_uuid', $koboUuid)
                ->where('user_id', $user->id)
                ->first();
        }

        return null;
    }

    protected function submissionXml(Request $request): string
    {
        $xmlFile = $request->file('xml_submission_file');
        if ($xmlFile instanceof UploadedFile) {
            return trim((string) file_get_contents($xmlFile->getPathname()));
        }

        return trim((string) $request->input('xml_submission_file'));
    }

    protected function extractAssetUid(string $xml): ?string
    {
        if ($xml === '') {
            return null;
        }

        $document = $this->xmlDocument($xml);
        if (!$document || !$document->documentElement) {
            return null;
        }

        return $document->documentElement->getAttribute('id') ?: null;
    }

    protected function extractInstanceUuid(string $xml): ?string
    {
        if ($xml === '') {
            return null;
        }

        $document = $this->xmlDocument($xml);
        if ($document) {
            $xpath = new DOMXPath($document);
            $nodes = $xpath->query('//*[local-name() = "instanceID"]');
            if ($nodes && $nodes->length) {
                return $this->normalizeUuid($nodes->item(0)->textContent);
            }
        }

        if (preg_match('#<[^>]*instanceID[^>]*>([^<]+)</[^>]*instanceID>#i', $xml, $matches)) {
            return $this->normalizeUuid($matches[1]);
        }

        return null;
    }

    protected function metadataFromOpenRosaResponse(string $body): array
    {
        $uuid = null;

        $document = $this->xmlDocument($body);
        if ($document) {
            $xpath = new DOMXPath($document);
            foreach (['instanceID', 'deprecatedID'] as $nodeName) {
                $nodes = $xpath->query('//*[local-name() = "' . $nodeName . '"]');
                if ($nodes && $nodes->length) {
                    $uuid = $this->normalizeUuid($nodes->item(0)->textContent);
                    if ($uuid) break;
                }
            }
        }

        return ['uuid' => $uuid];
    }

    protected function xmlDocument(string $xml): ?DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    protected function normalizeUuid(?string $value): ?string
    {
        $value = trim((string) $value);
        $value = preg_replace('/^uuid:/i', '', $value);

        if ($value === '') {
            return null;
        }

        return substr($value, 0, 36);
    }

    protected function shortError(string $error): string
    {
        $error = trim($error);

        return strlen($error) > 10000 ? substr($error, 0, 10000) : $error;
    }
}
