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
