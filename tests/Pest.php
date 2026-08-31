<?php

use Ppcharlier\StatamicEditorApi\Tests\Support\EloquentUsersTestCase;
use Ppcharlier\StatamicEditorApi\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(EloquentUsersTestCase::class)->in('EloquentUsers');
uses(\Ppcharlier\StatamicEditorApi\Tests\Support\SanctumTestCase::class)->in('Sanctum');
