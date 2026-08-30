<?php

namespace Ppcharlier\StatamicEditorApi\Http\Config;

use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Taxonomy;

final class ConfigController
{
    public function __invoke()
    {
        return response()->json(['data' => [
            'collections' => $this->collections(),
            'asset_containers' => $this->assetContainers(),
            'taxonomies' => $this->taxonomies(),
            'globals' => $this->globals(),
            'navigations' => $this->navigations(),
        ]]);
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
            ])->values()->all();
    }

    private function assetContainers(): array
    {
        if (! ResourceConfig::enabled('assets')) {
            return [];
        }

        return AssetContainer::all()
            ->filter(fn ($c) => ResourceConfig::enabled('assets', $c->handle()))
            ->map(fn ($c) => ['handle' => $c->handle(), 'title' => $c->title()])
            ->values()->all();
    }

    private function taxonomies(): array
    {
        if (! ResourceConfig::enabled('taxonomies')) {
            return [];
        }

        return Taxonomy::all()
            ->filter(fn ($t) => ResourceConfig::enabled('taxonomies', $t->handle()))
            ->map(fn ($t) => ['handle' => $t->handle(), 'title' => $t->title()])
            ->values()->all();
    }

    private function globals(): array
    {
        if (! ResourceConfig::enabled('globals')) {
            return [];
        }

        return GlobalSet::all()
            ->filter(fn ($g) => ResourceConfig::enabled('globals', $g->handle()))
            ->map(fn ($g) => ['handle' => $g->handle(), 'title' => $g->title()])
            ->values()->all();
    }

    private function navigations(): array
    {
        if (! ResourceConfig::enabled('navigations')) {
            return [];
        }

        return Nav::all()
            ->filter(fn ($n) => ResourceConfig::enabled('navigations', $n->handle()))
            ->map(fn ($n) => ['handle' => $n->handle(), 'title' => $n->title()])
            ->values()->all();
    }
}
