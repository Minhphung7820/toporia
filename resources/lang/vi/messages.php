<?php

declare(strict_types=1);

/**
 * Vietnamese Translation File - Messages
 *
 * File dịch tiếng Việt với các tính năng:
 * - Dịch đơn giản
 * - Nested keys (dot notation)
 * - Placeholder replacements
 * - Pluralization
 */

return [
    // Dịch đơn giản
    'welcome' => 'Chào mừng',
    'hello' => 'Xin chào',
    'goodbye' => 'Tạm biệt',
    'thank_you' => 'Cảm ơn',
    'please' => 'Vui lòng',
    'yes' => 'Có',
    'no' => 'Không',
    'save' => 'Lưu',
    'cancel' => 'Hủy',
    'delete' => 'Xóa',
    'edit' => 'Sửa',
    'create' => 'Tạo mới',
    'update' => 'Cập nhật',
    'search' => 'Tìm kiếm',
    'submit' => 'Gửi',
    'reset' => 'Đặt lại',
    'back' => 'Quay lại',
    'next' => 'Tiếp theo',
    'previous' => 'Trước đó',
    'close' => 'Đóng',
    'open' => 'Mở',
    'loading' => 'Đang tải...',
    'success' => 'Thành công',
    'error' => 'Lỗi',
    'warning' => 'Cảnh báo',
    'info' => 'Thông tin',

    // Messages với placeholders
    'welcome_user' => 'Chào mừng, :name!',
    'hello_user' => 'Xin chào :name, bạn khỏe không?',
    'user_created' => 'Người dùng :name đã được tạo thành công.',
    'user_updated' => 'Người dùng :name đã được cập nhật thành công.',
    'user_deleted' => 'Người dùng :name đã được xóa thành công.',
    'item_count' => 'Bạn có :count mục.',
    'items_found' => 'Tìm thấy :count mục phù hợp với tìm kiếm của bạn.',

    // Nested keys (dot notation)
    'user' => [
        'name' => 'Tên',
        'email' => 'Email',
        'password' => 'Mật khẩu',
        'created_at' => 'Ngày tạo',
        'updated_at' => 'Ngày cập nhật',
        'profile' => [
            'title' => 'Hồ sơ người dùng',
            'edit' => 'Sửa hồ sơ',
            'view' => 'Xem hồ sơ',
        ],
    ],

    'product' => [
        'title' => 'Sản phẩm',
        'name' => 'Tên sản phẩm',
        'price' => 'Giá',
        'description' => 'Mô tả',
        'created' => 'Sản phẩm :name đã được tạo.',
        'updated' => 'Sản phẩm :name đã được cập nhật.',
    ],

    // Pluralization examples
    'apples' => '{0} Không có quả táo|{1} Một quả táo|[2,*] :count quả táo',
    'items' => '{0} Không có mục|{1} Một mục|[2,*] :count mục',
    'users' => '{0} Không có người dùng|{1} Một người dùng|[2,*] :count người dùng',
    'products' => '{0} Không có sản phẩm|{1} Một sản phẩm|[2,*] :count sản phẩm',
    'messages' => '{0} Không có tin nhắn|{1} Một tin nhắn|[2,*] :count tin nhắn',

    // Complex pluralization
    'user_count' => '{0} Không tìm thấy người dùng|{1} Tìm thấy một người dùng|[2,*] Tìm thấy :count người dùng',
    'item_count_detailed' => '{0} Bạn không có mục nào|{1} Bạn có một mục|[2,*] Bạn có :count mục',
];

