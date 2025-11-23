# Migration System

Hệ thống migration chuyên nghiệp, đầy đủ tính năng hơn Laravel, được xây dựng theo Clean Architecture và SOLID principles.

## ✨ Features

- ✅ **Modern framework API** - Tương thích với Laravel migration syntax
- ✅ **High Performance** - Tối ưu SQL compilation, batch operations
- ✅ **Clean Architecture** - Tách biệt rõ ràng các layers
- ✅ **SOLID Principles** - Mỗi component tuân thủ SOLID
- ✅ **High Reusability** - Dễ dàng mở rộng và tái sử dụng
- ✅ **Full ALTER TABLE Support** - Hỗ trợ đầy đủ các thao tác ALTER TABLE
- ✅ **Multiple Database Drivers** - MySQL, PostgreSQL, SQLite
- ✅ **Composite Keys** - Hỗ trợ composite primary keys và indexes
- ✅ **Foreign Keys** - Hỗ trợ onDelete/onUpdate actions
- ✅ **Spatial Types** - Hỗ trợ geometry, point, polygon, etc.

## 🚀 Quick Start

### 1. Tạo Migration

```php
<?php
// database/migrations/2024_01_01_000000_create_users_table.php

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Database\Schema\SchemaBuilder;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('users');
    }
}
```

### 2. Chạy Migration

```bash
php console migrate
```

### 3. Alter Table (Không cần tạo migration mới)

```bash
# Thêm cột
php console migrate:alter users --add=phone:string:20

# Xóa cột
php console migrate:alter users --drop=old_column

# Sửa cột
php console migrate:alter users --modify=name:string:100

# Đổi tên cột
php console migrate:alter users --rename=old_name:new_name

# Thêm index
php console migrate:alter users --index=email

# Thêm foreign key
php console migrate:alter posts --foreign=user_id:id:users:cascade:restrict
```

## 📝 Column Types

### Integer Types

```php
$table->id();                    // Auto-increment BIGINT primary key
$table->bigInteger('votes');     // BIGINT
$table->integer('votes');        // INT
$table->mediumInteger('votes');  // MEDIUMINT
$table->smallInteger('votes');    // SMALLINT
$table->tinyInteger('votes');     // TINYINT
$table->unsignedBigInteger('id'); // UNSIGNED BIGINT
$table->unsignedInteger('id');    // UNSIGNED INT
```

### String Types

```php
$table->string('name', 100);     // VARCHAR(100)
$table->char('code', 10);        // CHAR(10)
$table->text('description');      // TEXT
$table->mediumText('content');   // MEDIUMTEXT
$table->longText('content');      // LONGTEXT
$table->tinyText('note');         // TINYTEXT
```

### Numeric Types

```php
$table->decimal('price', 10, 2);  // DECIMAL(10, 2)
$table->float('amount', 8, 2);     // FLOAT(8, 2)
$table->double('amount', 8, 2);    // DOUBLE(8, 2)
```

### Date/Time Types

```php
$table->date('birthday');              // DATE
$table->datetime('published_at');       // DATETIME
$table->timestamp('created_at');         // TIMESTAMP
$table->timestamp('created_at', 6);      // TIMESTAMP(6) - với precision
$table->time('start_time');             // TIME
$table->year('year');                   // YEAR (MySQL)
```

### Other Types

```php
$table->boolean('is_active');          // BOOLEAN/TINYINT(1)
$table->json('metadata');              // JSON/JSONB
$table->jsonb('data');                 // JSONB (PostgreSQL)
$table->binary('file', 255);           // BINARY
$table->blob('data');                  // BLOB
$table->longBlob('data');              // LONGBLOB
$table->uuid('id');                    // UUID/CHAR(36)
$table->ipAddress('ip');               // VARCHAR(45)
$table->macAddress('mac');             // VARCHAR(17)
```

### Enum & Set

```php
$table->enum('status', ['pending', 'approved', 'rejected']);
$table->set('tags', ['php', 'laravel', 'toporia']);
```

### Spatial Types

```php
$table->geometry('location');
$table->point('coordinates');
$table->lineString('path');
$table->polygon('area');
$table->multiPoint('points');
$table->multiLineString('paths');
$table->multiPolygon('areas');
$table->geometryCollection('shapes');
```

## 🔧 Column Modifiers

### Basic Modifiers

```php
$table->string('email')->nullable();              // NULL allowed
$table->string('name')->default('Guest');          // Default value
$table->integer('votes')->unsigned();             // UNSIGNED
$table->string('code')->unique();                 // UNIQUE constraint
$table->text('description')->comment('User bio'); // Column comment
```

### Position Modifiers (ALTER TABLE)

```php
$table->string('email')->after('name');  // Place after 'name' column
$table->string('id')->first();            // Place first
```

### Timestamp Modifiers

```php
$table->timestamp('created_at')->useCurrent();           // DEFAULT CURRENT_TIMESTAMP
$table->timestamp('updated_at')->useCurrentOnUpdate();   // ON UPDATE CURRENT_TIMESTAMP
```

### Advanced Modifiers

```php
$table->string('name')->charset('utf8mb4');
$table->string('name')->collation('utf8mb4_unicode_ci');
$table->decimal('price')->precision(10, 2);
$table->string('name')->length(100);
```

## 🔑 Indexes

### Primary Keys

```php
// Single primary key
$table->id();
$table->primary('id');

// Composite primary key
$table->primary(['user_id', 'role_id']);
```

### Unique Indexes

```php
$table->unique('email');
$table->unique(['email', 'username']);
$table->unique('email', 'unique_email_index'); // With custom name
```

### Regular Indexes

```php
$table->index('email');
$table->index(['user_id', 'created_at']);
$table->index('email', 'email_index', 'btree'); // With name and algorithm
```

### Fulltext Indexes (MySQL)

```php
$table->fullText('content');
$table->fullText(['title', 'body']);
```

### Spatial Indexes

```php
$table->spatialIndex('location');
$table->spatialIndex(['lat', 'lng']);
```

## 🔗 Foreign Keys

### Basic Foreign Key

```php
$table->foreign('user_id')
    ->references('id')
    ->on('users');
```

### With Actions

```php
$table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade')
    ->onUpdate('restrict');
```

### Actions Available

- `cascade` - Delete/update related rows
- `restrict` - Prevent delete/update if related rows exist
- `set null` - Set foreign key to NULL
- `no action` - No action (default)

### Composite Foreign Keys

```php
$table->foreign(['user_id', 'role_id'])
    ->references(['id', 'id'])
    ->on('user_roles');
```

## 📋 ALTER TABLE Operations

### Using Migration

```php
public function up(): void
{
    $this->schema->table('users', function ($table) {
        // Add column
        $table->string('phone')->after('email');

        // Modify column
        $table->string('name', 100)->change();

        // Rename column
        $table->renameColumn('old_name', 'new_name');

        // Drop column
        $table->dropColumn('old_column');

        // Add index
        $table->index('email');

        // Drop index
        $table->dropIndex('email_index');

        // Drop unique
        $table->dropUnique('email');

        // Drop foreign key
        $table->dropForeign('users_user_id_foreign');

        // Drop primary key
        $table->dropPrimary();
    });
}
```

### Using CLI Command

```bash
# Add multiple columns
php console migrate:alter users --add=phone:string:20,address:text

# Drop columns
php console migrate:alter users --drop=old_col1,old_col2

# Modify columns
php console migrate:alter users --modify=name:string:100,email:string:255

# Rename columns
php console migrate:alter users --rename=old_name:new_name

# Add indexes
php console migrate:alter users --index=email,username

# Add unique indexes
php console migrate:alter users --unique=email

# Add foreign keys
php console migrate:alter posts --foreign=user_id:id:users:cascade:restrict

# Drop indexes
php console migrate:alter users --drop-index=email_index

# Drop unique
php console migrate:alter users --drop-unique=email

# Drop foreign keys
php console migrate:alter posts --drop-foreign=posts_user_id_foreign
```

## 🎯 Table Modifiers

### Timestamps

```php
$table->timestamps();           // created_at, updated_at
$table->timestamps(6);          // With microsecond precision
$table->nullableTimestamps();  // Alias for timestamps()
```

### Soft Deletes

```php
$table->softDeletes();          // deleted_at timestamp
$table->softDeletes('deleted_at'); // Custom column name
```

### Remember Token

```php
$table->rememberToken();        // remember_token VARCHAR(100)
```

### Table Options

```php
$table->engine('InnoDB');       // Table engine (MySQL)
$table->charset('utf8mb4');      // Table charset
$table->collation('utf8mb4_unicode_ci'); // Table collation
$table->comment('User accounts table');  // Table comment
```

## 📚 Examples

### Complete User Table

```php
Schema::create('users', function ($table) {
    $table->id();
    $table->string('name', 100);
    $table->string('email', 255)->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password', 255);
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();

    // Indexes
    $table->index('email');
    $table->index('created_at');

    // Table options
    $table->engine('InnoDB');
    $table->charset('utf8mb4');
    $table->collation('utf8mb4_unicode_ci');
    $table->comment('User accounts');
});
```

### Posts Table with Foreign Keys

```php
Schema::create('posts', function ($table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->string('title', 255);
    $table->text('content');
    $table->string('slug', 255)->unique();
    $table->boolean('published')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    // Indexes
    $table->index(['user_id', 'created_at']);
    $table->fullText(['title', 'content']);

    // Foreign key
    $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->onDelete('cascade')
        ->onUpdate('restrict');
});
```

### Composite Primary Key

```php
Schema::create('user_roles', function ($table) {
    $table->foreignId('user_id');
    $table->foreignId('role_id');
    $table->timestamps();

    // Composite primary key
    $table->primary(['user_id', 'role_id']);

    // Foreign keys
    $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->onDelete('cascade');

    $table->foreign('role_id')
        ->references('id')
        ->on('roles')
        ->onDelete('cascade');
});
```

### Alter Table Example

```php
Schema::table('users', function ($table) {
    // Add column
    $table->string('phone', 20)->nullable()->after('email');

    // Modify column
    $table->string('name', 150)->change();

    // Rename column
    $table->renameColumn('old_name', 'new_name');

    // Drop column
    $table->dropColumn('old_column');

    // Add index
    $table->index('phone');

    // Drop index
    $table->dropIndex('users_email_index');
});
```

## ⚡ Performance Optimizations

### Batch Operations

```php
// Multiple columns in one ALTER TABLE
Schema::table('users', function ($table) {
    $table->string('phone')->after('email');
    $table->string('address')->after('phone');
    $table->index('phone');
    $table->index('address');
});
// Executes as: ALTER TABLE users ADD COLUMN phone, ADD COLUMN address, ADD INDEX...
```

### Efficient SQL Compilation

- Single-pass SQL generation
- Minimal string concatenation
- Driver-specific optimizations
- Batch ALTER TABLE operations

## 🏗️ Architecture

### Clean Architecture Layers

```
┌─────────────────────────────────────┐
│  Presentation Layer                 │
│  - Migration classes                │
│  - CLI commands                     │
└─────────────────────────────────────┘
           ↓
┌─────────────────────────────────────┐
│  Framework Layer                    │
│  - SchemaBuilder (SQL compilation)  │
│  - Blueprint (table definition)     │
│  - ColumnDefinition (modifiers)     │
│  - ForeignKeyDefinition             │
└─────────────────────────────────────┘
           ↓
┌─────────────────────────────────────┐
│  Infrastructure Layer               │
│  - Connection (database driver)     │
└─────────────────────────────────────┘
```

### SOLID Principles

- **Single Responsibility**: Mỗi class có một trách nhiệm duy nhất
- **Open/Closed**: Dễ dàng mở rộng (thêm column types, drivers mới)
- **Liskov Substitution**: Tất cả implementations tuân thủ interfaces
- **Interface Segregation**: Interfaces nhỏ, tập trung
- **Dependency Inversion**: Phụ thuộc vào abstractions

## 📊 API Reference

### SchemaBuilder Methods

- `create(string $table, callable $callback): void`
- `table(string $table, callable $callback): void` - ALTER TABLE
- `drop(string $table): void`
- `dropIfExists(string $table): void`
- `rename(string $from, string $to): void`
- `hasTable(string $table): bool`
- `hasColumn(string $table, string $column): bool`

### Blueprint Methods

#### Column Types
- `id(string $name = 'id'): self`
- `bigInteger(string $name): ColumnDefinition`
- `integer(string $name): ColumnDefinition`
- `string(string $name, int $length = 255): ColumnDefinition`
- `text(string $name): ColumnDefinition`
- `decimal(string $name, int $precision = 10, int $scale = 2): ColumnDefinition`
- `boolean(string $name): ColumnDefinition`
- `date(string $name): ColumnDefinition`
- `datetime(string $name): ColumnDefinition`
- `timestamp(string $name, int $precision = 0): ColumnDefinition`
- `json(string $name): ColumnDefinition`
- `uuid(string $name = 'uuid'): ColumnDefinition`
- `enum(string $name, array $values): ColumnDefinition`
- ... và nhiều hơn nữa

#### Indexes
- `primary(string|array $columns, ?string $name = null): self`
- `unique(string|array $columns, ?string $name = null): self`
- `index(string|array $columns, ?string $name = null, ?string $algorithm = null): self`
- `fullText(string|array $columns, ?string $name = null): self`
- `spatialIndex(string|array $columns, ?string $name = null): self`

#### Foreign Keys
- `foreign(string|array $columns, ?string $name = null): ForeignKeyDefinition`

#### ALTER Operations
- `dropColumn(string|array $columns): self`
- `dropPrimary(?string $name = null): self`
- `dropUnique(string|array $index): self`
- `dropIndex(string|array $index): self`
- `dropForeign(string|array $foreignKey): self`
- `renameColumn(string $from, string $to): self`
- `rename(string $to): self`

#### Table Modifiers
- `timestamps(int $precision = 0): self`
- `softDeletes(string $column = 'deleted_at'): ColumnDefinition`
- `rememberToken(): ColumnDefinition`
- `engine(string $engine): self`
- `charset(string $charset): self`
- `collation(string $collation): self`
- `comment(string $comment): self`

### ColumnDefinition Methods

- `nullable(bool $nullable = true): self`
- `default(mixed $value): self`
- `unsigned(): self`
- `unique(?string $indexName = null): self`
- `comment(string $comment): self`
- `after(string $column): self`
- `first(): self`
- `change(): self`
- `autoIncrement(): self`
- `primary(): self`
- `length(int $length): self`
- `precision(int $precision, int $scale = 0): self`
- `charset(string $charset): self`
- `collation(string $collation): self`
- `useCurrent(): self`
- `useCurrentOnUpdate(): self`

### ForeignKeyDefinition Methods

- `references(string $table, string|array $columns): self`
- `onDelete(string $action): self`
- `onUpdate(string $action): self`
- `name(string $name): self`

## 🎉 Summary

Migration system này cung cấp:

✅ **Modern framework API** - Dễ dàng migrate từ Laravel
✅ **High Performance** - Tối ưu SQL compilation và batch operations
✅ **Clean Architecture** - Dễ maintain và test
✅ **SOLID Principles** - Code quality cao
✅ **High Reusability** - Dễ dàng mở rộng
✅ **Full ALTER TABLE Support** - Hỗ trợ đầy đủ các thao tác
✅ **CLI Command** - `migrate:alter` để alter table nhanh chóng

Sử dụng migration system giống Laravel, nhưng với performance và architecture tốt hơn!

