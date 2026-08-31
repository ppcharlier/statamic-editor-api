<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Taxonomy;

uses(BuildsEntryFixtures::class);

it('exposes the sites and each collection availability in /config', function () {
    $this->makeArticlesCollection();
    Collection::findByHandle('articles')->sites(['en', 'fr'])->save();

    $config = $this->withToken($this->makeSuperToken())
        ->getJson('/api/editor/v1/config')
        ->assertOk()
        ->json('data');

    expect(collect($config['sites'])->pluck('handle')->all())->toBe(['en', 'fr'])
        ->and(collect($config['sites'])->firstWhere('handle', 'en'))
        ->toMatchArray(['name' => 'English', 'default' => true, 'locale' => 'en_US'])
        ->and(collect($config['sites'])->firstWhere('handle', 'fr')['default'])->toBeFalse();

    $articles = collect($config['collections'])->firstWhere('handle', 'articles');
    expect($articles['sites'])->toBe(['en', 'fr']);
});

it('exposes the site availability of taxonomies, globals and navigations', function () {
    Taxonomy::make('themes')->title('Thèmes')->sites(['en', 'fr'])->save();
    Taxonomy::make('tags')->title('Tags')->sites(['fr'])->save();

    GlobalSet::make('footer')->title('Footer')->sites(['en', 'fr'])->save();
    GlobalSet::make('social')->title('Social')->save(); // sans sites déclarés → site par défaut

    // Une nav n'est disponible que là où un arbre existe (`Nav::sites()` = clés de `trees()`).
    $main = tap(Nav::make('main')->title('Menu'))->save();
    $main->makeTree('en', [['id' => 'b1', 'title' => 'Home', 'url' => '/']])->save();
    $main->makeTree('fr', [['id' => 'b1', 'title' => 'Accueil', 'url' => '/fr']])->save();

    $solo = tap(Nav::make('footer')->title('Pied'))->save();
    $solo->makeTree('en', [['id' => 'b2', 'title' => 'X', 'url' => '/x']])->save();

    $config = $this->withToken($this->makeSuperToken())
        ->getJson('/api/editor/v1/config')
        ->assertOk()
        ->json('data');

    expect(collect($config['taxonomies'])->firstWhere('handle', 'themes')['sites'])->toBe(['en', 'fr'])
        ->and(collect($config['taxonomies'])->firstWhere('handle', 'tags')['sites'])->toBe(['fr'])
        ->and(collect($config['globals'])->firstWhere('handle', 'footer')['sites'])->toBe(['en', 'fr'])
        ->and(collect($config['globals'])->firstWhere('handle', 'social')['sites'])->toBe(['en'])
        ->and(collect($config['navigations'])->firstWhere('handle', 'main')['sites'])->toBe(['en', 'fr'])
        ->and(collect($config['navigations'])->firstWhere('handle', 'footer')['sites'])->toBe(['en']);
});
