<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    Taxonomy::make('themes')->title('Thèmes')->save();
    Blueprint::make('theme')
        ->setNamespace('taxonomies.themes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'description', 'field' => ['type' => 'textarea']],
        ]]]]]])
        ->save();

    Term::make('philosophie')->taxonomy('themes')->data(['title' => 'Philosophie', 'description' => 'Origine'])->save();
    $this->token = $this->makeSuperToken();
});

it('updates a term', function () {
    $response = $this->withToken($this->token)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/philosophie', [
            'data' => ['title' => 'Philosophie moderne', 'description' => 'Mise à jour'],
        ])->assertOk();

    expect($response->json('data.title'))->toBe('Philosophie moderne');
    expect(Term::find('themes::philosophie')->in(\Statamic\Facades\Site::default()->handle())->get('description'))->toBe('Mise à jour');
});

it('renames the slug', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/philosophie', [
            'slug' => 'philo',
            'data' => ['title' => 'Philosophie'],
        ])->assertOk()
        ->assertJsonPath('data.slug', 'philo');

    expect(Term::find('themes::philo'))->not->toBeNull();
});

it('rejects slug inside data', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/philosophie', [
            'data' => ['title' => 'X', 'slug' => 'sournois'],
        ])->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']]);
});

it('404s an unknown slug and enforces permissions', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/inconnu', ['data' => ['title' => 'X']])
        ->assertStatus(404);

    $viewOnly = $this->makeTokenWithPermissions(['view themes terms']);
    $this->withToken($viewOnly)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/philosophie', ['data' => ['title' => 'X']])
        ->assertStatus(403);
});

it('deletes a term', function () {
    $this->withToken($this->token)
        ->deleteJson('/api/editor/v1/taxonomies/themes/terms/philosophie')
        ->assertStatus(204);

    expect(Term::find('themes::philosophie'))->toBeNull();
});

it('403s delete without the permission', function () {
    $editOnly = $this->makeTokenWithPermissions(['view themes terms', 'edit themes terms']);

    $this->withToken($editOnly)
        ->deleteJson('/api/editor/v1/taxonomies/themes/terms/philosophie')
        ->assertStatus(403);
});
