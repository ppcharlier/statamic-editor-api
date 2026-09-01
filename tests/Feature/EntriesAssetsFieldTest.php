<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

beforeEach(function () {
    $this->container = $this->makeUploadsContainer();
    $this->makeArticlesCollection();
    $this->token = $this->makeSuperToken();

    // `max_files: 1` est le cas d'une image de couverture : Statamic STOCKE alors un chemin
    // scalaire, mais son fieldtype ATTEND en écriture un tableau d'ids `container::path`
    // (voir Statamic\Fieldtypes\Assets\Assets::preProcess/process). C'est cette asymétrie
    // que ce fichier verrouille.
    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Titre', 'validate' => ['required']]],
            ['handle' => 'hero', 'field' => ['type' => 'assets', 'display' => 'Cover image', 'container' => 'uploads', 'max_files' => 1]],
            ['handle' => 'gallery', 'field' => ['type' => 'assets', 'display' => 'Gallery', 'container' => 'uploads']],
        ]]]]]])
        ->save();

    $this->container->makeAsset('cover.jpg')->upload(UploadedFile::fake()->image('cover.jpg'));
    $this->container->makeAsset('second.jpg')->upload(UploadedFile::fake()->image('second.jpg'));

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('avec-couverture')->date('2026-01-01')
            ->data(['title' => 'Avec couverture', 'hero' => 'cover.jpg', 'gallery' => ['cover.jpg', 'second.jpg']])
            ->published(false)
    )->save();
});

it('accepts back verbatim the assets data it just returned', function () {
    $read = $this->withToken($this->token)
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())
        ->assertOk()->json('data.data');

    expect($read['hero'])->toBe('cover.jpg')
        ->and($read['gallery'])->toBe(['cover.jpg', 'second.jpg']);

    // Le contrat documenté : « Echoing GET data straight back into PATCH data is safe ».
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => $read])
        ->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('hero'))->toBe('cover.jpg')
        ->and($live->value('gallery'))->toBe(['cover.jpg', 'second.jpg']);
});

it('still accepts the container::path id shape a Control Panel style client sends', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Avec couverture',
            'hero' => ['uploads::second.jpg'],
            'gallery' => ['uploads::second.jpg'],
        ]])->assertOk();

    $live = Entry::find($this->entry->id());
    expect($live->value('hero'))->toBe('second.jpg')
        ->and($live->value('gallery'))->toBe(['second.jpg']);
});

it('clears an assets field when the client sends null', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Avec couverture', 'hero' => null, 'gallery' => [],
        ]])->assertOk();

    expect(Entry::find($this->entry->id())->value('hero'))->toBeNull();
});

it('422s a path that does not exist in the container instead of a 500', function () {
    $this->withToken($this->token)
        ->patchJson('/api/editor/v1/entries/'.$this->entry->id(), ['data' => [
            'title' => 'Avec couverture', 'hero' => 'nexistepas.jpg',
        ]])->assertStatus(422);
});
