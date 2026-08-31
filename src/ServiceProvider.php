<?php

namespace Ppcharlier\StatamicEditorApi;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiError;
use Ppcharlier\StatamicEditorApi\Http\Errors\NotFoundController;
use Statamic\Providers\AddonServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ServiceProvider extends AddonServiceProvider
{
    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/editor-api.php', 'statamic.editor-api');

        $this->app->singleton(\Ppcharlier\StatamicEditorApi\Auth\TokenRepository::class, function ($app) {
            $driver = config('statamic.editor-api.auth.driver', 'file');

            if ($driver === 'sanctum') {
                if (! class_exists(\Laravel\Sanctum\PersonalAccessToken::class)) {
                    throw new \RuntimeException(
                        "editor-api: auth.driver 'sanctum' requires laravel/sanctum (composer require laravel/sanctum)."
                    );
                }

                return $app->make(\Ppcharlier\StatamicEditorApi\Auth\SanctumTokenRepository::class);
            }

            if ($driver !== 'file') {
                throw new \RuntimeException("editor-api: unknown auth.driver '{$driver}' — expected 'file' or 'sanctum'.");
            }

            return $app->make(\Ppcharlier\StatamicEditorApi\Auth\FileTokenRepository::class);
        });
    }

    public function bootAddon()
    {
        Route::aliasMiddleware('editor-api.auth', \Ppcharlier\StatamicEditorApi\Auth\AuthenticateEditorApi::class);
        Route::aliasMiddleware('editor-api.can', \Ppcharlier\StatamicEditorApi\Permissions\EnsurePermission::class);

        // The editor API sends structured (nested) JSON, unlike the CP's web forms which
        // serialize rich-text fields (e.g. bard) as a single JSON string — a shape the
        // global TrimStrings / ConvertEmptyStringsToNull middleware never reaches into.
        // Because we send real nested JSON, those middleware walk every leaf and mangle
        // payloads before they ever reach the blueprint: interior/edge whitespace (incl.
        // NBSP) trimmed on every text node, and "" silently rewritten to null. Editors
        // need byte-for-byte round-trips, so both are skipped for editor API requests.
        $whenEditorApiRequest = fn (Request $request) => $this->isEditorApiRequest($request);
        TrimStrings::skipWhen($whenEditorApiRequest);
        ConvertEmptyStringsToNull::skipWhen($whenEditorApiRequest);

        $this->publishes([
            __DIR__.'/../config/editor-api.php' => config_path('statamic/editor-api.php'),
        ], 'editor-api-config');

        RateLimiter::for('editor-api-auth', function (Request $request) {
            return Limit::perMinute(config('statamic.editor-api.rate_limits.auth', 5))->by($request->ip());
        });

        RateLimiter::for('editor-api', function (Request $request) {
            $bearer = $request->bearerToken();

            return [
                // Per-token limit — the bearer is hashed so the raw secret never
                // becomes a cache key.
                Limit::perMinute(config('statamic.editor-api.rate_limits.api', 120))
                    ->by($bearer ? 'token:'.hash('sha256', $bearer) : 'ip:'.$request->ip()),
                // Per-IP ceiling — without it, rotating garbage bearers would each
                // get a fresh per-token bucket.
                Limit::perMinute(config('statamic.editor-api.rate_limits.api_per_ip', 480))
                    ->by('ip-cap:'.$request->ip()),
            ];
        });

        $this->callAfterResolving(ExceptionHandler::class, function ($handler) {
            if (! method_exists($handler, 'renderable')) {
                return;
            }

            $whenEditorApi = fn (Request $request, callable $respond) => $this->isEditorApiRequest($request) ? $respond() : null;

            $handler->renderable(fn (ValidationException $e, Request $request) => $whenEditorApi($request,
                fn () => ApiError::response('validation_failed', $e->getMessage(), 422, $e->errors())));

            $handler->renderable(fn (ThrottleRequestsException $e, Request $request) => $whenEditorApi($request,
                fn () => ApiError::response('rate_limited', 'Too many requests.', 429)));

            $handler->renderable(fn (AuthenticationException $e, Request $request) => $whenEditorApi($request,
                fn () => ApiError::response('unauthenticated', 'Unauthenticated.', 401)));

            $handler->renderable(fn (NotFoundHttpException $e, Request $request) => $whenEditorApi($request,
                fn () => ApiError::response('not_found', 'Not found.', 404)));

            $handler->renderable(fn (HttpExceptionInterface $e, Request $request) => $whenEditorApi($request,
                fn () => $this->respondToHttpException($e)));

            $handler->renderable(fn (Throwable $e, Request $request) => $whenEditorApi($request,
                fn () => ApiError::response('server_error', 'An unexpected error occurred.', 500)));
        });

        Route::middleware(['api'])
            ->prefix($this->routePrefix().'/v1')
            ->name('editor-api.')
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Maps any HttpExceptionInterface (e.g. AccessDeniedHttpException, which is what
     * AuthorizationException is converted into by Handler::prepareException() before
     * renderables run) to the standard error envelope, by status code.
     */
    private function respondToHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        [$code, $message] = match ($status) {
            401 => ['unauthenticated', 'Unauthenticated.'],
            403 => ['forbidden', 'This action is not authorized.'],
            404 => ['not_found', 'Not found.'],
            429 => ['rate_limited', 'Too many requests.'],
            default => $status >= 500
                ? ['server_error', 'An unexpected error occurred.']
                : ['http_error', 'An error occurred.'],
        };

        return ApiError::response($code, $message, $status);
    }

    private function isEditorApiRequest(Request $request): bool
    {
        return $request->is($this->routePrefix().'/*');
    }

    private function routePrefix(): string
    {
        return config('statamic.editor-api.route_prefix', 'api/editor');
    }
}
