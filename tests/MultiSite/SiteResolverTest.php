<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection();
    Collection::findByHandle('articles')->sites(['en', 'fr'])->save();
    $this->token = $this->makeSuperToken();
});

it('accepts a known non-default site on entry listings', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site=fr')
        ->assertOk();
});

it('422s an unknown site handle', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site=de')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['site']]]);
});

it('422s a site the collection is not available in', function () {
    Collection::findByHandle('articles')->sites(['en'])->save();

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site=fr')
        ->assertStatus(422);
});

it('falls back to the first allowed site when the resource excludes the default one', function () {
    // Parité CP : une collection indisponible dans le site global par défaut reste
    // consultable sans `?site=` — on retombe sur le premier site de la ressource plutôt
    // que de 422. Un handle EXPLICITE hors périmètre continue de 422 (test plus haut).
    Collection::findByHandle('articles')->sites(['fr'])->save();

    tap(Entry::make()->collection('articles')->locale('fr')->slug('bonjour')
        ->date('2026-01-01')->data(['title' => 'Bonjour'])->published(true))->save();

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Bonjour');
});

it('422s an array ?site[]= parameter instead of tripping a PHP notice', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site[]=en&site[]=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['site']]]);
});

it('still defaults to the default site without the param', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries')
        ->assertOk();
});
