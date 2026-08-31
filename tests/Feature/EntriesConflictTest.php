<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('conflit')->date('2026-01-01')
            ->data(['title' => 'Base'])->published(true)
    )->save();
});

it('409s when the entry changed after the client base', function () {
    $base = now()->subHour()->toIso8601String();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'X']], [
            'X-Base-Modified' => $base,
        ])->assertStatus(409)
        ->assertJson(['error' => ['code' => 'conflict']]);
});

it('accepts when the base is current', function () {
    $show = $this->withToken($this->token)->getJson('/api/editor/v1/entries/'.$this->entry->id());
    $base = $show->json('data.last_modified');

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'OK']], [
            'X-Base-Modified' => $base,
        ])->assertOk();
});

it('detects a conflict caused by a newer working copy', function () {
    $show = $this->withToken($this->token)->getJson('/api/editor/v1/entries/'.$this->entry->id());
    $base = $show->json('data.last_modified');

    $this->travel(2)->minutes();
    // quelqu'un d'autre (CP web) sauve un brouillon entre-temps
    $this->entry->makeWorkingCopy()->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'X']], [
            'X-Base-Modified' => $base,
        ])->assertStatus(409);
});

it('skips the check without the header', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'Sans header']])
        ->assertOk();
});

it('422s an unparseable header', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'X']], [
            'X-Base-Modified' => 'pas-une-date',
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});

it('409s a publish when the entry changed after the client base', function () {
    // Un brouillon existe (sinon nothing_to_publish court-circuite la garde)
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'Brouillon']])
        ->assertOk();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/published', [], [
            'X-Base-Modified' => now()->subHour()->toIso8601String(),
        ])->assertStatus(409)
        ->assertJson(['error' => ['code' => 'conflict']]);
});

it('publishes when the base is current', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'Brouillon']])
        ->assertOk();

    $base = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->json('data.last_modified');

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/published', [], [
            'X-Base-Modified' => $base,
        ])->assertOk();
});

it('publishes without the header as before', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'Brouillon']])
        ->assertOk();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/published', [])
        ->assertOk();
});
