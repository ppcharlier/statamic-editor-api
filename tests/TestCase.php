<?php

namespace Ppcharlier\StatamicEditorApi\Tests;

use Ppcharlier\StatamicEditorApi\ServiceProvider;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.editor-api.storage_path', sys_get_temp_dir().'/editor-api-tests/'.uniqid());
    }
}
