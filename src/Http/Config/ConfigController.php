<?php

namespace Ppcharlier\StatamicEditorApi\Http\Config;

use Ppcharlier\StatamicEditorApi\Permissions\Capabilities;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Form;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;

final class ConfigController
{
    public function __invoke()
    {
        return response()->json(['data' => [
            'sites' => $this->sites(),
            'collections' => $this->collections(),
            'asset_containers' => $this->assetContainers(),
            'taxonomies' => $this->taxonomies(),
            'globals' => $this->globals(),
            'navigations' => $this->navigations(),
            'forms' => $this->forms(),
        ]]);
    }

    private function sites(): array
    {
        $default = Site::default()->handle();

        return Site::all()->map(fn ($site) => [
            'handle' => $site->handle(),
            'name' => $site->name(),
            'url' => $site->url(),
            'locale' => $site->locale(),
            'default' => $site->handle() === $default,
        ])->values()->all();
    }

    private function collections(): array
    {
        if (! ResourceConfig::enabled('collections')) {
            return [];
        }

        return Collection::all()
            ->filter(fn ($c) => ResourceConfig::enabled('collections', $c->handle()))
            ->map(fn ($c) => [
                'handle' => $c->handle(),
                'title' => $c->title(),
                'revisions_enabled' => (bool) $c->revisionsEnabled(),
                'dated' => (bool) $c->dated(),
                'structured' => $c->hasStructure(),
                'blueprints' => $c->entryBlueprints()->map->handle()->values()->all(),
                'sites' => $c->sites()->all(),
                'can' => Capabilities::of([EntryContract::class, $c], ['create' => 'create', 'publish' => 'publish']),
            ])->values()->all();
    }

    private function assetContainers(): array
    {
        if (! ResourceConfig::enabled('assets')) {
            return [];
        }

        return AssetContainer::all()
            ->filter(fn ($c) => ResourceConfig::enabled('assets', $c->handle()))
            ->map(fn ($c) => [
                'handle' => $c->handle(),
                'title' => $c->title(),
                'can' => Capabilities::of([AssetContract::class, $c], ['upload' => 'store']),
            ])
            ->values()->all();
    }

    private function taxonomies(): array
    {
        if (! ResourceConfig::enabled('taxonomies')) {
            return [];
        }

        return Taxonomy::all()
            ->filter(fn ($t) => ResourceConfig::enabled('taxonomies', $t->handle()))
            ->map(fn ($t) => [
                'handle' => $t->handle(),
                'title' => $t->title(),
                'blueprints' => $t->termBlueprints()->map->handle()->values()->all(),
                'sites' => $t->sites()->values()->all(),
                'can' => Capabilities::of([TermContract::class, $t], ['create' => 'create']),
            ])
            ->values()->all();
    }

    private function globals(): array
    {
        if (! ResourceConfig::enabled('globals')) {
            return [];
        }

        return GlobalSet::all()
            ->filter(fn ($g) => ResourceConfig::enabled('globals', $g->handle()))
            ->map(fn ($g) => [
                'handle' => $g->handle(),
                'title' => $g->title(),
                'blueprint' => $g->blueprint()?->handle(),
                'sites' => $g->sites()->values()->all(),
            ])
            ->values()->all();
    }

    private function navigations(): array
    {
        if (! ResourceConfig::enabled('navigations')) {
            return [];
        }

        return Nav::all()
            ->filter(fn ($n) => ResourceConfig::enabled('navigations', $n->handle()))
            ->map(fn ($n) => [
                'handle' => $n->handle(),
                'title' => $n->title(),
                'max_depth' => $n->maxDepth(),
                'expects_root' => (bool) $n->expectsRoot(),
                // Nav::sites() = les clés de trees(), donc les sites où un arbre existe
                // réellement — vide tant qu'aucun arbre n'a été enregistré.
                'sites' => $n->sites()->values()->all(),
            ])
            ->values()->all();
    }

    private function forms(): array
    {
        if (! ResourceConfig::enabled('forms')) {
            return [];
        }

        return Form::all()
            ->filter(fn ($f) => ResourceConfig::enabled('forms', $f->handle()))
            ->map(fn ($f) => [
                'handle' => $f->handle(),
                'title' => $f->title(),
                'store' => (bool) $f->store(),
            ])
            ->values()->all();
    }
}
