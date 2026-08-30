<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('site-guard')->date('2026-01-01')
            ->data(['title' => 'Base'])->published(true)
    )->save();
});

it('422s a non-default ?site= on the entries index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'not_supported']]);
});

it('passes through a ?site= matching the default site on the entries index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site='.Site::default()->handle())
        ->assertOk();
});

it('422s a non-default ?site= when creating an entry', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries?site=fr', [
            'slug' => 'nope', 'date' => '2026-08-30',
            'data' => ['title' => 'X'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'not_supported']]);
});

it('422s a non-default ?site= on entry show', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id().'?site=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'not_supported']]);
});

it('passes through a ?site= matching the default site on entry show', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id().'?site='.Site::default()->handle())
        ->assertOk();
});

it('422s a non-default ?site= on the blueprints index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/blueprints?site=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'not_supported']]);
});

it('passes through a ?site= matching the default site on the blueprints index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/blueprints?site='.Site::default()->handle())
        ->assertOk();
});

it('422s a non-default ?site= on the revisions index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id().'/revisions?site=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'not_supported']]);
});

it('passes through a ?site= matching the default site on the revisions index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id().'/revisions?site='.Site::default()->handle())
        ->assertOk();
});
