<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Rules\Slug;

final class EntriesController
{
    use ResolvesEntries;

    public function index(Request $request, $collection)
    {
        ResourceGate::collection($collection->handle());

        $params = $request->validate([
            'status' => ['sometimes', 'in:any,published,draft,scheduled,expired'],
            'search' => ['sometimes', 'string', 'max:200'],
            'sort' => ['sometimes', 'string', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Entry::query()->where('collection', $collection->handle());
        $query->whereStatus($params['status'] ?? 'any');

        if ($search = $params['search'] ?? null) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        $sort = $params['sort'] ?? ($collection->dated() ? '-date' : 'title');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $query->orderBy(ltrim($sort, '-'), $direction);

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
        $entry = $this->findEntry($id);
        Guard::check($request->user(), PermissionMap::entries('view', $entry->collectionHandle()));

        return response()->json(['data' => EntryResource::detail($entry)]);
    }

    public function store(Request $request, $collection)
    {
        ResourceGate::collection($handle = $collection->handle());

        $payload = $request->validate([
            'slug' => ['required', 'string', new Slug],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'published' => ['sometimes', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
            'data' => ['required', 'array'],
        ]);

        $blueprint = $collection->entryBlueprint();
        $this->rejectUnknownFields($payload['data'], $blueprint);

        $site = Site::default()->handle();

        // The blueprint of a dated collection always carries an ensured 'date' field
        // (see Statamic\Entries\Collection::ensureEntryBlueprintFields()), whose Date
        // fieldtype validates against its own save format (an ISO datetime by default).
        // Our payload carries the date as its own top-level 'Y-m-d' param rather than
        // inside 'data', validated above, and applied via $entry->date() below — so
        // that ensured field is excluded here to avoid double (and incompatible)
        // validation of the same value.
        $blueprintFields = $collection->dated() ? $blueprint->fields()->except('date') : $blueprint->fields();

        $fields = $blueprintFields->addValues($payload['data']);
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
        $entry = $this->findEntry($id);
        Guard::check($request->user(), PermissionMap::entries('edit', $handle = $entry->collectionHandle()));

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

        $working = $entry->fromWorkingCopy();

        $blueprint = $working->blueprint();
        $this->rejectUnknownFields($payload['data'], $blueprint);

        // Same exclusion (and rationale) as store(): the blueprint's ensured 'date'
        // field validates against its own save format, incompatible with our top-level
        // Y-m-d payload date validated above and applied via $working->date().
        $blueprintFields = $working->collection()->dated() ? $blueprint->fields()->except('date') : $blueprint->fields();

        $fields = $blueprintFields->addValues($payload['data']);
        $fields->validator()
            ->withRules(Entry::updateRules($working->collection(), $working))
            ->withReplacements(['id' => $working->id(), 'collection' => $handle, 'site' => $working->locale()])
            ->validate();

        $working->merge($fields->process()->values()->all());

        if (isset($payload['slug'])) {
            $working->slug($payload['slug']);
        }

        if (isset($payload['date']) && $working->collection()->dated()) {
            $working->date($payload['date']);
        }

        if ($working->revisionsEnabled() && $working->published()) {
            $working->makeWorkingCopy()->user($request->user())->save();
        } else {
            $working->updateLastModified($request->user())->save();
        }

        return response()->json(['data' => EntryResource::detail($this->findEntry($id))]);
    }

    public function destroy(Request $request, string $id)
    {
        $entry = $this->findEntry($id);
        Guard::check($request->user(), PermissionMap::entries('delete', $entry->collectionHandle()));

        if ($entry->revisionsEnabled()) {
            $entry->deleteWorkingCopy();
        }

        $entry->delete();

        return response()->noContent();
    }

    private function guardAgainstConflict(Request $request, $entry): void
    {
        if (! $header = $request->header('X-Base-Modified')) {
            return;
        }

        try {
            $base = \Carbon\CarbonImmutable::parse($header);
        } catch (\Throwable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'X-Base-Modified' => ['Must be a valid ISO-8601 datetime.'],
            ]);
        }

        $current = EntryResource::effectiveLastModified($entry);

        if ($current && $current->startOfSecond()->gt($base->startOfSecond())) {
            throw new \Ppcharlier\StatamicEditorApi\Http\Errors\ApiException(
                'conflict',
                'The entry was modified since your last read. Reload it or overwrite by resending without the header.',
                409,
            );
        }
    }

    private function rejectUnknownFields(array $data, $blueprint): void
    {
        $known = $blueprint->fields()->all()->keys()->all();
        $unknown = array_values(array_diff(array_keys($data), $known));

        if ($unknown !== []) {
            throw new ApiException(
                'unknown_field',
                'Unknown fields: '.implode(', ', $unknown).'.',
                422,
                collect($unknown)->mapWithKeys(fn ($f) => [$f => ['This field is not in the blueprint.']])->all(),
            );
        }
    }
}
