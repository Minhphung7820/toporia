# Unique và Exists Validation với Ignore Conditions

Framework hỗ trợ validation `unique` và `exists` với khả năng ignore nhiều điều kiện, phù hợp cho các trường hợp update hoặc validation phức tạp.

## Unique Validation

### String Rules

#### 1. Simple Unique
```php
'email' => 'unique:users,email'
```

#### 2. Unique với Single Ignore (Backward Compatible)
```php
// Format: unique:table,column,ignoreValue,ignoreColumn
'email' => 'unique:users,email,1,id'  // Ignore id=1
```

#### 3. Unique với Multiple Ignores (New Format)
```php
// Format: unique:table,column,column1:value1,column2:value2
'email' => 'unique:users,email,id:1,status:deleted'  // Ignore id=1 AND status=deleted
'email' => 'unique:users,email,id:1,name:John'      // Ignore id=1 AND name=John
```

### Rule Objects

#### 1. Simple Unique Rule
```php
use Toporia\Framework\Validation\Rules\Unique;

'email' => [new Unique('users', 'email')]
```

#### 2. Unique với Single Ignore
```php
'email' => [new Unique('users', 'email', ['id' => 1])]
```

#### 3. Unique với Multiple Ignores
```php
'email' => [new Unique('users', 'email', [
    'id' => 1,
    'status' => 'deleted'
])]
```

#### 4. Unique với Field Reference
```php
// Sử dụng giá trị từ field khác trong form data
'email' => [new Unique('users', 'email', ['id' => 'user_id'])]
// Sẽ lấy giá trị từ $data['user_id'] làm ignore condition
```

## Exists Validation

### String Rules

#### 1. Simple Exists
```php
'category_id' => 'exists:categories,id'
```

#### 2. Exists với Additional Conditions
```php
// Format: exists:table,column,column1:value1,column2:value2
'category_id' => 'exists:categories,id,status:active'  // Must exist AND status=active
'user_id' => 'exists:users,id,status:active,deleted_at:null'  // Must exist AND status=active AND deleted_at IS NULL
```

### Rule Objects

#### 1. Simple Exists Rule
```php
use Toporia\Framework\Validation\Rules\Exists;

'category_id' => [new Exists('categories', 'id')]
```

#### 2. Exists với Additional Conditions
```php
'category_id' => [new Exists('categories', 'id', [
    'status' => 'active'
])]

'user_id' => [new Exists('users', 'id', [
    'status' => 'active',
    'deleted_at' => null
])]
```

## Array Validation

### Unique trong Arrays

```php
// Validate mỗi email trong array phải unique
'emails.*' => ['required', 'email', 'unique:users,email']

// Với ignore condition
'emails.*' => ['required', 'email', 'unique:users,email,id:1']

// Với Rule Object
'emails.*' => [
    'required',
    'email',
    new Unique('users', 'email', ['id' => 1])
]
```

### Exists trong Arrays

```php
// Validate mỗi category_id phải exists
'category_ids.*' => ['required', 'integer', 'exists:categories,id']

// Với conditions
'category_ids.*' => [
    'required',
    'integer',
    'exists:categories,id,status:active'
]

// Với Rule Object
'category_ids.*' => [
    'required',
    'integer',
    new Exists('categories', 'id', ['status' => 'active'])
]
```

## Use Cases

### 1. Update User Email (Ignore Current User)
```php
$rules = [
    'email' => [
        'required',
        'email',
        'unique:users,email,id:' . $userId  // Ignore current user
    ]
];
```

### 2. Update User Email với Multiple Ignores
```php
$rules = [
    'email' => [
        'required',
        'email',
        'unique:users,email,id:' . $userId . ',status:deleted'  // Ignore deleted users
    ]
];
```

### 3. Validate Category với Status
```php
$rules = [
    'category_id' => [
        'required',
        'exists:categories,id,status:active'  // Must be active category
    ]
];
```

### 4. Validate Multiple Categories
```php
$rules = [
    'category_ids.*' => [
        'required',
        'integer',
        'exists:categories,id,status:active'
    ]
];
```

### 5. FormRequest Example
```php
class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'email' => [
                'required',
                'email',
                new Unique('users', 'email', ['id' => $userId])
            ],
            'category_ids.*' => [
                'required',
                'integer',
                new Exists('categories', 'id', ['status' => 'active'])
            ]
        ];
    }
}
```

## Performance

- **Single Query**: Mỗi unique/exists rule chỉ thực hiện 1 query duy nhất
- **Prepared Statements**: Tất cả queries sử dụng prepared statements để tránh SQL injection
- **Indexed Queries**: Sử dụng index trên column để tối ưu performance
- **Array Validation**: Validate từng element một cách tuần tự, có thể tối ưu bằng batch queries trong tương lai

## Notes

1. **Field References**: Khi sử dụng field reference (ví dụ: `'id' => 'user_id'`), giá trị sẽ được lấy từ validation data
2. **Null Values**: Để check `IS NULL`, sử dụng `deleted_at:null` trong string rules hoặc `'deleted_at' => null` trong Rule objects
3. **Backward Compatibility**: Format cũ `unique:table,column,value,column` vẫn được hỗ trợ
4. **Array Validation**: Unique trong arrays sẽ check cả uniqueness trong array và trong database

