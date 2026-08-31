<?php

namespace Ppcharlier\StatamicEditorApi\Http\Navigations;

use Facades\Statamic\Structures\BranchIdGenerator;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Entry;

final class NavTreeSerializer
{
    /** Arbre vendor → arbre API (enrichit les nœuds entry avec entry_title). */
    public static function toApi(array $tree): array
    {
        return array_map(function (array $node) {
            $out = array_filter([
                'id' => $node['id'] ?? null,
                'entry' => $node['entry'] ?? null,
                'title' => $node['title'] ?? null,
                'url' => $node['url'] ?? null,
            ], fn ($v) => $v !== null);

            if (isset($node['entry'])) {
                $out['entry_title'] = Entry::find($node['entry'])?->value('title');
            }

            if (! empty($node['data'])) {
                $out['data'] = $node['data'];
            }

            if (! empty($node['children'])) {
                $out['children'] = self::toApi($node['children']);
            }

            return $out;
        }, $tree);
    }

    /** Payload API → arbre vendor (valide chaque nœud, génère les ids manquants). */
    public static function fromApi(array $tree, string $path = 'tree'): array
    {
        return collect($tree)->map(function ($node, $i) use ($path) {
            $at = "{$path}.{$i}";

            if (! is_array($node)) {
                throw ValidationException::withMessages([$at => ['Each node must be an object.']]);
            }

            $hasEntry = isset($node['entry']);
            $hasTitle = isset($node['title']);
            $hasUrl = isset($node['url']);

            // An entry node may carry a title and/or url override (the CP's own shape).
            // A non-entry node must still stand on its own via title and/or url.
            if (! $hasEntry && ! $hasTitle && ! $hasUrl) {
                throw ValidationException::withMessages([
                    $at => ['Each node needs either an entry reference or a title/url — not both, not neither.'],
                ]);
            }

            if ($hasEntry && ! Entry::find($node['entry'])) {
                throw ValidationException::withMessages([$at => ['Unknown entry.']]);
            }

            $out = ['id' => $node['id'] ?? BranchIdGenerator::generate()];

            foreach (['entry', 'title', 'url'] as $key) {
                if (isset($node[$key])) {
                    $out[$key] = $node[$key];
                }
            }

            if (isset($node['data'])) {
                $out['data'] = $node['data'];
            }

            if (! empty($node['children'])) {
                $out['children'] = self::fromApi($node['children'], $at.'.children');
            }

            return $out;
        })->values()->all();
    }
}
