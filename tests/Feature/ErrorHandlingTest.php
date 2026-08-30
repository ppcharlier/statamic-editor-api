<?php

it('returns the standard envelope for unknown routes inside the prefix', function () {
    $this->getJson('/api/editor/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('converts uncaught exceptions to server_error without leaking details over real HTTP', function () {
    $response = $this->getJson('/api/editor/v1/_boom');

    $response->assertStatus(500)->assertJson(['error' => ['code' => 'server_error']]);
    expect($response->json('error.message'))->not->toContain('secret detail');
});

it('renders controller validation failures in the standard envelope', function () {
    $this->postJson('/api/editor/v1/_validate', [])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['title']]]);
});

it('does not intercept errors outside the editor api prefix', function () {
    $this->getJson('/definitely-not-editor-api-xyz')->assertStatus(404);
    expect($this->getJson('/definitely-not-editor-api-xyz')->json('error.code'))->toBeNull();
});
