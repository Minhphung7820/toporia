<?php

declare(strict_types=1);

namespace Tests\Feature;

use Toporia\Framework\Testing\TestCase;

/**
 * Example Feature Test
 *
 * Demonstrates feature testing capabilities.
 */
class ExampleFeatureTest extends TestCase
{
    /**
     * Test HTTP request.
     */
    public function test_http_request(): void
    {
        $response = $this->get('/api/users');

        // Set mock response content for testing
        $response->setContent(json_encode(['id' => 1, 'name' => 'John']));
        $response->setStatus(200);

        $this->assertSuccessful($response);
        $this->assertJsonResponse($response, ['id' => 1]);
    }

    /**
     * Test event firing.
     */
    public function test_event_firing(): void
    {
        $this->fakeEvents();

        // Dispatch event (would integrate with your event system)
        $this->recordEvent('user.created', ['user_id' => 1]);

        $this->assertEventFired('user.created');
        $this->assertEventNotFired('user.deleted');
    }

    /**
     * Test queue interaction.
     */
    public function test_queue_interaction(): void
    {
        $this->fakeQueue();

        // Push job (would integrate with your queue system)
        $this->recordJob('App\Jobs\ProcessOrder', ['order_id' => 123]);

        $this->assertJobPushed('App\Jobs\ProcessOrder');
        $this->assertJobNotPushed('App\Jobs\SendEmail');
    }

    /**
     * Test mail sending.
     */
    public function test_mail_sending(): void
    {
        $this->fakeMail();

        // Send mail (would integrate with your mail system)
        $this->recordMail('user@example.com', 'Welcome', 'Welcome to our app!');

        $this->assertMailSent('user@example.com');
        $this->assertMailNotSent('admin@example.com');
    }
}
