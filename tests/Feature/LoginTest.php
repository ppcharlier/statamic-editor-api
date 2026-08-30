<?php

use Illuminate\Support\Facades\Hash;
use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\User;

beforeEach(function () {
    $this->user = tap(User::make()->email('pp@example.com')->password('secret-password')->makeSuper())->save();
});

it('issues a token for valid credentials', function () {
    $response = $this->postJson('/api/editor/v1/auth/tokens', [
        'email' => 'pp@example.com',
        'password' => 'secret-password',
        'device_name' => 'iPhone de Pierre',
    ]);

    $response->assertStatus(201);

    $plainText = $response->json('data.token');
    expect($plainText)->toBeString()->not->toBeEmpty();

    $stored = app(TokenRepository::class)->findByPlainText($plainText);
    expect($stored->userId)->toBe($this->user->id())
        ->and($stored->name)->toBe('iPhone de Pierre');
});

it('rejects a wrong password with invalid_credentials', function () {
    $this->postJson('/api/editor/v1/auth/tokens', [
        'email' => 'pp@example.com',
        'password' => 'wrong',
        'device_name' => 'iPhone',
    ])->assertStatus(401)->assertJson(['error' => ['code' => 'invalid_credentials']]);
});

it('rejects an unknown email with invalid_credentials (no user enumeration)', function () {
    $this->postJson('/api/editor/v1/auth/tokens', [
        'email' => 'nobody@example.com',
        'password' => 'whatever',
        'device_name' => 'iPhone',
    ])->assertStatus(401)->assertJson(['error' => ['code' => 'invalid_credentials']]);
});

it('validates the payload', function () {
    $this->postJson('/api/editor/v1/auth/tokens', [])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['email', 'password', 'device_name']]]);
});

it('rate limits login attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/editor/v1/auth/tokens', [
            'email' => 'pp@example.com', 'password' => 'wrong', 'device_name' => 'iPhone',
        ])->assertStatus(401);
    }

    $this->postJson('/api/editor/v1/auth/tokens', [
        'email' => 'pp@example.com', 'password' => 'wrong', 'device_name' => 'iPhone',
    ])->assertStatus(429)->assertJson(['error' => ['code' => 'rate_limited']]);
});

it('revokes the current token on logout', function () {
    $token = app(TokenRepository::class)->create($this->user->id(), 'iPhone');

    $this->withToken($token->plainText)
        ->deleteJson('/api/editor/v1/auth/tokens/current')
        ->assertStatus(204);

    $this->withToken($token->plainText)
        ->deleteJson('/api/editor/v1/auth/tokens/current')
        ->assertStatus(401);
});
