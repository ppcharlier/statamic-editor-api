<?php

namespace Ppcharlier\StatamicEditorApi\Http\Terms;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Support\FieldShape;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SiteResolver;
use Ppcharlier\StatamicEditorApi\Support\SortParam;
use Ppcharlier\StatamicEditorApi\Support\UnknownFields;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Rules\Slug;
use Statamic\Rules\UniqueTermValue;

final class TermsController
{
    use ResolvesTerms;

    public function index(Request $request, $taxonomy)
    {
        $site = SiteResolver::resolve($request, $taxonomy->sites()->all());
        ResourceGate::taxonomy($taxonomy->handle());
        Guard::authorize($request->user(), 'view', $taxonomy);

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
                ->map(fn ($t) => TermResource::toArray($t->in($site)))
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
        $site = SiteResolver::resolve($request, $taxonomy->sites()->all());
        ResourceGate::taxonomy($handle = $taxonomy->handle());
        Guard::authorize($request->user(), 'create', [TermContract::class, $taxonomy, Site::get($site)]);

        $payload = $request->validate([
            'slug' => ['required', 'string', new Slug, new UniqueTermValue(taxonomy: $handle, site: $site)],
            'blueprint' => ['sometimes', 'string', 'max:100'],
            'published' => ['sometimes', 'boolean'],
            'data' => ['required', 'array'],
        ]);

        $blueprint = $this->resolveBlueprint($taxonomy, $payload['blueprint'] ?? null);
        UnknownFields::reject($payload['data'], $blueprint->fields()->all()->keys()->all());

        // The blueprint always carries an ensured 'slug' field (see
        // Statamic\Taxonomies\Taxonomy::ensureTermBlueprintFields()), which this API
        // exposes as its own top-level 'slug' param rather than inside 'data'. It's
        // merged in here so the blueprint's own `required` rule for it is satisfied,
        // then excluded from the processed values so it isn't stored twice in term data.
        // The top-level 'slug' must win on collision (PHP's array + keeps the left
        // operand), since a client-supplied `data.slug` is otherwise indistinguishable
        // from a real blueprint field and would silently override the validated slug.
        $fields = $blueprint->fields()->addValues(
            ['slug' => $payload['slug']] + FieldShape::normalize($payload['data'], $blueprint));
        $fields->validator()->validate();

        $term = Term::make($payload['slug'])->taxonomy($taxonomy);

        if (isset($payload['blueprint'])) {
            $term->blueprint($payload['blueprint']);
        }

        $values = $fields->process()->values()->except('slug')->all();
        $published = $payload['published'] ?? $taxonomy->defaultPublishState();

        // A term's file always keeps the taxonomy's default-site data as its base
        // (Statamic\Taxonomies\Term::fileData() unconditionally pulls it out to build
        // the file), so a brand-new term with no data at all for the default site can't
        // be saved — the write blows up. Statamic's own CP TermsController::store()
        // works around this the same way when creating directly in a non-default site:
        // copy the same values into the default localization too before saving.
        $defaultSite = $taxonomy->sites()->first();

        if ($site !== $defaultSite) {
            $term->in($defaultSite)->merge($values)->published($published);
        }

        $localized = $term->in($site);
        $localized->merge($values);
        $localized->published($published);
        $localized->save();

        return response()->json(['data' => TermResource::toArray($term->in($site))], 201);
    }

    public function update(Request $request, $taxonomy, string $slug)
    {
        $site = SiteResolver::resolve($request, $taxonomy->sites()->all());
        ResourceGate::taxonomy($handle = $taxonomy->handle());

        $term = $this->findTerm($taxonomy, $slug, $site);
        Guard::authorize($request->user(), 'update', $term);

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
            'slug' => ['sometimes', 'string', new Slug, new UniqueTermValue(taxonomy: $handle, except: $term->id(), site: $site)],
            'blueprint' => ['sometimes', 'string', 'max:100'],
            'published' => ['sometimes', 'boolean'],
            'data' => ['required', 'array'],
        ]);

        // Validate against the TARGET blueprint object without installing it on the term
        // yet: LocalizedTerm::blueprint() writes through to the shared Stache Term (the
        // clone above is shallow — it keeps the same ->term reference), so setting it
        // before validation would leave the process-wide instance carrying a blueprint
        // that was never saved once the payload is rejected with a 422.
        $blueprint = isset($payload['blueprint'])
            ? $this->resolveBlueprint($taxonomy, $payload['blueprint']) // 422 hors set
            : $term->blueprint();

        UnknownFields::reject($payload['data'], array_diff($blueprint->fields()->all()->keys()->all(), ['slug']));

        $fields = $blueprint->fields()->except(['slug'])
            ->addValues(FieldShape::normalize($payload['data'], $blueprint));
        $fields->validator()->validate();

        // Payload is known good from here on — safe to mutate.
        if (isset($payload['blueprint'])) {
            $term->blueprint($payload['blueprint']);
        }

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
        return response()->json(['data' => TermResource::toArray($this->findTerm($taxonomy, $term->slug(), $site))]);
    }

    private function resolveBlueprint($taxonomy, ?string $handle)
    {
        if ($handle === null) {
            return $taxonomy->termBlueprints()->first();
        }

        $blueprint = $taxonomy->termBlueprints()->first(fn ($bp) => $bp->handle() === $handle);

        if (! $blueprint) {
            throw new \Ppcharlier\StatamicEditorApi\Http\Errors\ApiException(
                'validation_failed', 'The given data was invalid.', 422,
                ['blueprint' => ["Blueprint [{$handle}] is not in this taxonomy's set."]],
            );
        }

        return $blueprint;
    }

    public function destroy(Request $request, $taxonomy, string $slug)
    {
        // The delete itself is NOT localized — it removes the term and all of its
        // localizations at once (CP parity, documented in the multi-site spec §5). The
        // ?site= param is still validated rather than silently ignored, so a caller who
        // believes they are deleting one site's version gets a 422 on a bad handle instead
        // of a 204 that wiped every site.
        SiteResolver::resolve($request, $taxonomy->sites()->all());
        ResourceGate::taxonomy($taxonomy->handle());

        $term = $this->findTerm($taxonomy, $slug);
        Guard::authorize($request->user(), 'delete', $term);

        Term::delete($term->term());

        return response()->noContent();
    }
}
