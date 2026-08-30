<?php

namespace Ppcharlier\StatamicEditorApi\Http\Errors;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    public static function response(string $code, string $message, int $status, ?array $errors = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($errors !== null) {
            $error['errors'] = $errors;
        }

        return response()->json(['error' => $error], $status);
    }
}
