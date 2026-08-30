<?php

use Ppcharlier\StatamicEditorApi\Support\ResourceConfig;

it('treats true as fully enabled', function () {
    config()->set('statamic.editor-api.resources.collections', true);

    expect(ResourceConfig::enabled('collections'))->toBeTrue()
        ->and(ResourceConfig::enabled('collections', 'articles'))->toBeTrue();
});

it('treats false as disabled', function () {
    config()->set('statamic.editor-api.resources.users', false);

    expect(ResourceConfig::enabled('users'))->toBeFalse()
        ->and(ResourceConfig::enabled('users', 'any'))->toBeFalse();
});

it('treats an array as a handle whitelist', function () {
    config()->set('statamic.editor-api.resources.collections', ['articles']);

    expect(ResourceConfig::enabled('collections'))->toBeTrue()
        ->and(ResourceConfig::enabled('collections', 'articles'))->toBeTrue()
        ->and(ResourceConfig::enabled('collections', 'pages'))->toBeFalse();
});

it('defaults to disabled for unknown resources', function () {
    expect(ResourceConfig::enabled('unknown-thing'))->toBeFalse();
});

it('treats an empty whitelist as disabled', function () {
    config()->set('statamic.editor-api.resources.collections', []);

    expect(ResourceConfig::enabled('collections'))->toBeFalse()
        ->and(ResourceConfig::enabled('collections', 'articles'))->toBeFalse();
});
