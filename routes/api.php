<?php

use Illuminate\Support\Facades\Route;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiError;

Route::get('ping', fn () => response()->json(['data' => ['pong' => true]]))->name('ping');

if (app()->runningUnitTests()) {
    Route::middleware('editor-api.auth')
        ->get('_protected', fn () => response()->json(['data' => ['user' => request()->user()->email()]]));

    Route::get('_boom', fn () => throw new RuntimeException('secret detail'));
    Route::post('_validate', function () {
        request()->validate(['title' => 'required']);

        return response()->json(['data' => ['ok' => true]]);
    });
}

Route::any('{any}', fn () => ApiError::response('not_found', 'Not found.', 404))->where('any', '.*');
