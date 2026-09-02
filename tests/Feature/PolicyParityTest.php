<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Role;
use Statamic\Facades\User;

uses(BuildsEntryFixtures::class);

// Statamic's policies open a whole area to whoever may configure it (`before()`):
// `configure collections` edits any entry in the CP without `edit ... entries`.
// The API must reach the same verdict, or a content administrator is locked out.
function tokenWithOnly(array $permissions): string
{
    $handle = 'role_'.uniqid();
    Role::make($handle)->title($handle)->permissions($permissions)->save();
    $user = tap(User::make()->email(uniqid().'@example.com')->assignRole($handle))->save();

    return app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
}

it('lets a user with configure collections edit entries, as the CP does', function () {
    $this->makeArticlesCollection();
    $entry = tap(Entry::make()->collection('articles')->slug('a')->date('2026-01-01')
        ->data(['title' => 'A'])->published(false))->save();

    $this->withToken(tokenWithOnly(['configure collections']))
        ->patchJson('/api/editor/v1/entries/'.$entry->id(), ['data' => ['title' => 'B']])
        ->assertOk();
});

it('lets a user with configure globals edit any global set, as the CP does', function () {
    $set = tap(GlobalSet::make('footer')->title('Footer'))->save();
    Blueprint::make('footer')->setNamespace('globals')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'text', 'field' => ['type' => 'text']],
        ]]]]]])->save();
    $set->inDefaultSite()->data(['text' => 'x'])->save();

    $this->withToken(tokenWithOnly(['configure globals']))
        ->patchJson('/api/editor/v1/globals/footer', ['data' => ['text' => 'y']])
        ->assertOk();
});
