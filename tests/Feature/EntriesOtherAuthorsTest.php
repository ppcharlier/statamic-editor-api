<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;

uses(BuildsEntryFixtures::class);

// Statamic's EntryPolicy only distinguishes authors when the blueprint carries an
// `author` field: without it, "edit other authors" never comes into play.
beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);

    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'author', 'field' => ['type' => 'users', 'max_items' => 1]],
        ]]]]]])
        ->save();

    // Writers may edit, delete and publish articles — but only their own.
    Role::make('writer')->title('Writer')->permissions([
        'view articles entries', 'edit articles entries', 'delete articles entries', 'publish articles entries',
    ])->save();

    $this->owner = tap(User::make()->email('owner@example.com')->assignRole('writer'))->save();
    $this->colleague = tap(User::make()->email('colleague@example.com')->assignRole('writer'))->save();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('mien')->date('2026-01-01')
            ->data(['title' => 'Mon article', 'author' => $this->owner->id()])->published(false)
    )->save();

    $this->url = '/api/editor/v1/entries/'.$this->entry->id();
});

function tokenOf($user): string
{
    return app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
}

it('lets an author edit their own entry', function () {
    $this->withToken(tokenOf($this->owner))
        ->patchJson($this->url, ['data' => ['title' => 'Retouché']])
        ->assertOk();
});

it('refuses editing another author\'s entry without the other-authors permission', function () {
    $this->withToken(tokenOf($this->colleague))
        ->patchJson($this->url, ['data' => ['title' => 'Pirate']])
        ->assertStatus(403)
        ->assertJson(['error' => ['code' => 'forbidden']]);

    expect(Entry::find($this->entry->id())->value('title'))->toBe('Mon article');
});

it('lets a user holding the other-authors permission edit a colleague\'s entry', function () {
    Role::make('chief')->title('Chief')->permissions([
        'edit articles entries', 'edit other authors articles entries',
    ])->save();
    $chief = tap(User::make()->email('chief@example.com')->assignRole('chief'))->save();

    $this->withToken(tokenOf($chief))
        ->patchJson($this->url, ['data' => ['title' => 'Relu']])
        ->assertOk();
});

it('refuses deleting another author\'s entry', function () {
    $this->withToken(tokenOf($this->colleague))
        ->deleteJson($this->url)
        ->assertStatus(403);

    expect(Entry::find($this->entry->id()))->not->toBeNull();
});

it('refuses publishing another author\'s entry', function () {
    $this->withToken(tokenOf($this->colleague))
        ->postJson($this->url.'/published')
        ->assertStatus(403);

    expect(Entry::find($this->entry->id())->published())->toBeFalse();
});

it('refuses restoring a revision of another author\'s entry', function () {
    // The guard runs before the revision lookup, so an unknown revision id still proves the refusal.
    $this->withToken(tokenOf($this->colleague))
        ->postJson($this->url.'/revisions/999999999/restore')
        ->assertStatus(403);
});
