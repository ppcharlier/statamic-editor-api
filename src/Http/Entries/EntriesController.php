<?php

namespace Ppcharlier\StatamicEditorApi\Http\Entries;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Statamic\Facades\Entry;

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
}
