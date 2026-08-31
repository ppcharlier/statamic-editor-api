<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    Taxonomy::make('themes')->title('Thèmes')->sites(['en', 'fr'])->save();
    Blueprint::make('theme')->setNamespace('taxonomies.themes')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
        ]]]]]])->save();

    $term = Term::make('philosophie')->taxonomy('themes');
    $term->in('en')->data(['title' => 'Philosophy']);
    $term->in('fr')->data(['title' => 'Philosophie']);
    $term->save();

    $this->token = $this->makeSuperToken();
});

it('lists and updates the requested site localization of a term', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies/themes/terms?site=fr')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Philosophie');

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/taxonomies/themes/terms/philosophie?site=fr', [
            'data' => ['title' => 'La philosophie'],
        ])->assertOk();

    expect(Term::find('themes::philosophie')->in('fr')->value('title'))->toBe('La philosophie')
        ->and(Term::find('themes::philosophie')->in('en')->value('title'))->toBe('Philosophy');
});

it('creates a term directly in a non-default site', function () {
    // A term stores every locale's data in ONE file with the default locale as its
    // base (Statamic\Taxonomies\Term::fileData()), so creating with no data at all
    // for the default site can't be saved (matches Statamic's own CP
    // TermsController::store(), which copies the same values into the default
    // localization before saving when the target site isn't the default one).
    // The response and both localizations should therefore all carry the term.
    $response = $this->withToken($this->token)
        ->postJson('/api/editor/v1/taxonomies/themes/terms?site=fr', [
            'slug' => 'mystique',
            'data' => ['title' => 'Mystique'],
        ])->assertStatus(201);

    expect($response->json('data.title'))->toBe('Mystique');

    $term = Term::find('themes::mystique');
    expect($term->in('fr')->value('title'))->toBe('Mystique')
        ->and($term->in('en')->value('title'))->toBe('Mystique');
});

it('422s an unknown site outside the taxonomy set', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/taxonomies/themes/terms?site=de')
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']]);
});
