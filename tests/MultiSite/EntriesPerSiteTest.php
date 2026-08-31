<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection();
    Collection::findByHandle('articles')->sites(['en', 'fr'])->save();
    $this->token = $this->makeSuperToken();

    $this->en = tap(Entry::make()->collection('articles')->locale('en')->slug('hello')
        ->date('2026-01-01')->data(['title' => 'Hello'])->published(true))->save();
    $this->fr = tap($this->en->makeLocalization('fr'))->save();
});

it('filters the listing by site', function () {
    $en = $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site=en')->assertOk();
    $fr = $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site=fr')->assertOk();

    expect($en->json('meta.total'))->toBe(1)
        ->and($en->json('data.0.id'))->toBe($this->en->id())
        ->and($fr->json('data.0.id'))->toBe($this->fr->id());
});

it('exposes site and localizations on the detail', function () {
    $detail = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->en->id())
        ->assertOk()->json('data');

    expect($detail['site'])->toBe('en')
        ->and(collect($detail['localizations'])->pluck('site')->sort()->values()->all())->toBe(['en', 'fr'])
        ->and(collect($detail['localizations'])->firstWhere('site', 'fr')['id'])->toBe($this->fr->id());
});

it('creates an independent entry directly in a non-default site', function () {
    $created = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries?site=fr', [
            'slug' => 'bonjour', 'date' => '2026-02-01', 'data' => ['title' => 'Bonjour'],
        ])->assertStatus(201)->json('data');

    expect($created['site'])->toBe('fr');

    $entry = Entry::find($created['id']);
    expect($entry->locale())->toBe('fr')
        ->and($entry->hasOrigin())->toBeFalse();
});
