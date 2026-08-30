<?php

namespace Ppcharlier\StatamicEditorApi\Http\Errors;

use Exception;
use Illuminate\Http\JsonResponse;

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
}
