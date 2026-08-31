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

// v1.2 contract change (SiteResolver replaces SiteGuard): this suite runs mono-site, so only
// the default site handle ever exists — 'fr' is unknown to Site::all(), and still 422s. What
// changed is the shape: `not_supported` (SiteGuard's blanket "multi-site isn't implemented"
// rejection of any non-default handle) becomes `validation_failed` with an `errors.site`
// message (SiteResolver validates the handle against known/allowed sites instead of rejecting
// every non-default one). See tests/MultiSite/SiteResolverTest.php for the multisite case where
// a non-default site is actually known and gets accepted.

it('422s an unknown ?site= on the entries index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['site']]]);
});

it('passes through a ?site= matching the default site on the entries index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site='.Site::default()->handle())
        ->assertOk();
});

it('422s an unknown ?site= when creating an entry', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries?site=fr', [
            'slug' => 'nope', 'date' => '2026-08-30',
            'data' => ['title' => 'X'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['site']]]);
});

it('422s an unknown ?site= on entry show', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id().'?site=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['site']]]);
});

it('passes through a ?site= matching the default site on entry show', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id().'?site='.Site::default()->handle())
        ->assertOk();
});

it('422s an unknown ?site= on the blueprints index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/blueprints?site=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['site']]]);
});

it('passes through a ?site= matching the default site on the blueprints index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/blueprints?site='.Site::default()->handle())
        ->assertOk();
});

it('422s an unknown ?site= on the revisions index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id().'/revisions?site=fr')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['site']]]);
});

it('passes through a ?site= matching the default site on the revisions index', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id().'/revisions?site='.Site::default()->handle())
        ->assertOk();
});
