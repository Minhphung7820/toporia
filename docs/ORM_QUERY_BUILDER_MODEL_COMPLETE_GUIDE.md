# Tài Liệu Hoàn Chỉnh: ORM, Query Builder và Model

## Mục Lục

1. [Tổng Quan](#tổng-quan)
2. [Query Builder](#query-builder)
3. [Model (ORM)](#model-orm)
4. [Relationships (Quan Hệ)](#relationships-quan-hệ)
5. [Eager Loading](#eager-loading)
6. [Ví Dụ Sử Dụng](#ví-dụ-sử-dụng)
7. [Best Practices](#best-practices)

---

## Tổng Quan

Toporia Framework cung cấp một hệ thống ORM mạnh mẽ với Query Builder linh hoạt, hỗ trợ:

- **Query Builder**: Fluent interface để xây dựng SQL queries
- **Model (ORM)**: Active Record pattern với relationships, events, scopes
- **Multi-database**: Hỗ trợ MySQL, PostgreSQL, SQLite, MongoDB
- **Performance**: Chunking, lazy loading, eager loading để tối ưu hiệu suất

### Kiến Trúc

```
QueryBuilder (Base)
    ├── ModelQueryBuilder (extends QueryBuilder)
    └── Concerns (Traits)
        ├── BuildsWhereClausesAdvanced
        ├── BuildsWhereClausesExtended
        ├── BuildsSubqueries
        ├── BuildsConditionalClauses
        ├── BuildsUnions
        ├── BuildsLocks
        ├── BuildsAggregates
        ├── BuildsChunking
        └── BuildsAdvancedQueries

Model (Base)
    ├── ModelQueryBuilder (for queries)
    └── Relationships
        ├── HasOne, HasMany
        ├── BelongsTo, BelongsToMany
        ├── HasOneThrough, HasManyThrough
        └── MorphOne, MorphMany, MorphTo, MorphToMany
```

---

## Query Builder

### Khởi Tạo Query Builder

```php
use Toporia\Framework\Database\DatabaseManager;

// Từ DatabaseManager
$db = container(DatabaseManager::class);
$query = $db->table('users');

// Hoặc từ Model
$query = UserModel::query();
```

### Các Phương Thức Cơ Bản

#### 1. SELECT - Chọn Cột

```php
// Chọn tất cả
$query->select('*');

// Chọn nhiều cột
$query->select(['id', 'name', 'email']);

// Chọn với alias
$query->select(['id', 'name as full_name', 'email']);

// Raw expression
$query->selectRaw('COUNT(*) as total');
$query->selectRaw('price * quantity as total_price', []);

// Kết hợp
$query->select(['id', 'name'])
      ->selectRaw('COUNT(*) as count');
```

#### 2. FROM - Chọn Bảng

```php
$query->table('users');
$query->from('users'); // Alias của table()
```

#### 3. WHERE - Điều Kiện

##### WHERE Cơ Bản

```php
// where($column, $value) - operator mặc định là '='
$query->where('status', 'active');

// where($column, $operator, $value)
$query->where('price', '>', 100);
$query->where('name', 'LIKE', '%john%');

// Nested WHERE với closure
$query->where('status', 'active')
      ->where(function($q) {
          $q->where('price', '>', 100)
            ->orWhere('featured', true);
      });
// WHERE status = 'active' AND (price > 100 OR featured = true)
```

##### OR WHERE

```php
$query->where('status', 'active')
      ->orWhere('role', 'admin');

// OR với closure
$query->where('status', 'active')
      ->orWhere(function($q) {
          $q->where('role', 'admin')
            ->where('verified', true);
      });
```

##### WHERE IN / NOT IN

```php
// WHERE IN với array
$query->whereIn('id', [1, 2, 3, 4, 5]);

// WHERE IN với subquery
$query->whereIn('user_id', function($q) {
    $q->select('id')
      ->from('active_users')
      ->where('status', 'active');
});

// WHERE NOT IN
$query->whereNotIn('id', [1, 2, 3]);

// OR WHERE IN
$query->orWhereIn('role', ['admin', 'moderator']);
```

##### WHERE NULL / NOT NULL

```php
$query->whereNull('deleted_at');
$query->whereNotNull('email');
$query->orWhereNull('phone');
$query->orWhereNotNull('address');
```

##### WHERE BETWEEN

```php
// WHERE BETWEEN
$query->whereBetween('price', [100, 500]);
$query->whereBetween('created_at', [$startDate, $endDate]);

// WHERE NOT BETWEEN
$query->whereNotBetween('price', [100, 500]);

// OR WHERE BETWEEN
$query->orWhereBetween('age', [18, 65]);
```

##### WHERE DATE / TIME

```php
// WHERE DATE
$query->whereDate('created_at', '2024-01-01');
$query->whereDate('created_at', '>=', '2024-01-01');
$query->orWhereDate('updated_at', '2024-01-01');

// WHERE MONTH
$query->whereMonth('created_at', 12); // Tháng 12
$query->whereMonth('created_at', '>=', 6);

// WHERE DAY
$query->whereDay('created_at', 25); // Ngày 25
$query->whereDay('created_at', '>', 15);

// WHERE YEAR
$query->whereYear('created_at', 2024);
$query->whereYear('created_at', '>=', 2020);

// WHERE TIME
$query->whereTime('created_at', '09:00:00');
$query->whereTime('created_at', '>=', '09:00:00');
```

##### WHERE COLUMN

```php
// So sánh giữa các cột
$query->whereColumn('updated_at', '>', 'created_at');
$query->whereColumn('first_name', 'last_name'); // first_name = last_name
$query->orWhereColumn('price', 'discount_price');
```

##### WHERE JSON

```php
// WHERE JSON CONTAINS
$query->whereJsonContains('metadata->tags', 'php');
$query->whereJsonContains('settings->notifications', true);

// WHERE JSON DOESN'T CONTAIN
$query->whereJsonDoesntContain('metadata->tags', 'deprecated');

// WHERE JSON LENGTH
$query->whereJsonLength('metadata->tags', '>', 3);
$query->whereJsonLength('settings->permissions', 5);
```

##### WHERE LIKE / REGEXP

```php
// WHERE LIKE
$query->whereLike('name', '%john%');
$query->whereNotLike('email', '%@spam.com');
$query->orWhereLike('title', '%important%');

// WHERE REGEXP (MySQL)
$query->whereRegexp('email', '^[a-z]+@example\.com$');
$query->orWhereRegexp('phone', '^\+84');
```

##### WHERE FULLTEXT

```php
// Full-text search (MySQL/PostgreSQL)
$query->whereFullText('title', 'search term');
$query->whereFullText(['title', 'content'], 'search term');
$query->orWhereFullText('description', 'keyword');
```

##### WHERE RAW

```php
// Raw SQL WHERE
$query->whereRaw('price * quantity > ?', [1000]);
$query->orWhereRaw('DATE(created_at) = CURDATE()');
```

##### WHERE EXISTS / NOT EXISTS

```php
// WHERE EXISTS với subquery
$query->whereExists(function($q) {
    $q->select('id')
      ->from('orders')
      ->whereColumn('orders.user_id', 'users.id');
});

// WHERE NOT EXISTS
$query->whereNotExists(function($q) {
    $q->select('id')
      ->from('bans')
      ->whereColumn('bans.user_id', 'users.id');
});

// OR WHERE EXISTS
$query->orWhereExists(function($q) { /* ... */ });
```

#### 4. JOIN - Kết Nối Bảng

```php
// INNER JOIN
$query->join('orders', 'users.id', '=', 'orders.user_id');
$query->join('orders', function($join) {
    $join->on('users.id', '=', 'orders.user_id')
         ->where('orders.status', 'completed');
});

// LEFT JOIN
$query->leftJoin('profiles', 'users.id', '=', 'profiles.user_id');

// RIGHT JOIN
$query->rightJoin('orders', 'users.id', '=', 'orders.user_id');

// CROSS JOIN
$query->crossJoin('categories');

// FULL OUTER JOIN (PostgreSQL)
$query->fullOuterJoin('orders', 'users.id', '=', 'orders.user_id');

// JOIN với subquery
$query->joinSub(function($q) {
    $q->select('user_id', 'SUM(total) as total')
      ->from('orders')
      ->groupBy('user_id');
}, 'order_totals', 'users.id', '=', 'order_totals.user_id');

// LEFT JOIN với subquery
$query->leftJoinSub(function($q) { /* ... */ }, 'alias', 'col1', '=', 'col2');
```

#### 5. ORDER BY - Sắp Xếp

```php
// ORDER BY đơn giản
$query->orderBy('created_at', 'DESC');
$query->orderBy('name', 'ASC');

// Nhiều ORDER BY
$query->orderBy('status', 'ASC')
      ->orderBy('created_at', 'DESC');

// Helper methods
$query->oldest('created_at'); // ORDER BY created_at ASC
$query->latest('created_at');  // ORDER BY created_at DESC
$query->inRandomOrder();      // ORDER BY RAND()
```

#### 6. GROUP BY / HAVING

```php
// GROUP BY
$query->groupBy('status');
$query->groupBy(['status', 'category_id']);

// HAVING
$query->having('COUNT(*)', '>', 10);
$query->orHaving('SUM(total)', '>', 1000);
```

#### 7. LIMIT / OFFSET

```php
// LIMIT
$query->limit(10);
$query->take(10); // Alias của limit()

// OFFSET
$query->offset(20);
$query->skip(20); // Alias của offset()

// Kết hợp
$query->limit(10)->offset(20); // LIMIT 10 OFFSET 20

// Helper cho pagination
$query->forPage(2, 15); // Page 2, 15 items per page
```

#### 8. DISTINCT

```php
$query->distinct();
```

### Các Phương Thức Nâng Cao

#### 1. Conditional Clauses (Điều Kiện Có Điều Kiện)

```php
// when() - thực thi callback nếu condition là true
$query->when($request->has('status'), function($q) use ($request) {
    $q->where('status', $request->get('status'));
});

// when() với else
$query->when($role === 'admin', function($q) {
    $q->where('role', 'admin');
}, function($q) {
    $q->where('role', 'user');
});

// unless() - ngược lại với when()
$query->unless($isAdmin, function($q) {
    $q->where('status', 'active');
});

// tap() - thực thi callback và trả về query
$query->tap(function($q) {
    // Thực hiện side effects
    logger()->info('Query: ' . $q->toSql());
})->get();
```

#### 2. Unions

```php
// UNION
$query->union(function($q) {
    $q->select('name', 'email')
      ->from('users');
});

// UNION ALL
$query->unionAll(function($q) {
    $q->select('name', 'email')
      ->from('admins');
});

// UNION với QueryBuilder instance
$secondQuery = DB::table('admins')->select('name', 'email');
$query->union($secondQuery);
```

#### 3. Locks (Khóa Bản Ghi)

```php
// SELECT ... FOR UPDATE (pessimistic lock)
$query->lockForUpdate();

// SELECT ... LOCK IN SHARE MODE (shared lock)
$query->sharedLock();
```

#### 4. Aggregates (Hàm Tổng Hợp)

```php
// COUNT
$count = $query->count();
$count = $query->count('id');

// SUM
$total = $query->sum('price');
$total = $query->sum('price * quantity'); // Expression

// AVG / AVERAGE
$avg = $query->avg('price');
$avg = $query->average('price');

// MIN
$min = $query->min('price');

// MAX
$max = $query->max('price');

// Aggregate tùy chỉnh
$result = $query->aggregate('COUNT', 'DISTINCT user_id');

// Nhiều aggregates trong một query
$stats = $query->aggregates([
    'total_sum' => 'SUM(total)',
    'total_avg' => 'AVG(total)',
    'order_count' => 'COUNT(*)',
    'max_total' => 'MAX(total)'
]);
// Returns: ['total_sum' => 1000, 'total_avg' => 100, ...]
```

#### 5. Chunking (Xử Lý Theo Chunk)

```php
// chunk() - xử lý theo chunk với OFFSET
$query->chunk(100, function($users) {
    foreach ($users as $user) {
        // Xử lý mỗi user
        processUser($user);
    }
    // Return false để dừng chunking
    // return false;
});

// chunkById() - hiệu quả hơn với WHERE id > lastId
$query->chunkById(100, function($users) {
    foreach ($users as $user) {
        processUser($user);
    }
}, 'id', 'id'); // column, alias

// each() - xử lý từng record
$query->each(function($user) {
    processUser($user);
}, 1000); // chunk size

// eachById() - each với chunkById
$query->eachById(function($user) {
    processUser($user);
}, 1000);
```

#### 6. Lazy Loading (Tải Lười)

```php
// lazy() - Generator với chunking
foreach ($query->lazy(1000) as $user) {
    processUser($user);
}

// lazyById() - Generator với chunkById
foreach ($query->lazyById(1000) as $user) {
    processUser($user);
}

// cursor() - Generator từng record (streaming)
foreach ($query->cursor() as $user) {
    processUser($user);
}
```

#### 7. Advanced Queries (CTE và Window Functions)

##### Common Table Expressions (CTE)

```php
// CTE đơn giản
$query->with('active_users', function($q) {
    $q->table('users')->where('status', 'active');
})
->from('active_users')
->select('*');

// CTE với columns
$query->with('user_stats', function($q) {
    $q->table('users')
      ->select(['id', 'COUNT(*) as order_count'])
      ->groupBy('id');
}, ['user_id', 'order_count'])
->from('user_stats')
->where('order_count', '>', 10);

// Nhiều CTEs
$query->with('cte1', function($q) { /* ... */ })
      ->with('cte2', function($q) { /* ... */ })
      ->from('cte1')
      ->join('cte2', 'cte1.id', '=', 'cte2.id');

// Recursive CTE
$query->withRecursive('category_tree',
    // Anchor member
    function($q) {
        $q->table('categories')
          ->where('parent_id', null)
          ->select(['id', 'name', 'parent_id']);
    },
    // Recursive member
    function($q) {
        $q->table('categories')
          ->join('category_tree', 'categories.parent_id', '=', 'category_tree.id')
          ->select(['categories.id', 'categories.name', 'categories.parent_id']);
    }
)
->from('category_tree')
->select('*');
```

##### Window Functions

```php
// Window function đơn giản
$query->select('*')
      ->window('row_number', 'ROW_NUMBER()', ['created_at' => 'DESC'])
      ->get();
// SELECT *, ROW_NUMBER() OVER (ORDER BY created_at DESC) AS row_number

// Window function với PARTITION BY
$query->select('*')
      ->window('rank_in_category', 'RANK()', ['category_id'], ['price' => 'DESC'])
      ->get();
// SELECT *, RANK() OVER (PARTITION BY category_id ORDER BY price DESC) AS rank_in_category
```

### Thực Thi Query

```php
// get() - Lấy tất cả kết quả
$users = $query->get(); // Returns RowCollection

// first() - Lấy record đầu tiên
$user = $query->first(); // Returns array|null

// find() - Tìm theo ID
$user = $query->find(1); // Returns array|null
$user = $query->find(1, 'id'); // Column name

// exists() - Kiểm tra tồn tại
if ($query->exists()) {
    // Có kết quả
}

// count() - Đếm số lượng
$count = $query->count();

// toSql() - Lấy SQL string
$sql = $query->toSql();
$bindings = $query->getBindings();

// getArray() - Lấy kết quả dạng array
$array = $query->getArray();
```

### INSERT / UPDATE / DELETE

```php
// INSERT
$id = $query->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]); // Returns last insert ID

// INSERT nhiều records
$query->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
]);

// UPDATE
$affected = $query->where('id', 1)
                  ->update(['name' => 'New Name']); // Returns affected rows

// UPDATE OR INSERT
$query->updateOrInsert(
    ['email' => 'john@example.com'], // WHERE conditions
    ['name' => 'John Doe', 'updated_at' => now()] // UPDATE values
);

// UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
$affected = $query->upsert(
    [
        ['sku' => 'PROD-001', 'title' => 'Product 1', 'price' => 99.99],
        ['sku' => 'PROD-002', 'title' => 'Product 2', 'price' => 149.99]
    ],
    'sku', // Unique column
    ['title', 'price'] // Columns to update on conflict
);

// DELETE
$affected = $query->where('status', 'inactive')
                  ->delete(); // Returns affected rows
```

### Increment / Decrement

```php
// INCREMENT
$affected = $query->where('id', 1)
                  ->increment('views', 1); // +1
$affected = $query->where('id', 1)
                  ->increment('balance', 100, ['last_updated' => now()]);

// DECREMENT
$affected = $query->where('id', 1)
                  ->decrement('stock', 1); // -1
$affected = $query->where('id', 1)
                  ->decrement('points', 10, ['updated_at' => now()]);
```

### Pagination

```php
// paginate() - Phân trang
$paginator = $query->paginate(15, 1, '/users'); // perPage, page, path

// Sử dụng paginator
foreach ($paginator->items() as $item) {
    // Xử lý item
}

$total = $paginator->total();
$lastPage = $paginator->lastPage();
$hasMore = $paginator->hasMorePages();
$currentPage = $paginator->currentPage();
```

### Connection

```php
// Chọn connection khác
$query->onConnection('analytics');
```

---

## Model (ORM)

### Định Nghĩa Model

```php
<?php

namespace App\Models;

use Toporia\Framework\Database\ORM\Model;

class UserModel extends Model
{
    // Tên bảng (tự động suy ra từ class name nếu không set)
    protected static string $table = 'users';

    // Primary key (mặc định: 'id')
    protected static string $primaryKey = 'id';

    // Timestamps (mặc định: true)
    protected static bool $timestamps = true;

    // Fillable - whitelist cho mass assignment
    protected static array $fillable = [
        'name',
        'email',
        'password'
    ];

    // Guarded - blacklist cho mass assignment
    protected static array $guarded = []; // Hoặc ['*'] để disable mass assignment

    // Casts - tự động cast kiểu dữ liệu
    protected static array $casts = [
        'is_active' => 'bool',
        'metadata' => 'array',
        'settings' => 'json',
        'created_at' => 'date'
    ];

    // Hidden - ẩn khi serialize
    protected static array $hidden = [
        'password',
        'remember_token'
    ];

    // Visible - chỉ hiển thị các field này
    protected static array $visible = []; // Nếu set, chỉ hiển thị các field này

    // Appends - thêm computed attributes
    protected static array $appends = [
        'full_name'
    ];

    // Connection - sử dụng connection khác
    protected static ?string $connection = null; // 'analytics', 'mongodb', etc.

    // MongoDB collection name (chỉ dùng với MongoDB)
    protected static string $collection = 'users';
}
```

### Truy Vấn Với Model

#### Static Methods

```php
// query() - Tạo query builder
$query = UserModel::query();

// find() - Tìm theo ID
$user = UserModel::find(1);
$user = UserModel::findOrFail(1); // Throw exception nếu không tìm thấy

// all() - Lấy tất cả
$users = UserModel::all();

// first() - Lấy record đầu tiên
$user = UserModel::first();

// get() - Lấy với điều kiện
$users = UserModel::where('status', 'active')->get();

// create() - Tạo và lưu
$user = UserModel::create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// upsert() - Bulk upsert
UserModel::upsert(
    [
        ['email' => 'john@example.com', 'name' => 'John'],
        ['email' => 'jane@example.com', 'name' => 'Jane']
    ],
    'email', // Unique column
    ['name'] // Columns to update
);

// paginate() - Phân trang
$users = UserModel::paginate(15, 1, '/users');

// with() - Eager loading
$users = UserModel::with('posts')->get();
$users = UserModel::with(['posts', 'comments'])->get();
```

#### Instance Methods

```php
// save() - Lưu model
$user = new UserModel();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// Hoặc
$user = new UserModel(['name' => 'John', 'email' => 'john@example.com']);
$user->save();

// update() - Cập nhật
$user->name = 'Jane Doe';
$user->save(); // Chỉ update các field đã thay đổi

// delete() - Xóa
$user->delete();

// refresh() - Làm mới từ database
$user->refresh();

// fill() - Mass assignment
$user->fill([
    'name' => 'John',
    'email' => 'john@example.com'
]);
$user->save();

// replicate() - Tạo bản sao
$newUser = $user->replicate();
$newUser->name = 'Jane';
$newUser->save();

// replicate() với exclude
$newUser = $user->replicate(['email', 'phone']);
```

### Attributes (Thuộc Tính)

#### Truy Cập Attributes

```php
// Get attribute
$name = $user->name;
$name = $user->getAttribute('name');

// Set attribute
$user->name = 'John';
$user->setAttribute('name', 'John');

// Check exists
if (isset($user->name)) {
    // ...
}

// Get all attributes
$attributes = $user->toArray();
```

#### Accessors và Mutators

```php
class UserModel extends Model
{
    // Accessor - get{Attribute}Attribute
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Mutator - set{Attribute}Attribute
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    // Sử dụng
    // $user->full_name; // Tự động gọi getFullNameAttribute()
    // $user->email = 'JOHN@EXAMPLE.COM'; // Tự động gọi setEmailAttribute()
}
```

#### Casts

```php
protected static array $casts = [
    'is_active' => 'bool',        // true/false
    'price' => 'float',           // 99.99
    'quantity' => 'int',          // 10
    'metadata' => 'array',        // ['key' => 'value']
    'settings' => 'json',         // {"key": "value"}
    'created_at' => 'date',      // DateTime object
];
```

### Dirty Checking (Kiểm Tra Thay Đổi)

```php
// isDirty() - Kiểm tra có thay đổi không
if ($user->isDirty()) {
    // Có thay đổi
}

// isDirty('field') - Kiểm tra field cụ thể
if ($user->isDirty('name')) {
    // Field 'name' đã thay đổi
}

// getDirty() - Lấy các field đã thay đổi
$dirty = $user->getDirty(); // ['name' => 'New Name', 'email' => 'new@example.com']

// getOriginal() - Lấy giá trị gốc
$original = $user->getOriginal('name');
$allOriginal = $user->getOriginal();

// getChanges() - Alias của getDirty()
$changes = $user->getChanges();

// wasChanged() - Kiểm tra field đã thay đổi
if ($user->wasChanged('name')) {
    // Field 'name' đã thay đổi
}
```

### Events (Sự Kiện)

Model hỗ trợ các lifecycle events:

```php
class UserModel extends Model
{
    // Creating - Trước khi tạo
    public function creating(): bool
    {
        // Return false để hủy
        // return false;
        return true;
    }

    // Created - Sau khi tạo
    public function created(): void
    {
        // Logic sau khi tạo
    }

    // Updating - Trước khi update
    public function updating(): bool
    {
        return true;
    }

    // Updated - Sau khi update
    public function updated(): void
    {
        // Logic sau khi update
    }

    // Saving - Trước khi save (create hoặc update)
    public function saving(): bool
    {
        return true;
    }

    // Saved - Sau khi save
    public function saved(): void
    {
        // Logic sau khi save
    }

    // Deleting - Trước khi delete
    public function deleting(): bool
    {
        return true;
    }

    // Deleted - Sau khi delete
    public function deleted(): void
    {
        // Logic sau khi delete
    }
}
```

### Serialization

```php
// toArray() - Chuyển thành array
$array = $user->toArray();

// toJson() - Chuyển thành JSON
$json = $user->toJson();
$json = json_encode($user); // Tự động gọi toJson()

// Hidden fields sẽ không xuất hiện
// Appended fields sẽ được thêm vào
```

### Mass Assignment Protection

```php
// Fillable (whitelist)
protected static array $fillable = ['name', 'email'];
// Chỉ cho phép fill 'name' và 'email'

// Guarded (blacklist)
protected static array $guarded = ['password', 'role'];
// Không cho phép fill 'password' và 'role'

// Guarded = ['*'] - Disable mass assignment hoàn toàn
protected static array $guarded = ['*'];

// Auto-fillable (mặc định)
// Nếu $fillable và $guarded đều rỗng -> cho phép tất cả
```

---

## Relationships (Quan Hệ)

### Định Nghĩa Relationships

#### 1. HasOne (Một-Một)

```php
class UserModel extends Model
{
    public function profile()
    {
        return $this->hasOne(ProfileModel::class);
        // Tự động: profile.user_id = users.id

        // Custom foreign key
        return $this->hasOne(ProfileModel::class, 'user_id');

        // Custom local key
        return $this->hasOne(ProfileModel::class, 'user_id', 'id');
    }
}

// Sử dụng
$user = UserModel::find(1);
$profile = $user->profile; // Lazy load
$profile = $user->profile(); // Relation instance
```

#### 2. HasMany (Một-Nhiều)

```php
class UserModel extends Model
{
    public function posts()
    {
        return $this->hasMany(PostModel::class);
        // Tự động: posts.user_id = users.id
    }
}

// Sử dụng
$user = UserModel::find(1);
$posts = $user->posts; // Collection
$posts = $user->posts()->where('status', 'published')->get();
```

#### 3. BelongsTo (Nhiều-Một)

```php
class PostModel extends Model
{
    public function user()
    {
        return $this->belongsTo(UserModel::class);
        // Tự động: posts.user_id = users.id

        // Custom foreign key
        return $this->belongsTo(UserModel::class, 'user_id');

        // Custom owner key
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }
}

// Sử dụng
$post = PostModel::find(1);
$user = $post->user; // UserModel instance
```

#### 4. BelongsToMany (Nhiều-Nhiều)

```php
class PostModel extends Model
{
    public function tags()
    {
        return $this->belongsToMany(TagModel::class);
        // Tự động: post_tag (pivot table)
        //         post_tag.post_id = posts.id
        //         post_tag.tag_id = tags.id

        // Custom pivot table
        return $this->belongsToMany(TagModel::class, 'post_tags');

        // Custom keys
        return $this->belongsToMany(
            TagModel::class,
            'post_tags',           // pivot table
            'post_id',             // foreign pivot key
            'tag_id',              // related pivot key
            'id',                   // parent key
            'id'                    // related key
        );
    }
}

// Sử dụng
$post = PostModel::find(1);
$tags = $post->tags; // Collection

// Attach/Detach
$post->tags()->attach(1); // Attach tag ID 1
$post->tags()->attach([1, 2, 3]); // Attach multiple
$post->tags()->detach(1); // Detach tag ID 1
$post->tags()->sync([1, 2, 3]); // Sync (detach all, attach these)
```

#### 5. HasOneThrough (Một-Một Qua Bảng Trung Gian)

```php
class CountryModel extends Model
{
    public function phone()
    {
        return $this->hasOneThrough(
            PhoneModel::class,    // Related model
            UserModel::class,      // Through model
            'country_id',           // First key (users.country_id)
            'user_id',              // Second key (phones.user_id)
            'id',                   // Local key (countries.id)
            'id'                    // Second local key (users.id)
        );
    }
}

// Sử dụng
$country = CountryModel::find(1);
$phone = $country->phone; // PhoneModel instance
```

#### 6. HasManyThrough (Một-Nhiều Qua Bảng Trung Gian)

```php
class CountryModel extends Model
{
    public function posts()
    {
        return $this->hasManyThrough(
            PostModel::class,      // Related model
            UserModel::class,       // Through model
            'country_id',           // First key
            'user_id',              // Second key
            'id',                   // Local key
            'id'                    // Second local key
        );
    }
}

// Sử dụng
$country = CountryModel::find(1);
$posts = $country->posts; // Collection
```

#### 7. Polymorphic Relationships

##### MorphOne / MorphMany

```php
class PostModel extends Model
{
    public function image()
    {
        return $this->morphOne(ImageModel::class, 'imageable');
        // Tự động: images.imageable_type = 'PostModel'
        //         images.imageable_id = posts.id
    }

    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }
}
```

##### MorphTo

```php
class CommentModel extends Model
{
    public function commentable()
    {
        return $this->morphTo('commentable');
        // Tự động: comments.commentable_type
        //         comments.commentable_id
    }
}

// Sử dụng
$comment = CommentModel::find(1);
$post = $comment->commentable; // PostModel hoặc VideoModel
```

##### MorphToMany

```php
class PostModel extends Model
{
    public function tags()
    {
        return $this->morphToMany(TagModel::class, 'taggable');
        // Pivot table: taggables
        // taggables.taggable_type = 'PostModel'
        // taggables.taggable_id = posts.id
        // taggables.tag_id = tags.id
    }
}
```

### Sử Dụng Relationships

```php
// Lazy loading
$user = UserModel::find(1);
$posts = $user->posts; // Query được thực thi khi truy cập

// Eager loading
$users = UserModel::with('posts')->get();
$users = UserModel::with(['posts', 'profile'])->get();

// Nested eager loading
$users = UserModel::with('posts.comments')->get();

// Eager loading với constraints
$users = UserModel::with(['posts' => function($query) {
    $query->where('status', 'published');
}])->get();

// Eager loading với column selection
$users = UserModel::with('posts:id,title,user_id')->get();

// Relationship query
$user = UserModel::find(1);
$publishedPosts = $user->posts()->where('status', 'published')->get();
$postCount = $user->posts()->count();
```

---

## Eager Loading

### Cơ Bản

```php
// Single relationship
$users = UserModel::with('posts')->get();

// Multiple relationships
$users = UserModel::with(['posts', 'profile', 'comments'])->get();

// Nested relationships
$users = UserModel::with('posts.comments')->get();
$users = UserModel::with(['posts.comments', 'posts.tags'])->get();
```

### Với Constraints

```php
// Constraint trên relationship
$users = UserModel::with(['posts' => function($query) {
    $query->where('status', 'published')
          ->orderBy('created_at', 'DESC');
}])->get();

// Nested constraints
$users = UserModel::with(['posts' => function($query) {
    $query->where('status', 'published')
          ->with(['comments' => function($q) {
              $q->where('approved', true);
          }]);
}])->get();
```

### Với Column Selection

```php
// Chỉ load các cột cần thiết
$users = UserModel::with('posts:id,title,user_id')->get();
$users = UserModel::with([
    'posts:id,title,user_id',
    'profile:id,user_id,bio'
])->get();
```

### Eager Loading Counts

```php
// withCount() - Đếm số lượng relationship
$users = UserModel::withCount('posts')->get();
// Thêm 'posts_count' vào mỗi user

$users = UserModel::withCount(['posts', 'comments'])->get();
// Thêm 'posts_count' và 'comments_count'

// withCount với constraints
$users = UserModel::withCount(['posts' => function($query) {
    $query->where('status', 'published');
}])->get();
```

### Eager Loading Aggregates

```php
// withSum() - Tổng
$users = UserModel::withSum('orders', 'total')->get();
// Thêm 'orders_sum_total'

// withAvg() - Trung bình
$users = UserModel::withAvg('orders', 'total')->get();
// Thêm 'orders_avg_total'

// withMin() - Tối thiểu
$users = UserModel::withMin('orders', 'total')->get();
// Thêm 'orders_min_total'

// withMax() - Tối đa
$users = UserModel::withMax('orders', 'total')->get();
// Thêm 'orders_max_total'

// Với constraints
$users = UserModel::withSum(['orders' => function($query) {
    $query->where('status', 'completed');
}], 'total')->get();
```

### Lazy Loading Prevention

```php
// Bật prevention (development)
Model::preventLazyLoading(true);

// Tắt (production)
Model::preventLazyLoading(false);

// Kiểm tra
if (Model::preventsLazyLoading()) {
    // Prevention đang bật
}

// Khi bật, truy cập relationship chưa eager load sẽ throw exception
$user = UserModel::find(1);
$posts = $user->posts; // RuntimeException nếu chưa eager load
```

---

## Ví Dụ Sử Dụng

### Ví Dụ 1: Query Phức Tạp

```php
$users = UserModel::query()
    ->select(['id', 'name', 'email'])
    ->where('status', 'active')
    ->where(function($q) {
        $q->where('role', 'admin')
          ->orWhere('role', 'moderator');
    })
    ->whereIn('id', function($q) {
        $q->select('user_id')
          ->from('orders')
          ->where('total', '>', 1000);
    })
    ->with(['profile', 'posts' => function($q) {
        $q->where('status', 'published')
          ->orderBy('created_at', 'DESC')
          ->limit(5);
    }])
    ->withCount('posts')
    ->orderBy('created_at', 'DESC')
    ->paginate(20);
```

### Ví Dụ 2: Bulk Operations

```php
// Bulk insert
$users = [
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
    // ... 1000 users
];
UserModel::query()->insert($users);

// Bulk upsert
UserModel::upsert(
    $users,
    'email', // Unique
    ['name'] // Update on conflict
);
```

### Ví Dụ 3: Chunking Large Dataset

```php
// Xử lý 1 triệu records
UserModel::query()
    ->where('status', 'pending')
    ->chunkById(1000, function($users) {
        foreach ($users as $user) {
            processUser($user);
        }
    });
```

### Ví Dụ 4: Complex Relationships

```php
// Lấy users với posts, comments, và tags
$users = UserModel::with([
    'posts' => function($q) {
        $q->where('status', 'published')
          ->with(['comments', 'tags']);
    },
    'profile'
])->get();

// Lấy posts với author và category
$posts = PostModel::with([
    'user:id,name,email',
    'category:id,name',
    'tags:id,name'
])->get();
```

### Ví Dụ 5: Aggregates và Statistics

```php
// Thống kê user
$stats = UserModel::query()
    ->where('created_at', '>=', now()->subMonth())
    ->aggregates([
        'total_users' => 'COUNT(*)',
        'avg_age' => 'AVG(age)',
        'max_age' => 'MAX(age)',
        'min_age' => 'MIN(age)'
    ]);

// Users với tổng đơn hàng
$users = UserModel::withSum('orders', 'total')
    ->withCount('orders')
    ->having('orders_sum_total', '>', 1000)
    ->get();
```

### Ví Dụ 6: CTE và Window Functions

```php
// Sử dụng CTE để tìm category tree
$categories = CategoryModel::query()
    ->withRecursive('category_tree',
        // Anchor: root categories
        function($q) {
            $q->where('parent_id', null);
        },
        // Recursive: child categories
        function($q) {
            $q->join('category_tree', 'categories.parent_id', '=', 'category_tree.id');
        }
    )
    ->from('category_tree')
    ->get();

// Window function để rank products
$products = ProductModel::query()
    ->select('*')
    ->window('rank_in_category', 'RANK()', ['category_id'], ['price' => 'DESC'])
    ->get();
```

---

## Best Practices

### 1. Performance

```php
// ✅ Tốt: Eager loading
$users = UserModel::with('posts')->get();

// ❌ Xấu: N+1 queries
$users = UserModel::all();
foreach ($users as $user) {
    $posts = $user->posts; // Query mỗi lần
}

// ✅ Tốt: Column selection
$users = UserModel::with('posts:id,title,user_id')->get();

// ✅ Tốt: Chunking cho large dataset
UserModel::query()->chunkById(1000, function($users) {
    // Process
});

// ✅ Tốt: Indexes cho WHERE, JOIN
// Đảm bảo có indexes trên các cột thường dùng trong WHERE và JOIN
```

### 2. Mass Assignment

```php
// ✅ Tốt: Sử dụng fillable/guarded
protected static array $fillable = ['name', 'email'];

// ❌ Xấu: Không có protection
// Có thể bị mass assignment attack

// ✅ Tốt: Explicit assignment cho sensitive fields
$user->password = Hash::make($password);
$user->save();
```

### 3. Relationships

```php
// ✅ Tốt: Eager load khi cần
$users = UserModel::with('posts')->get();

// ✅ Tốt: Constraint trên eager load
$users = UserModel::with(['posts' => function($q) {
    $q->where('status', 'published');
}])->get();

// ❌ Xấu: Lazy load trong loop
foreach ($users as $user) {
    $posts = $user->posts; // N+1 queries
}
```

### 4. Query Building

```php
// ✅ Tốt: Chainable methods
$query = UserModel::query()
    ->where('status', 'active')
    ->where('role', 'admin')
    ->orderBy('created_at', 'DESC');

// ✅ Tốt: Conditional clauses
$query->when($request->has('status'), function($q) use ($request) {
    $q->where('status', $request->get('status'));
});

// ✅ Tốt: Reusable query scopes (nếu có)
$query = UserModel::active()->verified();
```

### 5. Error Handling

```php
// ✅ Tốt: findOrFail() khi chắc chắn phải có
$user = UserModel::findOrFail(1);

// ✅ Tốt: Kiểm tra null
$user = UserModel::find(1);
if ($user) {
    // Process
}

// ✅ Tốt: Try-catch cho database operations
try {
    $user->save();
} catch (\Exception $e) {
    // Handle error
}
```

### 6. Transactions

```php
// ✅ Tốt: Sử dụng transactions cho operations phức tạp
DB::transaction(function() {
    $user = UserModel::create([...]);
    $user->profile()->create([...]);
    $user->posts()->create([...]);
});
```

---

## Kết Luận

Tài liệu này đã bao phủ toàn bộ các tính năng của ORM, Query Builder và Model trong Toporia Framework. Để tìm hiểu thêm:

- Xem code examples trong `src/Framework/Database/`
- Tham khảo tests trong `tests/`
- Đọc các tài liệu khác trong `docs/`

Happy coding! 🚀

