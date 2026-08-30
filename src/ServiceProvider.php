<?php

namespace Ppcharlier\StatamicEditorApi;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Ppcharlier\StatamicEditorApi\Http\Errors\ApiError;
use Statamic\Providers\AddonServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ServiceProvider extends AddonServiceProvider
{
    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/editor-api.php', 'statamic.editor-api');

        $this->app->singleton(
            \Ppcharlier\StatamicEditorApi\Auth\TokenRepository::class,
            \Ppcharlier\StatamicEditorApi\Auth\FileTokenRepository::class,
        );
    }

    public function bootAddon()
    {
        Route::aliasMiddleware('editor-api.auth', \Ppcharlier\StatamicEditorApi\Auth\AuthenticateEditorApi::class);

        $this->publishes([
            __DIR__.'/../config/editor-api.php' => config_path('statamic/editor-api.php'),
        ], 'editor-api-config');

        RateLimiter::for('editor-api-auth', function (Request $request) {
            return Limit::perMinute(config('statamic.editor-api.rate_limits.auth', 5))->by($request->ip());
        });

        RateLimiter::for('editor-api', function (Request $request) {
            return Limit::perMinute(config('statamic.editor-api.rate_limits.api', 120))
                ->by($request->bearerToken() ?? $request->ip());
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

            $handler->renderable(fn (AuthorizationException $e, Request $request) => $whenEditorApi($request,
                fn () => ApiError::response('forbidden', 'This action is not authorized.', 403)));

            $handler->renderable(fn (NotFoundHttpException $e, Request $request) => $whenEditorApi($request,
                fn () => ApiError::response('not_found', 'Not found.', 404)));

            $handler->renderable(fn (Throwable $e, Request $request) => $whenEditorApi($request,
                fn () => ApiError::response('server_error', 'An unexpected error occurred.', 500)));
        });

        Route::middleware(['api'])
            ->prefix(config('statamic.editor-api.route_prefix', 'api/editor').'/v1')
            ->name('editor-api.')
            ->group(__DIR__.'/../routes/api.php');
    }

    private function isEditorApiRequest(Request $request): bool
    {
        return $request->is(config('statamic.editor-api.route_prefix', 'api/editor').'/*');
    }
}
