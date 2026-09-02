<?php

namespace Ppcharlier\StatamicEditorApi\Http\Forms;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Ppcharlier\StatamicEditorApi\Support\SortParam;
use Statamic\Facades\Form;

final class FormsController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $forms = Form::all()
            ->filter(fn ($form) => ResourceConfig::enabled('forms', $form->handle()))
            ->filter(fn ($form) => Guard::allows($user, 'view', $form))
            ->map(fn ($form) => ['handle' => $form->handle(), 'title' => $form->title()])
            ->values()->all();

        return response()->json(['data' => $forms]);
    }

    public function submissions(Request $request, $form)
    {
        ResourceGate::form($form->handle());
        Guard::authorize($request->user(), 'view', $form);

        $params = $request->validate([
            'sort' => ['sometimes', 'string', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        // Submission ids ARE creation timestamps, so `id` is the chronological axis.
        [$column, $direction] = SortParam::resolve($params['sort'] ?? null, ['id'], '-id');

        $paginator = $form->querySubmissions()->orderBy($column, $direction)->paginate((int) ($params['per_page'] ?? 25));

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($s) => SubmissionResource::toArray($s))->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function destroySubmission(Request $request, $form, string $id)
    {
        ResourceGate::form($form->handle());

        $submission = $form->submission($id);

        if (! $submission) {
            throw new ApiException('not_found', 'Submission not found.', 404);
        }

        Guard::authorize($request->user(), 'delete', $submission);

        $submission->delete();

        return response()->noContent();
    }
}
