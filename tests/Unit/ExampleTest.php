<?php

declare(strict_types=1);

namespace Tests\Unit;

use Toporia\Framework\Testing\TestCase;

/**
 * Example Unit Test
 *
 * Demonstrates unit testing capabilities.
 */
class ExampleTest extends TestCase
{
    /**
     * Test basic assertion.
     */
    public function test_basic_assertion(): void
    {
        $this->assertTrue(true);
        $this->assertEquals(1, 1);
    }

    /**
     * Test container interaction.
     */
    public function test_container_interaction(): void
    {
        $this->bind('test.service', fn() => new \stdClass());

        $service = $this->getFromContainer('test.service');
        $this->assertInstanceOf(\stdClass::class, $service);
    }

    /**
     * Test database interaction.
     */
    public function test_database_interaction(): void
    {
        $db = $this->getDb();
        if ($db === null) {
            $this->markTestSkipped('Database not available');
            return;
        }

        // Create table
        $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

        // Insert data
        $this->dbInsert('users', ['name' => 'John Doe']);

        // Assert data exists
        $this->assertDatabaseHas('users', ['name' => 'John Doe']);
        $this->assertDatabaseCount('users', 1);
    }

    /**
     * Test performance assertion.
     */
    public function test_performance_assertion(): void
    {
        $this->assertExecutionTimeLessThan(
            fn() => usleep(1000), // 1ms
            0.01 // 10ms max
        );
    }

    /**
     * Test time manipulation.
     */
    public function test_time_manipulation(): void
    {
        $this->setFakeTime(1609459200); // 2021-01-01

        $now = $this->now();
        $this->assertEquals(1609459200, $now);

        $this->travel(86400); // Travel 1 day forward
        $this->assertEquals(1609545600, $this->now());
    }
}
