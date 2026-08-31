<?php

namespace Ppcharlier\StatamicEditorApi\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class EloquentTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'super' => 'boolean',
        'preferences' => 'json',
    ];
}
