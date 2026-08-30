<?php

namespace Ppcharlier\StatamicEditorApi\Http\Errors;

use Illuminate\Http\JsonResponse;

final class NotFoundController
{
    public function __invoke(): JsonResponse
    {
        return ApiError::response('not_found', 'Not found.', 404);
    }
}
