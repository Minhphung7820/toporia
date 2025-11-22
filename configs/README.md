# Config Files Directory

Thư mục này chứa các file cấu hình của project.

## Files trong thư mục này

- **`.npmrc`** - Cấu hình NPM
- **`.nvmrc`** - Cấu hình Node Version Manager
- **`.dockerignore`** - Docker ignore rules

## ⚠️ Lưu ý quan trọng

Các file config này **KHÔNG còn symlinks ở root directory**.

Nếu bạn cần các tool (npm, nvm, docker) tự động phát hiện config files, hãy tạo symlinks:

```bash
# Tạo symlinks ở root
ln -s configs/.npmrc .npmrc
ln -s configs/.nvmrc .nvmrc
ln -s configs/.dockerignore .dockerignore
```

Hoặc chạy setup script:

```bash
bash configs/setup-symlinks.sh
```

## Tại sao không có symlinks?

Các file config có thể được tổ chức trong thư mục `configs/` để giữ root directory gọn gàng. Tuy nhiên, nếu cần các tool tự động phát hiện, hãy tạo symlinks như hướng dẫn trên.
