# Webhook System Documentation

## Overview

Toporia Framework provides a comprehensive webhook system for both **outbound** (sending webhooks) and **inbound** (receiving webhooks) functionality. The system is designed with performance, security, and reliability in mind.

## Features

- ✅ **Outbound Webhooks**: Send webhooks to external endpoints
- ✅ **Inbound Webhooks**: Receive and process webhooks from external services
- ✅ **Signature Verification**: HMAC-based signature generation and verification
- ✅ **Retry Logic**: Automatic retry with configurable attempts and delays
- ✅ **Async Dispatch**: Queue-based async webhook dispatch
- ✅ **Delivery Tracking**: Track webhook delivery attempts and results
- ✅ **Event Filtering**: Filter endpoints by event names/patterns
- ✅ **Multiple Algorithms**: Support for SHA256, SHA1, SHA512

## Architecture

### Clean Architecture

- **Domain Layer**: Models (`WebhookEndpoint`, `WebhookDelivery`)
- **Application Layer**: Manager (`WebhookManager`)
- **Infrastructure Layer**: Dispatcher, Receiver, Signature Generator
- **Presentation Layer**: Controllers

### SOLID Principles

- **Single Responsibility**: Each class has one clear purpose
- **Open/Closed**: Extensible via interfaces
- **Liskov Substitution**: All implementations are interchangeable
- **Interface Segregation**: Focused, minimal interfaces
- **Dependency Inversion**: Depends on abstractions

## Configuration

### Environment Variables

```env
# Webhook Configuration
WEBHOOK_SIGNATURE_ALGORITHM=sha256
WEBHOOK_TIMEOUT=30
WEBHOOK_RETRY=3
WEBHOOK_RETRY_DELAY=1000
WEBHOOK_SECRET=your-secret-key-here
WEBHOOK_QUEUE_ENABLED=true
WEBHOOK_QUEUE_NAME=webhooks
```

### Config File

Edit `config/webhook.php`:

```php
return [
    'signature_algorithm' => env('WEBHOOK_SIGNATURE_ALGORITHM', 'sha256'),
    'defaults' => [
        'timeout' => env('WEBHOOK_TIMEOUT', 30),
        'retry' => env('WEBHOOK_RETRY', 3),
        'retry_delay' => env('WEBHOOK_RETRY_DELAY', 1000),
    ],
    'secret' => env('WEBHOOK_SECRET', ''),
    'queue' => [
        'enabled' => env('WEBHOOK_QUEUE_ENABLED', true),
        'queue_name' => env('WEBHOOK_QUEUE_NAME', 'webhooks'),
    ],
];
```

## Outbound Webhooks

### Basic Usage

```php
use Toporia\Framework\Support\Accessors\Webhook;

// Dispatch webhook synchronously
$results = Webhook::dispatch('user.created', [
    'user_id' => 123,
    'email' => 'user@example.com',
], false);

// Dispatch webhook asynchronously (via queue)
Webhook::dispatch('user.created', [
    'user_id' => 123,
    'email' => 'user@example.com',
], true);
```

### Using WebhookManager Directly

```php
use Toporia\Framework\Webhook\WebhookManager;

$manager = app('webhook');

// Dispatch to all matching endpoints
$results = $manager->dispatch('order.updated', [
    'order_id' => 456,
    'status' => 'shipped',
]);
```

### Using WebhookDispatcher

```php
use Toporia\Framework\Webhook\Contracts\WebhookDispatcherInterface;

$dispatcher = app('webhook.dispatcher');

// Dispatch to single endpoint
$success = $dispatcher->dispatchTo('payment.completed', [
    'payment_id' => 789,
    'amount' => 99.99,
], 'https://example.com/webhook', [
    'secret' => 'webhook-secret',
    'timeout' => 30,
    'retry' => 3,
    'retry_delay' => 1000,
    'headers' => [
        'X-Custom-Header' => 'value',
    ],
]);

// Queue for async dispatch
$dispatcher->queue('invoice.generated', [
    'invoice_id' => 101,
], 'https://example.com/webhook', [
    'secret' => 'webhook-secret',
]);
```

### Managing Webhook Endpoints

```php
use Toporia\Framework\Webhook\Models\WebhookEndpoint;

// Create endpoint
$endpoint = WebhookEndpoint::create([
    'name' => 'Payment Service',
    'url' => 'https://payments.example.com/webhook',
    'secret' => 'secret-key',
    'events' => ['payment.*', 'invoice.*'], // Wildcard patterns
    'active' => true,
    'timeout' => 30,
    'retry_count' => 3,
    'retry_delay' => 1000,
    'headers' => [
        'X-API-Key' => 'api-key',
    ],
]);

// Update endpoint
$endpoint->update([
    'active' => false,
]);

// Check if endpoint should receive event
if ($endpoint->shouldReceive('payment.completed')) {
    // Dispatch to this endpoint
}
```

### Tracking Deliveries

```php
use Toporia\Framework\Webhook\Models\WebhookDelivery;

// Get delivery history
$deliveries = WebhookDelivery::where('endpoint_id', $endpointId)
    ->where('event', 'payment.completed')
    ->orderBy('created_at', 'desc')
    ->get();

// Get failed deliveries
$failed = WebhookDelivery::whereNotNull('failed_at')
    ->where('created_at', '>', now()->subDays(7))
    ->get();
```

## Inbound Webhooks

### Receiving Webhooks

The framework provides a controller to handle incoming webhooks:

**Route**: `POST /webhook/{provider?}`

```php
use Toporia\Framework\Webhook\Contracts\WebhookReceiverInterface;

$receiver = app('webhook.receiver');

// Process incoming webhook
try {
    $data = $receiver->process($request, $secret, function ($event, $payload, $request) {
        // Handle webhook event
        match ($event) {
            'payment.completed' => $this->handlePaymentCompleted($payload),
            'invoice.generated' => $this->handleInvoiceGenerated($payload),
            default => null,
        };
    });
} catch (\RuntimeException $e) {
    // Invalid signature or processing error
    return response()->json(['error' => $e->getMessage()], 400);
}
```

### Signature Verification

```php
use Toporia\Framework\Webhook\Contracts\WebhookReceiverInterface;

$receiver = app('webhook.receiver');

// Verify signature
$isValid = $receiver->verifySignature($request, $secret, 'sha256');

if (!$isValid) {
    return response()->json(['error' => 'Invalid signature'], 401);
}

// Extract event and payload
$event = $receiver->extractEvent($request);
$payload = $receiver->extractPayload($request);
```

### Custom Webhook Handler

Create a custom controller:

```php
namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\{Request, JsonResponse};
use Toporia\Framework\Webhook\Contracts\WebhookReceiverInterface;

class WebhookController
{
    public function __construct(
        private WebhookReceiverInterface $receiver
    ) {}

    public function handle(Request $request, string $provider = 'default'): JsonResponse
    {
        $secret = config("webhook.providers.{$provider}.secret");

        try {
            $data = $this->receiver->process($request, $secret, function ($event, $payload) {
                // Dispatch to event system
                event("webhook.{$event}", $payload);
            });

            return new JsonResponse(['success' => true], 200);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
```

## Performance Optimization

### Async Dispatch

Always use async dispatch for non-critical webhooks:

```php
// Good: Async dispatch
Webhook::dispatch('user.created', $data, true);

// Bad: Synchronous dispatch (blocks request)
Webhook::dispatch('user.created', $data, false);
```

### Batch Operations

```php
// Dispatch multiple events efficiently
$events = [
    ['event' => 'user.created', 'payload' => $userData],
    ['event' => 'order.created', 'payload' => $orderData],
];

foreach ($events as $item) {
    Webhook::dispatch($item['event'], $item['payload'], true);
}
```

### Database Indexing

Ensure proper indexes on `webhook_deliveries` table:

```sql
CREATE INDEX idx_endpoint_event ON webhook_deliveries(endpoint_id, event);
CREATE INDEX idx_created_at ON webhook_deliveries(created_at);
CREATE INDEX idx_failed_at ON webhook_deliveries(failed_at);
```

## Security Best Practices

1. **Always use signatures**: Never send webhooks without signature verification
2. **Use HTTPS**: Always use HTTPS for webhook endpoints
3. **Validate payloads**: Validate incoming webhook payloads
4. **Rate limiting**: Implement rate limiting for inbound webhooks
5. **Secret rotation**: Rotate webhook secrets regularly

## Error Handling

```php
try {
    $results = Webhook::dispatch('user.created', $data);

    foreach ($results as $endpoint => $success) {
        if (!$success) {
            // Log failure
            logger()->error("Webhook failed for endpoint: {$endpoint}");
        }
    }
} catch (\Throwable $e) {
    // Handle error
    logger()->error("Webhook dispatch error: " . $e->getMessage());
}
```

## Testing

```php
use Toporia\Framework\Webhook\WebhookManager;

// Mock webhook dispatcher
$mockDispatcher = Mockery::mock(WebhookDispatcherInterface::class);
$mockDispatcher->shouldReceive('dispatchTo')
    ->once()
    ->andReturn(true);

$manager = new WebhookManager($mockDispatcher);
$results = $manager->dispatch('test.event', ['data' => 'test']);
```

## Migration

Run migrations to create webhook tables:

```bash
php console migrate
```

This creates:
- `webhook_endpoints` - Webhook endpoint configurations
- `webhook_deliveries` - Webhook delivery tracking

## Examples

### E-commerce Order Webhook

```php
// When order is created
Webhook::dispatch('order.created', [
    'order_id' => $order->id,
    'customer_id' => $order->customer_id,
    'total' => $order->total,
    'items' => $order->items->toArray(),
], true);

// When order is shipped
Webhook::dispatch('order.shipped', [
    'order_id' => $order->id,
    'tracking_number' => $order->tracking_number,
    'shipped_at' => $order->shipped_at->toIso8601String(),
], true);
```

### Payment Webhook Handler

```php
public function handlePaymentWebhook(Request $request): JsonResponse
{
    $receiver = app('webhook.receiver');
    $secret = config('webhook.payment.secret');

    return $receiver->process($request, $secret, function ($event, $payload) {
        match ($event) {
            'payment.completed' => $this->processPayment($payload),
            'payment.failed' => $this->handlePaymentFailure($payload),
            'payment.refunded' => $this->processRefund($payload),
            default => logger()->warning("Unknown payment event: {$event}"),
        };
    });
}
```

## API Reference

### WebhookManager

- `dispatch(string $event, mixed $payload, bool $async = false): array`

### WebhookDispatcherInterface

- `dispatch(string $event, mixed $payload, array $endpoints, array $options = []): array`
- `dispatchTo(string $event, mixed $payload, string $endpoint, array $options = []): bool`
- `queue(string $event, mixed $payload, string $endpoint, array $options = []): void`

### WebhookReceiverInterface

- `verifySignature(Request $request, string $secret, string $algorithm = 'sha256'): bool`
- `process(Request $request, string $secret, ?callable $handler = null): array`
- `extractEvent(Request $request): string`
- `extractPayload(Request $request): array`

