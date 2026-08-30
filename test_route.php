<?php

use Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\User;

it('debug route', function () {
    Route::middleware(['api', 'editor-api.errors', 'editor-api.auth'])
        ->get('/_test/protected', fn () => response()->json(['data' => ['user' => request()->user()->email()]]));

    $user = tap(User::make()->email('pp@example.com')->makeSuper())->save();
    
    $response = $this->getJson('/_test/protected');
    dump('Response status: ' . $response->status());
    dump('Response body: ');
    dump($response->getContent());
})->only();
