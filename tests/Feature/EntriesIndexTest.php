<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection();
    $this->token = $this->makeSuperToken();

    Entry::make()->collection('articles')->slug('premier')->date('2026-01-01')
        ->data(['title' => 'Premier article'])->published(true)->save();
    Entry::make()->collection('articles')->slug('brouillon')->date('2026-02-01')
        ->data(['title' => 'Un brouillon'])->published(false)->save();
});

it('lists entries with pagination meta', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(2)
        ->and($response->json('meta.current_page'))->toBe(1);

    $first = collect($response->json('data'))->firstWhere('slug', 'premier');
    expect($first['status'])->toBe('published')
        ->and($first['title'])->toBe('Premier article')
        ->and($first)->toHaveKeys(['id', 'collection', 'published', 'date', 'has_unpublished_changes', 'last_modified']);
});

it('filters by status', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?status=draft')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.slug'))->toBe('brouillon');
});

it('searches on title', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?search=brouillon')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.slug'))->toBe('brouillon');
});

it('sorts and paginates', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?sort=date&per_page=1&page=2')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.slug'))->toBe('brouillon')
        ->and($response->json('meta.per_page'))->toBe(1);
});

it('sorts on a blueprint field handle', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?sort=-title')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'brouillon'); // « Un brouillon » > « Premier article »
});

it('422s an unknown sort field instead of silently ignoring it', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?sort=nope')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['sort']]]);
});

it('422s a date sort on an undated collection', function () {
    \Statamic\Facades\Collection::make('notes')->title('Notes')->dated(false)->save();
    \Statamic\Facades\Blueprint::make('note')->setNamespace('collections.notes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]]])->save();

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/notes/entries?sort=date')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});

it('403s without view permission', function () {
    $this->withToken($this->makeTokenWithPermissions(['edit articles entries']))
        ->getJson('/api/editor/v1/collections/articles/entries')
        ->assertStatus(403);
});
