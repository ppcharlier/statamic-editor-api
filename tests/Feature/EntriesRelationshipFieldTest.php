<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection();
    $this->token = $this->makeSuperToken();

    Collection::make('series')->title('Séries')->save();
    Taxonomy::make('topics')->title('Topics')->save();

    // `max_items: 1` est au fieldtype `entries` ce que `max_files: 1` est aux assets :
    // Statamic STOCKE alors un scalaire, mais ses règles de validation exigent un tableau
    // (Statamic\Fieldtypes\Relationship::rules/process).
    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Titre', 'validate' => ['required']]],
            ['handle' => 'serie', 'field' => ['type' => 'entries', 'display' => 'Série', 'collections' => ['series'], 'max_items' => 1]],
            ['handle' => 'lectures', 'field' => ['type' => 'entries', 'display' => 'À lire aussi', 'collections' => ['series']]],
            ['handle' => 'topic', 'field' => ['type' => 'terms', 'display' => 'Sujet', 'taxonomies' => ['topics'], 'max_items' => 1]],
        ]]]]]])
        ->save();

    $this->serie = tap(Entry::make()->collection('series')->slug('ma-serie')->data(['title' => 'Ma série']))->save();
    Term::make('php')->taxonomy('topics')->data(['title' => 'PHP'])->save();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('premier')->date('2026-01-01')
            ->data(['title' => 'Premier', 'serie' => $this->serie->id(), 'topic' => 'php'])
            ->published(false)
    )->save();
});

it('accepts back verbatim the relationship data it just returned', function () {
    $read = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk()->json('data.data');

    expect($read['serie'])->toBe($this->serie->id())
        ->and($read['topic'])->toBe('php');

    // Le cas rapporté : on ne change que le slug, et le champ série — non touché — est rejeté.
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['slug' => 'premier-bis', 'data' => $read])
        ->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('serie'))->toBe($this->serie->id())
        ->and($live->value('topic'))->toBe('php');
});

it('still accepts the array shape a Control Panel style client sends', function () {
    $autre = tap(Entry::make()->collection('series')->slug('autre-serie')->data(['title' => 'Autre']))->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Premier',
            'serie' => [$autre->id()],
            'topic' => ['topics::php'],
            'lectures' => [$this->serie->id(), $autre->id()],
        ]])->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('serie'))->toBe($autre->id())
        ->and($live->value('topic'))->toBe('php')
        ->and($live->value('lectures'))->toBe([$this->serie->id(), $autre->id()]);
});

it('clears a relationship field when the client sends null', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Premier', 'serie' => null, 'topic' => null,
        ]])->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('serie'))->toBeNull()
        ->and($live->value('topic'))->toBeNull();
});

it('still enforces max_items', function () {
    $autre = tap(Entry::make()->collection('series')->slug('autre-serie')->data(['title' => 'Autre']))->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Premier', 'serie' => [$this->serie->id(), $autre->id()],
        ]])->assertStatus(422)
        ->assertJsonPath('error.errors.serie.0', 'The Série field must not have more than 1 items.');
});

it('accepts the stored shape on create too', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/collections/articles/entries', [
            'slug' => 'second', 'date' => '2026-02-01',
            'data' => ['title' => 'Second', 'serie' => $this->serie->id(), 'topic' => 'php'],
        ])->assertCreated();

    $created = Entry::query()->where('collection', 'articles')->where('slug', 'second')->first();
    expect($created->value('serie'))->toBe($this->serie->id())
        ->and($created->value('topic'))->toBe('php');
});
