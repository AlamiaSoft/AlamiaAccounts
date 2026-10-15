<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DomainContext::reset();
    }
}
