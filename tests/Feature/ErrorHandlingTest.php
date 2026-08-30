<?php

it('returns the standard envelope for unknown routes inside the prefix', function () {
    $this->getJson('/api/editor/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('converts uncaught exceptions to server_error without leaking details', function () {
    $middleware = new \Ppcharlier\StatamicEditorApi\Http\Errors\HandleApiErrors();
    $response = $middleware->handle(
        \Illuminate\Http\Request::create('/api/editor/v1/anything'),
        fn () => throw new RuntimeException('secret detail'),
    );

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['error']['code'])->toBe('server_error')
        ->and($response->getData(true)['error']['message'])->not->toContain('secret detail');
});
