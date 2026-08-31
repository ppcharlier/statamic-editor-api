<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    Taxonomy::make('themes')->title('Thèmes')->save();

    Blueprint::make('theme')
        ->setNamespace('taxonomies.themes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Titre', 'validate' => ['required']]],
            ['handle' => 'description', 'field' => ['type' => 'textarea', 'display' => 'Description']],
        ]]]]]])
        ->save();

    $this->token = $this->makeSuperToken();
});

it('lists taxonomies with a compact term blueprint', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies')
        ->assertOk();

    $taxonomy = collect($response->json('data'))->firstWhere('handle', 'themes');

    expect($taxonomy['title'])->toBe('Thèmes');

    $fieldHandles = collect($taxonomy['blueprint']['tabs'])
        ->pluck('fields')->flatten(1)->pluck('handle');

    expect($fieldHandles)->toContain('title', 'description');
});

it('filters the taxonomies list by permission', function () {
    $viewOnly = $this->makeTokenWithPermissions(['view themes terms']);

    $response = $this->withToken($viewOnly)
        ->getJson('/api/editor/v1/taxonomies')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('handle')->all())->toBe(['themes']);

    $unrelated = $this->makeTokenWithPermissions(['view main nav']);

    $response = $this->withToken($unrelated)
        ->getJson('/api/editor/v1/taxonomies')
        ->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('excludes taxonomies outside the whitelist', function () {
    config()->set('statamic.editor-api.resources.taxonomies', ['autres']);

    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies')
        ->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('exposes every blueprint of the set in blueprints, keeping blueprint as the first', function () {
    Blueprint::make('writer')
        ->setNamespace('taxonomies.themes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'bio', 'field' => ['type' => 'textarea']],
        ]]]]]])
        ->save();

    $taxonomy = collect($this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies')
        ->assertOk()
        ->json('data'))->firstWhere('handle', 'themes');

    expect(collect($taxonomy['blueprints'])->pluck('handle')->all())->toBe(['theme', 'writer'])
        ->and($taxonomy['blueprint']['handle'])->toBe('theme'); // compat : premier du set
});
