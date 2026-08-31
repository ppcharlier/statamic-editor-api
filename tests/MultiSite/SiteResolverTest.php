<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Collection;

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

it('still defaults to the default site without the param', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries')
        ->assertOk();
});
