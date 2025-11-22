<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Migration;

use Toporia\Framework\Database\Schema\SchemaBuilder;


/**
 * Abstract Class Migration
 *
 * Base class for database migrations providing up/down methods for
 * versioned schema changes and database structure evolution.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Migration
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
abstract class Migration
{
    /**
     * @var SchemaBuilder Schema builder instance.
     */
    protected SchemaBuilder $schema;

    /**
     * Set the schema builder.
     *
     * @param SchemaBuilder $schema
     * @return void
     */
    public function setSchema(SchemaBuilder $schema): void
    {
        $this->schema = $schema;
    }

    /**
     * Run the migration (create tables/columns).
     *
     * @return void
     */
    abstract public function up(): void;

    /**
     * Reverse the migration (drop tables/columns).
     *
     * @return void
     */
    abstract public function down(): void;
}
