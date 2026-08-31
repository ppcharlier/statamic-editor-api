<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Collection;

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
