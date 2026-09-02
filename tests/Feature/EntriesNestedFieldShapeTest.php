<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

// Un champ relation `max_items: 1` niché dans un conteneur est validé sous son chemin complet
// (`blocs.0.serie`) et rejeté exactement comme à la racine. Ce fichier verrouille la descente
// dans les quatre conteneurs dont la forme stockée est connue.
beforeEach(function () {
    $this->makeArticlesCollection();
    $this->container = $this->makeUploadsContainer();
    $this->token = $this->makeSuperToken();

    Collection::make('series')->title('Séries')->save();

    $serieField = ['handle' => 'serie', 'field' => [
        'type' => 'entries', 'display' => 'Série', 'collections' => ['series'], 'max_items' => 1,
    ]];
    $heroField = ['handle' => 'hero', 'field' => [
        'type' => 'assets', 'display' => 'Image', 'container' => 'uploads', 'max_files' => 1,
    ]];

    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Titre', 'validate' => ['required']]],
            ['handle' => 'meta', 'field' => ['type' => 'group', 'display' => 'Méta', 'fields' => [$serieField]]],
            ['handle' => 'blocs', 'field' => ['type' => 'grid', 'display' => 'Blocs', 'fields' => [$serieField, $heroField]]],
            ['handle' => 'sections', 'field' => ['type' => 'replicator', 'display' => 'Sections', 'sets' => [
                'renvoi' => ['fields' => [$serieField]],
            ]]],
            ['handle' => 'contenu', 'field' => ['type' => 'bard', 'display' => 'Contenu', 'sets' => [
                'encart' => ['fields' => [$serieField]],
            ]]],
        ]]]]]])
        ->save();

    $this->container->makeAsset('cover.jpg')->upload(UploadedFile::fake()->image('cover.jpg'));

    $this->serie = tap(Entry::make()->collection('series')->slug('ma-serie')->data(['title' => 'Ma série']))->save();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('premier')->date('2026-01-01')->data([
            'title' => 'Premier',
            'meta' => ['serie' => $this->serie->id()],
            'blocs' => [['id' => 'row-1', 'serie' => $this->serie->id(), 'hero' => 'cover.jpg']],
            'sections' => [['id' => 'set-1', 'type' => 'renvoi', 'serie' => $this->serie->id()]],
            'contenu' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bonjour']]],
                ['type' => 'set', 'attrs' => ['id' => 'bard-1', 'values' => [
                    'type' => 'encart', 'serie' => $this->serie->id(),
                ]]],
            ],
        ])->published(false)
    )->save();
});

it('accepts back verbatim a relationship nested in a group, grid, replicator or bard set', function () {
    $read = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk()->json('data.data');

    expect($read['meta']['serie'])->toBe($this->serie->id())
        ->and($read['blocs'][0]['serie'])->toBe($this->serie->id())
        ->and($read['blocs'][0]['hero'])->toBe('cover.jpg')
        ->and($read['sections'][0]['serie'])->toBe($this->serie->id())
        ->and($read['contenu'][1]['attrs']['values']['serie'])->toBe($this->serie->id());

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['slug' => 'premier-bis', 'data' => $read])
        ->assertOk();

    $live = Entry::find($this->entry->id());

    expect($live->value('meta')['serie'])->toBe($this->serie->id())
        ->and($live->value('blocs')[0]['serie'])->toBe($this->serie->id())
        ->and($live->value('blocs')[0]['hero'])->toBe('cover.jpg')
        ->and($live->value('sections')[0]['serie'])->toBe($this->serie->id())
        ->and($live->value('contenu')[1]['attrs']['values']['serie'])->toBe($this->serie->id())
        ->and($live->value('contenu')[0]['content'][0]['text'])->toBe('Bonjour');
});

it('still accepts the array shape a Control Panel style client sends when nested', function () {
    $autre = tap(Entry::make()->collection('series')->slug('autre-serie')->data(['title' => 'Autre']))->save();

    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Premier',
            'blocs' => [['id' => 'row-1', 'serie' => [$autre->id()], 'hero' => ['uploads::cover.jpg']]],
        ]])->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('blocs')[0]['serie'])->toBe($autre->id())
        ->and($live->value('blocs')[0]['hero'])->toBe('cover.jpg');
});

it('still enforces max_items on a nested relationship', function () {
    $autre = tap(Entry::make()->collection('series')->slug('autre-serie')->data(['title' => 'Autre']))->save();

    $errors = $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Premier',
            'blocs' => [['id' => 'row-1', 'serie' => [$this->serie->id(), $autre->id()]]],
        ]])->assertStatus(422)->json('error.errors');

    expect($errors['blocs.0.serie'][0])->toBe('The Série field must not have more than 1 items.');
});

it('names the full path when a nested asset does not exist', function () {
    $errors = $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Premier',
            'blocs' => [['id' => 'row-1', 'hero' => 'nexistepas.jpg']],
        ]])->assertStatus(422)->json('error.errors');

    expect($errors)->toHaveCount(1)
        ->and($errors['blocs.0.hero'][0])->toBe('One or more of these assets do not exist in this container.');
});
