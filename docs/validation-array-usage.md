# Array Validation Guide

Toporia Framework hỗ trợ validation cho mảng với nhiều tính năng nâng cao, tối ưu performance và tuân thủ SOLID.

## 1. Basic Array Validation

### Validate array type:

```php
$rules = [
    'tags' => 'required|array',
    'items' => 'array|min:1|max:10', // Array với size validation
];
```

### Validate array size:

```php
$rules = [
    'items' => 'array:2,5', // Array phải có từ 2 đến 5 phần tử
    'tags' => 'array:1',    // Array phải có ít nhất 1 phần tử
];
```

## 2. Wildcard Notation (items.*.name)

Validate tất cả phần tử trong array:

```php
$rules = [
    'items.*.name' => 'required|string|max:255',
    'items.*.email' => 'required|email',
    'items.*.age' => 'required|integer|min:18',
];

$data = [
    'items' => [
        ['name' => 'John', 'email' => 'john@example.com', 'age' => 25],
        ['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 30],
    ],
];
```

### Nested Wildcards:

```php
$rules = [
    'items.*.tags.*' => 'required|string|max:50',
    'items.*.tags.*.name' => 'required|string',
];

$data = [
    'items' => [
        [
            'tags' => [
                ['name' => 'php'],
                ['name' => 'laravel'],
            ],
        ],
    ],
];
```

## 3. Indexed Notation (items.0.name)

Validate từng phần tử cụ thể:

```php
$rules = [
    'items.0.name' => 'required|string',
    'items.0.email' => 'required|email',
    'items.1.name' => 'required|string',
    'items.1.email' => 'required|email',
];
```

## 4. Array Element Validation

### Validate từng phần tử trong array:

```php
$rules = [
    'emails' => 'required|array',
    'emails.*' => 'required|email', // Validate mỗi email trong array
];

$data = [
    'emails' => [
        'john@example.com',
        'jane@example.com',
        'invalid-email', // ❌ Validation error
    ],
];
```

### Validate với rules phức tạp:

```php
$rules = [
    'users.*.email' => 'required|email|unique:users,email',
    'users.*.password' => 'required|string|min:8',
    'users.*.role' => 'required|in:admin,user,guest',
];
```

## 5. Array Size Rules

### Array Min/Max:

```php
use Toporia\Framework\Validation\Rules\ArrayMin;
use Toporia\Framework\Validation\Rules\ArrayMax;

$rules = [
    'items' => [
        'required',
        'array',
        new ArrayMin(2),  // Ít nhất 2 phần tử
        new ArrayMax(10), // Tối đa 10 phần tử
    ],
];
```

### Hoặc dùng string rules:

```php
$rules = [
    'items' => 'required|array|min:2|max:10',
];
```

## 6. Array Distinct (Unique Values)

### Validate array có giá trị unique:

```php
use Toporia\Framework\Validation\Rules\ArrayDistinct;

$rules = [
    'tags' => [
        'required',
        'array',
        new ArrayDistinct(), // Tất cả giá trị phải unique
    ],
];

// Hoặc dùng string rule
$rules = [
    'tags' => 'required|array|distinct',
];
```

## 7. Nested Array Validation

### Validate nested structures:

```php
$rules = [
    'products' => 'required|array|min:1',
    'products.*.name' => 'required|string|max:255',
    'products.*.price' => 'required|numeric|min:0',
    'products.*.categories' => 'required|array|min:1',
    'products.*.categories.*' => 'required|string|exists:categories,name',
];
```

## 8. Form Request với Array Validation

```php
use Toporia\Framework\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer' => 'required|array',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'items.*.price' => 'required|numeric|min:0',

            'shipping' => 'required|array',
            'shipping.address' => 'required|string',
            'shipping.city' => 'required|string',
            'shipping.postal_code' => 'required|string|max:10',
        ];
    }
}
```

## 9. Custom Error Messages cho Array

```php
public function messages(): array
{
    return [
        'items.*.name.required' => 'Each item must have a name.',
        'items.*.email.email' => 'Each item must have a valid email.',
        'items.*.quantity.min' => 'Each item quantity must be at least :min.',
    ];
}
```

## 10. Performance Tips

1. **Use wildcard notation** thay vì validate từng index
2. **Validate array size first** để fail-fast
3. **Use distinct rule** cho unique values (nhanh hơn manual check)
4. **Cache validation rules** trong FormRequest

## 11. Examples

### Example 1: User Registration với Multiple Emails

```php
$rules = [
    'name' => 'required|string|max:255',
    'emails' => 'required|array|min:1|max:5',
    'emails.*' => 'required|email|distinct',
];
```

### Example 2: Product với Multiple Images

```php
$rules = [
    'product' => 'required|array',
    'product.name' => 'required|string|max:255',
    'product.images' => 'required|array|min:1|max:10',
    'product.images.*' => 'required|url|max:500',
];
```

### Example 3: Order với Multiple Items

```php
$rules = [
    'order' => 'required|array',
    'order.items' => 'required|array|min:1',
    'order.items.*.product_id' => 'required|integer|exists:products,id',
    'order.items.*.quantity' => 'required|integer|min:1',
    'order.items.*.price' => 'required|numeric|min:0',
    'order.items.*.discount' => 'nullable|numeric|min:0|max:100',
];
```

### Example 4: Nested Array với Tags

```php
$rules = [
    'posts' => 'required|array|min:1',
    'posts.*.title' => 'required|string|max:255',
    'posts.*.content' => 'required|string|min:10',
    'posts.*.tags' => 'required|array|min:1|max:5',
    'posts.*.tags.*' => 'required|string|max:50|distinct',
];
```

## 12. Advanced: Conditional Array Validation

```php
public function rules(): array
{
    $rules = [
        'items' => 'required|array|min:1',
        'items.*.type' => 'required|in:product,service',
    ];

    // Conditional rules based on type
    if ($this->has('items')) {
        foreach ($this->input('items', []) as $index => $item) {
            if (($item['type'] ?? null) === 'product') {
                $rules["items.{$index}.product_id"] = 'required|exists:products,id';
            } else {
                $rules["items.{$index}.service_id"] = 'required|exists:services,id';
            }
        }
    }

    return $rules;
}
```

## 13. Best Practices

1. **Always validate array type first** (`array` rule)
2. **Use wildcard notation** (`items.*.name`) thay vì indexed (`items.0.name`)
3. **Validate array size** để tránh empty hoặc quá lớn
4. **Use distinct rule** cho unique values
5. **Nested validation** cho complex structures
6. **Custom messages** cho better UX

## 14. Performance Considerations

- **O(n * m)** complexity where n = array size, m = rules per element
- **Lazy expansion** - chỉ expand khi cần
- **Early exit** - dừng khi gặp lỗi đầu tiên (optional)
- **Cached rules** - rules được cache trong FormRequest

