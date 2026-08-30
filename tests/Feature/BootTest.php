<?php

it('merges the addon config with defaults', function () {
    expect(config('statamic.editor-api.route_prefix'))->toBe('api/editor')
        ->and(config('statamic.editor-api.auth.driver'))->toBe('file')
        ->and(config('statamic.editor-api.resources.collections'))->toBeTrue();
});

it('registers versioned routes under the configured prefix', function () {
    $this->getJson('/api/editor/v1/ping')
        ->assertStatus(401)
        ->assertJson(['error' => ['code' => 'unauthenticated']]);
});
