<?php

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiError;

Route::get('ping', fn () => response()->json(['data' => ['pong' => true]]))->name('ping');

if (app()->runningUnitTests()) {
    Route::group(['middleware' => ['editor-api.errors', 'editor-api.auth']], function () {
        Route::get('_protected', fn () => response()->json(['data' => ['user' => request()->user()->email()]]));
    });
}

Route::any('{any}', fn () => ApiError::response('not_found', 'Not found.', 404))->where('any', '.*');
