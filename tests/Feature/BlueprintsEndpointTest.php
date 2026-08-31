<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection();
});

it('lists the blueprints of a collection', function () {
    $this->withToken($this->makeSuperToken())
        ->getJson('/api/editor/v1/collections/articles/blueprints')
        ->assertOk()
        ->assertJsonPath('data.0.handle', 'article');
});

it('shows one blueprint in compact form', function () {
    $response = $this->withToken($this->makeSuperToken())
        ->getJson('/api/editor/v1/collections/articles/blueprints/article')
        ->assertOk();

    expect($response->json('data.handle'))->toBe('article')
        ->and($response->json('data.tabs.0.fields'))->toBeArray();
});

it('explodes a pipe-delimited validate string into a rules list', function () {
    \Statamic\Facades\Blueprint::make('piped')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Titre', 'validate' => 'required|min:3']],
            ['handle' => 'body', 'field' => ['type' => 'textarea', 'display' => 'Corps', 'validate' => ['required', 'min:3']]],
        ]]]]]])
        ->save();

    $fields = $this->withToken($this->makeSuperToken())
        ->getJson('/api/editor/v1/collections/articles/blueprints/piped')
        ->assertOk()
        ->json('data.tabs.0.fields');

    // String pipe syntax is exploded; the array form passes through untouched.
    expect($fields[0]['rules'])->toBe(['required', 'min:3'])
        ->and($fields[1]['rules'])->toBe(['required', 'min:3']);
});

it('404s an unknown blueprint handle with the standard envelope', function () {
    $this->withToken($this->makeSuperToken())
        ->getJson('/api/editor/v1/collections/articles/blueprints/nope')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('404s an unknown collection (statamic binding) with the standard envelope', function () {
    $this->withToken($this->makeSuperToken())
        ->getJson('/api/editor/v1/collections/nope/blueprints')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('404s a collection excluded by the whitelist', function () {
    config()->set('statamic.editor-api.resources.collections', ['pages']);

    $this->withToken($this->makeSuperToken())
        ->getJson('/api/editor/v1/collections/articles/blueprints')
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'not_found']]);
});

it('403s a user without view permission', function () {
    $token = $this->makeTokenWithPermissions(['edit articles entries']); // pas view

    $this->withToken($token)
        ->getJson('/api/editor/v1/collections/articles/blueprints')
        ->assertStatus(403)
        ->assertJson(['error' => ['code' => 'forbidden']]);
});
