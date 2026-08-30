<?php

namespace Ppcharlier\StatamicEditorApi;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Statamic\Providers\AddonServiceProvider;

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
        Route::aliasMiddleware('editor-api.errors', \Ppcharlier\StatamicEditorApi\Http\Errors\HandleApiErrors::class);
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

        Route::middleware(['api', 'editor-api.errors'])
            ->prefix(config('statamic.editor-api.route_prefix', 'api/editor').'/v1')
            ->name('editor-api.')
            ->group(__DIR__.'/../routes/api.php');
    }
}
