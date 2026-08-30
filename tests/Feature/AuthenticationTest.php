<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\User;

beforeEach(function () {
    $this->withoutExceptionHandling();
    $this->user = tap(User::make()->email('pp@example.com')->makeSuper())->save();
});

it('rejects requests without a bearer token', function () {
    $this->getJson('/api/editor/v1/_protected')
        ->assertStatus(401)
        ->assertJson(['error' => ['code' => 'unauthenticated']]);
});

it('rejects unknown tokens', function () {
    $this->withToken('invalid-token')->getJson('/api/editor/v1/_protected')
        ->assertStatus(401)
        ->assertJson(['error' => ['code' => 'unauthenticated']]);
});

it('rejects expired tokens with a dedicated code', function () {
    config()->set('statamic.editor-api.auth.token_ttl_days', 1);
    $token = app(TokenRepository::class)->create($this->user->id(), 'iPhone');

    $this->travel(2)->days();

    $this->withToken($token->plainText)->getJson('/api/editor/v1/_protected')
        ->assertStatus(401)
        ->assertJson(['error' => ['code' => 'token_expired']]);
});

it('authenticates a valid token and resolves the statamic user', function () {
    $token = app(TokenRepository::class)->create($this->user->id(), 'iPhone');

    $this->withToken($token->plainText)->getJson('/api/editor/v1/_protected')
        ->assertOk()
        ->assertJson(['data' => ['user' => 'pp@example.com']]);
});

it('rejects tokens whose user no longer exists', function () {
    $token = app(TokenRepository::class)->create('deleted-user-id', 'iPhone');

    $this->withToken($token->plainText)->getJson('/api/editor/v1/_protected')
        ->assertStatus(401)
        ->assertJson(['error' => ['code' => 'unauthenticated']]);
});

it('prefers token_expired over unauthenticated when both apply', function () {
    config()->set('statamic.editor-api.auth.token_ttl_days', 1);
    $token = app(TokenRepository::class)->create('deleted-user-id', 'iPhone');

    $this->travel(2)->days();

    $this->withToken($token->plainText)->getJson('/api/editor/v1/_protected')
        ->assertStatus(401)
        ->assertJson(['error' => ['code' => 'token_expired']]);
});
