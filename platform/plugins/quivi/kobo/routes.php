<?php

use Illuminate\Http\Request;
use Quivi\Kobo\Classes\Api as KoboApi;
use Quivi\Kobo\Classes\SubmissionService;
use Quivi\Kobo\Models\Submission;
use Quivi\Profile\Classes\JwtMiddleware;
use Winter\Storm\Exception\ApplicationException;

Route::group(['prefix' => 'api/v1/kobo'], function () {

    Route::group(['middleware' => [JwtMiddleware::class]], function () {
        Route::post('submissions', function (Request $request) {
            if (!$request->hasFile('xml_submission_file') && !$request->input('xml_submission_file')) {
                return Response::json(['error' => trans('quivi.kobo::lang.api.errors.xml_submission_required')], 422);
            }

            $submission = null;
            $tracker = new SubmissionService();

            try {
                $submission = $tracker->start($request);

                if ($submission->status === Submission::STATUS_DONE) {
                    return Response::json([
                        'success' => true,
                        'status' => 200,
                        'submission_id' => $submission->id,
                        'kobo_id' => $submission->kobo_id,
                        'kobo_uuid' => $submission->kobo_uuid,
                    ]);
                }

                $api = KoboApi::make(
                    null,
                    null,
                    (int) env('KOBO_SUBMISSION_TIMEOUT', 120),
                    filter_var(env('KOBO_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN)
                );

                $result = $api->submitOpenRosa($request);
            } catch (ApplicationException $ex) {
                if ($submission) {
                    $tracker->markError($submission, $ex->getMessage());
                }

                return Response::json([
                    'success' => false,
                    'submission_id' => $submission ? $submission->id : null,
                    'kobo_id' => $submission ? $submission->kobo_id : null,
                    'kobo_uuid' => $submission ? $submission->kobo_uuid : null,
                    'error' => $ex->getMessage(),
                ], 500);
            } catch (\Throwable $ex) {
                $error = trans('quivi.kobo::lang.api.errors.submission_error', [
                    'error' => $ex->getMessage(),
                ]);
                if ($submission) {
                    $tracker->markError($submission, $error);
                }

                return Response::json([
                    'success' => false,
                    'submission_id' => $submission ? $submission->id : null,
                    'kobo_id' => $submission ? $submission->kobo_id : null,
                    'kobo_uuid' => $submission ? $submission->kobo_uuid : null,
                    'error' => $error,
                ], 500);
            }

            $payload = [
                'success' => $result['ok'],
                'status' => $result['status'],
                'submission_id' => $submission->id,
                'kobo_id' => $submission->kobo_id,
                'kobo_uuid' => $submission->kobo_uuid,
            ];

            if (!$result['ok']) {
                $error = trans('quivi.kobo::lang.api.errors.submission_failed');
                if (!empty($result['body'])) {
                    $error .= ' ' . $result['body'];
                }

                $tracker->markError($submission, $error);
                $payload['error'] = trans('quivi.kobo::lang.api.errors.submission_failed');
                $payload['kobo_response'] = $result['body'];

                return Response::json($payload, $result['status'] ?: 502);
            }

            $submission = $tracker->markDone($submission, $api, $result);
            $payload['kobo_id'] = $submission->kobo_id;
            $payload['kobo_uuid'] = $submission->kobo_uuid;

            return Response::json($payload, $result['status'] ?: 200);
        });
    });
});
