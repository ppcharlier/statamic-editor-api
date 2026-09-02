<?php

use Illuminate\Http\UploadedFile;
use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsAssetFixtures;
use Ppcharlier\StatamicEditorApi\Tests\Support\BuildsEntryFixtures;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Role;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;

uses(BuildsEntryFixtures::class, BuildsAssetFixtures::class);

// The same policies that refuse a write can answer ahead of time: every payload carries
// a `can` block so a client greys out what the user may not do instead of eating a 403.
beforeEach(function () {
    $this->makeArticlesCollection(withRevisions: true);

    Blueprint::make('article')
        ->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text', 'validate' => ['required']]],
            ['handle' => 'author', 'field' => ['type' => 'users', 'max_items' => 1]],
        ]]]]]])
        ->save();

    Role::make('writer')->title('Writer')->permissions([
        'access editor-api',
        'view articles entries', 'edit articles entries', 'create articles entries', 'publish articles entries',
    ])->save();

    $this->owner = tap(User::make()->email('owner@example.com')->data(['name' => 'Jane Owner'])->assignRole('writer'))->save();
    $this->colleague = tap(User::make()->email('colleague@example.com')->assignRole('writer'))->save();

    $this->entry = tap(
        Entry::make()->collection('articles')->slug('mien')->date('2026-01-01')
            ->data(['title' => 'Mon article', 'author' => $this->owner->id()])->published(false)
    )->save();
});

function tokenFor2($user): string
{
    return app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
}

it('tells the author what they may do with their entry, in the list and the detail', function () {
    $list = $this->withToken(tokenFor2($this->owner))
        ->getJson('/api/editor/v1/collections/articles/entries')->assertOk()->json('data.0');
    $detail = $this->withToken(tokenFor2($this->owner))
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())->assertOk()->json('data');

    expect($list['can'])->toBe(['edit' => true, 'delete' => false, 'publish' => true])
        ->and($detail['can'])->toBe(['edit' => true, 'delete' => false, 'publish' => true]);
});

it('tells a colleague they may not touch another author\'s entry', function () {
    $list = $this->withToken(tokenFor2($this->colleague))
        ->getJson('/api/editor/v1/collections/articles/entries')->assertOk()->json('data.0');

    expect($list['can'])->toBe(['edit' => false, 'delete' => false, 'publish' => false]);
});

it('resolves the author to an id and a display name, never an email', function () {
    $detail = $this->withToken(tokenFor2($this->colleague))
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())->assertOk()->json('data');

    expect($detail['author'])->toBe(['id' => (string) $this->owner->id(), 'name' => 'Jane Owner']);
});

it('reports a null author when the blueprint has no author field', function () {
    Blueprint::make('article')->setNamespace('collections.articles')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]]])->save();
    \Statamic\Facades\Blink::flush(); // the entry memoizes its blueprint per request

    $detail = $this->withToken(tokenFor2($this->colleague))
        ->getJson('/api/editor/v1/entries/'.$this->entry->id())->assertOk()->json('data');

    expect($detail['author'])->toBeNull()
        ->and($detail['can']['edit'])->toBeTrue();
});

it('describes what the user may create, publish and upload in /config', function () {
    Taxonomy::make('tags')->title('Tags')->save();
    $this->makeUploadsContainer();

    $config = $this->withToken(tokenFor2($this->owner))
        ->getJson('/api/editor/v1/config')->assertOk()->json('data');

    $articles = collect($config['collections'])->firstWhere('handle', 'articles');
    $tags = collect($config['taxonomies'])->firstWhere('handle', 'tags');
    $uploads = collect($config['asset_containers'])->firstWhere('handle', 'uploads');

    expect($articles['can'])->toBe(['create' => true, 'publish' => true])
        ->and($tags['can'])->toBe(['create' => false])
        ->and($uploads['can'])->toBe(['upload' => false]);
});

it('carries a can block on terms, assets and globals', function () {
    Taxonomy::make('tags')->title('Tags')->save();
    Blueprint::make('tag')->setNamespace('taxonomies.tags')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]]])->save();
    Term::make('php')->taxonomy('tags')->data(['title' => 'PHP'])->save();

    $container = $this->makeUploadsContainer();
    $container->makeAsset('a.jpg')->upload(UploadedFile::fake()->image('a.jpg'));

    $set = tap(GlobalSet::make('footer')->title('Footer'))->save();
    Blueprint::make('footer')->setNamespace('globals')
        ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'text', 'field' => ['type' => 'text']],
        ]]]]]])->save();
    $set->inDefaultSite()->data(['text' => 'x'])->save();

    Role::make('curator')->title('Curator')->permissions([
        'access editor-api',
        'view tags terms', 'edit tags terms',
        'view uploads assets', 'rename uploads assets',
        'edit footer globals',
    ])->save();
    $curator = tap(User::make()->email('curator@example.com')->assignRole('curator'))->save();

    $term = $this->withToken(tokenFor2($curator))
        ->getJson('/api/editor/v1/taxonomies/tags/terms')->assertOk()->json('data.0');
    $asset = $this->withToken(tokenFor2($curator))
        ->getJson('/api/editor/v1/assets/uploads/a.jpg')->assertOk()->json('data');
    $global = $this->withToken(tokenFor2($curator))
        ->getJson('/api/editor/v1/globals/footer')->assertOk()->json('data');

    expect($term['can'])->toBe(['edit' => true, 'delete' => false])
        ->and($asset['can'])->toBe(['edit' => false, 'move' => false, 'rename' => true, 'delete' => false])
        ->and($global['can'])->toBe(['edit' => true]);
});
