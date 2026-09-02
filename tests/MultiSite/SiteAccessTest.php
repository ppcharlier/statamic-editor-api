<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Role;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;

uses(BuildsEntryFixtures::class);

// The Control Panel never lets a user act in a site they lack `access {site} site` for:
// the site switcher refuses it and every policy re-checks it. The API must say no too.
beforeEach(function () {
    $this->makeArticlesCollection();
    Collection::findByHandle('articles')->sites(['en', 'fr'])->save();

    $footer = tap(GlobalSet::make('footer')->title('Footer')->sites(['en', 'fr']))->save();
    $footer->in('en')->data(['text' => 'EN'])->save();
    $footer->in('fr')->data(['text' => 'FR'])->save();

    $main = tap(Nav::make('main')->title('Menu'))->save();
    $main->makeTree('en', [['id' => 'b1', 'title' => 'Home', 'url' => '/']])->save();
    $main->makeTree('fr', [['id' => 'b1', 'title' => 'Accueil', 'url' => '/fr']])->save();

    Taxonomy::make('tags')->title('Tags')->sites(['en', 'fr'])->save();

    Role::make('en_only')->title('EN only')->permissions([
        'access en site',
        'view articles entries', 'edit articles entries', 'create articles entries',
        'edit footer globals',
        'view main nav', 'edit main nav',
        'view tags terms', 'create tags terms',
    ])->save();
    $user = tap(User::make()->email('en-only@example.com')->assignRole('en_only'))->save();
    $this->token = app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;

    $this->en = tap(Entry::make()->collection('articles')->locale('en')->slug('hello')
        ->date('2026-01-01')->data(['title' => 'Hello'])->published(true))->save();
    $this->fr = tap($this->en->makeLocalization('fr'))->save();
});

it('still serves the site the user may access', function () {
    $this->withToken($this->token)->getJson('/api/editor/v1/entries/'.$this->en->id())->assertOk();
    $this->withToken($this->token)->getJson('/api/editor/v1/collections/articles/entries?site=en')->assertOk();
    $this->withToken($this->token)->getJson('/api/editor/v1/globals/footer?site=en')->assertOk();
    $this->withToken($this->token)->getJson('/api/editor/v1/navigations/main/tree?site=en')->assertOk();
});

it('refuses reading an entry that lives in a site the user may not access', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->fr->id())
        ->assertStatus(403)
        ->assertJson(['error' => ['code' => 'forbidden']]);
});

it('refuses listing entries of a site the user may not access', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/collections/articles/entries?site=fr')
        ->assertStatus(403);
});

it('refuses creating an entry in a site the user may not access', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries?site=fr', [
            'slug' => 'intrus', 'date' => '2026-03-01', 'data' => ['title' => 'Intrus'],
        ])->assertStatus(403);

    expect(Entry::query()->where('slug', 'intrus')->count())->toBe(0);
});

it('refuses localizing an entry into a site the user may not access', function () {
    $solo = tap(Entry::make()->collection('articles')->locale('en')->slug('solo')
        ->date('2026-01-02')->data(['title' => 'Solo'])->published(true))->save();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$solo->id().'/localizations', ['site' => 'fr'])
        ->assertStatus(403);
});

it('refuses editing the globals of a site the user may not access', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/globals/footer?site=fr', ['data' => ['text' => 'Pirate']])
        ->assertStatus(403);

    expect(GlobalSet::findByHandle('footer')->in('fr')->get('text'))->toBe('FR');
});

it('refuses reading the navigation tree of a site the user may not access', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/navigations/main/tree?site=fr')
        ->assertStatus(403);
});

it('refuses creating a term in a site the user may not access', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/tags/terms?site=fr', ['slug' => 'intrus', 'data' => ['title' => 'Intrus']])
        ->assertStatus(403);
});
