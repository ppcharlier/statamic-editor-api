<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

uses(BuildsEntryFixtures::class);

function makeThemesTaxonomy(): void
{
    Taxonomy::make('themes')->title('Thèmes')->save();

    Blueprint::make('theme')
        ->setNamespace('taxonomies.themes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'description', 'field' => ['type' => 'textarea']],
        ]]]]]])
        ->save();
}

beforeEach(function () {
    makeThemesTaxonomy();
    $this->token = $this->makeSuperToken();

    Term::make('philosophie')->taxonomy('themes')->data(['title' => 'Philosophie'])->save();
    Term::make('poesie')->taxonomy('themes')->data(['title' => 'Poésie'])->save();
});

it('lists terms with pagination', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies/themes/terms')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2)
        ->and(collect($response->json('data'))->pluck('slug'))->toContain('philosophie', 'poesie');

    $term = collect($response->json('data'))->firstWhere('slug', 'philosophie');
    expect($term['id'])->toBe('themes::philosophie')
        ->and($term['title'])->toBe('Philosophie');
});

it('sorts terms descending with ?sort=-title', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies/themes/terms?sort=-title')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'poesie');
});

it('422s an unknown terms sort field', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies/themes/terms?sort=nope')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});

it('searches on title', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies/themes/terms?search=Philo')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('creates a term', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', [
            'slug' => 'mystique',
            'data' => ['title' => 'Mystique', 'description' => 'Textes mystiques'],
        ])->assertStatus(201);

    expect($response->json('data.id'))->toBe('themes::mystique');
    expect(Term::find('themes::mystique'))->not->toBeNull();
});

it('rejects a duplicate slug', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', [
            'slug' => 'philosophie',
            'data' => ['title' => 'Doublon'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});

it('validates blueprint rules and unknown keys', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', ['slug' => 'x', 'data' => ['description' => 'sans titre']])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['errors' => ['title']]]);

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', ['slug' => 'y', 'data' => ['title' => 'Ok', 'nope' => 1]])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']]);
});

it('ignores a client-supplied slug inside data in favor of the top-level slug', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', [
            'slug' => 'valide',
            'data' => ['title' => 'Valide', 'slug' => ''],
        ])->assertStatus(201)
        ->assertJsonPath('data.slug', 'valide');

    expect(\Statamic\Facades\Term::find('themes::valide'))->not->toBeNull();
});

it('enforces permissions and whitelist', function () {
    $viewOnly = $this->makeTokenWithPermissions(['view themes terms']);

    $this->withToken($viewOnly)->getJson('/api/editor/v1/taxonomies/themes/terms')->assertOk();
    $this->withToken($viewOnly)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', ['slug' => 'z', 'data' => ['title' => 'Z']])
        ->assertStatus(403);

    config()->set('statamic.editor-api.resources.taxonomies', ['autres']);
    $this->withToken($this->token)->getJson('/api/editor/v1/taxonomies/themes/terms')->assertStatus(404);
});

function addPersonBlueprint(): void
{
    Blueprint::make('writer')
        ->setNamespace('taxonomies.themes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'bio', 'field' => ['type' => 'textarea']],
        ]]]]]])
        ->save();
}

it('creates a term with an explicit blueprint from the set', function () {
    addPersonBlueprint();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', [
            'slug' => 'victor-hugo', 'blueprint' => 'writer',
            'data' => ['title' => 'Victor Hugo', 'bio' => 'Écrivain.'],
        ])->assertStatus(201)
        ->assertJsonPath('data.blueprint', 'writer')
        ->assertJsonPath('data.data.bio', 'Écrivain.');

    expect(Term::find('themes::victor-hugo')->blueprint()->handle())->toBe('writer');
});

it('422s a blueprint outside the set', function () {
    addPersonBlueprint();

    $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', [
            'slug' => 'x', 'blueprint' => 'nope',
            'data' => ['title' => 'X'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['blueprint']]]);
});

it('validates data against the chosen blueprint, not the first of the set', function () {
    addPersonBlueprint();

    // bio n'existe pas dans le blueprint 'theme' (premier) mais existe dans 'writer'
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms', [
            'slug' => 'sans-blueprint', 'data' => ['title' => 'X', 'bio' => 'refusé'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']]);
});

it('exposes the blueprint handle in term listings', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies/themes/terms')
        ->assertOk()
        ->assertJsonPath('data.0.blueprint', 'theme');
});
