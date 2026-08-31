<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;

uses(BuildsEntryFixtures::class);

function makeFooterGlobals(): void
{
    // Contingence brief §step1 : `addLocalization`/`makeLocalization` public n'existe pas sur
    // GlobalSet (vendor/statamic/cms/src/Globals/GlobalSet.php). `save()` crée automatiquement
    // la localisation du site par défaut via `saveOrDeleteLocalizations()` quand `sites()` est
    // vide (fallback sur `Site::default()->handle()`), donc un simple save() suffit.
    $set = GlobalSet::make('footer')->title('Pied de page');
    $set->save();

    Blueprint::make('footer')
        ->setNamespace('globals')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'copyright', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'tagline', 'field' => ['type' => 'text']],
        ]]]]]])
        ->save();

    $set->inDefaultSite()->data(['copyright' => '© 2026', 'tagline' => 'Un blog'])->save();
}

beforeEach(function () {
    makeFooterGlobals();
    $this->token = $this->makeSuperToken();
});

it('lists editable global sets', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/globals')
        ->assertOk()
        ->assertJsonPath('data.0.handle', 'footer')
        ->assertJsonPath('data.0.title', 'Pied de page');
});

it('hides sets the user cannot edit from the list', function () {
    $token = $this->makeTokenWithPermissions(['view articles entries']); // aucune permission globals

    $response = $this->withToken($token)->getJson('/api/editor/v1/globals')->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('shows a set with compact blueprint and round-trippable values', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/editor/v1/globals/footer')
        ->assertOk();

    expect($response->json('data.handle'))->toBe('footer')
        ->and($response->json('data.values.copyright'))->toBe('© 2026')
        ->and($response->json('data.blueprint.tabs'))->toBeArray();
});

it('updates values as a full replacement with blueprint validation', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/globals/footer', [
            'data' => ['copyright' => '© 2027', 'tagline' => 'Nouveau'],
        ])->assertOk()
        ->assertJsonPath('data.values.copyright', '© 2027');

    expect(GlobalSet::findByHandle('footer')->inDefaultSite()->get('copyright'))->toBe('© 2027');
});

it('validates blueprint rules', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/globals/footer', ['data' => ['copyright' => '', 'tagline' => 'x']])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'validation_failed']])
        ->assertJsonStructure(['error' => ['errors' => ['copyright']]]);
});

it('rejects unknown keys', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/globals/footer', ['data' => ['copyright' => 'x', 'hacker' => 'y']])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'unknown_field']]);
});

it('403s update without the edit permission and 404s outside the whitelist', function () {
    $token = $this->makeTokenWithPermissions(['view articles entries']);

    $this->withToken($token)
        ->patchJson('/api/editor/v1/globals/footer', ['data' => ['copyright' => 'x']])
        ->assertStatus(403);

    config()->set('statamic.editor-api.resources.globals', ['autre']);

    $this->withToken($this->token)
        ->getJson('/api/editor/v1/globals/footer')
        ->assertStatus(404);
});

it('rejects a non-default site parameter', function () {
    // Contingence brief §step1 : pour `{global}`, le binder vendor
    // (RouteServiceProvider::bindGlobalSets) lit request('site'), résout la localisation
    // *avant* que le contrôleur ne s'exécute, et lève un 404 (NotFoundHttpException) si le
    // set n'a pas de localisation pour ce site — ici 'fr' n'existe pas sur `footer` (mono-site
    // dans ce contexte de test, donc 'fr' est de toute façon inconnu). Notre
    // SiteResolver::resolve (422 `validation_failed`) ne peut donc jamais s'exécuter pour cette
    // route : le contrat « site non supporté ne passe pas » est préservé, seule la forme (404 au
    // lieu de 422) diffère. Le contrôleur appelle quand même SiteResolver::resolve en défense en
    // profondeur, utile pour d'éventuelles routes globals futures non liées par ce binder.
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/globals/footer?site=fr')
        ->assertStatus(404);
});
