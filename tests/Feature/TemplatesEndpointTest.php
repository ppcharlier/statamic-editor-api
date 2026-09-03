<?php

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\User;

beforeEach(function () {
    $this->viewPath = sys_get_temp_dir().'/editor-api-tests-views/'.uniqid();
    foreach (['layout', 'default', 'articles/index', 'articles/_card', 'partials/_nav', '.hidden'] as $name) {
        $file = $this->viewPath.'/'.$name.'.antlers.html';
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, '');
    }
    file_put_contents($this->viewPath.'/README.md', '');
    config()->set('view.paths', [$this->viewPath]);

    $user = tap(User::make()->email('pp@example.com')->makeSuper())->save();
    $this->plainToken = app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
});

it('lists every view as a template name, sorted, without dotfiles', function () {
    // Same rule as the CP's TemplatesController: path relative to the view path,
    // everything before the first dot, dotfiles skipped, sorted. Partials (leading
    // underscore) ARE listed — hiding them is the field's own `hide_partials` option.
    $response = $this->withToken($this->plainToken)->getJson('/api/editor/v1/templates')->assertOk();

    expect($response->json('data'))->toBe([
        'README', 'articles/_card', 'articles/index', 'default', 'layout', 'partials/_nav',
    ]);
});

it('requires authentication', function () {
    $this->getJson('/api/editor/v1/templates')->assertStatus(401);
});
