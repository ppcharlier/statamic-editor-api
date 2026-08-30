<?php

use Illuminate\Support\Facades\Route;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiError;

Route::get('ping', fn () => response()->json(['data' => ['pong' => true]]))->name('ping');

// Test route for authentication testing
Route::middleware('editor-api.auth')
    ->get('_protected', fn () => response()->json(['data' => ['user' => request()->user()->email()]]))
    ->name('protected');

Route::any('{any}', fn () => ApiError::response('not_found', 'Not found.', 404))->where('any', '.*');
