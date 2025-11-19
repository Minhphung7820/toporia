# Cách Sử Dụng Validation Rules

Framework Toporia hỗ trợ 3 cách sử dụng rules trong validation:

1. **String Rules** (built-in rules) - Tương thích ngược
2. **Rule Objects** (custom rules) - Clean Architecture, type-safe
3. **Kết hợp cả hai** - Linh hoạt

## 1. String Rules (Built-in Rules)

Sử dụng string rules cho các validation đơn giản:

```php
use Toporia\Framework\Validation\Validator;

$validator = new Validator();

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25,
];

$rules = [
    'name' => 'required|string|max:255',
    'email' => 'required|email|max:255',
    'age' => 'required|integer|min:18|max:100',
];

if ($validator->validate($data, $rules)) {
    $validated = $validator->validated();
    // Use validated data
} else {
    $errors = $validator->errors();
    // Handle errors
}
```

### String Rules với Parameters

```php
$rules = [
    'password' => 'required|string|min:8|max:255',
    'email' => 'required|email|unique:users,email',
    'age' => 'required|integer|between:18,100',
    'website' => 'nullable|url|max:255',
];
```

## 2. Rule Objects (Custom Rules)

### 2.1. Standard Rule

Rule chỉ chạy khi field có giá trị:

```php
use Toporia\Framework\Validation\Validator;
use App\Application\Rules\Uppercase;

$validator = new Validator();

$data = ['name' => 'john doe'];
$rules = [
    'name' => [
        'required',
        new Uppercase(), // Custom rule
    ],
];

$validator->validate($data, $rules);
```

### 2.2. Implicit Rule

Rule chạy ngay cả khi field empty (như "required"):

```php
use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\Rules\Required;
use App\Application\Rules\CustomRequired;

$validator = new Validator();

$data = ['email' => ''];
$rules = [
    'email' => [
        new Required(), // Implicit rule - chạy ngay cả khi empty
        'email',
    ],
];

$validator->validate($data, $rules);
```

### 2.3. Data-Aware Rule

Rule có quyền truy cập toàn bộ validation data:

```php
use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\Rules\Same;

$validator = new Validator();

$data = [
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
];

$rules = [
    'password' => 'required|string|min:8',
    'password_confirmation' => [
        'required',
        new Same('password'), // Data-aware rule - so sánh với field khác
    ],
];

$validator->validate($data, $rules);
```

### 2.4. Implicit + Data-Aware Rule

Rule vừa implicit vừa data-aware:

```php
use Toporia\Framework\Validation\Validator;
use App\Application\Rules\ComplexRule;

$validator = new Validator();

$data = [
    'field1' => 'value1',
    'field2' => 'value2',
];

$rules = [
    'field1' => [
        new ComplexRule('field2'), // Implicit + Data-aware
    ],
];

$validator->validate($data, $rules);
```

## 3. Kết Hợp String Rules và Rule Objects

Bạn có thể kết hợp cả hai:

```php
use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\Rules\Required;
use Toporia\Framework\Validation\Rules\Same;
use App\Application\Rules\Uppercase;

$validator = new Validator();

$data = [
    'name' => 'john doe',
    'email' => 'john@example.com',
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
];

$rules = [
    'name' => [
        new Required(),
        'string',
        'max:255',
        new Uppercase(),
    ],
    'email' => 'required|email|max:255|unique:users,email',
    'password' => 'required|string|min:8|max:255',
    'password_confirmation' => [
        'required',
        new Same('password'),
    ],
];

if ($validator->validate($data, $rules)) {
    $validated = $validator->validated();
    echo "Validation passed!\n";
    print_r($validated);
} else {
    $errors = $validator->errors();
    echo "Validation failed!\n";
    print_r($errors);
}
```

## 4. Custom Error Messages

Bạn có thể tùy chỉnh error messages:

```php
$validator = new Validator();

$data = ['email' => 'invalid-email'];
$rules = [
    'email' => [
        'required',
        'email',
        new CustomRule(),
    ],
];

$messages = [
    'email.required' => 'Email là bắt buộc',
    'email.email' => 'Email không hợp lệ',
    'email.' . CustomRule::class => 'Custom rule failed',
];

$validator->validate($data, $rules, $messages);
```

## 5. Tạo Custom Rule

### 5.1. Tạo Standard Rule

```bash
php console make:rule Uppercase
```

```php
<?php

namespace App\Application\Rules;

use Toporia\Framework\Validation\Contracts\RuleInterface;

final class Uppercase implements RuleInterface
{
    public function passes(string $attribute, mixed $value): bool
    {
        return is_string($value) && strtoupper($value) === $value;
    }

    public function message(): string
    {
        return 'The :attribute must be uppercase.';
    }
}
```

### 5.2. Tạo Implicit Rule

```bash
php console make:rule CustomRequired --implicit
```

```php
<?php

namespace App\Application\Rules;

use Toporia\Framework\Validation\Contracts\ImplicitRuleInterface;

final class CustomRequired implements ImplicitRuleInterface
{
    public function passes(string $attribute, mixed $value): bool
    {
        // Chạy ngay cả khi value là null/empty
        return $value !== null && $value !== '';
    }

    public function message(): string
    {
        return 'The :attribute field is required.';
    }
}
```

### 5.3. Tạo Data-Aware Rule

```bash
php console make:rule SamePassword --data-aware
```

```php
<?php

namespace App\Application\Rules;

use Toporia\Framework\Validation\Contracts\DataAwareRuleInterface;
use Toporia\Framework\Validation\ValidationData;

final class SamePassword implements DataAwareRuleInterface
{
    private ?ValidationData $data = null;

    public function setData(ValidationData $data): void
    {
        $this->data = $data;
    }

    public function passes(string $attribute, mixed $value): bool
    {
        if ($this->data === null) {
            return false;
        }

        $password = $this->data->get('password');
        return $value === $password;
    }

    public function message(): string
    {
        return 'The :attribute must match password.';
    }
}
```

### 5.4. Tạo Implicit + Data-Aware Rule

```bash
php console make:rule ComplexRule --implicit --data-aware
```

## 6. Ví Dụ Thực Tế

### Form Registration

```php
use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\Rules\Required;
use Toporia\Framework\Validation\Rules\Same;
use App\Application\Rules\StrongPassword;

$validator = new Validator();

$data = $_POST; // Form data

$rules = [
    'username' => [
        new Required(),
        'string',
        'min:3',
        'max:50',
        'alpha_dash',
        'unique:users,username',
    ],
    'email' => [
        'required',
        'email',
        'max:255',
        'unique:users,email',
    ],
    'password' => [
        'required',
        'string',
        'min:8',
        new StrongPassword(), // Custom rule
    ],
    'password_confirmation' => [
        'required',
        new Same('password'),
    ],
    'terms' => [
        'required',
        'accepted', // Must be 'yes', 'on', 1, true
    ],
];

if ($validator->validate($data, $rules)) {
    $validated = $validator->validated();
    // Create user with validated data
} else {
    $errors = $validator->errors();
    // Return errors to form
}
```

### Nested Data Validation

```php
$data = [
    'user' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'profile' => [
            'bio' => 'Developer',
            'website' => 'https://example.com',
        ],
    ],
];

$rules = [
    'user.name' => 'required|string|max:255',
    'user.email' => 'required|email|max:255',
    'user.profile.bio' => 'nullable|string|max:500',
    'user.profile.website' => 'nullable|url|max:255',
];

$validator->validate($data, $rules);
```

## 7. Performance Tips

1. **Sử dụng Implicit Rules cho required checks** - Chạy trước, fail-fast
2. **Cache Rule instances** - Rules stateless có thể reuse
3. **Sử dụng string rules cho simple validations** - Nhanh hơn Rule objects
4. **Sử dụng Rule objects cho complex logic** - Dễ test, maintain

## 8. Best Practices

1. **Clean Architecture**: Đặt rules trong `App\Application\Rules`
2. **Single Responsibility**: Mỗi rule chỉ validate một điều kiện
3. **Type Safety**: Sử dụng Rule objects thay vì callable khi có thể
4. **Reusability**: Tạo reusable rules thay vì duplicate logic
5. **Testing**: Viết unit tests cho custom rules

