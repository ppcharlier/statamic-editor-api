<?php

namespace Ppcharlier\StatamicEditorApi\Tests\Support;

use Ppcharlier\StatamicEditorApi\Auth\TokenRepository;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Role;
use Statamic\Facades\User;

trait BuildsEntryFixtures
{
    protected function makeArticlesCollection(bool $withRevisions = false)
    {
        if ($withRevisions) {
            config()->set('statamic.revisions.enabled', true);
        }

        $collection = tap(
            Collection::make('articles')->title('Articles')->dated(true)->revisionsEnabled($withRevisions)
        )->save();

        Blueprint::make('article')
            ->setNamespace('collections.articles')
            ->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Titre', 'validate' => ['required']]],
                ['handle' => 'body', 'field' => ['type' => 'textarea', 'display' => 'Corps']],
            ]]]]]])
            ->save();

        return $collection;
    }

    protected function makeSuperToken(): string
    {
        $user = tap(User::make()->email(uniqid().'@example.com')->makeSuper())->save();

        return app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
    }

    protected function makeTokenWithPermissions(array $permissions): string
    {
        $handle = 'role_'.uniqid();
        Role::make($handle)->title($handle)->permissions($permissions)->save();
        $user = tap(User::make()->email(uniqid().'@example.com')->assignRole($handle))->save();

        return app(TokenRepository::class)->create($user->id(), 'iPhone')->plainText;
    }
}
