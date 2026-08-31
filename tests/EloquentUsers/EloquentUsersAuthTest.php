<?php

use Illuminate\Support\Facades\Hash;
use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\EloquentTestUser;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->model = EloquentTestUser::create([
        'name' => 'Pierre-Philippe',
        'email' => 'pp@example.com',
        'password' => Hash::make('secret-password'),
        'super' => true,
    ]);
});

it('issues a token for valid credentials against a database user', function () {
    $response = $this->postJson('/api/editor/v1/auth/tokens', [
        'email' => 'pp@example.com',
        'password' => 'secret-password',
        'device_name' => 'iPhone de Pierre',
    ]);

    $response->assertStatus(201);

    $plainText = $response->json('data.token');
    expect($plainText)->toBeString()->not->toBeEmpty();

    $stored = app(TokenRepository::class)->findByPlainText($plainText);
    expect($stored->userId)->toBe((string) $this->model->getKey());
});

it('rejects a wrong password for a database user', function () {
    $this->postJson('/api/editor/v1/auth/tokens', [
        'email' => 'pp@example.com',
        'password' => 'wrong',
        'device_name' => 'iPhone',
    ])->assertStatus(401)->assertJson(['error' => ['code' => 'invalid_credentials']]);
});

it('resolves the authenticated database user on /me with a string id', function () {
    $token = app(TokenRepository::class)->create((string) $this->model->getKey(), 'iPhone');

    $this->withToken($token->plainText)
        ->getJson('/api/editor/v1/me')
        ->assertStatus(200)
        // The iOS client (EditorAPI/Models.swift) decodes `id` as String —
        // Eloquent's integer key must not leak into the contract.
        ->assertJsonPath('data.id', (string) $this->model->getKey())
        ->assertJsonPath('data.email', 'pp@example.com')
        ->assertJsonPath('data.super', true)
        ->assertJsonPath('data.permissions', ['*']);
});

it('stamps revision authorship from the database user with a string id', function () {
    $this->makeArticlesCollection(withRevisions: true);
    $token = app(TokenRepository::class)->create((string) $this->model->getKey(), 'iPhone')->plainText;

    $id = $this->withToken($token)->postJson('/api/editor/v1/collections/articles/entries', [
        'slug' => 'db-author', 'date' => '2026-01-01', 'message' => 'Création',
        'data' => ['title' => 'V1'],
    ])->assertStatus(201)->json('data.id');

    $this->withToken($token)
        ->postJson("/api/editor/v1/entries/{$id}/published", ['message' => 'Publie V1'])
        ->assertOk();

    $revisions = $this->withToken($token)
        ->getJson("/api/editor/v1/entries/{$id}/revisions")
        ->assertOk()
        ->json('data');

    expect($revisions)->not->toBeEmpty()
        ->and($revisions[0]['user']['id'])->toBe((string) $this->model->getKey())
        ->and($revisions[0]['user']['name'])->toBe('Pierre-Philippe')
        ->and($revisions[0]['user']['email'])->toBe('pp@example.com');
});
