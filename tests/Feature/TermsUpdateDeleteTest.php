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
    expect(Term::find('themes::philosophie'))->toBeNull();
});

it('normalizes an uppercase slug on rename and still returns 200', function () {
    // Statamic\Support\Str::slug() lowercases but deliberately preserves underscores
    // ("Statamic is a-OK with underscores in slugs" — see vendor source), so the
    // normalized form of 'Philo_Nouvelle' is 'philo_nouvelle', not 'philo-nouvelle'.
    // The case change alone is enough to make the raw client string diverge from the
    // normalized slug, which is what this test guards against.
    $response = $this->withToken($this->token)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/philosophie', [
            'slug' => 'Philo_Nouvelle',
            'data' => ['title' => 'Philosophie'],
        ])->assertOk();

    expect($response->json('data.slug'))->toBe('philo_nouvelle');
    expect(Term::find('themes::philo_nouvelle'))->not->toBeNull();
});

function makeVarianteBlueprint(): void
{
    // Nommé après 'theme' dans l'ordre alphabétique pour que le blueprint par défaut du
    // set (le premier) reste 'theme' et que le switch testé soit un vrai changement.
    Blueprint::make('variante')
        ->setNamespace('taxonomies.themes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'resume', 'field' => ['type' => 'textarea']],
        ]]]]]])
        ->save();
}

it('switches the blueprint when the data is valid', function () {
    makeVarianteBlueprint();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/philosophie', [
            'blueprint' => 'variante',
            'data' => ['title' => 'Philosophie', 'resume' => 'Court'],
        ])->assertOk()
        ->assertJsonPath('data.blueprint', 'variante');

    expect(Term::find('themes::philosophie')->blueprint()->handle())->toBe('variante');
});

it('leaves the blueprint untouched when the payload is rejected', function () {
    // Stache rend le MÊME objet Term à chaque lookup du process, et LocalizedTerm::blueprint()
    // écrit dans ce Term partagé (le clone du contrôleur est superficiel). Poser le blueprint
    // avant la validation laisserait donc l'instance en cache mutée après un 422 — verrouillé ici.
    makeVarianteBlueprint();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/philosophie', [
            'blueprint' => 'variante',
            'data' => ['title' => ''], // viole le `required` du blueprint
        ])->assertStatus(422);

    expect(Term::find('themes::philosophie')->blueprint()->handle())->toBe('theme');
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
