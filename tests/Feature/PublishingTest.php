<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();
});

function makePublishedEntryWithDraftChanges($test)
{
    $entry = tap(Entry::make()->collection('articles')->slug('e')->date('2026-01-01')
        ->data(['title' => 'V1'])->published(true))->save();

    $test->withToken($test->token)->patchJson('/api/editor/v1/entries/'.$entry->id(), [
        'data' => ['title' => 'V2 brouillon'],
    ])->assertOk();

    return Entry::find($entry->id());
}

it('publishes the working copy with a revision message', function () {
    $entry = makePublishedEntryWithDraftChanges($this);

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$entry->id().'/published', ['message' => 'Mise en ligne V2'])
        ->assertOk()
        ->assertJsonPath('data.data.title', 'V2 brouillon')
        ->assertJsonPath('data.has_unpublished_changes', false);

    $fresh = Entry::find($entry->id());
    expect($fresh->value('title'))->toBe('V2 brouillon')
        ->and($fresh->hasWorkingCopy())->toBeFalse()
        ->and($fresh->latestRevision()->action())->toBe('publish')
        ->and($fresh->latestRevision()->message())->toBe('Mise en ligne V2');
});

it('publishes a draft entry', function () {
    $draft = tap(Entry::make()->collection('articles')->slug('d')->date('2026-01-01')
        ->data(['title' => 'Brouillon'])->published(false))->save();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$draft->id().'/published')
        ->assertOk()
        ->assertJsonPath('data.published', true);
});

it('422s publishing a published entry with no working copy', function () {
    $entry = tap(Entry::make()->collection('articles')->slug('p')->date('2026-01-01')
        ->data(['title' => 'Déjà publié'])->published(true))->save();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$entry->id().'/published')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'nothing_to_publish']]);
});

it('unpublishes a published entry', function () {
    $entry = tap(Entry::make()->collection('articles')->slug('u')->date('2026-01-01')
        ->data(['title' => 'En ligne'])->published(true))->save();

    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/entries/'.$entry->id().'/published')
        ->assertOk()
        ->assertJsonPath('data.published', false);

    expect(Entry::find($entry->id())->latestRevision()->action())->toBe('unpublish');
});

it('422s unpublishing a draft', function () {
    $draft = tap(Entry::make()->collection('articles')->slug('d2')->date('2026-01-01')
        ->data(['title' => 'Brouillon'])->published(false))->save();

    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/entries/'.$draft->id().'/published')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'nothing_to_unpublish']]);
});

it('403s without the publish permission', function () {
    $entry = makePublishedEntryWithDraftChanges($this);
    $token = $this->makeTokenWithPermissions(['view articles entries', 'edit articles entries']);

    $this->withToken($token)
        ->postJson('/api/editor/v1/entries/'.$entry->id().'/published')
        ->assertStatus(403);
});

it('works without revisions too', function () {
    config()->set('statamic.revisions.enabled', false);
    tap(\Statamic\Facades\Collection::findByHandle('articles')->revisionsEnabled(false))->save();

    $draft = tap(Entry::make()->collection('articles')->slug('nr')->date('2026-01-01')
        ->data(['title' => 'Sans révisions'])->published(false))->save();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$draft->id().'/published')
        ->assertOk()
        ->assertJsonPath('data.published', true);
});
