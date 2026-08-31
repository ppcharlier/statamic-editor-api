<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection();
    Collection::findByHandle('articles')->sites(['en', 'fr'])->save();
    $this->token = $this->makeSuperToken();
    $this->entry = tap(Entry::make()->collection('articles')->locale('en')->slug('origine')
        ->date('2026-01-01')->data(['title' => 'Origine'])->published(true))->save();
});

it('creates a linked localization inheriting from its origin', function () {
    $created = $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/localizations', ['site' => 'fr'])
        ->assertStatus(201)->json('data');

    expect($created['site'])->toBe('fr');

    $localization = Entry::find($created['id']);
    expect($localization->origin()->id())->toBe($this->entry->id())
        ->and($localization->published())->toBeTrue() // hérité
        ->and($localization->value('title'))->toBe('Origine'); // hérité tant que non surchargé
});

it('409s when the localization already exists', function () {
    tap($this->entry->makeLocalization('fr'))->save();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/localizations', ['site' => 'fr'])
        ->assertStatus(409)
        ->assertJson(['error' => ['code' => 'conflict']]);
});

it('422s an unknown or out-of-scope site', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/localizations', ['site' => 'de'])
        ->assertStatus(422);

    Collection::findByHandle('articles')->sites(['en'])->save();
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/localizations', ['site' => 'fr'])
        ->assertStatus(422);
});

it('403s without the create permission', function () {
    $this->withToken($this->makeTokenWithPermissions(['view articles entries']))
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/localizations', ['site' => 'fr'])
        ->assertStatus(403);
});
