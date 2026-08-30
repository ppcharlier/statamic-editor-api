<?php

namespace Ppcharlier\StatamicEditorApi\Http\Errors;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }

    public function toResponse(): JsonResponse
    {
        return ApiError::response($this->errorCode, $this->getMessage(), $this->status, $this->errors);
    }

    /**
     * Laravel's routing pipeline (Illuminate\Routing\Pipeline::handleException()) converts
     * exceptions thrown by route middleware into a response via the app's exception handler
     * *before* they can propagate as a real PHP exception to an outer middleware's try/catch —
     * so HandleApiErrors's own catch (ApiException $e) block never sees exceptions thrown by
     * a downstream middleware such as AuthenticateEditorApi. Defining render() here is Laravel's
     * documented hook for a custom exception to control its own HTTP response regardless of
     * which layer of the middleware stack threw it.
     */
    public function render(Request $request): JsonResponse
    {
        return $this->toResponse();
    }
}
