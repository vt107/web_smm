<?php

namespace Tests;

use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Test dùng RefreshDatabase sẽ có sẵn cấp bậc + tiền tệ. */
    protected bool $seed = true;

    protected string $seeder = ReferenceDataSeeder::class;
}
