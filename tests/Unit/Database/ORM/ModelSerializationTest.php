<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\ORM\Model;

/**
 * Test Model Serialization
 *
 * ✅ TEST STATUS: ALL PASSED (18/18)
 * ✅ Last verified: 2025-01-22
 *
 * Tests serialization functionality from HasSerialization trait:
 * - toArrayWithOptions() with hidden/visible/appends
 * - makeVisible(), makeHidden()
 * - append(), setAppends()
 * - setVisible(), setHidden()
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class ModelSerializationTest extends TestCase
{
    /**
     * Test toArrayWithOptions returns all attributes by default
     */
    public function test_to_array_returns_all_attributes(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John Doe');
        $user->setAttribute('email', 'john@example.com');

        $array = $user->toArrayWithOptions();

        $this->assertEquals([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ], $array);
    }

    /**
     * Test hidden attributes are excluded from array
     */
    public function test_hidden_attributes_excluded(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
            protected static array $hidden = ['password'];
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John Doe');
        $user->setAttribute('password', 'secret');

        $array = $user->toArrayWithOptions();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('password', $array);
    }

    /**
     * Test visible attributes limit what's included
     */
    public function test_visible_attributes_limit_output(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
            protected static array $visible = ['id', 'name'];
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John Doe');
        $user->setAttribute('email', 'john@example.com');
        $user->setAttribute('password', 'secret');

        $array = $user->toArrayWithOptions();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('email', $array);
        $this->assertArrayNotHasKey('password', $array);
    }

    /**
     * Test makeHidden temporarily hides attributes
     */
    public function test_make_hidden(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John Doe');
        $user->setAttribute('email', 'john@example.com');

        $user->makeHidden('email');
        $array = $user->toArrayWithOptions();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('email', $array);
    }

    /**
     * Test makeHidden with array of attributes
     */
    public function test_make_hidden_array(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John Doe');
        $user->setAttribute('email', 'john@example.com');
        $user->setAttribute('password', 'secret');

        $user->makeHidden(['email', 'password']);
        $array = $user->toArrayWithOptions();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('email', $array);
        $this->assertArrayNotHasKey('password', $array);
    }

    /**
     * Test makeVisible temporarily shows hidden attributes
     */
    public function test_make_visible(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
            protected static array $hidden = ['password'];
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('password', 'secret');

        // First verify password is hidden
        $array1 = $user->toArrayWithOptions();
        $this->assertArrayNotHasKey('password', $array1);

        // Make it visible temporarily
        $user->makeVisible('password');
        $array2 = $user->toArrayWithOptions();

        $this->assertArrayHasKey('id', $array2);
        $this->assertArrayHasKey('password', $array2);
        $this->assertEquals('secret', $array2['password']);
    }

    /**
     * Test setVisible overrides visible attributes
     */
    public function test_set_visible(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John Doe');
        $user->setAttribute('email', 'john@example.com');
        $user->setAttribute('password', 'secret');

        $user->setVisible(['id', 'name']);
        $array = $user->toArrayWithOptions();

        $this->assertEquals(['id' => 1, 'name' => 'John Doe'], $array);
    }

    /**
     * Test setHidden overrides hidden attributes
     */
    public function test_set_hidden(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John Doe');
        $user->setAttribute('email', 'john@example.com');

        $user->setHidden(['email']);
        $array = $user->toArrayWithOptions();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('email', $array);
    }

    /**
     * Test append adds computed attributes
     */
    public function test_append_computed_attributes(): void
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
        $user->append('full_name');

        $array = $user->toArrayWithOptions();

        $this->assertArrayHasKey('first_name', $array);
        $this->assertArrayHasKey('last_name', $array);
        $this->assertArrayHasKey('full_name', $array);
        $this->assertEquals('John Doe', $array['full_name']);
    }

    /**
     * Test append with array of attributes
     */
    public function test_append_array(): void
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
        $user->append(['full_name', 'display_name']);

        $array = $user->toArrayWithOptions();

        $this->assertArrayHasKey('full_name', $array);
        $this->assertArrayHasKey('display_name', $array);
        $this->assertEquals('John Doe', $array['full_name']);
        $this->assertEquals('JOHN', $array['display_name']);
    }

    /**
     * Test setAppends replaces appended attributes
     */
    public function test_set_appends(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function getFullNameAttribute(): string
            {
                return 'Full Name';
            }

            protected function getDisplayNameAttribute(): string
            {
                return 'Display Name';
            }
        };

        $user->setAppends(['full_name', 'display_name']);
        $appends = $user->getAppends();

        $this->assertEquals(['full_name', 'display_name'], $appends);
    }

    /**
     * Test getAppends returns appended attributes
     */
    public function test_get_appends(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
            protected static array $appends = ['full_name'];
        };

        $this->assertEquals(['full_name'], $user->getAppends());
    }

    /**
     * Test appended attributes respect hidden
     */
    public function test_appends_respect_hidden(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
            protected static array $hidden = ['full_name'];

            protected function getFullNameAttribute(): string
            {
                return 'John Doe';
            }
        };

        $user->append('full_name');
        $array = $user->toArrayWithOptions();

        // full_name should not appear because it's hidden
        $this->assertArrayNotHasKey('full_name', $array);
    }

    /**
     * Test method chaining
     */
    public function test_method_chaining(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';

            protected function getFullNameAttribute(): string
            {
                return 'John Doe';
            }
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John');
        $user->setAttribute('email', 'john@example.com');

        $result = $user->makeHidden('email')
                       ->append('full_name')
                       ->toArrayWithOptions();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayHasKey('full_name', $result);
    }

    /**
     * Test visible takes precedence over hidden
     */
    public function test_visible_takes_precedence(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
            protected static array $visible = ['id', 'name'];
            protected static array $hidden = ['name']; // Should be ignored when visible is set
        };

        $user->setAttribute('id', 1);
        $user->setAttribute('name', 'John Doe');
        $user->setAttribute('email', 'john@example.com');

        $array = $user->toArrayWithOptions();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array); // visible takes precedence
        $this->assertArrayNotHasKey('email', $array);
    }

    /**
     * Test makeHidden returns $this for chaining
     */
    public function test_make_hidden_returns_this(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $result = $user->makeHidden('email');

        $this->assertSame($user, $result);
    }

    /**
     * Test makeVisible returns $this for chaining
     */
    public function test_make_visible_returns_this(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $result = $user->makeVisible('password');

        $this->assertSame($user, $result);
    }

    /**
     * Test append returns $this for chaining
     */
    public function test_append_returns_this(): void
    {
        $user = new class extends Model {
            protected static string $table = 'users';
        };

        $result = $user->append('full_name');

        $this->assertSame($user, $result);
    }
}
