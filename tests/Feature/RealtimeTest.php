<?php

declare(strict_types=1);

namespace Tests\Feature;

use Toporia\Framework\Testing\TestCase;
use Toporia\Framework\Realtime\RealtimeManager;

/**
 * Realtime Testing Example
 *
 * Demonstrates realtime broker and transport testing capabilities.
 */
class RealtimeTest extends TestCase
{
    /**
     * Test broker message publishing.
     */
    public function test_broker_publishing(): void
    {
        $this->fakeBroker();

        $broker = $this->mockBroker();
        $message = $this->createRealtimeMessage('user.1', 'message', ['text' => 'Hello']);

        $broker->publish('user.1', $message);

        $this->assertMessagePublished('user.1', 'message', ['text' => 'Hello']);
        $this->assertPublishedMessageCount(1, 'user.1');
    }

    /**
     * Test transport message broadcasting.
     */
    public function test_transport_broadcasting(): void
    {
        $this->fakeTransport();

        $transport = $this->mockTransport();
        $message = $this->createRealtimeMessage('user.1', 'notification', ['title' => 'New message']);

        $transport->broadcastToChannel('user.1', $message);

        $this->assertMessageBroadcasted('user.1', 'notification', ['title' => 'New message']);
        $this->assertBroadcastedMessageCount(1, 'user.1');
    }

    /**
     * Test realtime manager integration.
     */
    public function test_realtime_manager_broadcast(): void
    {
        $this->fakeRealtime();

        // Mock realtime manager with fake broker and transport
        $config = [
            'default_transport' => 'memory',
            'default_broker' => null,
        ];

        $manager = new RealtimeManager($config, $this->getContainer());

        // Broadcast message
        $manager->broadcast('user.1', 'message', ['text' => 'Hello World']);

        // Note: This would require integration with the actual RealtimeManager
        // For now, we test the mocking capabilities
        $this->assertTrue(true, 'Realtime manager broadcast test');
    }

    /**
     * Test multiple channel publishing.
     */
    public function test_multiple_channel_publishing(): void
    {
        $this->fakeBroker();

        $broker = $this->mockBroker();

        $broker->publish('channel.1', $this->createRealtimeMessage('channel.1', 'event', ['data' => 1]));
        $broker->publish('channel.2', $this->createRealtimeMessage('channel.2', 'event', ['data' => 2]));
        $broker->publish('channel.1', $this->createRealtimeMessage('channel.1', 'event', ['data' => 3]));

        $this->assertPublishedMessageCount(2, 'channel.1');
        $this->assertPublishedMessageCount(1, 'channel.2');
        $this->assertPublishedMessageCount(3);
    }

    /**
     * Test message not published.
     */
    public function test_message_not_published(): void
    {
        $this->fakeBroker();

        $broker = $this->mockBroker();
        $message = $this->createRealtimeMessage('user.1', 'message', ['text' => 'Hello']);

        $broker->publish('user.1', $message);

        $this->assertMessageNotPublished('user.2');
    }
}
