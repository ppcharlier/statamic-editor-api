<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Nav;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $nav = Nav::make('main')->title('Menu');
    $nav->save();
    $nav->makeTree('en', [['id' => 'b1', 'title' => 'Home', 'url' => '/']])->save();
    $nav->makeTree('fr', [['id' => 'b1', 'title' => 'Accueil', 'url' => '/fr']])->save();

    $this->token = $this->makeSuperToken();
});

it('serves and rewrites the tree of the requested site', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/navigations/main/tree?site=fr')
        ->assertOk()
        ->assertJsonPath('data.tree.0.title', 'Accueil');

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/navigations/main/tree?site=fr', ['tree' => [
            ['id' => 'b1', 'title' => 'Bienvenue', 'url' => '/fr'],
        ]])->assertOk();

    expect(Nav::findByHandle('main')->in('fr')->tree()[0]['title'])->toBe('Bienvenue')
        ->and(Nav::findByHandle('main')->in('en')->tree()[0]['title'])->toBe('Home');
});

it('404s a site the nav has no tree for', function () {
    $solo = Nav::make('footer')->title('Pied');
    $solo->save();
    $solo->makeTree('en', [['id' => 'b2', 'title' => 'X', 'url' => '/x']])->save();

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/navigations/footer/tree?site=fr')
        ->assertStatus(404);
});
