<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\MetaFields;
use Ppcharlier\StatamicEditorApi\Support\FieldShape;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SiteResolver;
use Ppcharlier\StatamicEditorApi\Support\SortParam;
use Ppcharlier\StatamicEditorApi\Support\UniqueUri;
use Ppcharlier\StatamicEditorApi\Support\UnknownFields;
use Statamic\Facades\Entry;
use Statamic\Rules\Slug;

final class EntriesController
{
    use ResolvesEntries;

    public function index(Request $request, $collection)
    {
        $site = SiteResolver::resolve($request, $collection->sites()->all());
        ResourceGate::collection($collection->handle());

        $params = $request->validate([
            'status' => ['sometimes', 'in:any,published,draft,scheduled,expired'],
            'search' => ['sometimes', 'string', 'max:200'],
            'sort' => ['sometimes', 'string', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Entry::query()->where('collection', $collection->handle())->where('site', $site);
        $query->whereStatus($params['status'] ?? 'any');

        if ($search = $params['search'] ?? null) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        $sortable = $collection->entryBlueprints()
            ->flatMap(fn ($blueprint) => $blueprint->fields()->all()->keys())
            ->merge(['slug', 'title'])
            ->when($collection->dated(), fn ($fields) => $fields->push('date'))
            ->unique()->values()->all();
        [$column, $direction] = SortParam::resolve(
            $params['sort'] ?? null, $sortable, $collection->dated() ? '-date' : 'title'
        );
        $query->orderBy($column, $direction);

        $paginator = $query->paginate((int) ($params['per_page'] ?? 25));

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($e) => EntryResource::summary($e))->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $entry = $this->findEntry($request, $id);
        Guard::check($request->user(), PermissionMap::entries('view', $entry->collectionHandle()));

        return response()->json(['data' => EntryResource::detail($entry)]);
    }

    public function store(Request $request, $collection)
    {
        $site = SiteResolver::resolve($request, $collection->sites()->all());
        ResourceGate::collection($handle = $collection->handle());

        $payload = $request->validate([
            'slug' => ['required', 'string', new Slug],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'published' => ['sometimes', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
            'data' => ['required', 'array'],
        ]);

        if (isset($payload['date']) && ! $collection->dated()) {
            throw new ApiException('validation_failed', 'The given data was invalid.', 422,
                ['date' => ['This collection is not dated.']]);
        }

        $blueprint = $collection->entryBlueprint();
        $this->rejectUnknownFields($payload['data'], $blueprint);

        // The blueprint always carries ensured 'slug' and, for dated collections,
        // 'date' fields (see Statamic\Entries\Collection::ensureEntryBlueprintFields()).
        // Both are exposed by this API as their own top-level params rather than inside
        // 'data' (see MetaFields), so they're excluded from the field set used to
        // validate and process 'data' — otherwise the Date fieldtype would double
        // (and incompatibly) validate the top-level 'Y-m-d' param, and 'slug' would be
        // processed with no value and land in entry data as a stray `slug: null`.
        $blueprintFields = $blueprint->fields()->except(MetaFields::HANDLES);

        $fields = $blueprintFields->addValues(FieldShape::normalize($payload['data'], $blueprint));
        $fields->validator()
            ->withRules(Entry::createRules($collection, $site))
            ->withReplacements(['collection' => $handle, 'site' => $site])
            ->validate();

        $entry = Entry::make()
            ->collection($collection)
            ->locale($site)
            ->slug($payload['slug'])
            ->data($fields->process()->values()->all());

        if ($collection->dated()) {
            $entry->date($payload['date'] ?? now()->format('Y-m-d'));
        }

        UniqueUri::guard($entry);

        $published = (bool) ($payload['published'] ?? false);

        if ($published) {
            Guard::check($request->user(), PermissionMap::entries('publish', $handle));
        }

        if ($entry->revisionsEnabled()) {
            $entry->store(['message' => $payload['message'] ?? null, 'user' => $request->user()]);

            if ($published) {
                $entry->publish(['message' => $payload['message'] ?? null, 'user' => $request->user()]);
            }
        } else {
            $entry->published($published);
            $entry->updateLastModified($request->user())->save();
        }

        return response()->json(['data' => EntryResource::detail(Entry::find($entry->id()))], 201);
    }

    public function update(Request $request, string $id)
    {
        $entry = $this->findEntry($request, $id);
        Guard::authorize($request->user(), 'update', $entry);
        $handle = $entry->collectionHandle();

        $this->guardAgainstConflict($request, $entry);

        if ($request->has('published')) {
            throw new ApiException(
                'unknown_field',
                'The published flag cannot be changed here — use the published endpoints.',
                422,
                ['published' => ['Use POST/DELETE /entries/{id}/published instead.']],
            );
        }

        $payload = $request->validate([
            'slug' => ['sometimes', 'string', new Slug],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'data' => ['required', 'array'],
        ]);

        if (isset($payload['date']) && ! $entry->collection()->dated()) {
            throw new ApiException('validation_failed', 'The given data was invalid.', 422,
                ['date' => ['This collection is not dated.']]);
        }

        $working = $entry->fromWorkingCopy();

        $blueprint = $working->blueprint();
        $this->rejectUnknownFields($payload['data'], $blueprint);

        // Same exclusion (and rationale) as store(): 'slug' and 'date' are top-level
        // params, not data fields (see MetaFields), and the blueprint's ensured 'date'
        // field would otherwise validate against a save format incompatible with our
        // top-level Y-m-d payload date validated above and applied via $working->date().
        $blueprintFields = $blueprint->fields()->except(MetaFields::HANDLES);

        $fields = $blueprintFields->addValues(FieldShape::normalize($payload['data'], $blueprint));
        $fields->validator()
            ->withRules(Entry::updateRules($working->collection(), $working))
            ->withReplacements(['id' => $working->id(), 'collection' => $handle, 'site' => $working->locale()])
            ->validate();

        $working->merge($fields->process()->values()->all());

        if (isset($payload['slug'])) {
            $working->slug($payload['slug']);
        }

        if (isset($payload['date'])) { // guarded above: only reaches here on a dated collection
            $working->date($payload['date']);
        }

        if (isset($payload['slug']) || isset($payload['date'])) {
            UniqueUri::guard($working);
        }

        if ($working->revisionsEnabled() && $working->published()) {
            $working->makeWorkingCopy()->user($request->user())->save();
        } else {
            $working->updateLastModified($request->user())->save();
        }

        return response()->json(['data' => EntryResource::detail($this->findEntry($request, $id))]);
    }

    public function destroy(Request $request, string $id)
    {
        $entry = $this->findEntry($request, $id);
        Guard::authorize($request->user(), 'delete', $entry);

        if ($entry->revisionsEnabled()) {
            $entry->deleteWorkingCopy();
        }

        $entry->delete();

        return response()->noContent();
    }

    private function rejectUnknownFields(array $data, $blueprint): void
    {
        $meta = array_values(array_intersect(array_keys($data), MetaFields::HANDLES));

        if ($meta !== []) {
            throw new ApiException(
                'unknown_field',
                'These are top-level parameters, not data fields: '.implode(', ', $meta).'.',
                422,
                collect($meta)->mapWithKeys(fn ($f) => [$f => ["Use the top-level `{$f}` parameter instead of data.{$f}."]])->all(),
            );
        }

        UnknownFields::reject($data, $blueprint->fields()->all()->keys()->all());
    }
}
