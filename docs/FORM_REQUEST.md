# Form Request Validation

Professional form validation system with Laravel-like features, optimized for performance, clean architecture, SOLID principles, and high reusability.

## 🚀 Features

- **Auto-validation** before controller method execution
- **Authorization** support (check permissions before validation)
- **Custom validation rules** per request
- **Custom error messages** and attribute names
- **Prepare for validation** hook (modify data before validation)
- **Custom validator configuration** (withValidator hook)
- **Conditional validation rules** (sometimes, when, etc.)
- **Route parameter access**
- **Performance optimized** (lazy validation, cached rules)
- **Clean Architecture** compliant
- **SOLID principles** applied

## 📖 Basic Usage

### 1. Create FormRequest Class

```php
<?php

namespace App\Presentation\Http\Requests;

use Toporia\Framework\Http\FormRequest;

final class CreateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get validation rules.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'email' => 'required|email|unique:users,email',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Product title is required',
            'price.min' => 'Price must be at least 0',
        ];
    }
}
```

### 2. Use in Controller

```php
<?php

namespace App\Presentation\Http\Controllers;

use App\Presentation\Http\Requests\CreateProductRequest;

class ProductController extends BaseController
{
    public function store(CreateProductRequest $request)
    {
        // Validation already passed!
        // Get validated data
        $validated = $request->validated();

        // Or get specific fields
        $title = $request->input('title');
        $price = $request->input('price');

        // Create product...
    }
}
```

## 🎯 Advanced Features

### Prepare for Validation

Modify data before validation:

```php
protected function prepareForValidation(): void
{
    $this->merge([
        'slug' => Str::slug($this->request->input('title')),
        'user_id' => $this->user()->id,
    ]);
}
```

### Custom Validator Configuration

Add custom validation logic:

```php
public function withValidator(ValidatorInterface $validator): void
{
    $validator->after(function ($validator) {
        // Custom validation after standard rules
        if ($this->input('price') > 1000 && !$this->user()->isPremium()) {
            $validator->errors()->add('price', 'Premium users only');
        }
    });
}
```

### Custom Attribute Names

Customize field names in error messages:

```php
public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'tags.*' => 'tag',
        ];
    }
```

### Conditional Rules

Rules that depend on request state:

```php
public function rules(): array
{
    return [
        'email' => $this->sometimes('required|email', fn() => $this->has('email')),
        'password' => $this->method() === 'POST' ? 'required|min:8' : 'sometimes|min:8',
    ];
}
```

### Route Parameters

Access route parameters:

```php
public function authorize(): bool
{
    $productId = $this->route('id');
    $product = Product::find($productId);

    return $product && $product->user_id === $this->user()->id;
}
```

## 🔧 Manual Validation (ValidatesRequests Trait)

For cases where FormRequest is not used:

```php
<?php

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\{Request, ValidatesRequests};

class ProductController extends BaseController
{
    use ValidatesRequests;

    public function store(Request $request)
    {
        $validated = $this->validate($request, [
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        // Use validated data...
    }
}
```

## 📊 Performance Optimizations

1. **Lazy Validation**: Validation only runs when needed
2. **Cached Rules**: Rules and messages are cached after first call
3. **Early Authorization**: Authorization checked before validation
4. **Efficient Data Access**: O(1) for validated data access

## 🏗️ Architecture

### Clean Architecture Layers

- **Presentation Layer**: `FormRequest` classes
- **Application Layer**: Validation rules and business logic
- **Domain Layer**: Validation contracts

### SOLID Principles

- **Single Responsibility**: Each FormRequest validates one request type
- **Open/Closed**: Extensible via hooks (prepareForValidation, withValidator)
- **Liskov Substitution**: All FormRequests are interchangeable
- **Interface Segregation**: Focused interfaces
- **Dependency Inversion**: Depends on ValidatorInterface

## 📝 Example FormRequests

See examples in:
- `src/App/Presentation/Http/Requests/CreateProductRequest.php`
- `src/App/Presentation/Http/Requests/UpdateProductRequest.php`

## 🔍 Validation Rules

All standard validation rules are supported:
- `required`, `nullable`, `sometimes`
- `string`, `integer`, `numeric`, `boolean`
- `email`, `url`, `ip`
- `min`, `max`, `size`
- `in`, `not_in`
- `unique`, `exists`
- `array`, `array:key`
- `regex`, `date`, `before`, `after`
- And more...

See `docs/VALIDATION.md` for full list.

## ⚡ Best Practices

1. **One FormRequest per action** (CreateProductRequest, UpdateProductRequest)
2. **Use authorization** to check permissions early
3. **Customize messages** for better UX
4. **Use prepareForValidation** for computed fields
5. **Cache expensive operations** in rules() if needed
6. **Keep rules simple** - complex logic in withValidator

## 🎓 Comparison with Laravel

| Feature | Laravel | Toporia |
|---------|---------|---------|
| FormRequest class | ✅ | ✅ |
| Authorization | ✅ | ✅ |
| Custom messages | ✅ | ✅ |
| Prepare for validation | ✅ | ✅ |
| Custom validator | ✅ | ✅ |
| Route parameters | ✅ | ✅ |
| Conditional rules | ✅ | ✅ |
| Performance optimized | ⚠️ | ✅ |
| Cached rules | ❌ | ✅ |

## 📚 Related Documentation

- `docs/VALIDATION.md` - Validation rules reference
- `docs/HTTP.md` - HTTP layer documentation
- `docs/ARCHITECTURE.md` - Architecture overview

