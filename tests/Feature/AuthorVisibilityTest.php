<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Permission;
use Statamic\Facades\Role;
use Statamic\Facades\User;

uses(BuildsEntryFixtures::class);

// Deliberately STRICTER than the Control Panel, which lists every entry — author column
// included — to anyone allowed to view the collection. `enforce_author_visibility` is the
// opt-in switch a site flips to keep other authors' work out of the mobile app; left off,
// nothing about the API changes, which is what keeps this a minor release.
beforeEach(function () {
    $this->makeArticlesCollection();

    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'author', 'field' => ['type' => 'users', 'max_items' => 1]],
        ]]]]]])
        ->save();

    Role::make('writer')->title('Writer')->permissions([
        'access editor-api', 'view articles entries', 'edit articles entries',
    ])->save();

    $this->owner = tap(User::make()->email('owner@example.com')->data(['name' => 'Ada Owner'])->assignRole('writer'))->save();
    $this->colleague = tap(User::make()->email('colleague@example.com')->data(['name' => 'Bo Colleague'])->assignRole('writer'))->save();

    $this->mine = tap(Entry::make()->collection('articles')->slug('mien')->date('2026-01-01')
        ->data(['title' => 'Mon article', 'author' => $this->owner->id()]))->save();

    // Array form on purpose: the very same field without `max_items: 1` stores a list, and
    // `Entry::authors()` reads both shapes — so the query constraint must too.
    $this->theirs = tap(Entry::make()->collection('articles')->slug('sien')->date('2026-02-01')
        ->data(['title' => 'Son article', 'author' => [$this->colleague->id()]]))->save();

    $this->index = '/api/editor/v1/collections/articles/entries';
});

function slugsListedBy($token): array
{
    return collect(test()->withToken($token)->getJson('/api/editor/v1/collections/articles/entries')
        ->assertOk()->json('data'))->pluck('slug')->sort()->values()->all();
}

it('lists every author\'s entries while the setting is off', function () {
    expect(slugsListedBy($this->tokenFor($this->owner)))->toBe(['mien', 'sien']);
});

it('hides other authors\' entries once enforced', function () {
    config()->set('statamic.editor-api.enforce_author_visibility', true);

    $response = $this->withToken($this->tokenFor($this->owner))->getJson($this->index)->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.slug'))->toBe('mien')
        // Constrained in the query, not filtered after paginating — otherwise the count would lie.
        ->and($response->json('meta.total'))->toBe(1);
});

it('lists other authors\' entries with the addon permission', function () {
    config()->set('statamic.editor-api.enforce_author_visibility', true);

    Role::make('desk')->title('Desk')->permissions([
        'access editor-api', 'view articles entries',
        'editor-api list other authors articles entries',
    ])->save();
    $desk = tap(User::make()->email('desk@example.com')->assignRole('desk'))->save();

    expect(slugsListedBy($this->tokenFor($desk)))->toBe(['mien', 'sien']);
});

it('lists other authors\' entries for a role that may edit them', function () {
    config()->set('statamic.editor-api.enforce_author_visibility', true);

    // Whoever may edit another author's entry already sees it in the CP; hiding it in the
    // app would be absurd — and would break every existing editor role on upgrade.
    Role::make('chief')->title('Chief')->permissions([
        'access editor-api', 'view articles entries', 'edit articles entries',
        'edit other authors articles entries',
    ])->save();
    $chief = tap(User::make()->email('chief@example.com')->assignRole('chief'))->save();

    expect(slugsListedBy($this->tokenFor($chief)))->toBe(['mien', 'sien']);
});

it('never restricts a super user', function () {
    config()->set('statamic.editor-api.enforce_author_visibility', true);

    expect(slugsListedBy($this->makeSuperToken()))->toBe(['mien', 'sien']);
});

it('ignores the setting when the blueprint has no author field', function () {
    config()->set('statamic.editor-api.enforce_author_visibility', true);

    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
        ]]]]]])
        ->save();
    Blink::flush();

    // No `author` field means no ownership at all, exactly as EntryPolicy reads it.
    expect(slugsListedBy($this->tokenFor($this->owner)))->toBe(['mien', 'sien']);
});

it('refuses a direct read of another author\'s entry once enforced', function () {
    config()->set('statamic.editor-api.enforce_author_visibility', true);
    $token = $this->tokenFor($this->owner);

    // 404, not 403: an entry kept out of your list has no business being confirmed to exist.
    $this->withToken($token)->getJson('/api/editor/v1/entries/'.$this->theirs->id())
        ->assertNotFound()
        ->assertJson(['error' => ['code' => 'not_found']]);

    $this->withToken($token)->getJson('/api/editor/v1/entries/'.$this->mine->id())->assertOk();
});

it('drops the name but keeps the id of another author once enforced', function () {
    config()->set('statamic.editor-api.enforce_author_visibility', true);

    Role::make('desk')->title('Desk')->permissions([
        'access editor-api', 'view articles entries',
        'editor-api list other authors articles entries',
    ])->save();
    $desk = tap(User::make()->email('desk@example.com')->data(['name' => 'Dee Desk'])->assignRole('desk'))->save();
    tap(Entry::make()->collection('articles')->slug('desk')->date('2026-03-01')
        ->data(['title' => 'Article du desk', 'author' => $desk->id()]))->save();

    $data = collect($this->withToken($this->tokenFor($desk))->getJson($this->index)->assertOk()->json('data'))
        ->keyBy('slug');

    // The id stays: it is what lets a client say "someone else's" rather than "no author".
    expect($data['sien']['author'])->toBe(['id' => (string) $this->colleague->id(), 'name' => null])
        // Your own name is never withheld from you.
        ->and($data['desk']['author'])->toBe(['id' => (string) $desk->id(), 'name' => 'Dee Desk']);
});

it('shows the other author\'s name with the dedicated permission', function () {
    config()->set('statamic.editor-api.enforce_author_visibility', true);

    Role::make('desk')->title('Desk')->permissions([
        'access editor-api', 'view articles entries',
        'editor-api list other authors articles entries',
        'editor-api view other authors of articles entries',
    ])->save();
    $desk = tap(User::make()->email('desk@example.com')->assignRole('desk'))->save();

    $data = collect($this->withToken($this->tokenFor($desk))->getJson($this->index)->assertOk()->json('data'))
        ->keyBy('slug');

    expect($data['sien']['author'])->toBe(['id' => (string) $this->colleague->id(), 'name' => 'Bo Colleague']);
});

it('registers both permissions for the CP roles editor', function () {
    // Registered whatever the setting: a roles.yaml carrying a permission that only shows up
    // when a config file says so would be unreadable. boot() is what the roles editor calls.
    $values = Permission::boot()->flattened()->map->value()->all();

    expect($values)->toContain('editor-api list other authors articles entries')
        ->and($values)->toContain('editor-api view other authors of articles entries');
});
