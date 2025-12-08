# 🧪 Comprehensive Relationship Testing System

## 📚 **Overview**

Hệ thống test SIÊU PHỨC TẠP cho tất cả Eloquent relationship types trong Toporia ORM.

---

## 🗂️ **Database Schema**

### **Tables Created:**
1. **countries** - Quốc gia
2. **cities** - Thành phố (belongs to country)
3. **authors** - Tác giả (belongs to city, user)
4. **publishers** - Nhà xuất bản (belongs to country)
5. **books** - Sách (belongs to author, publisher)
6. **chapters** - Chương sách (belongs to book)
7. **pages** - Trang sách (belongs to chapter)
8. **roles** - Vai trò
9. **permissions** - Quyền hạn
10. **role_user** - Pivot table (user-role)
11. **permission_role** - Pivot table (role-permission)
12. **book_category** - Pivot table (book-category)

### **Relationship Graph:**
```
Country (1) -----> (N) Cities
   |                      |
   |                      v
   |                 (N) Authors (1) -----> (1) User
   |                      |
   v                      v
(N) Publishers      (N) Books (M) -----> (N) Categories
                         |
                         v
                    (N) Chapters
                         |
                         v
                    (N) Pages

User (M) <-----> (N) Roles (M) <-----> (N) Permissions
```

---

## 🔗 **Relationships Tested**

### **1. hasOne**
- `CityModel::topAuthor()` - City has one top author
- `AuthorModel::latestBook()` - Author's latest book
- `AuthorModel::bestsellerBook()` - Author's bestselling book
- `ChapterModel::firstPage()` - Chapter's first page

### **2. hasMany**
- `CountryModel::cities()` - Country has many cities
- `CountryModel::publishers()` - Country has many publishers
- `CityModel::authors()` - City has many authors
- `AuthorModel::books()` - Author has many books
- `BookModel::chapters()` - Book has many chapters
- `ChapterModel::pages()` - Chapter has many pages

### **3. belongsTo**
- `CityModel::country()` - City belongs to country
- `AuthorModel::city()` - Author belongs to city
- `AuthorModel::user()` - Author belongs to user
- `BookModel::author()` - Book belongs to author
- `BookModel::publisher()` - Book belongs to publisher
- `ChapterModel::book()` - Chapter belongs to book
- `PageModel::chapter()` - Page belongs to chapter

### **4. belongsToMany**
- `BookModel::categories()` - Book belongs to many categories (with pivot: is_primary, order)
- `UserModel::roles()` - User belongs to many roles (with pivot: assigned_at, expires_at, is_active)
- `RoleModel::permissions()` - Role has many permissions

### **5. hasOneThrough**
- `AuthorModel::country()` - Author -> City -> Country

### **6. hasManyThrough**
- `CountryModel::authors()` - Country -> Cities -> Authors
- `CityModel::books()` - City -> Authors -> Books
- `BookModel::pages()` - Book -> Chapters -> Pages

---

## 🚀 **API Endpoints**

### **Main Test Endpoint**
```
GET /api/relationships/test-all
```
**Parameters:**
- `load_publisher` (optional): true/false - Conditional loading
- `min_rating` (optional): float - Minimum book rating

**Tests executed (12 total):**
1. ✅ hasOne: City with Top Author
2. ✅ hasMany: Countries with Cities
3. ✅ belongsTo: Authors with City and Country
4. ✅ belongsToMany: Books with Categories
5. ✅ hasOneThrough: Authors with Country through City
6. ✅ hasManyThrough (Simple): City Books through Authors
7. ✅ hasManyThrough (Complex): Country Authors through Cities
8. ✅ Ultra Nested (4 levels): Book -> Author -> City -> Country
9. ✅ Double BelongsToMany: Users with Roles and Permissions
10. ✅ hasManyThrough with Constraints: Book Pages through Chapters
11. ✅ ULTRA COMPLEX: All relationship types combined
12. ✅ Conditional Eager Loading

### **Individual Test Endpoints**
```
GET /api/relationships/has-one
GET /api/relationships/has-many
GET /api/relationships/has-many-through
GET /api/relationships/belongs-to-many
```

---

## 🎯 **Complex Features Tested**

### **1. Nested Eager Loading (4+ levels)**
```php
BookModel::with([
    'author.city.country',
    'publisher',
    'categories',
    'chapters.pages'
])
```

### **2. whereHas with Multiple Levels**
```php
CountryModel::whereHas('cities.authors.books', function($q) {
    $q->where('rating', '>=', 4.5)
      ->where('reviews_count', '>', 100);
})
```

### **3. withCount with Aggregates**
```php
AuthorModel::withCount('books')
    ->withAvg('books', 'rating')
    ->withSum('books', 'stock')
    ->having('books_count', '>=', 3)
    ->having('books_avg_rating', '>=', 4.0)
```

### **4. Pivot Constraints**
```php
BookModel::with(['categories' => function($q) {
    $q->wherePivot('is_primary', true)
      ->orderByPivot('order', 'ASC');
}])
```

### **5. Conditional Eager Loading**
```php
$query = BookModel::with(['author.city.country']);
if ($loadPublisher) {
    $query->with('publisher');
}
```

### **6. Mixed Constraints on Multiple Relationships**
```php
CountryModel::with([
    'cities' => fn($q) => $q->where('is_capital', true),
    'cities.authors' => fn($q) => $q->where('is_verified', true),
    'cities.authors.books' => fn($q) => $q->where('stock', '>', 0),
    'cities.authors.books.categories' => fn($q) => $q->wherePivot('is_primary', true),
])
```

---

## 📋 **Setup Instructions**

### **Step 1: Run Migrations**
```bash
php artisan migrate --path=database/migrations/2025_12_08_*
```

### **Step 2: Seed Data (Optional)**
```php
// Create seeders or use Faker to populate:
// - 50 countries
// - 500 cities
// - 1000 authors
// - 5000 books
// - 20000 chapters
// - 10 roles
// - 100 permissions
```

### **Step 3: Add Routes**
In `routes/api.php`:
```php
use App\Presentation\Http\Controllers\RelationshipTestController;

$router->get('/relationships/test-all', [RelationshipTestController::class, 'testAllRelationships']);
$router->get('/relationships/has-one', [RelationshipTestController::class, 'testHasOne']);
$router->get('/relationships/has-many', [RelationshipTestController::class, 'testHasMany']);
$router->get('/relationships/has-many-through', [RelationshipTestController::class, 'testHasManyThrough']);
$router->get('/relationships/belongs-to-many', [RelationshipTestController::class, 'testBelongsToMany']);
```

### **Step 4: Test**
```bash
curl http://localhost:8000/api/relationships/test-all
```

---

## 🎪 **Test Case Highlights**

### **Test 8: Ultra Nested (4 Levels)**
The most complex query with 4-level nested eager loading and constraints at EACH level:

```php
BookModel::with([
    'author' => fn($q) => $q->where('is_verified', true),
    'author.city' => fn($q) => $q->where('population', '>', 500000),
    'author.city.country' => fn($q) => $q->where('is_active', true),
    'chapters' => fn($q) => $q->orderBy('chapter_number', 'ASC')->limit(3)
])
->whereHas('author.city.country', function($q) {
    $q->where('continent', 'Europe')
      ->where('population', '>', 10000000);
})
```

### **Test 11: ULTRA COMPLEX Mixed Relationships**
Combines ALL relationship types in ONE query:
- hasMany
- belongsTo
- hasManyThrough (multiple levels)
- belongsToMany (with pivot constraints)
- Nested constraints on 5+ levels
- Multiple withCount aggregates
- Multiple HAVING clauses

---

## 📊 **Performance Metrics**

Expected results:
- **Total queries**: 20-50 (with eager loading optimization)
- **Without eager loading**: 1000+ queries (N+1 problem)
- **Average query time**: 5-50ms per query
- **Total execution time**: 200-500ms

---

## 🐛 **Known Issues to Test**

1. ✅ Binding order in HAVING + WHERE
2. ✅ hasManyThrough with constraints on intermediate table
3. ✅ belongsToMany pivot constraints
4. ✅ Nested whereHas with multiple levels
5. ✅ withCount with having clauses
6. ✅ Conditional eager loading

---

## 🎓 **Learning Outcomes**

This test suite covers:
- ✅ All 6 basic Eloquent relationship types
- ✅ Polymorphic relationships (already covered in ProductController)
- ✅ Nested eager loading (up to 5 levels)
- ✅ Complex whereHas queries
- ✅ Aggregate functions (count, avg, sum)
- ✅ Pivot table constraints
- ✅ Conditional loading
- ✅ Query optimization techniques
- ✅ N+1 problem prevention

---

## 📝 **Notes**

- All models are in `App\Infrastructure\Persistence\Models\`
- All migrations are in `database/migrations/2025_12_08_*`
- Controller is `App\Presentation\Http\Controllers\RelationshipTestController`
- This system tests REAL production-level complexity
- Can be used as reference for actual application development

---

## 🚀 **Next Steps**

1. Run migrations
2. Create seeders (optional)
3. Add routes
4. Test endpoints
5. Monitor query log for optimization opportunities
6. Use as reference for building actual features

**Happy Testing! 🎉**

