<?php

use Illuminate\Support\Facades\Route;
use Ppcharlier\StatamicEditorApi\Http\Auth\MeController;
use Ppcharlier\StatamicEditorApi\Http\Auth\TokensController;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiError;

Route::post('auth/tokens', [TokensController::class, 'store'])
    ->middleware('throttle:editor-api-auth')
    ->name('auth.tokens.store');

Route::middleware(['editor-api.auth', 'throttle:editor-api'])->group(function () {
    Route::delete('auth/tokens/current', [TokensController::class, 'destroyCurrent'])->name('auth.tokens.destroy');

    Route::get('ping', fn () => response()->json(['data' => ['pong' => true]]))->name('ping');

    Route::get('me', MeController::class)->name('me');
});

if (app()->runningUnitTests()) {
    Route::middleware('editor-api.auth')
        ->get('_protected', fn () => response()->json(['data' => ['user' => request()->user()->email()]]));

    Route::get('_boom', fn () => throw new RuntimeException('secret detail'));
    Route::post('_validate', function () {
        request()->validate(['title' => 'required']);

        return response()->json(['data' => ['ok' => true]]);
    });

    Route::middleware(['editor-api.auth', 'editor-api.can:entries,edit,collection'])
        ->get('_guarded/{collection}', fn () => response()->json(['data' => ['ok' => true]]));
}

Route::any('{any}', fn () => ApiError::response('not_found', 'Not found.', 404))->where('any', '.*');
