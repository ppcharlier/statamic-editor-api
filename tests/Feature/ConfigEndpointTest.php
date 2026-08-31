<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Facades\User;

beforeEach(function () {
    config()->set('statamic.revisions.enabled', true);

    Collection::make('articles')->title('Articles')->dated(true)->revisionsEnabled(true)->save();
    Collection::make('pages')->title('Pages')->save();
    AssetContainer::make('uploads')->title('Uploads')->disk('local')->save();

    $user = tap(User::make()->email('pp@example.com')->makeSuper())->save();
    $this->plainToken = app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
});

it('describes collections with their capabilities', function () {
    $response = $this->withToken($this->plainToken)->getJson('/api/editor/v1/config')->assertOk();

    $articles = collect($response->json('data.collections'))->firstWhere('handle', 'articles');

    expect($articles)->not->toBeNull()
        ->and($articles['title'])->toBe('Articles')
        ->and($articles['revisions_enabled'])->toBeTrue()
        ->and($articles['dated'])->toBeTrue()
        ->and($articles['blueprints'])->toBeArray();

    expect(collect($response->json('data.asset_containers'))->firstWhere('handle', 'uploads'))->not->toBeNull();
});

it('reports a single default site and scopes every collection to it', function () {
    // Verrou mono-site : sans Pro / sans multisite, `sites` ne contient que le site par
    // défaut et chaque ressource s'y limite — le bloc additif de la v1.2 ne doit rien
    // changer au contrat par défaut.
    $config = $this->withToken($this->plainToken)->getJson('/api/editor/v1/config')->assertOk()->json('data');

    $default = Site::default()->handle();

    expect($config['sites'])->toHaveCount(1)
        ->and($config['sites'][0]['handle'])->toBe($default)
        ->and($config['sites'][0]['default'])->toBeTrue()
        ->and($config['collections'][0]['sites'])->toBe([$default]);
});

it('omits collections excluded by the resource whitelist', function () {
    config()->set('statamic.editor-api.resources.collections', ['articles']);

    $response = $this->withToken($this->plainToken)->getJson('/api/editor/v1/config')->assertOk();

    expect(collect($response->json('data.collections'))->pluck('handle'))
        ->toContain('articles')->not->toContain('pages');
});

it('requires authentication', function () {
    $this->getJson('/api/editor/v1/config')->assertStatus(401);
});

it('exposes per-resource capabilities', function () {
    \Statamic\Facades\Taxonomy::make('themes')->title('Thèmes')->save();
    tap(\Statamic\Facades\GlobalSet::make('footer')->title('Footer'))->save();
    tap(\Statamic\Facades\Nav::make('main')->title('Menu')->maxDepth(2))->save();
    tap(\Statamic\Facades\Form::make('contact')->title('Contact'))->save();

    $response = $this->withToken($this->plainToken)->getJson('/api/editor/v1/config')->assertOk();

    $taxonomy = collect($response->json('data.taxonomies'))->firstWhere('handle', 'themes');
    expect($taxonomy['blueprints'])->toBeArray()
        ->and($taxonomy['sites'])->toBe([Site::default()->handle()]);

    // Une nav n'est disponible que là où un arbre existe : celle-ci n'en a aucun.
    $nav = collect($response->json('data.navigations'))->firstWhere('handle', 'main');
    expect($nav['max_depth'])->toBe(2)->and($nav)->toHaveKey('expects_root')
        ->and($nav['sites'])->toBe([]);

    $global = collect($response->json('data.globals'))->firstWhere('handle', 'footer');
    expect($global)->toHaveKey('blueprint')
        ->and($global['sites'])->toBe([Site::default()->handle()]);

    $form = collect($response->json('data.forms'))->firstWhere('handle', 'contact');
    expect($form['title'])->toBe('Contact')->and($form)->toHaveKey('store');
});
