<?php

use Ppcharlier\StatamicEditorApi\Http\Errors\ApiError;

it('builds the standard error envelope', function () {
    $response = ApiError::response('forbidden', 'Nope.', 403);

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true))->toBe(['error' => ['code' => 'forbidden', 'message' => 'Nope.']]);
});

it('includes field errors when provided', function () {
    $response = ApiError::response('validation_failed', 'Invalid.', 422, ['title' => ['Required.']]);

    expect($response->getData(true)['error']['errors'])->toBe(['title' => ['Required.']]);
});
