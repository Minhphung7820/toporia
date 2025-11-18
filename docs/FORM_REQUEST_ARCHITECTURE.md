# FormRequest Validation Architecture

## Cách tiếp cận: Router tự động xử lý

**Flow:**
```
Router → Detect FormRequest → Validate → Controller
```

**Ưu điểm:**
- ✅ Performance tốt nhất: Ít layer, direct access
- ✅ Tự động: Không cần config middleware
- ✅ Giống Laravel: Laravel cũng làm trong Controller resolution
- ✅ Không cần request attribute
- ✅ Clean: Router chỉ detect và validate, không chứa validation logic
- ✅ SOLID: Router depends on FormRequest abstraction, không chứa business logic

**Performance:**
- O(P) để reflection (P = parameters)
- Không có middleware overhead
- Direct access to handler

## Lý do chọn cách này:

1. **Performance tốt nhất**
   - Ít layer hơn (không có middleware)
   - Không cần middleware pipeline overhead
   - Direct access to handler

2. **Clean Architecture đảm bảo**
   - Router chỉ detect và gọi validate()
   - Validation logic vẫn ở FormRequest
   - Router không chứa business logic

3. **SOLID Principles**
   - Single Responsibility: Router route + detect FormRequest (minimal)
   - Open/Closed: Có thể extend FormRequest
   - Dependency Inversion: Router depends on FormRequest abstraction

4. **Giống Laravel**
   - Laravel cũng validate FormRequest trong Controller resolution
   - Industry standard

5. **Tự động và đơn giản**
   - Không cần config middleware
   - Developer chỉ cần type-hint FormRequest

### Implementation:

```php
// Router.php
private function buildCoreHandler(mixed $handler, array $parameters): callable
{
    return function (Request $req, Response $res) use ($handler, $parameters) {
        // ... existing code ...

        if (is_array($handler) && is_string($handler[0])) {
            $controller = $this->container->get($handler[0]);
            $method = $handler[1];

            // Auto-validate FormRequest if present
            $this->validateFormRequestInMethod($controller, $method, $req);

            return $this->container->call([$controller, $method], $parameters);
        }
    };
}
```

## Kết luận

**Router tự động xử lý** là cách tối ưu về:
- ✅ Performance (tốt nhất)
- ✅ Clean Architecture (vẫn đảm bảo)
- ✅ SOLID Principles
- ✅ Developer Experience (tự động, không cần config)
- ✅ Industry Standard (giống Laravel)

Middleware approach đã được loại bỏ vì không cần thiết và kém hiệu quả hơn.

