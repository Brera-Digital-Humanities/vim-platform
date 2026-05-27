<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Quivi\Profile\Classes\JwtMiddleware;
use Quivi\Profile\Classes\JwtService;
use Quivi\Profile\Classes\UserResource;
use Winter\Storm\Auth\AuthenticationException;
use Winter\User\Models\Settings as UserSettings;
use Winter\User\Models\User as UserModel;

Route::group(['prefix' => 'api/v1/users'], function () {

    Route::post('login', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'login' => 'required_without:email|string',
            'email' => 'required_without:login|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return Response::json(['errors' => $validator->errors()], 422);
        }

        $login = $request->input('login', $request->input('email'));
        $credentials = [
            'login' => $login,
            'password' => $request->input('password'),
        ];

        try {
            $user = Auth::authenticate($credentials, false);
        } catch (AuthenticationException $ex) {
            return Response::json(['error' => 'Invalid credentials.'], 401);
        }

        if ($user->isBanned()) {
            Auth::logout();
            return Response::json(['error' => 'User is banned.'], 403);
        }

        if ($request->ip()) {
            $user->touchIpAddress($request->ip());
        }

        $token = (new JwtService())->issueForUser($user);

        return Response::json([
            'access_token' => $token['token'],
            'token_type' => $token['token_type'],
            'expires_in' => $token['expires_in'],
            'user' => UserResource::make($user),
        ]);
    });


    Route::group(['middleware' => [JwtMiddleware::class]], function () {
        Route::get('logged', function (Request $request) {
            return Response::json(UserResource::make($request->attributes->get('api_user')));
        });

        Route::match(['put', 'patch'], 'profile', function (Request $request) {
            $user = $request->attributes->get('api_user');

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|between:2,255',
                'surname' => 'sometimes|required|string|between:2,255',
                'username' => 'sometimes|required|string|between:2,255|unique:users,username,' . $user->id,
                'email' => 'sometimes|required|email|between:6,255|unique:users,email,' . $user->id,
                'birth_date' => 'sometimes|required|date_format:Y-m-d|before:today',
            ]);

            if ($validator->fails()) {
                return Response::json(['errors' => $validator->errors()], 422);
            }

            $user->fill($request->only([
                'name',
                'surname',
                'username',
                'email',
                'birth_date',
            ]));

            $user->save();

            return Response::json(UserResource::make($user->fresh()));
        });

        Route::post('refresh', function (Request $request) {
            $token = (new JwtService())->issueForUser($request->attributes->get('api_user'));

            return Response::json([
                'access_token' => $token['token'],
                'token_type' => $token['token_type'],
                'expires_in' => $token['expires_in'],
            ]);
        });

        Route::post('logout', function () {
            return Response::json(['success' => true]);
        });
    });
});
