<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class);

beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);
    $this->token = $this->makeSuperToken();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('live-slug')->date('2026-01-01')
            ->data(['title' => 'Titre live', 'body' => 'Corps live'])->published(true)
    )->save();

    // Un brouillon qui déplace TOUT ce qui est éditable : contenu, date et slug.
    $this->withToken($this->token)->patchJson('/api/editor/v1/entries/'.$this->entry->id(), [
        'slug' => 'slug-du-brouillon',
        'date' => '2026-06-15',
        'data' => ['title' => 'Titre du brouillon', 'body' => 'Corps du brouillon'],
    ])->assertOk();
});

it('returns the working copy for every editable field, not just data', function () {
    $detail = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk()->json('data');

    // `data` venait déjà de la working copy ; date, slug et title doivent suivre, sinon le
    // client édite un mélange de deux versions — et c'est la working copy que publish applique.
    expect($detail['data']['title'])->toBe('Titre du brouillon')
        ->and($detail['title'])->toBe('Titre du brouillon')
        ->and($detail['slug'])->toBe('slug-du-brouillon')
        ->and($detail['date'])->toStartWith('2026-06-15');
});

it('keeps the published state live, since the draft is precisely what is not published', function () {
    $detail = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk()->json('data');

    expect($detail['status'])->toBe('published')
        ->and($detail['published'])->toBeTrue()
        ->and($detail['has_unpublished_changes'])->toBeTrue();

    // Le fichier live, lui, n'a pas bougé.
    $live = Entry::find($this->entry->id());
    expect($live->slug())->toBe('live-slug')
        ->and($live->value('title'))->toBe('Titre live');
});

it('publishes exactly what the detail payload showed', function () {
    $this->withToken($this->token)
        ->postJson('/api/editor/v1/entries/'.$this->entry->id().'/published', [])
        ->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('title'))->toBe('Titre du brouillon')
        ->and($live->slug())->toBe('slug-du-brouillon')
        ->and($live->date()->format('Y-m-d'))->toBe('2026-06-15');
});

it('leaves an entry without a working copy untouched', function () {
    $plain = tap(Entry::make()->collection('articles')->slug('sans-brouillon')->date('2026-03-03')
        ->data(['title' => 'Sans brouillon'])->published(true))->save();

    $detail = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$plain->id())
        ->assertOk()->json('data');

    expect($detail['title'])->toBe('Sans brouillon')
        ->and($detail['slug'])->toBe('sans-brouillon')
        ->and($detail['date'])->toStartWith('2026-03-03')
        ->and($detail['has_unpublished_changes'])->toBeFalse();
});
