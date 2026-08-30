<?php

use Illuminate\Support\Facades\Route;
use Ppcharlier\StatamicEditorApi\Http\Auth\MeController;
use Ppcharlier\StatamicEditorApi\Http\Auth\TokensController;
use Ppcharlier\StatamicEditorApi\Http\Blueprints\BlueprintsController;
use Ppcharlier\StatamicEditorApi\Http\Config\ConfigController;
use Ppcharlier\StatamicEditorApi\Http\Entries\EntriesController;
use Ppcharlier\StatamicEditorApi\Http\Errors\NotFoundController;

Route::post('auth/tokens', [TokensController::class, 'store'])
    ->middleware('throttle:editor-api-auth')
    ->name('auth.tokens.store');

Route::middleware(['editor-api.auth', 'throttle:editor-api'])->group(function () {
    Route::delete('auth/tokens/current', [TokensController::class, 'destroyCurrent'])->name('auth.tokens.destroy');

    Route::get('config', ConfigController::class)->name('config');

    Route::get('me', MeController::class)->name('me');

    Route::get('collections/{collection}/blueprints', [BlueprintsController::class, 'index'])
        ->middleware('editor-api.can:entries,view,collection')->name('blueprints.index');
    Route::get('collections/{collection}/blueprints/{blueprint}', [BlueprintsController::class, 'show'])
        ->middleware('editor-api.can:entries,view,collection')->name('blueprints.show');

    Route::get('collections/{collection}/entries', [EntriesController::class, 'index'])
        ->middleware('editor-api.can:entries,view,collection')->name('entries.index');
    Route::post('collections/{collection}/entries', [EntriesController::class, 'store'])
        ->middleware('editor-api.can:entries,create,collection')->name('entries.store');

    Route::get('entries/{id}', [EntriesController::class, 'show'])->name('entries.show');
    Route::patch('entries/{id}', [EntriesController::class, 'update'])->name('entries.update');
    Route::delete('entries/{id}', [EntriesController::class, 'destroy'])->name('entries.destroy');
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
