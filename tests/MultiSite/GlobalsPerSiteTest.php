<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    Blueprint::make('footer')->setNamespace('globals')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'tagline', 'field' => ['type' => 'text']],
        ]]]]]])->save();

    // Contingence brief §NOTE : `addLocalization`/`makeLocalization` public n'existe pas sur
    // GlobalSet (vendor/statamic/cms/src/Globals/GlobalSet.php). `sites()` déclare la liste des
    // sites du set ; `save()` crée les localisations manquantes via `saveOrDeleteLocalizations()`.
    // On peuple ensuite chaque localisation via `in($site)->data(...)->save()`.
    $set = GlobalSet::make('footer')->title('Footer')->sites(['en', 'fr']);
    $set->save();
    $set->in('en')->data(['tagline' => 'Hello'])->save();
    $set->in('fr')->data(['tagline' => 'Bonjour'])->save();

    $this->token = $this->makeSuperToken();
});

it('reads and writes the requested site localization', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/globals/footer?site=fr')
        ->assertOk()
        ->assertJsonPath('data.values.tagline', 'Bonjour');

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/globals/footer?site=fr', ['data' => ['tagline' => 'Salut']])
        ->assertOk();

    expect(GlobalSet::findByHandle('footer')->in('fr')->get('tagline'))->toBe('Salut')
        ->and(GlobalSet::findByHandle('footer')->in('en')->get('tagline'))->toBe('Hello'); // intact
});

it('404s a site the global set does not have', function () {
    $this->withToken($this->token)
        ->getJson('/api/editor/v1/globals/footer?site=de')
        ->assertStatus(404);
});
