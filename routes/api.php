<?php

use Illuminate\Support\Facades\Route;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiError;

Route::get('ping', fn () => response()->json(['data' => ['pong' => true]]))->name('ping');

Route::fallback(fn () => ApiError::response('not_found', 'Not found.', 404));
