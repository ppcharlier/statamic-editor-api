<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection();
    $this->entry = tap(Entry::make()->collection('articles')->slug('a-propos')->date('2026-01-01')
        ->data(['title' => 'À propos'])->published(true))->save();

    $nav = Nav::make('main')->title('Menu principal');
    $nav->save();
    $nav->makeTree(Site::default()->handle(), [
        ['id' => 'branche-1', 'entry' => $this->entry->id()],
        ['id' => 'branche-2', 'title' => 'Contact', 'url' => '/contact'],
    ])->save();

    $this->token = $this->makeSuperToken();
});

it('lists navigations', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/navigations')
        ->assertOk()
        ->assertJsonPath('data.0.handle', 'main');
});

it('shows the tree with entry titles resolved', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/navigations/main/tree')
        ->assertOk();

    $tree = $response->json('data.tree');
    expect($tree)->toHaveCount(2)
        ->and($tree[0]['entry'])->toBe($this->entry->id())
        ->and($tree[0]['entry_title'])->toBe('À propos')
        ->and($tree[1]['title'])->toBe('Contact')
        ->and($tree[1]['url'])->toBe('/contact');
});

it('rewrites the tree', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/navigations/main/tree', ['tree' => [
            ['title' => 'Accueil', 'url' => '/'],
            ['entry' => $this->entry->id(), 'children' => [
                ['title' => 'Sous-page', 'url' => '/sous'],
            ]],
        ]])->assertOk();

    $saved = Nav::findByHandle('main')->in(Site::default()->handle())->tree();
    expect($saved)->toHaveCount(2)
        ->and($saved[0]['title'])->toBe('Accueil')
        ->and($saved[1]['entry'])->toBe($this->entry->id())
        ->and($saved[1]['children'][0]['title'])->toBe('Sous-page')
        ->and($saved[0]['id'] ?? null)->not->toBeNull(); // ids générés
});

it('rejects a node with neither entry nor title/url', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/navigations/main/tree', ['tree' => [
            ['children' => []],
        ]])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});

it('422s a tree violating expectsRoot instead of 500ing', function () {
    tap(Nav::findByHandle('main')->expectsRoot(true))->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/navigations/main/tree', ['tree' => [
            ['title' => 'Racine', 'url' => '/', 'children' => [['title' => 'Interdit', 'url' => '/x']]],
            ['title' => 'Autre', 'url' => '/autre'],
        ]])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});

it('404s an unknown navigation and applies whitelist + permissions', function () {
    $this->withToken($this->token)->getJson('/api/editor/v1/navigations/nope/tree')->assertStatus(404);

    config()->set('statamic.editor-api.resources.navigations', ['autre']);
    $this->withToken($this->token)->getJson('/api/editor/v1/navigations/main/tree')->assertStatus(404);
    config()->set('statamic.editor-api.resources.navigations', true);

    $viewOnly = $this->makeTokenWithPermissions(['view main nav']);
    $this->withToken($viewOnly)->getJson('/api/editor/v1/navigations/main/tree')->assertOk();
    $this->withToken($viewOnly)
        ->patchJson('/api/editor/v1/navigations/main/tree', ['tree' => []])
        ->assertStatus(403);
});
