<?php

use Illuminate\Support\Facades\Route;
use Ppcharlier\StatamicEditorApi\Http\Auth\MeController;
use Ppcharlier\StatamicEditorApi\Http\Auth\TokensController;
use Ppcharlier\StatamicEditorApi\Http\Config\ConfigController;
use Ppcharlier\StatamicEditorApi\Http\Errors\NotFoundController;

Route::post('auth/tokens', [TokensController::class, 'store'])
    ->middleware('throttle:editor-api-auth')
    ->name('auth.tokens.store');

Route::middleware(['editor-api.auth', 'throttle:editor-api'])->group(function () {
    Route::delete('auth/tokens/current', [TokensController::class, 'destroyCurrent'])->name('auth.tokens.destroy');

    Route::get('config', ConfigController::class)->name('config');

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

    Route::get('_forbidden', fn () => abort(403));
    Route::get('_teapot', fn () => abort(418));
}

Route::any('{any}', NotFoundController::class)->where('any', '.*');
