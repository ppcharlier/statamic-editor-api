<?php

namespace Ppcharlier\StatamicEditorApi\Tests\Support;

use Illuminate\Support\Facades\Storage;
use Statamic\Facades\AssetContainer;

trait BuildsAssetFixtures
{
    protected function makeUploadsContainer()
    {
        config()->set('filesystems.disks.uploads_disk', [
            'driver' => 'local',
            'root' => storage_path('app/uploads-test'),
            'url' => '/uploads-test',
        ]);

        Storage::fake('uploads_disk', config('filesystems.disks.uploads_disk'));

        return tap(AssetContainer::make('uploads')->title('Uploads')->disk('uploads_disk'))->save();
    }
}
