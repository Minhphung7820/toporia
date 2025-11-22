# Config Files Directory

Thư mục này chứa các file cấu hình của project, được reference qua **symlinks** ở thư mục gốc.

## Files trong thư mục này

- **`.npmrc`** - Cấu hình NPM (symlinked to `../.npmrc`)
- **`.nvmrc`** - Cấu hình Node Version Manager (symlinked to `../.nvmrc`)
- **`.dockerignore`** - Docker ignore rules (symlinked to `../.dockerignore`)

## Tại sao dùng symlinks?

Các file này **phải ở thư mục gốc** để các tool (npm, nvm, docker) có thể tự động phát hiện chúng.

Bằng cách lưu file thực tế ở đây và dùng symlinks ở root, chúng ta:
- ✅ Giữ thư mục gốc gọn gàng, có tổ chức
- ✅ Vẫn giữ đầy đủ chức năng (tools vẫn tìm được files)
- ✅ Nhóm các file config liên quan lại với nhau

## Setup sau khi clone repository

Sau khi clone repository, chạy script để tạo symlinks:

```bash
bash configs/setup-symlinks.sh
```

Hoặc tạo thủ công:

```bash
cd /path/to/toporia
ln -s configs/.npmrc .npmrc
ln -s configs/.nvmrc .nvmrc
ln -s configs/.dockerignore .dockerignore
```

## Verification

Sau khi setup, kiểm tra:

- **npm**: `npm config list` should show config from `.npmrc`
- **nvm**: `nvm use` should read from `.nvmrc`
- **docker**: `docker build` should use `.dockerignore`
