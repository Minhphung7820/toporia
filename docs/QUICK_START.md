# 🚀 Quick Start - Relationship Testing System

## ⚡ **FASTEST WAY TO TEST**

### **Step 1: Run Migrations (1 command)**
```bash
docker compose exec app php artisan migrate --path=database/migrations/2025_12_08_*
```

### **Step 2: Test Immediately (no data needed!)**
```bash
# Test endpoint (will return empty arrays but no errors)
curl http://localhost:8000/api/relationships/test-all
```

**Expected Result:**
```json
{
  "success": true,
  "message": "All relationship tests executed successfully",
  "total_tests": 12,
  "total_queries": 20-50,
  "data": {
    "test_1_has_one": { "data": [] },
    "test_2_has_many": { "data": [] },
    ...
  }
}
```

---

## 📊 **WITH SAMPLE DATA (Optional)**

### **Quick Manual Insert:**
```sql
-- Connect to database
docker compose exec mysql mysql -u toporia_user -ptoporia_pass toporia_dev

-- Insert minimal test data
INSERT INTO countries (name, code, continent, population, is_active, created_at, updated_at) VALUES
('USA', 'US', 'North America', 331000000, 1, NOW(), NOW()),
('UK', 'GB', 'Europe', 67000000, 1, NOW(), NOW()),
('Japan', 'JP', 'Asia', 126000000, 1, NOW(), NOW());

INSERT INTO cities (country_id, name, population, is_capital, created_at, updated_at) VALUES
(1, 'New York', 8400000, 0, NOW(), NOW()),
(2, 'London', 9000000, 1, NOW(), NOW()),
(3, 'Tokyo', 14000000, 1, NOW(), NOW());

INSERT INTO authors (city_id, pen_name, bio, books_count, rating, is_verified, created_at, updated_at) VALUES
(1, 'John Doe', 'Famous author', 5, 4.5, 1, NOW(), NOW()),
(2, 'Jane Smith', 'Bestselling writer', 10, 4.8, 1, NOW(), NOW()),
(3, 'Yuki Tanaka', 'Award winner', 8, 4.7, 1, NOW(), NOW());

INSERT INTO publishers (country_id, name, founded_year, is_active, created_at, updated_at) VALUES
(1, 'Penguin Random House', 2013, 1, NOW(), NOW()),
(2, 'HarperCollins', 1989, 1, NOW(), NOW());

INSERT INTO books (author_id, publisher_id, title, isbn, pages_count, price, published_year, rating, is_bestseller, is_available, created_at, updated_at) VALUES
(1, 1, 'The Great Novel', '978-1234567890', 350, 29.99, 2023, 4.6, 1, 1, NOW(), NOW()),
(2, 2, 'Mystery Tales', '978-0987654321', 420, 34.99, 2022, 4.9, 1, 1, NOW(), NOW()),
(3, 1, 'Tokyo Stories', '978-1122334455', 280, 24.99, 2024, 4.5, 0, 1, NOW(), NOW());

INSERT INTO chapters (book_id, chapter_number, title, pages_count, words_count, is_free_preview, created_at, updated_at) VALUES
(1, 1, 'Chapter One', 20, 5000, 1, NOW(), NOW()),
(1, 2, 'Chapter Two', 25, 6000, 0, NOW(), NOW()),
(2, 1, 'Prologue', 15, 3500, 1, NOW(), NOW());

INSERT INTO categories (name, slug, is_active, created_at, updated_at) VALUES
('Fiction', 'fiction', 1, NOW(), NOW()),
('Mystery', 'mystery', 1, NOW(), NOW()),
('Drama', 'drama', 1, NOW(), NOW());

INSERT INTO book_category (book_id, category_id, is_primary, `order`, created_at, updated_at) VALUES
(1, 1, 1, 1, NOW(), NOW()),
(2, 2, 1, 1, NOW(), NOW()),
(3, 1, 1, 1, NOW(), NOW());
```

### **Test Again:**
```bash
curl http://localhost:8000/api/relationships/test-all | jq
```

**Now you'll see actual data!** 🎉

---

## 🎯 **INDIVIDUAL TESTS**

```bash
# Test hasOne
curl http://localhost:8000/api/relationships/has-one

# Test hasMany
curl http://localhost:8000/api/relationships/has-many

# Test hasManyThrough
curl http://localhost:8000/api/relationships/has-many-through

# Test belongsToMany
curl http://localhost:8000/api/relationships/belongs-to-many
```

---

## 📝 **WHAT EACH TEST DOES**

| Test | Relationship | Complexity |
|------|-------------|------------|
| 1 | hasOne | Simple |
| 2 | hasMany | With count |
| 3 | belongsTo | Nested 2 levels |
| 4 | belongsToMany | With pivot |
| 5 | hasOneThrough | 2 tables |
| 6 | hasManyThrough | 2 tables |
| 7 | hasManyThrough | Complex constraints |
| 8 | **ULTRA NESTED** | **4 levels deep** |
| 9 | Double belongsToMany | User→Role→Permission |
| 10 | hasManyThrough | With constraints on both |
| 11 | **ULTRA COMPLEX** | **ALL types + 5 levels** |
| 12 | Conditional Loading | Dynamic relationships |

---

## 🔍 **VERIFY TABLES CREATED**

```bash
docker compose exec mysql mysql -u toporia_user -ptoporia_pass toporia_dev -e "SHOW TABLES LIKE '%2025_12_08%';"
```

Should show:
- countries
- cities
- authors
- publishers
- books
- chapters
- pages
- roles
- permissions
- role_user
- permission_role
- book_category

---

## 🐛 **TROUBLESHOOTING**

### **Error: Table doesn't exist**
```bash
# Run migrations again
docker compose exec app php artisan migrate --path=database/migrations/2025_12_08_*
```

### **Error: Class not found**
```bash
# Restart app container
docker compose restart app
sleep 5
```

### **Empty results but no errors**
✅ **This is NORMAL!** Just means no data yet. Insert sample data above.

---

## 📚 **FULL DOCUMENTATION**

- Complete Guide: `RELATIONSHIP_TESTING_GUIDE.md`
- Implementation Details: `IMPLEMENTATION_SUMMARY.md`
- Controller Code: `src/App/Presentation/Http/Controllers/RelationshipTestController.php`

---

## ✅ **SUCCESS CHECKLIST**

- [ ] Migrations ran successfully
- [ ] Can access `/api/relationships/test-all` without errors
- [ ] Inserted sample data (optional)
- [ ] All 12 tests return data
- [ ] Query log shows optimized queries (20-50 queries, not 1000+)

---

**Ready to test! 🚀**

