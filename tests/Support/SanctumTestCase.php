<?php

namespace Ppcharlier\StatamicEditorApi\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users in the database AND tokens in the database: the sanctum driver
 * targets sites that already run Sanctum (Eloquent users, shared DB).
 */
abstract class SanctumTestCase extends EloquentUsersTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.editor-api.auth.driver', 'sanctum');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Mirrors laravel/sanctum's create_personal_access_tokens_table migration.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
