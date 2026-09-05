<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

// Statamic 6 valide un champ `date` avec heure contre `Y-m-d\TH:i:s.v\Z` (Rules\DateFieldtype),
// alors qu'il STOCKE `Y-m-d H:i` en heure murale du fuseau du site. Le Control Panel passe par
// Date::preProcess() avant l'envoi ; un client qui renvoie ce que GET a servi — l'app iOS — était
// rejeté « Not a valid date. » sur un champ qu'il n'avait pas touché (constaté le 2026-09-05).
beforeEach(function () {
    // Un fuseau décalé d'UTC : tout aller-retour qui perdrait le fuseau se verrait ici.
    config()->set('app.timezone', 'Europe/Brussels');
    date_default_timezone_set('Europe/Brussels');

    $this->makeArticlesCollection();
    $this->token = $this->makeSuperToken();

    $visited = ['handle' => 'visited_on', 'field' => ['type' => 'date', 'display' => 'Visité le', 'time_enabled' => true]];

    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Titre', 'validate' => ['required']]],
            $visited,
            ['handle' => 'day', 'field' => ['type' => 'date', 'display' => 'Jour', 'format' => 'Y-m-d']],
            ['handle' => 'etapes', 'field' => ['type' => 'grid', 'display' => 'Étapes', 'fields' => [$visited]]],
        ]]]]]])
        ->save();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('auberge')->date('2026-01-01')
            ->data([
                'title' => 'Auberge',
                'visited_on' => '2026-06-15 19:30',
                'day' => '2026-06-15',
                'etapes' => [['visited_on' => '2026-06-16 08:00']],
            ])
            ->published(false)
    )->save();
});

it('accepts back verbatim the date data it just returned, without shifting it', function () {
    $read = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk()->json('data.data');

    expect($read['visited_on'])->toBe('2026-06-15 19:30')
        ->and($read['day'])->toBe('2026-06-15')
        ->and($read['etapes'][0]['visited_on'])->toBe('2026-06-16 08:00');

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['slug' => 'auberge-bis', 'data' => $read])
        ->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('visited_on'))->toBe('2026-06-15 19:30')
        ->and($live->value('day'))->toBe('2026-06-15')
        ->and($live->value('etapes')[0]['visited_on'])->toBe('2026-06-16 08:00');
});

it('stores a new wall-clock value exactly as sent', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'Auberge', 'visited_on' => '2026-07-01 08:15', 'day' => '2026-07-02']])
        ->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('visited_on'))->toBe('2026-07-01 08:15')
        ->and($live->value('day'))->toBe('2026-07-02');
});

it('still accepts the Zulu shape a Control Panel style client sends, converted to the site timezone', function () {
    // 06:15 UTC = 08:15 à Bruxelles en été.
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'Auberge', 'visited_on' => '2026-06-30T06:15:00.000Z']])
        ->assertOk();

    expect(Entry::find($this->entry->id())->value('visited_on'))->toBe('2026-06-30 08:15');
});

it('rejects a value that is not a date with a 422 on the field, never a 500', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => ['title' => 'Auberge', 'visited_on' => 'pas-une-date']])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['errors' => ['visited_on']]]);

    expect(Entry::find($this->entry->id())->value('visited_on'))->toBe('2026-06-15 19:30');
});
