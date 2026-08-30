<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();

    $this->published = tap(
        Entry::make()->collection('articles')->slug('publie')->date('2026-01-01')
            ->data(['title' => 'Publié', 'body' => 'Original'])->published(true)
    )->save();
});

it('saves to a working copy without touching the live file for a published entry', function () {
    $response = $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), [
            'data' => ['title' => 'Modifié en brouillon', 'body' => 'Nouveau corps'],
        ])->assertOk();

    expect($response->json('data.data.title'))->toBe('Modifié en brouillon')
        ->and($response->json('data.has_unpublished_changes'))->toBeTrue()
        ->and($response->json('data.published'))->toBeTrue();

    $live = Entry::find($this->published->id());
    expect($live->value('title'))->toBe('Publié')
        ->and($live->hasWorkingCopy())->toBeTrue();
});

it('saves directly for a draft entry', function () {
    $draft = tap(Entry::make()->collection('articles')->slug('brouillon')->date('2026-02-01')
        ->data(['title' => 'Brouillon'])->published(false))->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$draft->id(), [
            'data' => ['title' => 'Brouillon modifié'],
        ])->assertOk();

    expect(Entry::find($draft->id())->value('title'))->toBe('Brouillon modifié')
        ->and(Entry::find($draft->id())->published())->toBeFalse();
});

it('saves directly when revisions are disabled, still never publishing', function () {
    config()->set('statamic.revisions.enabled', false);
    tap(\Statamic\Facades\Collection::findByHandle('articles')->revisionsEnabled(false))->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), [
            'data' => ['title' => 'Direct'],
        ])->assertOk();

    $live = Entry::find($this->published->id());
    expect($live->value('title'))->toBe('Direct')
        ->and($live->published())->toBeTrue();
});

it('rejects published in the payload', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), [
            'published' => false,
            'data' => ['title' => 'X'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']]);
});

it('validates blueprint rules', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), [
            'data' => ['title' => '', 'body' => 'x'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['title']]]);
});

it('updates the slug', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), [
            'slug' => 'publie-v2',
            'data' => ['title' => 'Avec slug'],
        ])->assertOk();

    // révisions actives + entrée publiée : le slug part dans la working copy, le live ne bouge pas
    expect(Entry::find($this->published->id())->slug())->toBe('publie');
});

it('403s without the edit permission', function () {
    $this->withToken($this->makeTokenWithPermissions(['view articles entries']))
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), ['data' => ['title' => 'X']])
        ->assertStatus(403);
});

it('422s an unparseable date', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), [
            'date' => 'not-a-date',
            'data' => ['title' => 'X'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['date']]]);
});

it('rejects data.slug as a meta handle, not a data field', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), [
            'data' => ['title' => 'X', 'slug' => 'sournois'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']])
        ->assertJsonStructure(['error' => ['errors' => ['slug']]]);
});

it('rejects data.date as a meta handle, not a data field', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->published->id(), [
            'data' => ['title' => 'X', 'date' => '2026-01-01T00:00:00Z'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']])
        ->assertJsonStructure(['error' => ['errors' => ['date']]]);
});
