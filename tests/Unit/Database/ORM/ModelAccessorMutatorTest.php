<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\ORM\Model;

/**
 * Test Model Accessors and Mutators
 *
 * Tests accessor/mutator functionality from HasAccessorsAndMutators trait:
 * - get{Attribute}Attribute() accessor methods
 * - set{Attribute}Attribute() mutator methods
 * - Method caching for performance
 * - snake_case to StudlyCase conversion
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class ModelAccessorMutatorTest extends TestCase
{
    /**
     * Test accessor is called when getting attribute
     */
    public function test_accessor_is_called(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function getFullNameAttribute(): string
            {
                return $this->getRawAttribute('first_name', '') . ' ' . $this->getRawAttribute('last_name', '');
            }
        };

        $user->setAttribute('first_name', 'John');
        $user->setAttribute('last_name', 'Doe');

        $this->assertEquals('John Doe', $user->getAttribute('full_name'));
    }

    /**
     * Test mutator is called when setting attribute
     */
    public function test_mutator_is_called(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function setPasswordAttribute(string $value): void
            {
                $this->setRawAttribute('password', strtoupper($value)); // Simple transformation for testing
            }
        };

        $user->setAttribute('password', 'secret123');

        // Verify mutator transformed the value by reading it back
        $this->assertEquals('SECRET123', $user->getAttribute('password'));
    }

    /**
     * Test accessor with snake_case to StudlyCase conversion
     */
    public function test_accessor_snake_case_conversion(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function getFirstNameAttribute(): string
            {
                return strtoupper($this->getRawAttribute('first_name', ''));
            }
        };

        $user->setAttribute('first_name', 'jane');

        $this->assertEquals('JANE', $user->getAttribute('first_name'));
    }

    /**
     * Test mutator with snake_case to StudlyCase conversion
     */
    public function test_mutator_snake_case_conversion(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function setEmailAddressAttribute(string $value): void
            {
                $this->setRawAttribute('email_address', strtolower($value));
            }
        };

        $user->setAttribute('email_address', 'USER@EXAMPLE.COM');

        // Verify mutator transformed the value
        $this->assertEquals('user@example.com', $user->getAttribute('email_address'));
    }

    /**
     * Test attribute without accessor returns raw value
     */
    public function test_attribute_without_accessor_returns_raw(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $user->setAttribute('name', 'John');

        $this->assertEquals('John', $user->getAttribute('name'));
    }

    /**
     * Test attribute without mutator sets raw value
     */
    public function test_attribute_without_mutator_sets_raw(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $user->setAttribute('name', 'John');

        // Verify value was set correctly
        $this->assertEquals('John', $user->getAttribute('name'));
    }

    /**
     * Test studlyCase conversion
     */
    public function test_studly_case_conversion(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            // Expose studlyCase for testing
            public function testStudlyCase(string $value): string
            {
                return $this->studlyCase($value);
            }
        };

        $this->assertEquals('FirstName', $user->testStudlyCase('first_name'));
        $this->assertEquals('EmailAddress', $user->testStudlyCase('email_address'));
        $this->assertEquals('FullName', $user->testStudlyCase('full_name'));
    }

    /**
     * Test snakeCase conversion
     */
    public function test_snake_case_conversion(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            // Expose snakeCase for testing
            public function testSnakeCase(string $value): string
            {
                return $this->snakeCase($value);
            }
        };

        $this->assertEquals('first_name', $user->testSnakeCase('FirstName'));
        $this->assertEquals('email_address', $user->testSnakeCase('EmailAddress'));
        $this->assertEquals('full_name', $user->testSnakeCase('FullName'));
    }

    /**
     * Test hasGetAccessor detects accessor methods
     */
    public function test_has_get_accessor(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function getFullNameAttribute(): string
            {
                return 'Test';
            }

            // Expose hasGetAccessor for testing
            public function testHasGetAccessor(string $key): bool
            {
                return $this->hasGetAccessor($key);
            }
        };

        $this->assertTrue($user->testHasGetAccessor('full_name'));
        $this->assertFalse($user->testHasGetAccessor('email'));
    }

    /**
     * Test hasSetMutator detects mutator methods
     */
    public function test_has_set_mutator(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function setPasswordAttribute(string $value): void
            {
                $this->attributes['password'] = $value;
            }

            // Expose hasSetMutator for testing
            public function testHasSetMutator(string $key): bool
            {
                return $this->hasSetMutator($key);
            }
        };

        $this->assertTrue($user->testHasSetMutator('password'));
        $this->assertFalse($user->testHasSetMutator('email'));
    }

    /**
     * Test multiple accessors work correctly
     */
    public function test_multiple_accessors(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function getFullNameAttribute(): string
            {
                return $this->getRawAttribute('first_name', '') . ' ' . $this->getRawAttribute('last_name', '');
            }

            protected function getDisplayNameAttribute(): string
            {
                return strtoupper($this->getRawAttribute('first_name', ''));
            }
        };

        $user->setAttribute('first_name', 'John');
        $user->setAttribute('last_name', 'Doe');

        $this->assertEquals('John Doe', $user->getAttribute('full_name'));
        $this->assertEquals('JOHN', $user->getAttribute('display_name'));
    }

    /**
     * Test accessor with type casting
     */
    public function test_accessor_with_casting(): void
    {
        $product = new class extends Model {
            protected static string $table = 'products';
            protected static array $casts = ['price' => 'float'];

            protected function getFormattedPriceAttribute(): string
            {
                $price = $this->getRawAttribute('price', 0);
                return '$' . number_format((float)$price, 2);
            }
        };

        $product->setAttribute('price', 99.99);

        $this->assertEquals('$99.99', $product->getAttribute('formatted_price'));
    }
}
