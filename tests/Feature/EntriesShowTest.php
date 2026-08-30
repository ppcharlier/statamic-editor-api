<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->collection = $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('premier')->date('2026-01-01')
            ->data(['title' => 'Version publiée', 'body' => 'Original'])->published(true)
    )->save();
});

it('shows an entry with its data', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk();

    expect($response->json('data.data.title'))->toBe('Version publiée')
        ->and($response->json('data.blueprint'))->toBe('article')
        ->and($response->json('data.has_unpublished_changes'))->toBeFalse();
});

it('returns working copy values when one exists', function () {
    $wc = $this->entry->makeWorkingCopy();
    $wc->attributes(array_merge($wc->attributes(), ['data' => array_merge($wc->attributes()['data'] ?? [], ['title' => 'Brouillon en cours'])]));
    $wc->save();

    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk();

    expect($response->json('data.data.title'))->toBe('Brouillon en cours')
        ->and($response->json('data.has_unpublished_changes'))->toBeTrue()
        ->and($response->json('data.published'))->toBeTrue();
});

it('404s an unknown id', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/definitely-not-an-id')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('403s without view permission on the entry collection', function () {
    $this->withToken($this->makeTokenWithPermissions(['edit articles entries']))
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertStatus(403);
});

it('404s an entry of a collection excluded by the whitelist', function () {
    config()->set('statamic.editor-api.resources.collections', ['pages']);

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertStatus(404);
});
