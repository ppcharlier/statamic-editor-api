<?php

namespace Ppcharlier\StatamicEditorApi\Http\Forms;

use Illuminate\Http\Request;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiException;
use Ppcharlier\StatamicEditorApi\Permissions\Guard;
use Ppcharlier\StatamicEditorApi\Permissions\PermissionMap;
use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;
use Ppcharlier\StatamicEditorApi\Support\ResourceGate;
use Statamic\Facades\Form;

final class FormsController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $forms = Form::all()
            ->filter(fn ($form) => ResourceConfig::enabled('forms', $form->handle()))
            ->filter(fn ($form) => Guard::allows($user, PermissionMap::formSubmissions('view', $form->handle())))
            ->map(fn ($form) => ['handle' => $form->handle(), 'title' => $form->title()])
            ->values()->all();

        return response()->json(['data' => $forms]);
    }

    public function submissions(Request $request, $form)
    {
        ResourceGate::form($handle = $form->handle());
        Guard::check($request->user(), PermissionMap::formSubmissions('view', $handle));

        $params = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        $paginator = $form->querySubmissions()->orderBy('id', 'desc')->paginate((int) ($params['per_page'] ?? 25));

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
        ResourceGate::form($handle = $form->handle());
        Guard::check($request->user(), PermissionMap::formSubmissions('delete', $handle));

        $submission = $form->submission($id);

        if (! $submission) {
            throw new ApiException('not_found', 'Submission not found.', 404);
        }

        $submission->delete();

        return response()->noContent();
    }
}
