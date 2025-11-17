# Performance Analysis: Eager vs Lazy Config Loading

## Tổng quan
- **Số lượng config files**: 17 files
- **Trước (Eager Loading)**: Load tất cả 17 files ngay trong bootstrap
- **Sau (Lazy Loading)**: Chỉ load file khi được access

## So sánh chi tiết

### 1. Bootstrap Time (Thời gian khởi động)

#### TRƯỚC (Eager Loading):
```
Bootstrap Time = Load 17 config files
- Mỗi file: ~0.5-2ms (file I/O + PHP parsing)
- Tổng: ~8.5-34ms cho 17 files
- Complexity: O(N) với N = số config files
```

#### SAU (Lazy Loading):
```
Bootstrap Time = Set directory path only
- Chỉ set 1 biến: ~0.001ms
- Tổng: ~0.001ms
- Complexity: O(1)
```

**Kết quả**: SAU nhanh hơn ~8,500-34,000 lần trong bootstrap! ⚡

---

### 2. Runtime Performance (Hiệu suất khi chạy)

#### TRƯỚC (Eager Loading):
```
Access config: O(1)
- Đã có sẵn trong memory
- Không có overhead
- Direct array access
```

#### SAU (Lazy Loading):
```
Access config: O(1) + overhead check
- Check if file loaded: ~0.0001ms
- Load file if needed: ~0.5-2ms (chỉ lần đầu)
- Sau đó: O(1) như eager loading
```

**Kết quả**:
- **Lần đầu access**: SAU chậm hơn ~0.5-2ms (do phải load file)
- **Các lần sau**: Bằng nhau (O(1))
- **Tổng thể**: Nếu app chỉ dùng 3-5 config files → SAU nhanh hơn

---

### 3. Memory Usage (Sử dụng bộ nhớ)

#### TRƯỚC (Eager Loading):
```
Memory = Load tất cả 17 config files
- Mỗi file: ~5-50KB (tùy file)
- Tổng: ~85-850KB cho tất cả files
- Luôn giữ trong memory
```

#### SAU (Lazy Loading):
```
Memory = Chỉ load file được dùng
- Nếu dùng 5 files: ~25-250KB
- Nếu dùng 17 files: ~85-850KB (bằng eager)
- Tiết kiệm: ~60-600KB nếu chỉ dùng một phần
```

**Kết quả**: SAU tiết kiệm memory nếu không dùng hết config files 💾

---

### 4. Use Cases (Trường hợp sử dụng)

#### Scenario 1: API Request (chỉ cần app, database, cache)
```
TRƯỚC: Load 17 files → ~34ms bootstrap
SAU:   Load 3 files  → ~1.5ms bootstrap + ~1.5ms runtime = ~3ms
→ SAU nhanh hơn ~11 lần! 🚀
```

#### Scenario 2: Full Application (dùng hết config files)
```
TRƯỚC: Load 17 files → ~34ms bootstrap
SAU:   Load 17 files → ~0.001ms bootstrap + ~34ms runtime = ~34ms
→ Bằng nhau, nhưng SAU vẫn tốt hơn vì bootstrap nhanh hơn
```

#### Scenario 3: Console Command (chỉ cần app, console)
```
TRƯỚC: Load 17 files → ~34ms bootstrap
SAU:   Load 2 files  → ~1ms bootstrap + ~1ms runtime = ~2ms
→ SAU nhanh hơn ~17 lần! 🚀
```

---

## Kết luận

### ✅ LAZY LOADING (SAU) TỐI ƯU HƠN vì:

1. **Bootstrap nhanh hơn**: ~8,500-34,000 lần nhanh hơn
   - Quan trọng cho production (mỗi request phải bootstrap)

2. **Tiết kiệm memory**: Chỉ load file cần thiết
   - Quan trọng cho high-traffic applications

3. **Linh hoạt hơn**: Có thể switch sang eager nếu cần
   - `loadDirectory($path, eager: true)` cho production nếu muốn

4. **Runtime performance**: Gần như bằng nhau
   - Overhead check rất nhỏ (~0.0001ms)
   - Chỉ load file lần đầu, sau đó cache

### 📊 Benchmark Estimate:

```
Bootstrap Time:
- Eager:  ~34ms (load 17 files)
- Lazy:    ~0.001ms (set path only)
→ Lazy nhanh hơn ~34,000 lần

First Request (API - 3 config files):
- Eager:  ~34ms bootstrap
- Lazy:   ~0.001ms bootstrap + ~1.5ms runtime = ~1.5ms
→ Lazy nhanh hơn ~22 lần

Memory (API - 3 config files):
- Eager:  ~850KB (all files)
- Lazy:   ~150KB (3 files)
→ Lazy tiết kiệm ~700KB (82%)
```

### 🎯 Recommendation:

**Sử dụng LAZY LOADING (SAU)** vì:
- Bootstrap nhanh hơn đáng kể
- Tiết kiệm memory
- Runtime performance gần như bằng nhau
- Phù hợp với Clean Architecture (load khi cần)

**Chỉ dùng EAGER LOADING nếu:**
- Bạn chắc chắn sẽ dùng TẤT CẢ config files trong mọi request
- Và muốn trade-off bootstrap time để có runtime nhanh hơn 0.5ms

