<?php

namespace Ppcharlier\StatamicEditorApi\Http\Terms;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SortParam;
use Ppcharlier\StatamicEditorApi\Support\UnknownFields;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Rules\Slug;
use Statamic\Rules\UniqueTermValue;

final class TermsController
{
    use ResolvesTerms;

    public function index(Request $request, $taxonomy)
    {
        ResourceGate::taxonomy($handle = $taxonomy->handle());
        Guard::check($request->user(), PermissionMap::terms('view', $handle));

        $params = $request->validate([
            'search' => ['sometimes', 'string', 'max:200'],
            'sort' => ['sometimes', 'string', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $sortable = $taxonomy->termBlueprints()
            ->flatMap(fn ($blueprint) => $blueprint->fields()->all()->keys())
            ->merge(['slug', 'title'])
            ->unique()->values()->all();
        [$column, $direction] = SortParam::resolve($params['sort'] ?? null, $sortable, 'slug');

        $query = $taxonomy->queryTerms()->orderBy($column, $direction);

        if ($search = $params['search'] ?? null) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        $paginator = $query->paginate((int) ($params['per_page'] ?? 25));

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn ($t) => TermResource::toArray($t->in(Site::default()->handle())))
                ->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, $taxonomy)
    {
        ResourceGate::taxonomy($handle = $taxonomy->handle());
        Guard::check($request->user(), PermissionMap::terms('create', $handle));

        $payload = $request->validate([
            'slug' => ['required', 'string', new Slug, new UniqueTermValue(taxonomy: $handle, site: Site::default()->handle())],
            'published' => ['sometimes', 'boolean'],
            'data' => ['required', 'array'],
        ]);

        $blueprint = $taxonomy->termBlueprints()->first();
        UnknownFields::reject($payload['data'], $blueprint->fields()->all()->keys()->all());

        // The blueprint always carries an ensured 'slug' field (see
        // Statamic\Taxonomies\Taxonomy::ensureTermBlueprintFields()), which this API
        // exposes as its own top-level 'slug' param rather than inside 'data'. It's
        // merged in here so the blueprint's own `required` rule for it is satisfied,
        // then excluded from the processed values so it isn't stored twice in term data.
        // The top-level 'slug' must win on collision (PHP's array + keeps the left
        // operand), since a client-supplied `data.slug` is otherwise indistinguishable
        // from a real blueprint field and would silently override the validated slug.
        $fields = $blueprint->fields()->addValues(['slug' => $payload['slug']] + $payload['data']);
        $fields->validator()->validate();

        $term = Term::make($payload['slug'])->taxonomy($taxonomy);
        $localized = $term->in($site = Site::default()->handle());
        $localized->merge($fields->process()->values()->except('slug')->all());
        $localized->published($payload['published'] ?? $taxonomy->defaultPublishState());
        $localized->save();

        return response()->json(['data' => TermResource::toArray($term->in($site))], 201);
    }

    public function update(Request $request, $taxonomy, string $slug)
    {
        ResourceGate::taxonomy($handle = $taxonomy->handle());
        Guard::check($request->user(), PermissionMap::terms('edit', $handle));

        $term = $this->findTerm($taxonomy, $slug);

        // Stache returns the SAME cached PHP object on every lookup within this process.
        // Cloning detaches us from that shared reference so our mutations below (notably
        // the slug rename) don't corrupt what Stache still has cached for the old slug —
        // otherwise, when a slug rename triggers TaxonomyTermsStore::save()'s internal
        // `Term::find($oldSlugKey)` (to locate and delete the old record), that lookup
        // would cache-hit our already-mutated instance instead of the untouched original,
        // and the old slug's file/index entry would be left behind as an orphan.
        $term = clone $term;

        // Snapshots the pre-mutation state (crucially the current slug) so that, if the
        // slug is renamed below, TaxonomyTermsStore::save() can detect the change via
        // $term->getOriginal('slug') and clean up the term stored under the old slug
        // (verified against vendor: Statamic's own CP TermsController::update() does
        // the same right after resolving the term, before any mutation).
        $term->term()->syncOriginal();

        $payload = $request->validate([
            'slug' => ['sometimes', 'string', new Slug, new UniqueTermValue(taxonomy: $handle, except: $term->id(), site: Site::default()->handle())],
            'published' => ['sometimes', 'boolean'],
            'data' => ['required', 'array'],
        ]);

        $blueprint = $term->blueprint();
        UnknownFields::reject($payload['data'], array_diff($blueprint->fields()->all()->keys()->all(), ['slug']));

        $fields = $blueprint->fields()->except(['slug'])->addValues($payload['data']);
        $fields->validator()->validate();

        $term->merge($fields->process()->values()->all());

        if (array_key_exists('published', $payload)) {
            $term->published($payload['published']);
        }

        if (array_key_exists('slug', $payload)) {
            $term->slug($payload['slug']);
        }

        $term->save();

        // Re-resolve using $term->slug() (the actual, Str::slug()-normalized value the
        // save just wrote) rather than the raw client-supplied $payload['slug']: the
        // Slug validation rule permits characters (e.g. uppercase, underscores) that
        // Term::slug()'s setter normalizes away, so a literal client string can miss
        // the record it just renamed and produce a false 404 on a successful write.
        return response()->json(['data' => TermResource::toArray($this->findTerm($taxonomy, $term->slug()))]);
    }

    public function destroy(Request $request, $taxonomy, string $slug)
    {
        ResourceGate::taxonomy($handle = $taxonomy->handle());
        Guard::check($request->user(), PermissionMap::terms('delete', $handle));

        $term = $this->findTerm($taxonomy, $slug);

        Term::delete($term->term());

        return response()->noContent();
    }
}
