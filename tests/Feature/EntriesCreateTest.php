<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();
});

it('creates a draft entry by default', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'nouveau',
            'date' => '2026-08-30',
            'data' => ['title' => 'Nouvel article', 'body' => 'Contenu'],
        ])->assertStatus(201);

    expect($response->json('data.status'))->toBe('draft')
        ->and($response->json('data.published'))->toBeFalse();

    $entry = Entry::find($response->json('data.id'));
    expect($entry->value('title'))->toBe('Nouvel article')
        ->and($entry->published())->toBeFalse();
});

it('records an initial revision when revisions are enabled', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'avec-revision', 'date' => '2026-08-30', 'message' => 'Création',
            'data' => ['title' => 'Avec révision'],
        ])->assertStatus(201);

    $entry = Entry::find($response->json('data.id'));
    expect($entry->revisions())->toHaveCount(1)
        ->and($entry->latestRevision()->message())->toBe('Création');
});

it('publishes on creation when asked and permitted', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'direct', 'date' => '2026-08-30', 'published' => true,
            'data' => ['title' => 'Publié direct'],
        ])->assertStatus(201);

    expect($response->json('data.published'))->toBeTrue();
});

it('403s published:true without the publish permission', function () {
    $token = $this->makeTokenWithPermissions(['view articles entries', 'create articles entries', 'edit articles entries']);

    $this->withToken($token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'interdit', 'date' => '2026-08-30', 'published' => true,
            'data' => ['title' => 'Interdit'],
        ])->assertStatus(403);
});

it('validates blueprint rules field by field', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'sans-titre', 'date' => '2026-08-30',
            'data' => ['body' => 'Corps sans titre'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['title']]]);
});

it('rejects fields unknown to the blueprint', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'inconnu', 'date' => '2026-08-30',
            'data' => ['title' => 'Ok', 'hacker_field' => 'nope'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']])
        ->assertJsonStructure(['error' => ['errors' => ['hacker_field']]]);
});
