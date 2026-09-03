<?php

namespace Ppcharlier\StatamicEditorApi\Http\Templates;

use RecursiveCallbackFilterIterator;
use RecursiveIteratorIterator;
use Statamic\Filesystem\RecursiveDirectoryIterator;
use Statamic\Support\Str;

/**
 * Every view of the site as a template name — the option list of a `template` field.
 * Same rule as the CP's `CP\API\TemplatesController`: path relative to each view path,
 * everything before the first dot, dotfiles and node_modules skipped, sorted. Partials
 * and `errors/` are NOT filtered here: that is the field's own concern (`hide_partials`,
 * `folder`), applied client-side exactly like the CP's template fieldtype does.
 */
final class TemplatesController
{
    public function __invoke()
    {
        $templates = collect(config('view.paths'))->flatMap(function ($path) {
            if (! is_dir($path)) {
                return [];
            }

            return collect(new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
                    fn ($file) => ! str_starts_with($file->getFilename(), '.') && ! in_array($file->getBaseName(), ['node_modules'])
                )
            ))->map(fn ($file) => (string) Str::of($file->getPathname())
                ->after($path.DIRECTORY_SEPARATOR)
                ->before('.')
                ->replace('\\', '/'));
        })->unique()->sort()->values()->all();

        return response()->json(['data' => $templates]);
    }
}
