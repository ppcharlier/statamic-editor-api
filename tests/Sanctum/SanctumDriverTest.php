<?php

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Ppcharlier\StatamicEditorApi\Auth\SanctumTokenRepository;
use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Ppcharlier\StatamicEditorApi\Tests\Support\EloquentTestUser;

beforeEach(function () {
    $this->model = EloquentTestUser::create([
        'name' => 'Pierre-Philippe',
        'email' => 'pp@example.com',
        'password' => Hash::make('secret-password'),
        'super' => true,
    ]);
});

it('binds the sanctum repository when the driver says so', function () {
    expect(app(TokenRepository::class))->toBeInstanceOf(SanctumTokenRepository::class);
});

it('issues a token persisted in personal_access_tokens', function () {
    $response = $this->postJson('/api/editor/v1/auth/tokens', [
        'email' => 'pp@example.com',
        'password' => 'secret-password',
        'device_name' => 'iPhone de Pierre',
    ])->assertStatus(201);

    $plainText = $response->json('data.token');
    expect(PersonalAccessToken::count())->toBe(1);

    $row = PersonalAccessToken::first();
    expect($row->name)->toBe('iPhone de Pierre')
        ->and($row->token)->toBe(hash('sha256', $plainText))
        ->and((string) $row->tokenable_id)->toBe((string) $this->model->getKey());
});

it('authenticates /me with a database-stored token', function () {
    $plainText = app(TokenRepository::class)->create((string) $this->model->getKey(), 'iPhone')->plainText;

    $this->withToken($plainText)
        ->getJson('/api/editor/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', (string) $this->model->getKey())
        ->assertJsonPath('data.email', 'pp@example.com');
});

it('revokes the row on logout', function () {
    $plainText = app(TokenRepository::class)->create((string) $this->model->getKey(), 'iPhone')->plainText;

    $this->withToken($plainText)
        ->deleteJson('/api/editor/v1/auth/tokens/current')
        ->assertStatus(204);

    expect(PersonalAccessToken::count())->toBe(0);

    $this->withToken($plainText)->getJson('/api/editor/v1/me')->assertStatus(401);
});

it('honors the configured ttl through expires_at', function () {
    config()->set('statamic.editor-api.auth.token_ttl_days', 1);
    $plainText = app(TokenRepository::class)->create((string) $this->model->getKey(), 'iPhone')->plainText;

    $this->withToken($plainText)->getJson('/api/editor/v1/me')->assertOk();

    $this->travel(2)->days();

    $this->withToken($plainText)
        ->getJson('/api/editor/v1/me')
        ->assertStatus(401)
        ->assertJson(['error' => ['code' => 'token_expired']]);
});
