# Polymorphic Relationships API Testing Guide

## 📋 Bước 1: Lấy Available IDs từ Database

**Trước khi test, luôn lấy IDs thực tế từ database:**

```bash
GET /api/polymorphic/available-ids
```

**Response:**
```json
{
  "success": true,
  "data": {
    "posts": [1, 2, 3, 4, 5],
    "videos": [1, 2, 3, 4, 5],
    "comments": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    "images": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    "tags": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
    "sample_post_id": 1,
    "sample_video_id": 1,
    "sample_comment_id": 1,
    "sample_tag_ids": [1, 2, 3, 4, 5]
  }
}
```

**Sử dụng các IDs này cho tất cả các test endpoints bên dưới!**

---

## 🧪 Test Individual Relationships

### 1. Test MorphOne (Post/Video → Image)

**Endpoint:** `GET /api/polymorphic/test-morph-one`

**Parameters:**
- `type`: `post` hoặc `video` (required)
- `id`: ID của post hoặc video (required, phải tồn tại trong database)

**Example với data thực tế:**
```bash
# Lấy available IDs trước
curl "http://localhost:8000/api/polymorphic/available-ids"

# Giả sử có post ID = 1
curl "http://localhost:8000/api/polymorphic/test-morph-one?type=post&id=1"

# Giả sử có video ID = 1
curl "http://localhost:8000/api/polymorphic/test-morph-one?type=video&id=1"
```

**Full URL Example:**
```
GET http://localhost:8000/api/polymorphic/test-morph-one?type=post&id=1
GET http://localhost:8000/api/polymorphic/test-morph-one?type=video&id=1
```

---

### 2. Test MorphMany (Post/Video → Comments)

**Endpoint:** `GET /api/polymorphic/test-morph-many`

**Parameters:**
- `type`: `post` hoặc `video` (required)
- `id`: ID của post hoặc video (required, phải tồn tại trong database)

**Example với data thực tế:**
```bash
# Lấy available IDs trước
curl "http://localhost:8000/api/polymorphic/available-ids"

# Giả sử có post ID = 1
curl "http://localhost:8000/api/polymorphic/test-morph-many?type=post&id=1"

# Giả sử có video ID = 1
curl "http://localhost:8000/api/polymorphic/test-morph-many?type=video&id=1"
```

**Full URL Example:**
```
GET http://localhost:8000/api/polymorphic/test-morph-many?type=post&id=1
GET http://localhost:8000/api/polymorphic/test-morph-many?type=video&id=1
```

---

### 3. Test MorphTo (Comment → Post/Video)

**Endpoint:** `GET /api/polymorphic/test-morph-to`

**Parameters:**
- `comment_id`: ID của comment (required, phải tồn tại trong database)

**Example với data thực tế:**
```bash
# Lấy available IDs trước
curl "http://localhost:8000/api/polymorphic/available-ids"

# Giả sử có comment ID = 1
curl "http://localhost:8000/api/polymorphic/test-morph-to?comment_id=1"
```

**Full URL Example:**
```
GET http://localhost:8000/api/polymorphic/test-morph-to?comment_id=1
```

---

### 4. Test MorphToMany (Post/Video → Tags)

**Endpoint:** `GET /api/polymorphic/test-morph-to-many`

**Parameters:**
- `type`: `post` hoặc `video` (required)
- `id`: ID của post hoặc video (required, phải tồn tại trong database)

**Example với data thực tế:**
```bash
# Lấy available IDs trước
curl "http://localhost:8000/api/polymorphic/available-ids"

# Giả sử có post ID = 1
curl "http://localhost:8000/api/polymorphic/test-morph-to-many?type=post&id=1"

# Giả sử có video ID = 1
curl "http://localhost:8000/api/polymorphic/test-morph-to-many?type=video&id=1"
```

**Full URL Example:**
```
GET http://localhost:8000/api/polymorphic/test-morph-to-many?type=post&id=1
GET http://localhost:8000/api/polymorphic/test-morph-to-many?type=video&id=1
```

---

### 5. Test All Relationships Together

**Endpoint:** `GET /api/polymorphic/test-all`

**Parameters (tất cả optional, nhưng nên dùng IDs thực tế):**
- `post_id`: ID của post (optional)
- `video_id`: ID của video (optional)
- `comment_id`: ID của comment (optional)

**Example với data thực tế:**
```bash
# Lấy available IDs trước
curl "http://localhost:8000/api/polymorphic/available-ids"

# Giả sử có post_id=1, video_id=1, comment_id=1
curl "http://localhost:8000/api/polymorphic/test-all?post_id=1&video_id=1&comment_id=1"
```

**Full URL Example:**
```
GET http://localhost:8000/api/polymorphic/test-all?post_id=1&video_id=1&comment_id=1
```

---

## 📝 Create Operations

### 6. Create Post với Relationships

**Endpoint:** `POST /api/polymorphic/posts`

**Body (JSON):**
```json
{
  "title": "My First Post",
  "slug": "my-first-post",
  "content": "This is my first post content with detailed information.",
  "views": 0,
  "is_published": true,
  "tag_ids": [1, 2, 3],
  "image": {
    "url": "https://picsum.photos/800/600",
    "alt_text": "Featured image for my post",
    "width": 800,
    "height": 600,
    "size": 102400
  }
}
```

**Example với data thực tế:**
```bash
# 1. Lấy available tag IDs
curl "http://localhost:8000/api/polymorphic/available-ids"

# 2. Sử dụng tag IDs thực tế (ví dụ: [1, 2, 3])
curl -X POST "http://localhost:8000/api/polymorphic/posts" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Post",
    "content": "This is my first post content",
    "tag_ids": [1, 2, 3],
    "image": {
      "url": "https://picsum.photos/800/600",
      "alt_text": "Featured image",
      "width": 800,
      "height": 600,
      "size": 102400
    }
  }'
```

**Minimal Example (chỉ required fields):**
```json
{
  "title": "My Post",
  "content": "Post content"
}
```

---

### 7. Create Video với Relationships

**Endpoint:** `POST /api/polymorphic/videos`

**Body (JSON):**
```json
{
  "title": "My First Video",
  "slug": "my-first-video",
  "description": "This is my first video description with detailed information.",
  "video_url": "https://example.com/video.mp4",
  "duration": 3600,
  "views": 0,
  "is_published": true,
  "tag_ids": [1, 2, 3],
  "image": {
    "url": "https://picsum.photos/1280/720",
    "alt_text": "Video thumbnail",
    "width": 1280,
    "height": 720,
    "size": 204800
  }
}
```

**Example với data thực tế:**
```bash
# 1. Lấy available tag IDs
curl "http://localhost:8000/api/polymorphic/available-ids"

# 2. Sử dụng tag IDs thực tế (ví dụ: [1, 2, 3])
curl -X POST "http://localhost:8000/api/polymorphic/videos" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Video",
    "description": "This is my first video description",
    "video_url": "https://example.com/video.mp4",
    "duration": 3600,
    "tag_ids": [1, 2, 3],
    "image": {
      "url": "https://picsum.photos/1280/720",
      "alt_text": "Video thumbnail",
      "width": 1280,
      "height": 720,
      "size": 204800
    }
  }'
```

**Minimal Example (chỉ required fields):**
```json
{
  "title": "My Video",
  "description": "Video description"
}
```

---

### 8. Create Comment cho Post/Video

**Endpoint:** `POST /api/polymorphic/comments`

**Body (JSON):**
```json
{
  "commentable_type": "post",
  "commentable_id": 1,
  "content": "This is a great post!",
  "user_id": null,
  "is_approved": true
}
```

**Example với data thực tế:**
```bash
# 1. Lấy available IDs
curl "http://localhost:8000/api/polymorphic/available-ids"

# 2. Tạo comment cho post (giả sử post_id = 1)
curl -X POST "http://localhost:8000/api/polymorphic/comments" \
  -H "Content-Type: application/json" \
  -d '{
    "commentable_type": "post",
    "commentable_id": 1,
    "content": "This is a great post!",
    "is_approved": true
  }'

# 3. Tạo comment cho video (giả sử video_id = 1)
curl -X POST "http://localhost:8000/api/polymorphic/comments" \
  -H "Content-Type: application/json" \
  -d '{
    "commentable_type": "video",
    "commentable_id": 1,
    "content": "Awesome video!",
    "is_approved": true
  }'
```

**Minimal Example (chỉ required fields):**
```json
{
  "commentable_type": "post",
  "commentable_id": 1,
  "content": "Comment content"
}
```

---

## 🔧 Utility Endpoints

### 9. Get Available IDs

**Endpoint:** `GET /api/polymorphic/available-ids`

**Description:** Lấy tất cả IDs có sẵn trong database để test

**Example:**
```bash
curl "http://localhost:8000/api/polymorphic/available-ids"
```

---

### 10. Get Sample Data

**Endpoint:** `GET /api/polymorphic/sample-data`

**Description:** Lấy sample data với relationships đã load sẵn

**Example:**
```bash
curl "http://localhost:8000/api/polymorphic/sample-data"
```

---

### 11. Seed Sample Data

**Endpoint:** `POST /api/polymorphic/seed-data`

**Description:** Tự động seed sample data (5 posts, 5 videos, comments, images, tags)

**Example:**
```bash
curl -X POST "http://localhost:8000/api/polymorphic/seed-data"
```

---

## 📌 Lưu ý quan trọng

1. **Luôn lấy available IDs trước:** Gọi `GET /api/polymorphic/available-ids` để lấy IDs thực tế
2. **Kiểm tra data tồn tại:** Đảm bảo IDs bạn dùng thực sự tồn tại trong database
3. **Tag IDs phải hợp lệ:** Khi tạo post/video với tags, đảm bảo tag_ids là IDs thực tế từ database
4. **Commentable phải tồn tại:** Khi tạo comment, đảm bảo post_id hoặc video_id tồn tại

---

## 🚀 Quick Start

```bash
# 1. Seed data (nếu chưa có)
curl -X POST "http://localhost:8000/api/polymorphic/seed-data"

# 2. Lấy available IDs
curl "http://localhost:8000/api/polymorphic/available-ids"

# 3. Test với IDs thực tế từ response trên
curl "http://localhost:8000/api/polymorphic/test-morph-one?type=post&id=1"
curl "http://localhost:8000/api/polymorphic/test-morph-many?type=post&id=1"
curl "http://localhost:8000/api/polymorphic/test-morph-to?comment_id=1"
curl "http://localhost:8000/api/polymorphic/test-morph-to-many?type=post&id=1"
curl "http://localhost:8000/api/polymorphic/test-all?post_id=1&video_id=1&comment_id=1"
```

