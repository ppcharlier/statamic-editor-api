<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();
    $this->entry = tap(Entry::make()->collection('articles')->slug('a-supprimer')->date('2026-01-01')
        ->data(['title' => 'À supprimer'])->published(true))->save();
});

it('deletes an entry and its working copy', function () {
    $this->entry->makeWorkingCopy()->save();
    expect($this->entry->hasWorkingCopy())->toBeTrue();

    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertStatus(204);

    expect(Entry::find($this->entry->id()))->toBeNull()
        ->and($this->entry->hasWorkingCopy())->toBeFalse();
});

it('403s without the delete permission', function () {
    $this->withToken($this->makeTokenWithPermissions(['view articles entries', 'edit articles entries']))
        ->deleteJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertStatus(403);
});

it('404s an unknown id', function () {
    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/entries/nope')
        ->assertStatus(404);
});
