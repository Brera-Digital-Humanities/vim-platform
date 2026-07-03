<?php

use Illuminate\Http\Request;
use Quivi\Kobo\Classes\Api as KoboApi;
use Quivi\Kobo\Classes\ExternalFileStorage;
use Quivi\Kobo\Classes\SubmissionPreview;
use Quivi\Kobo\Classes\SubmissionService;
use Quivi\Kobo\Models\Submission;
use Quivi\Profile\Classes\JwtMiddleware;
use Winter\User\Facades\Auth;
use Winter\Storm\Exception\ApplicationException;

Route::group(['prefix' => 'api/v1/kobo'], function () {

    Route::group(['middleware' => [JwtMiddleware::class]], function () {
        Route::post('uploads/init', function (Request $request) {
            try {
                return Response::json((new ExternalFileStorage())->initUpload(
                    $request->attributes->get('api_user'),
                    $request->all()
                ));
            } catch (ApplicationException $ex) {
                return Response::json(['error' => $ex->getMessage()], 422);
            } catch (\Throwable $ex) {
                return Response::json(['error' => $ex->getMessage()], 500);
            }
        });

        Route::post('uploads/part-url', function (Request $request) {
            try {
                return Response::json((new ExternalFileStorage())->partUrl(
                    $request->attributes->get('api_user'),
                    $request->all()
                ));
            } catch (ApplicationException $ex) {
                return Response::json(['error' => $ex->getMessage()], 422);
            } catch (\Throwable $ex) {
                return Response::json(['error' => $ex->getMessage()], 500);
            }
        });

        Route::post('uploads/proxy-single', function (Request $request) {
            try {
                $payload = json_decode((string) $request->input('payload', '{}'), true) ?: [];

                return Response::json((new ExternalFileStorage())->proxySingleUpload(
                    $request->attributes->get('api_user'),
                    $payload,
                    $request->file('blob')
                ));
            } catch (ApplicationException $ex) {
                return Response::json(['error' => $ex->getMessage()], 422);
            } catch (\Throwable $ex) {
                return Response::json(['error' => $ex->getMessage()], 500);
            }
        });

        Route::post('uploads/proxy-part', function (Request $request) {
            try {
                $payload = json_decode((string) $request->input('payload', '{}'), true) ?: [];

                return Response::json((new ExternalFileStorage())->proxyPartUpload(
                    $request->attributes->get('api_user'),
                    $payload,
                    $request->file('blob')
                ));
            } catch (ApplicationException $ex) {
                return Response::json(['error' => $ex->getMessage()], 422);
            } catch (\Throwable $ex) {
                return Response::json(['error' => $ex->getMessage()], 500);
            }
        });

        Route::post('uploads/complete', function (Request $request) {
            try {
                return Response::json((new ExternalFileStorage())->completeUpload(
                    $request->attributes->get('api_user'),
                    $request->all()
                ));
            } catch (ApplicationException $ex) {
                return Response::json(['error' => $ex->getMessage()], 422);
            } catch (\Throwable $ex) {
                return Response::json(['error' => $ex->getMessage()], 500);
            }
        });

        Route::post('uploads/abort', function (Request $request) {
            try {
                return Response::json((new ExternalFileStorage())->abortUpload(
                    $request->attributes->get('api_user'),
                    $request->all()
                ));
            } catch (ApplicationException $ex) {
                return Response::json(['error' => $ex->getMessage()], 422);
            } catch (\Throwable $ex) {
                return Response::json(['error' => $ex->getMessage()], 500);
            }
        });

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

Route::get('user/submission-media/{recordId}/{attachmentIndex}', function ($recordId, $attachmentIndex) {
    $user = Auth::getUser();
    if (!$user) {
        return Response::make(trans('quivi.kobo::lang.review.errors.access_denied'), 403, ['Content-Type' => 'text/plain']);
    }

    $submission = Submission::where('id', (int) $recordId)
        ->where('user_id', $user->id)
        ->first();

    if (!$submission) {
        return Response::make(trans('quivi.kobo::lang.review.errors.submission_not_found'), 404, ['Content-Type' => 'text/plain']);
    }

    try {
        $media = SubmissionPreview::make()->downloadAttachment($submission, (int) $attachmentIndex);
    } catch (\Throwable $ex) {
        return Response::make($ex->getMessage(), 500, ['Content-Type' => 'text/plain']);
    }

    return Response::make($media['body'], 200, $media['headers']);
})->middleware('web');

Route::get('user/submission-external-file/{recordId}/{fileId}', function ($recordId, $fileId) {
    $user = Auth::getUser();
    if (!$user) {
        return Response::make(trans('quivi.kobo::lang.review.errors.access_denied'), 403, ['Content-Type' => 'text/plain']);
    }

    $submission = Submission::where('id', (int) $recordId)
        ->where('user_id', $user->id)
        ->first();

    if (!$submission) {
        return Response::make(trans('quivi.kobo::lang.review.errors.submission_not_found'), 404, ['Content-Type' => 'text/plain']);
    }

    try {
        $file = SubmissionPreview::make()->findExternalFile($submission, (string) $fileId);
        if (!$file) {
            return Response::make(trans('quivi.kobo::lang.review.errors.external_file_not_found'), 404, ['Content-Type' => 'text/plain']);
        }

        return Redirect::away((new ExternalFileStorage())->temporaryDownloadUrl($file));
    } catch (\Throwable $ex) {
        return Response::make($ex->getMessage(), 500, ['Content-Type' => 'text/plain']);
    }
})->middleware('web');
