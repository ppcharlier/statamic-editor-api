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

it('422s an unparseable date', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'bad-date', 'date' => 'not-a-date',
            'data' => ['title' => 'X'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['date']]]);
});

it('422s an impossible date instead of silently rolling it over', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'rollover', 'date' => '2026-99-99',
            'data' => ['title' => 'X'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});

it('422s a top-level date on an undated collection instead of silently dropping it', function () {
    \Statamic\Facades\Collection::make('notes')->title('Notes')->dated(false)->save();
    \Statamic\Facades\Blueprint::make('note')->setNamespace('collections.notes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]]])->save();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/notes/entries', [
            'slug' => 'sans-date', 'date' => '2026-01-01',
            'data' => ['title' => 'X'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['date']]]);
});

it('rejects data.slug as a meta handle, not a data field', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'meta-slug', 'date' => '2026-08-30',
            'data' => ['title' => 'X', 'slug' => 'sournois'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']])
        ->assertJsonStructure(['error' => ['errors' => ['slug']]]);
});

it('rejects data.date as a meta handle, not a data field', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'meta-date', 'date' => '2026-08-30',
            'data' => ['title' => 'X', 'date' => '2026-01-01T00:00:00Z'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']])
        ->assertJsonStructure(['error' => ['errors' => ['date']]]);
});

it('never persists a stray slug key inside entry data', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'propre', 'date' => '2026-08-30',
            'data' => ['title' => 'Propre'],
        ])->assertStatus(201);

    $entry = Entry::find($response->json('data.id'));
    expect($entry->data()->keys()->all())->not->toContain('slug')
        ->and($entry->data()->keys()->all())->not->toContain('date');
});

it('422s a slug whose uri is already taken in a routed collection', function () {
    \Statamic\Facades\Collection::findByHandle('articles')->routes('/articles/{slug}')->save();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'existant', 'date' => '2026-01-01', 'data' => ['title' => 'Premier'],
        ])->assertStatus(201);

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'existant', 'date' => '2026-02-01', 'data' => ['title' => 'Doublon'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'uri_taken']])
        ->assertJsonStructure(['error' => ['errors' => ['slug']]]);
});

it('allows a duplicate slug when the collection has no route (no uri to collide)', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'librement', 'date' => '2026-01-01', 'data' => ['title' => 'Un'],
        ])->assertStatus(201);

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'librement', 'date' => '2026-02-01', 'data' => ['title' => 'Deux'],
        ])->assertStatus(201);
});
