# Phân tích Clean Architecture - src/App/

## ✅ ĐÚNG VỊ TRÍ

### Domain Layer
- ✅ `Domain/Product/Product.php` - Entity
- ✅ `Domain/User/User.php` - Entity
- ✅ `Domain/Product/ProductRepository.php` - Repository Interface
- ✅ `Domain/User/UserRepository.php` - Repository Interface
- ✅ `Domain/Repository/RepositoryInterface.php` - Repository Contract
- ✅ `Domain/Repository/QueryBuilderInterface.php` - Query Builder Contract
- ✅ `Domain/Repository/Criteria/CriteriaInterface.php` - Criteria Contract
- ✅ `Domain/Services/*Interface.php` - Service Interfaces (TopicServiceInterface, HealthCheckerInterface, ClusterFixerInterface)
- ✅ `Domain/Transformer/TransformerInterface.php` - Transformer Contract
- ✅ `Domain/Export/ExportInterface.php` - Export Contract
- ✅ `Domain/Import/ImportInterface.php` - Import Contract
- ✅ `Domain/Macro/MacroInterface.php` - Macro Contract

### Application Layer
- ✅ `Application/Product/CreateProduct/CreateProductHandler.php` - Use Case Handler
- ✅ `Application/Product/CreateProduct/CreateProductCommand.php` - Command DTO
- ✅ `Application/Jobs/*` - Background Jobs (Application-level)
- ✅ `Application/Pipes/*` - Pipeline Logic (Application-level)

### Infrastructure Layer
- ✅ `Infrastructure/Repository/*` - Repository Implementations
- ✅ `Infrastructure/Persistence/Models/*` - Database Models
- ✅ `Infrastructure/Services/Kafka/*` - Kafka Service Implementation (implements Domain interfaces)
- ✅ `Infrastructure/Export/*` - Export Implementations
- ✅ `Infrastructure/Import/*` - Import Implementations
- ✅ `Infrastructure/Transformer/*` - Transformer Implementations
- ✅ `Infrastructure/Macro/MacroRegistry.php` - Macro Registry Implementation
- ✅ `Infrastructure/Mails/*` - Mail Implementations
- ✅ `Infrastructure/Notifications/*` - Notification Implementations
- ✅ `Infrastructure/Auth/RepositoryUserProvider.php` - Auth Provider Implementation
- ✅ `Infrastructure/Providers/*` - Service Providers

### Presentation Layer
- ✅ `Presentation/Http/Controllers/*` - HTTP Controllers
- ✅ `Presentation/Http/Middleware/*` - HTTP Middleware
- ✅ `Presentation/Http/Requests/*` - Request Validation
- ✅ `Presentation/Http/Action/*` - Action Classes (ADR pattern)
- ✅ `Presentation/Views/*` - View Templates
- ✅ `Presentation/Console/Kernel.php` - Console Kernel

---

## ❌ VẤN ĐỀ CẦN SỬA

### 1. **DUPLICATE: Application/Services/Kafka/** ❌
**Vấn đề:**
- `Application/Services/Kafka/KafkaTopicService.php` - KHÔNG implement interface, duplicate với Infrastructure version
- `Application/Services/Kafka/KafkaHealthChecker.php` - Duplicate
- `Application/Services/Kafka/KafkaClusterIdFixer.php` - Duplicate

**Nguyên nhân:**
- Application layer KHÔNG nên có implementation cụ thể của external services (Kafka)
- Chỉ Infrastructure layer mới implement các external services
- Application layer chỉ nên có Use Cases, Application Services (orchestration), không có implementation cụ thể

**Giải pháp:**
- ❌ XÓA `Application/Services/Kafka/` (duplicate)
- ✅ Sử dụng `Infrastructure/Services/Kafka/` (đã implement Domain interfaces)
- ✅ Cập nhật `KafkaTopicManagerCommand` để dùng Infrastructure version hoặc Domain interface

### 2. **Application/Console/Commands/** ⚠️
**Vấn đề:**
- Console Commands thường thuộc Presentation layer (entry points)
- Nhưng nếu chúng chứa business logic thì có thể ở Application

**Phân tích:**
- `OrderTrackingConsumerCommand` - Chứa business logic (order tracking), có thể ở Application ✅
- `KafkaTopicManagerCommand` - Orchestration command, có thể ở Application ✅
- `ImportExcelCommand`, `ExportExcelCommand` - Business logic, có thể ở Application ✅

**Kết luận:** ✅ **ĐÚNG VỊ TRÍ** - Vì các commands này chứa business logic, không chỉ là thin wrappers

### 3. **Application/Observers/ProductObserver** ⚠️
**Vấn đề:**
- Observer đang observe `ProductModel` (Infrastructure layer)
- Comment nói "Infrastructure Layer" nhưng file ở Application

**Phân tích:**
- Observers có thể ở:
  - **Domain**: Nếu observe domain events
  - **Application**: Nếu observe application events
  - **Infrastructure**: Nếu observe infrastructure events (model events)

**Kết luận:** ⚠️ **NÊN DI CHUYỂN** - Vì observe `ProductModel` (Infrastructure), nên nên ở `Infrastructure/Observers/`

---

## 📋 TÓM TẮT ĐÃ SỬA ✅

1. ✅ **ĐÃ XÓA** `Application/Services/Kafka/` (3 files: KafkaTopicService, KafkaHealthChecker, KafkaClusterIdFixer)
2. ✅ **ĐÃ CẬP NHẬT** `KafkaTopicManagerCommand` để dùng Domain interfaces (`TopicServiceInterface`, `HealthCheckerInterface`, `ClusterFixerInterface`) thay vì concrete classes
3. ✅ **ĐÃ DI CHUYỂN** `Application/Observers/ProductObserver` → `Infrastructure/Observers/ProductObserver`
4. ✅ **ĐÃ ĐĂNG KÝ** Kafka services trong `AppServiceProvider` (bind Domain interfaces với Infrastructure implementations)
5. ✅ **ĐÃ THÊM** method `performHealthCheck()` vào `Infrastructure/Services/Kafka/KafkaHealthChecker` để tương thích

---

## 🎯 NGUYÊN TẮC CLEAN ARCHITECTURE

### Domain Layer
- **Chỉ có:** Entities, Value Objects, Domain Services, Repository Interfaces, Domain Events
- **KHÔNG có:** Implementation, External Dependencies, Framework Code

### Application Layer
- **Có:** Use Cases, Application Services (orchestration), Commands/Queries, DTOs, Jobs, Application Events
- **KHÔNG có:** Implementation cụ thể của external services, Database code, Framework-specific code

### Infrastructure Layer
- **Có:** Repository Implementations, External Services (Kafka, Email, etc.), Database Models, Service Providers, Observers (infrastructure-level)
- **KHÔNG có:** Business Logic, Use Cases

### Presentation Layer
- **Có:** Controllers, Middleware, Views, Console Commands (nếu chỉ là entry points), Routes
- **KHÔNG có:** Business Logic (chỉ gọi Application layer)

