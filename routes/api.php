<?php

use Illuminate\Support\Facades\Route;

Route::get('ping', fn () => response()->json(['data' => ['pong' => true]]))->name('ping');
