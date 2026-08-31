<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\User;

beforeEach(function () {
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

it('caps rotating garbage bearers with a per-ip limit on top of the per-token one', function () {
    config()->set('statamic.editor-api.rate_limits.api', 2);
    config()->set('statamic.editor-api.rate_limits.api_per_ip', 3);

    $repo = app(TokenRepository::class);
    $a = $repo->create($this->user->id(), 'A')->plainText;
    $b = $repo->create($this->user->id(), 'B')->plainText;
    $c = $repo->create($this->user->id(), 'C')->plainText;

    // 2 requêtes token A : sous les deux limites
    $this->withToken($a)->getJson('/api/editor/v1/me')->assertOk();
    $this->withToken($a)->getJson('/api/editor/v1/me')->assertOk();

    // 3e requête token A : bucket par token plein
    $this->withToken($a)->getJson('/api/editor/v1/me')
        ->assertStatus(429)->assertJson(['error' => ['code' => 'rate_limited']]);

    // token B, bearer neuf : 4e requête de l'IP → le plafond IP doit bloquer
    // (sans lui, chaque bearer-poubelle obtiendrait un bucket vierge)
    $this->withToken($b)->getJson('/api/editor/v1/me')->assertOk(); // 3e hit IP : passe encore
    $this->withToken($c)->getJson('/api/editor/v1/me')
        ->assertStatus(429)->assertJson(['error' => ['code' => 'rate_limited']]);
});
