# Scripts Directory

Thư mục này chứa các utility scripts cho development và maintenance.

## Scripts

### `fix-permissions.sh`

Script để fix file permissions cho node_modules trong WSL2 environment.

**Usage:**
```bash
bash scripts/fix-permissions.sh
```

**Auto-run:**
Script này tự động chạy sau khi `npm install` (via `postinstall` hook trong `package.json`).

**Purpose:**
- Fix execute permissions cho các binaries trong `node_modules/.bin`
- Fix permissions cho vite, esbuild và các tools khác
- Giải quyết vấn đề "Permission denied" trong WSL2

## Adding New Scripts

Khi thêm script mới vào thư mục này:
1. Đảm bảo script có execute permissions: `chmod +x script-name.sh`
2. Thêm documentation vào file này
3. Cập nhật README.md ở root nếu cần

