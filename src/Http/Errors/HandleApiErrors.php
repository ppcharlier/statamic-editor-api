<?php

namespace Ppcharlier\StatamicEditorApi\Http\Errors;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class HandleApiErrors
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (ApiException $e) {
            return $e->toResponse();
        } catch (ValidationException $e) {
            return ApiError::response('validation_failed', $e->getMessage(), 422, $e->errors());
        } catch (AuthenticationException $e) {
            return ApiError::response('unauthenticated', 'Unauthenticated.', 401);
        } catch (AuthorizationException $e) {
            return ApiError::response('forbidden', 'This action is not authorized.', 403);
        } catch (NotFoundHttpException $e) {
            return ApiError::response('not_found', 'Not found.', 404);
        } catch (ThrottleRequestsException $e) {
            return ApiError::response('rate_limited', 'Too many requests.', 429);
        } catch (Throwable $e) {
            report($e);

            return ApiError::response('server_error', 'An unexpected error occurred.', 500);
        }
    }
}
