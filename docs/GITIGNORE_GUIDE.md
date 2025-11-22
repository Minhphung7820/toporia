# Gitignore Guide - Toporia Framework

Hướng dẫn về các file và folder nên ignore khỏi Git trong framework Toporia.

## 📋 Tổng Quan

File `.gitignore` đã được cấu hình để tự động ignore các file/folder không cần thiết trong repository. Tài liệu này giải thích chi tiết từng nhóm.

---

## 📁 1. Dependencies (Thư viện)

### Composer
- `/vendor/` - Thư mục chứa các package PHP từ Composer
- `composer.lock` - File lock của Composer (tùy project có thể giữ lại hoặc ignore)

### Node.js
- `node_modules/` - Thư mục chứa các package JavaScript/TypeScript
- `npm-debug.log*`, `yarn-debug.log*`, `pnpm-debug.log*` - Log files từ các package managers
- `.pnpm-store/` - Store của pnpm

---

## 🔐 2. Environment & Configuration

### Environment Variables
- `.env` - File chứa biến môi trường (sensitive data)
- `.env.*` - Các biến thể của .env (`.env.local`, `.env.production`, v.v.)
- `!.env.example` - Giữ lại file .env.example làm template

### Config Symlinks
- `/.npmrc`, `/.nvmrc`, `/.dockerignore` - Symlinks đến configs/ (actual files trong configs/)

---

## 💾 3. Storage & Runtime Files

### Storage Directories
- `/storage/app/*` - Files upload và user-generated content
  - Giữ lại: `/storage/app/.gitkeep`, `/storage/app/public/`
- `/storage/cache/*` - Cache files từ framework
  - Giữ lại: `/storage/cache/.gitkeep`
- `/storage/logs/*` - Log files
  - Giữ lại: `/storage/logs/.gitkeep`
- `/storage/sessions/*` - Session files
  - Giữ lại: `/storage/sessions/.gitkeep`
- `/storage/temp/*` - Temporary files
  - Giữ lại: `/storage/temp/.gitkeep`

### Process Files
- `/storage/queue-worker.pid` - PID file của queue worker
- `*.pid` - Tất cả PID files

### Bootstrap Cache
- `/bootstrap/cache/*` - Cache files từ bootstrap (nếu có)
  - Giữ lại: `/bootstrap/cache/.gitkeep` (nếu cần)

---

## 🏗️ 4. Build Artifacts

- `/build/` - Build output (nếu có)
- `/dist/` - Distribution files
- `/public/build/` - Vite build output
  - Giữ lại: `/public/build/.gitkeep`
- `/public/hot` - Vite HMR file
- `/public/storage` - Symlink đến storage/app/public
  - Giữ lại: `/public/storage/.gitkeep` (nếu cần)
- `.vite/` - Vite cache directory

---

## 🧪 5. Testing

- `.phpunit.cache/` - PHPUnit cache directory
- `.phpunit.result.cache` - PHPUnit result cache
- `/coverage/` - Test coverage reports
- `*.phpunit.result.cache` - PHPUnit result cache files
- `/clover.xml` - Code coverage XML report

---

## 💻 6. IDE & Editor Files

### PhpStorm/IntelliJ
- `.idea/`

### VS Code
- `.vscode/`

### Vim
- `*.swp`, `*.swo` - Swap files

### Eclipse
- `.project`, `.classpath`, `.settings/`

### Sublime Text
- `*.sublime-project`, `*.sublime-workspace`

### VS Code Workspace
- `*.code-workspace`

---

## 🖥️ 7. OS Files

### macOS
- `.DS_Store` - Finder metadata
- `.DS_Store?` - Backup DS_Store
- `._*` - Resource fork files
- `.Spotlight-V100`, `.Trashes` - System folders

### Windows
- `Thumbs.db` - Thumbnail cache
- `ehthumbs.db` - Explorer thumbnail cache
- `desktop.ini` - Folder settings
- `$RECYCLE.BIN/` - Recycle bin

---

## 📝 8. Temporary & Backup Files

- `*.tmp`, `*.temp` - Temporary files
- `*.bak`, `*.backup` - Backup files
- `*.old` - Old version files
- `*.orig` - Original files (from merges)
- `*.save` - Save files
- `*~` - Backup files (Emacs, vim)

---

## 🐳 9. Docker

- `/docker-data/` - Docker volumes data (nếu mount local)

---

## 📊 10. Logs

- `*.log` - Tất cả log files
- `logs/` - Log directory (nếu có)
- `*.log.*` - Rotated log files

---

## 🚀 11. Deployment & CI/CD

- `deployment/` - Deployment scripts và configs (nếu muốn ignore)
- `.vercel/` - Vercel config (nếu dùng)
- `.netlify/` - Netlify config (nếu dùng)

---

## 📚 12. Documentation (Optional)

Nếu muốn ignore docs/examples, uncomment:
- `#docs/`
- `#examples/`

---

## ✅ Files Nên Giữ Lại

Các file sau nên **KHÔNG** được ignore (có `!` trong .gitignore):

- `.env.example` - Template cho .env
- `.gitkeep` files - Giữ lại structure của empty directories
- Config files trong `configs/` - Actual config files (không phải symlinks)
- Source code trong `src/`
- Public assets trong `resources/`

---

## 🔍 Kiểm Tra Gitignore

### Xem file nào đang được ignore:
```bash
git status --ignored
```

### Kiểm tra file cụ thể có bị ignore không:
```bash
git check-ignore -v path/to/file
```

### Xem tất cả files đang được ignore:
```bash
git ls-files --others --ignored --exclude-standard
```

---

## 📝 Lưu Ý

1. **Storage directories**: Luôn giữ lại `.gitkeep` files để đảm bảo directory structure được tạo khi clone repo
2. **Environment files**: KHÔNG BAO GIỜ commit `.env` files chứa sensitive data
3. **Dependencies**: Luôn ignore `vendor/` và `node_modules/` - chúng được tạo ra từ `composer.json` và `package.json`
4. **Build artifacts**: Các file build nên được ignore, chỉ commit source code
5. **Cache files**: Tất cả cache files nên được ignore vì chúng được tạo tự động

---

## 🔄 Cập Nhật Gitignore

Nếu phát hiện file/folder nào cần ignore nhưng chưa có trong `.gitignore`:

1. Thêm pattern vào `.gitignore`
2. Nếu file đã được commit trước đó, cần remove khỏi Git cache:
   ```bash
   git rm --cached path/to/file
   git commit -m "Remove file from Git tracking"
   ```

---

**Cập nhật lần cuối**: 2024-11-22

