# Rate Limit Code Review & Improvements

## 📊 Đánh Giá Hiện Tại

### ✅ Điểm Tốt

1. **Interface-based Design**: Sử dụng `RateLimiterInterface` - dễ test và mở rộng
2. **Clean Architecture**: Tách biệt rõ ràng giữa Limiter và Middleware
3. **Type Safety**: Sử dụng strict types và type hints đầy đủ
4. **Documentation**: Có comments giải thích behavior

### ⚠️ Vấn Đề Cần Sửa

#### 1. **Logic Bug trong `tooManyAttempts()`**

```php
// Line 58-61: Delete resetTime nếu expired
if ($resetTime !== null && $resetTime < $currentTime) {
    $this->cache->delete($this->attemptsKey($key));
    $this->cache->delete($this->resetTimeKey($key)); // ← resetTime bị delete
}

// Line 72: Nhưng sau đó lại check $resetTime
if ($resetTime === null || $resetTime < $currentTime) { // ← $resetTime đã bị delete, nên sẽ null
    // ...
}
```

**Vấn đề**: `$resetTime` đã bị delete ở trên, nên check ở dưới là không cần thiết.

#### 2. **Hard-coded Default Values**

```php
$defaultDecay = 60; // ← Hard-coded, xuất hiện nhiều lần
```

**Vấn đề**: Nên dùng constant hoặc lấy từ parameter `decaySeconds`.

#### 3. **Logic Phức Tạp & Nested If-Else**

`tooManyAttempts()` có quá nhiều nested conditions, khó đọc và maintain.

#### 4. **Không Có Validation**

Không check:
- `maxAttempts > 0`
- `decaySeconds > 0`
- `key` không rỗng

#### 5. **Performance Issues**

- Check `attempts` trước, sau đó mới check `resetTime` → nên đảo ngược
- Nhiều cache calls không cần thiết

#### 6. **Code Duplication**

- `$defaultDecay = 60` xuất hiện 4 lần
- Logic set resetTime lặp lại nhiều nơi

#### 7. **Inconsistent Behavior**

- `tooManyAttempts()` xóa cache khi expired
- Nhưng `availableIn()` lại set lại resetTime nếu null
- Có thể gây race condition

---

## 🔧 Cải Thiện Đề Xuất

### Priority 1: Fix Logic Bug

### Priority 2: Simplify Logic

### Priority 3: Remove Hard-coded Values

### Priority 4: Add Validation

### Priority 5: Optimize Performance

