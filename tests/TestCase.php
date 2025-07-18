<?php

namespace HelgeSverre\Swarm\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test environment
        $_ENV['OPENAI_API_KEY'] = 'test-api-key';
    }
}
