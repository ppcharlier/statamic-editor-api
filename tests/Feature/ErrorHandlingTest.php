<?php

it('returns the standard envelope for unknown routes inside the prefix', function () {
    $this->getJson('/api/editor/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('converts uncaught exceptions to server_error without leaking details', function () {
    $this->app['router']->get('/_test/boom', fn () => throw new RuntimeException('secret detail'))
        ->middleware('editor-api.errors');

    $response = $this->getJson('/_test/boom');

    $response->assertStatus(500)->assertJson(['error' => ['code' => 'server_error']]);
    expect($response->json('error.message'))->not->toContain('secret detail');
});
