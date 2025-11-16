# Đề xuất Tổ chức lại Domain Layer

## Vấn đề hiện tại

Hiện tại trong `Domain/` có:
- **Entities** (Product, User) - Domain models
- **Contracts/Interfaces** (Repository, Services, Transformer, Export, Import, Macro)

Tất cả nằm cùng cấp, dễ nhầm lẫn giữa Entities và Interfaces.

## Đề xuất cấu trúc mới

```
Domain/
├── Entities/              # Domain Entities (pure business objects)
│   ├── Product.php
│   └── User.php
├── Contracts/            # Domain Contracts/Interfaces
│   ├── Repository/
│   │   ├── ProductRepository.php
│   │   ├── UserRepository.php
│   │   ├── RepositoryInterface.php
│   │   ├── QueryBuilderInterface.php
│   │   └── Criteria/
│   │       └── CriteriaInterface.php
│   ├── Services/
│   │   ├── TopicServiceInterface.php
│   │   ├── HealthCheckerInterface.php
│   │   └── ClusterFixerInterface.php
│   ├── Transformer/
│   │   ├── TransformerInterface.php
│   │   ├── ResourceInterface.php
│   │   └── ResourceCollectionInterface.php
│   ├── Export/
│   │   ├── ExportInterface.php
│   │   └── ExportResult.php
│   ├── Import/
│   │   ├── ImportInterface.php
│   │   └── ImportResult.php
│   └── Macro/
│       ├── MacroInterface.php
│       └── MacroableInterface.php
```

## Lợi ích

1. ✅ **Rõ ràng hơn**: Entities và Contracts tách biệt rõ ràng
2. ✅ **Dễ tìm**: Entities ở `Domain/Entities/`, Contracts ở `Domain/Contracts/`
3. ✅ **Dễ mở rộng**: Thêm Entity mới → `Entities/`, thêm Contract mới → `Contracts/`
4. ✅ **Tuân thủ Clean Architecture**: Phân tách rõ ràng giữa Domain Models và Domain Contracts

## Namespace mới

- Entities: `App\Domain\Entities\Product`, `App\Domain\Entities\User`
- Contracts: `App\Domain\Contracts\Repository\ProductRepository`, etc.

## Cần cập nhật

- Tất cả `use App\Domain\Product\Product` → `use App\Domain\Entities\Product`
- Tất cả `use App\Domain\User\User` → `use App\Domain\Entities\User`
- Tất cả `use App\Domain\Product\ProductRepository` → `use App\Domain\Contracts\Repository\ProductRepository`
- Tất cả `use App\Domain\User\UserRepository` → `use App\Domain\Contracts\Repository\UserRepository`
- Các Contracts khác cũng cần cập nhật namespace

