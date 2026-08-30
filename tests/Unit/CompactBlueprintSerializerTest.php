<?php

use Ppcharlier\StatamicEditorApi\Http\Blueprints\CompactBlueprintSerializer;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;

uses(BuildsEntryFixtures::class);

it('serializes a blueprint into the compact form format', function () {
    $this->makeArticlesCollection();

    $blueprint = \Statamic\Facades\Collection::findByHandle('articles')->entryBlueprint();
    $compact = CompactBlueprintSerializer::serialize($blueprint);

    expect($compact['handle'])->toBe('article')
        ->and($compact['tabs'])->toBeArray()->not->toBeEmpty();

    $fields = collect($compact['tabs'])->flatMap(fn ($tab) => $tab['fields']);
    $title = $fields->firstWhere('handle', 'title');

    expect($title['type'])->toBe('text')
        ->and($title['display'])->toBe('Titre')
        ->and($title['required'])->toBeTrue()
        ->and($title['rules'])->toContain('required');

    $body = $fields->firstWhere('handle', 'body');
    expect($body['required'])->toBeFalse();
});

it('excludes surfaced keys from the config passthrough', function () {
    $this->makeArticlesCollection();

    $blueprint = \Statamic\Facades\Collection::findByHandle('articles')->entryBlueprint();
    $fields = collect(CompactBlueprintSerializer::serialize($blueprint)['tabs'])->flatMap(fn ($t) => $t['fields']);

    expect($fields->firstWhere('handle', 'title')['config'])
        ->not->toHaveKeys(['display', 'instructions', 'validate', 'type']);
});

it('flags slug and date as meta handles, but not title or body', function () {
    $this->makeArticlesCollection(withRevisions: true);

    $blueprint = \Statamic\Facades\Collection::findByHandle('articles')->entryBlueprint();
    $fields = collect(CompactBlueprintSerializer::serialize($blueprint)['tabs'])->flatMap(fn ($t) => $t['fields']);

    expect($fields->firstWhere('handle', 'slug')['meta'])->toBeTrue()
        ->and($fields->firstWhere('handle', 'date')['meta'])->toBeTrue()
        ->and($fields->firstWhere('handle', 'title')['meta'])->toBeFalse()
        ->and($fields->firstWhere('handle', 'body')['meta'])->toBeFalse();
});
