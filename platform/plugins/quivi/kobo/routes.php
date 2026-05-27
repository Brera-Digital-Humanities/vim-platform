<?php

use Illuminate\Http\Request;
use Quivi\Kobo\Classes\Api as KoboApi;
use Quivi\Profile\Classes\JwtMiddleware;
use Winter\Storm\Exception\ApplicationException;

Route::group(['prefix' => 'api/v1/kobo'], function () {

    Route::group(['middleware' => [JwtMiddleware::class]], function () {
        Route::post('submissions', function (Request $request) {
            if (!$request->hasFile('xml_submission_file') && !$request->input('xml_submission_file')) {
                return Response::json(['error' => 'xml_submission_file is required.'], 422);
            }

            try {
                $result = KoboApi::make(
                    null,
                    null,
                    (int) env('KOBO_SUBMISSION_TIMEOUT', 120),
                    filter_var(env('KOBO_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN)
                )->submitOpenRosa($request);
            } catch (ApplicationException $ex) {
                return Response::json(['error' => $ex->getMessage()], 500);
            } catch (\Throwable $ex) {
                return Response::json(['error' => 'Kobo submission error: ' . $ex->getMessage()], 500);
            }

            $payload = [
                'success' => $result['ok'],
                'status' => $result['status'],
            ];

            if (!$result['ok']) {
                $payload['error'] = 'Kobo submission failed.';
                $payload['kobo_response'] = $result['body'];
            }

            return Response::json($payload, $result['status'] ?: 502);
        });
    });
});
