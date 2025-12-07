# Toporia Framework - Database ORM Documentation

## Mục lục

1. [Tổng quan](#tổng-quan)
2. [Query Builder](#query-builder)
3. [Model](#model)
4. [Relationships](#relationships)
5. [Eager Loading](#eager-loading)
6. [Advanced Features](#advanced-features)
7. [Best Practices](#best-practices)

---

## Tổng quan

Toporia Framework cung cấp một hệ thống ORM (Object-Relational Mapping) mạnh mẽ với Active Record pattern, hỗ trợ:

- **Query Builder**: Fluent interface để xây dựng SQL queries
- **Model**: Active Record pattern với relationships, scopes, events
- **Relationships**: Hỗ trợ đầy đủ các loại relationships (HasOne, HasMany, BelongsTo, BelongsToMany, MorphOne, MorphMany, MorphTo, MorphToMany)
- **Eager Loading**: Tối ưu hóa queries với batch loading và nested relationships
- **Advanced Features**: Soft deletes, optimistic locking, observers, caching

---

## Query Builder

Query Builder cung cấp một fluent interface để xây dựng SQL queries một cách an toàn với parameter binding tự động.

### Khởi tạo

```php
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\DatabaseManager;

// Từ connection
$db = DatabaseManager::connection();
$query = new QueryBuilder($db);

// Hoặc từ Model
$query = UserModel::query();
```

### SELECT Queries

#### Basic Select

```php
// Select tất cả columns
$users = $query->table('users')->get();

// Select specific columns
$users = $query->table('users')
    ->select('id', 'name', 'email')
    ->get();

// Select với alias
$users = $query->table('users')
    ->select('id', 'name as full_name', 'email')
    ->get();

// Select raw expressions
$users = $query->table('users')
    ->selectRaw('COUNT(*) as total')
    ->get();
```

#### WHERE Clauses

```php
// Basic WHERE
$users = $query->table('users')
    ->where('status', 'active')
    ->get();

// WHERE với operators
$users = $query->table('users')
    ->where('age', '>', 18)
    ->where('status', '!=', 'banned')
    ->get();

// WHERE IN
$users = $query->table('users')
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->get();

// WHERE NOT IN
$users = $query->table('users')
    ->whereNotIn('status', ['banned', 'deleted'])
    ->get();

// WHERE NULL
$users = $query->table('users')
    ->whereNull('deleted_at')
    ->get();

// WHERE NOT NULL
$users = $query->table('users')
    ->whereNotNull('email_verified_at')
    ->get();

// WHERE BETWEEN
$users = $query->table('users')
    ->whereBetween('age', [18, 65])
    ->get();

// WHERE LIKE
$users = $query->table('users')
    ->where('name', 'LIKE', '%john%')
    ->get();

// Multiple WHERE với AND/OR
$users = $query->table('users')
    ->where('status', 'active')
    ->where(function ($q) {
        $q->where('age', '>', 18)
          ->orWhere('is_vip', true);
    })
    ->get();
```

#### Advanced WHERE Clauses

```php
// WHERE EXISTS
$users = $query->table('users')
    ->whereExists(function ($q) {
        $q->table('orders')
          ->whereColumn('orders.user_id', 'users.id');
    })
    ->get();

// WHERE DATE
$users = $query->table('users')
    ->whereDate('created_at', '2024-01-01')
    ->get();

// WHERE YEAR/MONTH/DAY
$users = $query->table('users')
    ->whereYear('created_at', 2024)
    ->whereMonth('created_at', 1)
    ->get();

// WHERE TIME
$users = $query->table('users')
    ->whereTime('created_at', '>', '09:00:00')
    ->get();

// WHERE JSON
$users = $query->table('users')
    ->whereJsonContains('metadata->tags', 'premium')
    ->get();
```

#### JOINs

```php
// INNER JOIN
$users = $query->table('users')
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->select('users.*', 'profiles.bio', 'profiles.avatar')
    ->get();

// LEFT JOIN
$users = $query->table('users')
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();

// RIGHT JOIN
$users = $query->table('users')
    ->rightJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();

// Multiple JOINs
$orders = $query->table('orders')
    ->join('users', 'orders.user_id', '=', 'users.id')
    ->join('products', 'orders.product_id', '=', 'products.id')
    ->select('orders.*', 'users.name', 'products.title')
    ->get();

// JOIN với conditions
$users = $query->table('users')
    ->join('profiles', function ($join) {
        $join->on('users.id', '=', 'profiles.user_id')
             ->where('profiles.is_active', true);
    })
    ->get();
```

#### ORDER BY

```php
// Single column
$users = $query->table('users')
    ->orderBy('created_at', 'DESC')
    ->get();

// Multiple columns
$users = $query->table('users')
    ->orderBy('status', 'ASC')
    ->orderBy('created_at', 'DESC')
    ->get();

// ORDER BY raw
$users = $query->table('users')
    ->orderByRaw('FIELD(status, "active", "pending", "inactive")')
    ->get();
```

#### GROUP BY & HAVING

```php
// GROUP BY
$stats = $query->table('orders')
    ->select('user_id', 'SUM(amount) as total')
    ->groupBy('user_id')
    ->get();

// HAVING
$stats = $query->table('orders')
    ->select('user_id', 'SUM(amount) as total')
    ->groupBy('user_id')
    ->having('total', '>', 1000)
    ->get();
```

#### LIMIT & OFFSET

```php
// LIMIT
$users = $query->table('users')
    ->limit(10)
    ->get();

// OFFSET
$users = $query->table('users')
    ->offset(10)
    ->limit(10)
    ->get();

// Pagination helper
$users = $query->table('users')
    ->limit(10)
    ->offset(($page - 1) * 10)
    ->get();
```

#### Aggregates

```php
// COUNT
$count = $query->table('users')->count();

// SUM
$total = $query->table('orders')->sum('amount');

// AVG
$average = $query->table('orders')->avg('amount');

// MIN/MAX
$min = $query->table('orders')->min('amount');
$max = $query->table('orders')->max('amount');

// COUNT với conditions
$count = $query->table('users')
    ->where('status', 'active')
    ->count();
```

#### DISTINCT

```php
$uniqueEmails = $query->table('users')
    ->distinct()
    ->select('email')
    ->get();
```

### INSERT Queries

```php
// Single insert
$id = $query->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active'
]);

// Multiple inserts
$query->table('users')->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
]);

// Insert và lấy ID
$id = $query->table('users')->insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

### UPDATE Queries

```php
// Update với WHERE
$affected = $query->table('users')
    ->where('id', 1)
    ->update(['status' => 'inactive']);

// Update multiple rows
$affected = $query->table('users')
    ->whereIn('id', [1, 2, 3])
    ->update(['status' => 'active']);

// Increment/Decrement
$query->table('users')
    ->where('id', 1)
    ->increment('login_count');

$query->table('users')
    ->where('id', 1)
    ->increment('points', 10);

$query->table('users')
    ->where('id', 1)
    ->decrement('points', 5);
```

### DELETE Queries

```php
// Delete với WHERE
$deleted = $query->table('users')
    ->where('status', 'banned')
    ->delete();

// Delete all (cẩn thận!)
$deleted = $query->table('temp_table')->delete();
```

### Subqueries

```php
// Subquery trong SELECT
$users = $query->table('users')
    ->select('*')
    ->selectSub(function ($q) {
        $q->table('orders')
          ->selectRaw('COUNT(*)')
          ->whereColumn('orders.user_id', 'users.id');
    }, 'order_count')
    ->get();

// Subquery trong WHERE
$users = $query->table('users')
    ->whereIn('id', function ($q) {
        $q->table('orders')
          ->select('user_id')
          ->where('status', 'completed');
    })
    ->get();
```

### Raw Queries

```php
// Raw SELECT
$users = $query->table('users')
    ->selectRaw('*, (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) as order_count')
    ->get();

// Raw WHERE
$users = $query->table('users')
    ->whereRaw('DATE(created_at) = ?', ['2024-01-01'])
    ->get();
```

### Chunking

```php
// Process records in chunks
$query->table('users')->chunk(100, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});

// Chunk by ID (more efficient)
$query->table('users')->chunkById(100, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});
```

### Transactions

```php
use Toporia\Framework\Database\DatabaseManager;

DatabaseManager::connection()->transaction(function () {
    // Multiple queries
    $query->table('users')->insert([...]);
    $query->table('profiles')->insert([...]);
});
```

---

## Model

Model là lớp cơ bản cho Active Record pattern, cung cấp các tính năng ORM mạnh mẽ.

### Định nghĩa Model

```php
use Toporia\Framework\Database\ORM\Model;

class UserModel extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected static array $fillable = [
        'name',
        'email',
        'password',
    ];

    protected static array $guarded = [];

    protected static array $casts = [
        'is_active' => 'bool',
        'metadata' => 'json',
        'created_at' => 'datetime',
    ];

    protected static array $hidden = ['password', 'remember_token'];
}
```

### CRUD Operations

#### Create

```php
// Create single record
$user = UserModel::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password'),
]);

// Create với mass assignment protection
$user = new UserModel();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// First or create
$user = UserModel::firstOrCreate(
    ['email' => 'john@example.com'],
    ['name' => 'John Doe', 'status' => 'active']
);

// Update or create
$user = UserModel::updateOrCreate(
    ['email' => 'john@example.com'],
    ['name' => 'John Doe Updated']
);
```

#### Read

```php
// Find by ID
$user = UserModel::find(1);

// Find or fail (throws exception)
$user = UserModel::findOrFail(1);

// Find multiple
$users = UserModel::find([1, 2, 3]);

// Get all
$users = UserModel::all();

// First
$user = UserModel::where('status', 'active')->first();

// First or fail
$user = UserModel::where('email', 'john@example.com')->firstOrFail();
```

#### Update

```php
// Update single model
$user = UserModel::find(1);
$user->name = 'Jane Doe';
$user->save();

// Mass update
UserModel::where('status', 'pending')
    ->update(['status' => 'active']);

// Update or create
$user = UserModel::updateOrCreate(
    ['email' => 'john@example.com'],
    ['name' => 'John Updated']
);
```

#### Delete

```php
// Delete single model
$user = UserModel::find(1);
$user->delete();

// Delete by ID
UserModel::destroy(1);
UserModel::destroy([1, 2, 3]);

// Delete với query
UserModel::where('status', 'banned')->delete();
```

### Query Scopes

```php
class UserModel extends Model
{
    // Local scope
    public static function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope với parameters
    public static function scopeOlderThan($query, $age)
    {
        return $query->where('age', '>', $age);
    }

    // Global scope
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('active', function ($query) {
            $query->where('status', 'active');
        });
    }
}

// Sử dụng scopes
$activeUsers = UserModel::active()->get();
$seniors = UserModel::olderThan(65)->get();
```

### Accessors & Mutators

```php
class UserModel extends Model
{
    // Accessor
    public function getFullNameAttribute($value)
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Mutator
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }

    // Accessor với attribute không tồn tại
    public function getIsVipAttribute()
    {
        return $this->points > 1000;
    }
}

// Sử dụng
$user = UserModel::find(1);
echo $user->full_name; // Accessor
$user->password = 'newpassword'; // Mutator
if ($user->is_vip) { // Computed attribute
    // ...
}
```

### Attributes & Casting

```php
class UserModel extends Model
{
    protected static array $casts = [
        'is_active' => 'bool',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'price' => 'float',
        'tags' => 'array',
    ];
}

// Sử dụng
$user = UserModel::find(1);
$user->is_active; // boolean
$user->metadata; // array (tự động decode JSON)
$user->created_at; // Carbon instance
```

### Mass Assignment Protection

```php
class UserModel extends Model
{
    // Whitelist approach
    protected static array $fillable = [
        'name',
        'email',
    ];

    // Blacklist approach
    protected static array $guarded = [
        'id',
        'is_admin',
    ];

    // Disable mass assignment
    protected static array $guarded = ['*'];
}

// Mass assignment
$user = UserModel::create([
    'name' => 'John', // OK - trong fillable
    'email' => 'john@example.com', // OK - trong fillable
    'is_admin' => true, // IGNORED - không trong fillable
]);
```

### Timestamps

```php
class UserModel extends Model
{
    protected static bool $timestamps = true;

    // Custom timestamp columns
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    // Disable timestamps
    protected static bool $timestamps = false;
}

// Manual timestamps
$user = new UserModel();
$user->created_at = now();
$user->updated_at = now();
$user->save();
```

### Serialization

```php
class UserModel extends Model
{
    protected static array $hidden = ['password', 'api_token'];
    protected static array $visible = ['id', 'name', 'email']; // Override hidden
}

$user = UserModel::find(1);

// To array
$array = $user->toArray();

// To JSON
$json = $user->toJson();
$json = json_encode($user);

// Make visible/hidden
$user->makeVisible('password');
$user->makeHidden('email');
```

---

## Relationships

Toporia Framework hỗ trợ đầy đủ các loại relationships với eager loading và constraints.

### HasOne

```php
class UserModel extends Model
{
    public function profile()
    {
        return $this->hasOne(ProfileModel::class, 'user_id');
    }
}

// Sử dụng
$user = UserModel::find(1);
$profile = $user->profile;
```

### HasMany

```php
class UserModel extends Model
{
    public function posts()
    {
        return $this->hasMany(PostModel::class, 'user_id');
    }
}

// Sử dụng
$user = UserModel::find(1);
$posts = $user->posts;
```

### BelongsTo

```php
class PostModel extends Model
{
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

// Sử dụng
$post = PostModel::find(1);
$user = $post->user;
```

### BelongsToMany

```php
class UserModel extends Model
{
    public function roles()
    {
        return $this->belongsToMany(RoleModel::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot('created_at')
            ->withTimestamps();
    }
}

// Sử dụng
$user = UserModel::find(1);
$roles = $user->roles;

// Attach
$user->roles()->attach(1);
$user->roles()->attach([1, 2, 3]);
$user->roles()->attach(1, ['expires_at' => now()->addYear()]);

// Detach
$user->roles()->detach(1);
$user->roles()->detach([1, 2]);

// Sync
$user->roles()->sync([1, 2, 3]);

// Toggle
$user->roles()->toggle([1, 2]);
```

### MorphOne / MorphMany

```php
class PostModel extends Model
{
    public function image()
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }

    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }
}

class VideoModel extends Model
{
    public function image()
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }
}

class ImageModel extends Model
{
    public function imageable()
    {
        return $this->morphTo();
    }
}

// Sử dụng
$post = PostModel::find(1);
$image = $post->image;

$video = VideoModel::find(1);
$image = $video->image;

$image = ImageModel::find(1);
$imageable = $image->imageable; // PostModel hoặc VideoModel
```

### MorphToMany

```php
class PostModel extends Model
{
    public function tags()
    {
        return $this->morphToMany(TagModel::class, 'taggable', 'taggables')
            ->withTimestamps();
    }
}

class VideoModel extends Model
{
    public function tags()
    {
        return $this->morphToMany(TagModel::class, 'taggable', 'taggables')
            ->withTimestamps();
    }
}

// Sử dụng
$post = PostModel::find(1);
$tags = $post->tags;
```

### Relationship Constraints

```php
// HasMany với constraints
$user = UserModel::find(1);
$publishedPosts = $user->posts()
    ->where('is_published', true)
    ->orderBy('created_at', 'DESC')
    ->get();

// BelongsToMany với pivot constraints
$user = UserModel::find(1);
$activeRoles = $user->roles()
    ->wherePivot('is_active', true)
    ->get();
```

---

## Eager Loading

Eager loading giúp tối ưu hóa queries bằng cách load relationships trong batch thay vì N+1 queries.

### Basic Eager Loading

```php
// Load single relationship
$users = UserModel::with('profile')->get();

// Load multiple relationships
$users = UserModel::with(['profile', 'posts'])->get();

// Nested relationships
$users = UserModel::with('posts.comments')->get();
```

### Eager Loading với Constraints

```php
// Constraint trên relationship
$users = UserModel::with(['posts' => function ($query) {
    $query->where('is_published', true)
          ->orderBy('created_at', 'DESC');
}])->get();

// Multiple constraints
$users = UserModel::with([
    'posts' => function ($query) {
        $query->where('is_published', true);
    },
    'profile' => function ($query) {
        $query->select('id', 'user_id', 'bio');
    },
])->get();

// Nested constraints
$users = UserModel::with([
    'posts' => function ($query) {
        $query->where('is_published', true);
    },
    'posts.comments' => function ($query) {
        $query->where('is_approved', true);
    },
])->get();
```

### Eager Loading với Select

```php
// Select specific columns
$users = UserModel::with(['profile' => function ($query) {
    $query->select('id', 'user_id', 'bio', 'avatar');
}])->get();

// MorphToMany với select
$videos = VideoModel::with(['tags' => function ($query) {
    $query->select('id', 'name', 'slug')
          ->orderBy('name', 'ASC');
}])->get();

// MorphOne với select và orderBy
$videos = VideoModel::with(['image' => function ($query) {
    $query->select('id', 'url', 'width', 'height', 'size')
          ->orderBy('size', 'DESC');
}])->get();
```

### Lazy Eager Loading

```php
// Load sau khi model đã được retrieve
$user = UserModel::find(1);
$user->load('posts');

// Load missing relationships
$user = UserModel::with('profile')->find(1);
$user->loadMissing('posts'); // Chỉ load nếu chưa load

// Load với constraints
$user->load(['posts' => function ($query) {
    $query->where('is_published', true);
}]);
```

### Eager Loading Count

```php
// Load count của relationships
$users = UserModel::withCount('posts')->get();
// $user->posts_count

// Multiple counts
$users = UserModel::withCount(['posts', 'comments'])->get();

// Count với constraints
$users = UserModel::withCount(['posts' => function ($query) {
    $query->where('is_published', true);
}])->get();
```

### Preventing N+1 Queries

```php
// ❌ BAD: N+1 queries
$users = UserModel::all();
foreach ($users as $user) {
    echo $user->profile->bio; // Query cho mỗi user
}

// ✅ GOOD: Eager loading
$users = UserModel::with('profile')->get();
foreach ($users as $user) {
    echo $user->profile->bio; // Không có thêm queries
}
```

---

## Advanced Features

### Soft Deletes

```php
use Toporia\Framework\Database\ORM\Concerns\SoftDeletes;

class UserModel extends Model
{
    use SoftDeletes;

    protected static string $deletedAtColumn = 'deleted_at';
}

// Soft delete
$user = UserModel::find(1);
$user->delete(); // Sets deleted_at, không xóa thật

// Restore
$user->restore();

// Force delete
$user->forceDelete();

// Query với trashed
$users = UserModel::withTrashed()->get();
$users = UserModel::onlyTrashed()->get();
```

### Optimistic Locking

```php
use Toporia\Framework\Database\ORM\Concerns\OptimisticLocking;

class PostModel extends Model
{
    use OptimisticLocking;

    protected static string $lockColumn = 'version';
}

// Update với version check
$post = PostModel::find(1);
$post->title = 'New Title';
$post->save(); // Tự động tăng version và check
```

### Observers

```php
class UserObserver
{
    public function creating(UserModel $user)
    {
        // Before create
    }

    public function created(UserModel $user)
    {
        // After create
    }

    public function updating(UserModel $user)
    {
        // Before update
    }

    public function updated(UserModel $user)
    {
        // After update
    }

    public function deleting(UserModel $user)
    {
        // Before delete
    }

    public function deleted(UserModel $user)
    {
        // After delete
    }
}

// Register observer
UserModel::observe(UserObserver::class);
```

### Model Events

```php
class UserModel extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // Before create
        });

        static::created(function ($user) {
            // After create
        });
    }
}
```

### Query Scopes

```php
// Global scope
class UserModel extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('active', function ($query) {
            $query->where('status', 'active');
        });
    }
}

// Remove global scope
UserModel::withoutGlobalScope('active')->get();
UserModel::withoutGlobalScopes()->get();
```

### Model Factories

```php
use Toporia\Framework\Database\ORM\Concerns\HasFactory;

class UserModel extends Model
{
    use HasFactory;
}

// Sử dụng factory
$user = UserModel::factory()->create();
$users = UserModel::factory()->count(10)->create();

// Factory với attributes
$user = UserModel::factory()->create([
    'name' => 'John Doe',
]);
```

### Caching

```php
// Cache query results
$users = UserModel::remember(60)->get(); // Cache 60 seconds

// Cache forever
$users = UserModel::rememberForever()->get();

// Cache với custom key
$users = UserModel::remember(60, 'active_users')->get();
```

---

## Best Practices

### 1. Luôn sử dụng Eager Loading

```php
// ❌ BAD
$users = UserModel::all();
foreach ($users as $user) {
    $posts = $user->posts; // N+1 queries
}

// ✅ GOOD
$users = UserModel::with('posts')->get();
foreach ($users as $user) {
    $posts = $user->posts; // No additional queries
}
```

### 2. Sử dụng Select để giảm memory

```php
// ✅ GOOD: Chỉ select columns cần thiết
$users = UserModel::select('id', 'name', 'email')->get();

// ✅ GOOD: Eager loading với select
$users = UserModel::with(['profile' => function ($q) {
    $q->select('id', 'user_id', 'bio');
}])->get();
```

### 3. Sử dụng Chunking cho large datasets

```php
// ✅ GOOD: Process in chunks
UserModel::chunk(100, function ($users) {
    foreach ($users as $user) {
        // Process
    }
});
```

### 4. Mass Assignment Protection

```php
// ✅ GOOD: Luôn define fillable hoặc guarded
class UserModel extends Model
{
    protected static array $fillable = ['name', 'email'];
}
```

### 5. Sử dụng Scopes

```php
// ✅ GOOD: Reusable scopes
$activeUsers = UserModel::active()->get();
$seniors = UserModel::olderThan(65)->get();
```

### 6. Relationship Constraints

```php
// ✅ GOOD: Constraint trong relationship method
public function publishedPosts()
{
    return $this->hasMany(PostModel::class, 'user_id')
        ->where('is_published', true);
}
```

### 7. Indexes cho Foreign Keys

```php
// ✅ GOOD: Đảm bảo foreign keys có indexes
Schema::table('posts', function ($table) {
    $table->index('user_id');
});
```

### 8. Transactions cho multiple operations

```php
// ✅ GOOD: Use transactions
DB::transaction(function () {
    $user = UserModel::create([...]);
    $profile = ProfileModel::create([...]);
});
```

---

## Troubleshooting

### N+1 Query Problem

**Vấn đề**: Nhiều queries không cần thiết khi access relationships.

**Giải pháp**: Sử dụng eager loading với `with()`.

```php
// ❌ N+1 queries
$users = UserModel::all();
foreach ($users as $user) {
    echo $user->profile->bio;
}

// ✅ Fixed
$users = UserModel::with('profile')->get();
foreach ($users as $user) {
    echo $user->profile->bio;
}
```

### Eager Loading Constraints không work

**Vấn đề**: Constraints trong `with()` không được áp dụng.

**Giải pháp**: Đảm bảo constraints được áp dụng sau `addEagerConstraints()`.

```php
// ✅ Correct
$users = UserModel::with(['posts' => function ($query) {
    $query->where('is_published', true)
          ->orderBy('created_at', 'DESC');
}])->get();
```

### Mass Assignment không work

**Vấn đề**: Attributes không được fill khi dùng `create()`.

**Giải pháp**: Thêm attributes vào `$fillable` hoặc remove khỏi `$guarded`.

```php
class UserModel extends Model
{
    protected static array $fillable = ['name', 'email'];
}
```

---

## API Reference

### Model Methods

- `find($id)` - Find by ID
- `findOrFail($id)` - Find or throw exception
- `create($attributes)` - Create new record
- `update($attributes)` - Update record
- `delete()` - Delete record
- `save()` - Save changes
- `fresh()` - Reload from database
- `refresh()` - Reload and sync attributes
- `replicate()` - Clone model
- `toArray()` - Convert to array
- `toJson()` - Convert to JSON

### Query Builder Methods

- `where($column, $operator, $value)` - Add WHERE clause
- `whereIn($column, $values)` - WHERE IN
- `whereNull($column)` - WHERE NULL
- `join($table, $first, $operator, $second)` - JOIN
- `orderBy($column, $direction)` - ORDER BY
- `groupBy($column)` - GROUP BY
- `having($column, $operator, $value)` - HAVING
- `limit($value)` - LIMIT
- `offset($value)` - OFFSET
- `select($columns)` - SELECT columns
- `get()` - Execute SELECT
- `first()` - Get first result
- `count()` - Get count
- `sum($column)` - Get sum
- `avg($column)` - Get average
- `min($column)` - Get minimum
- `max($column)` - Get maximum

---

## Examples

Xem thêm examples trong:
- `src/App/Infrastructure/Persistence/Models/` - Model examples
- `src/App/Presentation/Http/Controllers/` - Usage examples

---

**Version**: 1.0.0
**Last Updated**: 2025-01-10
**Author**: Toporia Framework Team

