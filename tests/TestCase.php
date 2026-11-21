<?php

namespace Tests;

use App\Services\WebPageReader;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(WebPageReader::class, new WebPageReader(fn (string $host) => match ($host) {
            'intern.example' => ['10.0.0.5'],
            default => ['93.184.215.14'],
        }));
    }
}
