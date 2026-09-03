<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\User;

uses(BuildsEntryFixtures::class);

// The CP fills a `users` field declared `default: current` with the signed-in user when the
// form opens. The API applies no blueprint defaults, so an entry created without `author`
// would be born ownerless — and EntryPolicy treats an ownerless entry as someone else's,
// locking its own creator out. Creation must therefore default `author` to the current user.
beforeEach(function () {
    $this->makeArticlesCollection();

    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'author', 'field' => ['type' => 'users', 'max_items' => 1, 'default' => 'current']],
        ]]]]]])
        ->save();

    Role::make('writer')->title('Writer')->permissions([
        'access editor-api', 'view articles entries', 'edit articles entries', 'create articles entries',
    ])->save();
    $this->writer = tap(User::make()->email('writer@example.com')->data(['name' => 'Wil Writer'])->assignRole('writer'))->save();
    $this->token = app(TokenRepository::class)->create($this->writer->id(), 'iPhone')->plainText;
});

it('assigns the creating user as author when the payload omits it', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'mien', 'date' => '2026-03-01', 'data' => ['title' => 'Mon article'],
        ])->assertCreated();

    expect($response->json('data.author'))->toBe(['id' => (string) $this->writer->id(), 'name' => 'Wil Writer'])
        ->and($response->json('data.can.edit'))->toBeTrue('le créateur garde la main sur sa propre entrée')
        ->and(Entry::find($response->json('data.id'))->value('author'))->toBe($this->writer->id());
});

it('keeps an author the client sets explicitly', function () {
    $other = tap(User::make()->email('other@example.com')->data(['name' => 'Other One']))->save();

    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'autre', 'date' => '2026-03-01', 'data' => ['title' => 'Pour un autre', 'author' => $other->id()],
        ])->assertCreated();

    expect($response->json('data.author.id'))->toBe((string) $other->id());
});

it('leaves an entry authorless when the blueprint has no author field', function () {
    Blueprint::make('article')->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]]])->save();

    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'sans', 'date' => '2026-03-01', 'data' => ['title' => 'Sans auteur'],
        ])->assertCreated();

    expect($response->json('data.author'))->toBeNull()
        ->and(Entry::find($response->json('data.id'))->has('author'))->toBeFalse();
});
