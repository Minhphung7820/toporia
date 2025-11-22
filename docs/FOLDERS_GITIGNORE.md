# Các Folder Ở Thư Mục Gốc - Gitignore Guide

Tài liệu này liệt kê các **FOLDER** ở thư mục gốc của project Toporia và folder nào cần ignore.

---

## 📋 Tổng Hợp

### ✅ **CÁC FOLDER CẦN IGNORE** (Đã được ignore)

| Folder | Trạng thái | Lý do |
|--------|------------|-------|
| `/vendor/` | ✅ ĐÃ IGNORE | Composer dependencies - được tạo từ `composer install` |
| `node_modules/` | ✅ ĐÃ IGNORE | Node.js dependencies - được tạo từ `npm install` |
| `deployment/` | ✅ ĐÃ IGNORE | Deployment scripts/configs - thường chứa sensitive data |
| `.phpunit.cache/` | ✅ ĐÃ IGNORE | PHPUnit cache directory - được tạo khi chạy tests |

---

### ❌ **CÁC FOLDER KHÔNG NÊN IGNORE** (Source code - phải commit)

| Folder | Lý do |
|--------|-------|
| `bootstrap/` | Chứa bootstrap code (app.php, helpers.php) - **QUAN TRỌNG** |
| `config/` | Chứa config files - **QUAN TRỌNG** |
| `configs/` | Chứa config files (.npmrc, .nvmrc, .dockerignore) - **QUAN TRỌNG** |
| `database/` | Chứa migrations, seeders - **QUAN TRỌNG** |
| `docker/` | Chứa Docker configs - **QUAN TRỌNG** |
| `docs/` | Chứa documentation - **CÓ THỂ IGNORE** (tùy project) |
| `examples/` | Chứa example code - **CÓ THỂ IGNORE** (tùy project) |
| `public/` | Chứa public assets, index.php - **QUAN TRỌNG** |
| `resources/` | Chứa source assets (views, CSS, JS) - **QUAN TRỌNG** |
| `routes/` | Chứa route definitions - **QUAN TRỌNG** |
| `scripts/` | Chứa utility scripts - **QUAN TRỌNG** |
| `src/` | Chứa source code chính - **QUAN TRỌNG** |
| `tests/` | Chứa test files - **QUAN TRỌNG** |

---

### ⚠️ **CÁC FOLDER IGNORE MỘT PHẦN** (Giữ structure, ignore nội dung)

| Folder | Ignore gì? | Giữ lại gì? |
|--------|------------|-------------|
| `storage/` | `/storage/app/*` (uploads), `/storage/cache/*`, `/storage/logs/*`, `/storage/sessions/*`, `/storage/temp/*` | `.gitkeep` files, structure |
| `bootstrap/cache/` | `/bootstrap/cache/*` (cache files) | `.gitkeep` file (nếu có) |

---

## 📝 Chi Tiết Từng Folder

### 1. `/vendor/` ✅ **IGNORE**
- **Lý do**: Composer dependencies
- **Pattern**: `/vendor/`
- **Lưu ý**: Luôn ignore, được tạo từ `composer install`

### 2. `node_modules/` ✅ **IGNORE**
- **Lý do**: Node.js dependencies
- **Pattern**: `node_modules/`
- **Lưu ý**: Luôn ignore, được tạo từ `npm install`

### 3. `deployment/` ✅ **IGNORE**
- **Lý do**: Deployment scripts/configs có thể chứa sensitive data
- **Pattern**: `deployment/`
- **Lưu ý**: Nếu muốn commit deployment configs, có thể uncomment trong `.gitignore`

### 4. `.phpunit.cache/` ✅ **IGNORE**
- **Lý do**: PHPUnit cache - được tạo khi chạy tests
- **Pattern**: `.phpunit.cache/`
- **Lưu ý**: Auto-generated, không cần commit

### 5. `bootstrap/` ❌ **KHÔNG IGNORE**
- **Chứa**: `app.php`, `helpers.php` - core bootstrap files
- **Lưu ý**: **PHẢI COMMIT** - essential cho framework

### 6. `config/` ❌ **KHÔNG IGNORE**
- **Chứa**: Config files cho framework
- **Lưu ý**: **PHẢI COMMIT** - application configuration

### 7. `configs/` ❌ **KHÔNG IGNORE**
- **Chứa**: `.npmrc`, `.nvmrc`, `.dockerignore` (actual files)
- **Lưu ý**: **PHẢI COMMIT** - config files được symlink từ root

### 8. `database/` ❌ **KHÔNG IGNORE**
- **Chứa**: Migrations, seeders
- **Lưu ý**: **PHẢI COMMIT** - database schema

### 9. `docker/` ❌ **KHÔNG IGNORE**
- **Chứa**: Docker configs (Dockerfile, nginx config, PHP config)
- **Lưu ý**: **PHẢI COMMIT** - containerization configs

### 10. `docs/` ⚠️ **TÙY CHỌN**
- **Chứa**: Documentation
- **Pattern**: `#docs/` (đang comment trong .gitignore)
- **Lưu ý**: Nếu muốn ignore, uncomment `docs/` trong `.gitignore`

### 11. `examples/` ⚠️ **TÙY CHỌN**
- **Chứa**: Example code
- **Pattern**: `#examples/` (đang comment trong .gitignore)
- **Lưu ý**: Nếu muốn ignore, uncomment `examples/` trong `.gitignore`

### 12. `public/` ❌ **KHÔNG IGNORE**
- **Chứa**: `index.php`, public assets, build output
- **Lưu ý**: **PHẢI COMMIT** - chỉ ignore `/public/build/` (Vite output)

### 13. `resources/` ❌ **KHÔNG IGNORE**
- **Chứa**: Source assets (views, CSS, JS, images)
- **Lưu ý**: **PHẢI COMMIT** - source files cho frontend

### 14. `routes/` ❌ **KHÔNG IGNORE**
- **Chứa**: Route definitions
- **Lưu ý**: **PHẢI COMMIT** - routing configuration

### 15. `scripts/` ❌ **KHÔNG IGNORE**
- **Chứa**: Utility scripts (setup.sh, fix-permissions.sh, etc.)
- **Lưu ý**: **PHẢI COMMIT** - helper scripts

### 16. `src/` ❌ **KHÔNG IGNORE**
- **Chứa**: Source code chính của framework/application
- **Lưu ý**: **PHẢI COMMIT** - core code

### 17. `storage/` ⚠️ **IGNORE MỘT PHẦN**
- **Ignore**:
  - `/storage/app/*` (uploads, user files)
  - `/storage/cache/*` (cache files)
  - `/storage/logs/*.log` (log files)
  - `/storage/sessions/*` (session files)
  - `/storage/temp/*` (temp files)
  - `/storage/queue-worker.pid` (PID file)
- **Giữ lại**: `.gitkeep` files, folder structure
- **Lưu ý**: Chỉ ignore nội dung, giữ structure

### 18. `tests/` ❌ **KHÔNG IGNORE**
- **Chứa**: Test files
- **Lưu ý**: **PHẢI COMMIT** - test suite

---

## 🔍 Kiểm Tra Folder Nào Đang Được Ignore

### Xem tất cả folders đang được ignore:
```bash
git ls-files --others --ignored --exclude-standard --directory | grep -E "^[^/]+/$"
```

### Kiểm tra folder cụ thể:
```bash
git check-ignore -v folder-name/
```

### Xem folders chưa được track:
```bash
git status --porcelain | grep "^??" | grep "/$"
```

---

## ✅ Tóm Tắt Nhanh

### **IGNORE TOÀN BỘ FOLDER:**
```gitignore
/vendor/
node_modules/
deployment/
.phpunit.cache/
```

### **IGNORE NỘI DUNG (Giữ structure):**
```gitignore
/storage/app/*
/storage/cache/*
/storage/logs/*
/storage/sessions/*
/storage/temp/*
!/storage/**/.gitkeep

/bootstrap/cache/*
!/bootstrap/cache/.gitkeep
```

### **COMMIT TOÀN BỘ:**
- `bootstrap/`, `config/`, `configs/`, `database/`, `docker/`
- `public/`, `resources/`, `routes/`, `scripts/`, `src/`, `tests/`

### **TÙY CHỌN (Hiện tại đang comment):**
- `docs/` - Uncomment nếu muốn ignore
- `examples/` - Uncomment nếu muốn ignore

---

**Cập nhật lần cuối**: 2024-11-22

