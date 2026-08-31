<?php

namespace Ppcharlier\StatamicEditorApi\Tests\Support;

use Ppcharlier\StatamicEditorApi\Tests\TestCase;
use Statamic\Facades\Site;

/**
 * Boots the app with two sites (en default, fr) and the multisite flag on.
 * Multi-site is a Statamic Pro feature; pro is already enabled by TestCase.
 */
abstract class MultiSiteTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.system.multisite', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_US'],
            'fr' => ['name' => 'Français', 'url' => '/fr/', 'locale' => 'fr_FR'],
        ]);
    }
}
