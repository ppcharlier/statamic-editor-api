<?php

namespace Ppcharlier\StatamicEditorApi\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ppcharlier\StatamicEditorApi\Tests\TestCase;

/**
 * Boots the app with users stored in the database (Statamic's `eloquent`
 * user repository — the default of a fresh Statamic 6 install), unlike
 * AddonTestCase which forces the `file` repository.
 */
abstract class EloquentUsersTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Must come after AddonTestCase::getEnvironmentSetUp, which forces 'file'.
        $app['config']->set('statamic.users.repository', 'eloquent');
        $app['config']->set('auth.providers.users.model', EloquentTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthTables();
    }

    // Mirrors vendor/statamic/cms .../stubs/auth/statamic_auth_tables.php.stub
    private function createAuthTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->boolean('super')->default(false);
            $table->string('avatar')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamp('last_login')->nullable();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_id');
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('group_id');
        });
    }
}
