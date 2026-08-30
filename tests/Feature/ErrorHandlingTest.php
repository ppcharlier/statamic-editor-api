<?php

it('returns the standard envelope for unknown routes inside the prefix', function () {
    $this->getJson('/api/editor/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('converts uncaught exceptions to server_error without leaking details', function () {
    \Illuminate\Support\Facades\Route::middleware(['api', 'editor-api.errors'])
        ->get('/api/editor/v1/_boom', fn () => throw new RuntimeException('secret detail'));

    $response = $this->getJson('/api/editor/v1/_boom');

    $response->assertStatus(500)->assertJson(['error' => ['code' => 'server_error']]);
    expect($response->json('error.message'))->not->toContain('secret detail');
});
