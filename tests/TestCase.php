<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Prevent actual external API calls during testing
        $this->mockExternalServices();
        
        // Set up test-specific configurations
        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);
    }

    /**
     * Mock external services to prevent actual API calls during testing
     */
    protected function mockExternalServices(): void
    {
        // Mock Stripe
        $this->mock(\Stripe\PaymentIntent::class, function ($mock) {
            $mock->shouldReceive('create')->andReturn((object) [
                'id' => 'pi_test_123',
                'client_secret' => 'pi_test_123_secret',
                'status' => 'requires_payment_method',
                'amount' => 10000,
                'currency' => 'php',
            ]);
        });

        // Mock external notification services
        $this->mock(\App\Services\SecurityService::class, function ($mock) {
            $mock->shouldReceive('logSecurityEvent')->andReturn(true);
            $mock->shouldReceive('checkRateLimit')->andReturn(false);
            $mock->shouldReceive('detectSuspiciousActivity')->andReturn(false);
        });
    }

    /**
     * Create a test user with specific role
     */
    protected function createUserWithRole(string $role = 'renter', array $attributes = []): \App\Models\User
    {
        $user = \App\Models\User::factory()->create($attributes);
        $user->assignRole($role);
        return $user;
    }

    /**
     * Create a test property
     */
    protected function createProperty(array $attributes = []): \App\Models\Property
    {
        $owner = $this->createUserWithRole('landlord');
        
        return \App\Models\Property::factory()->create(array_merge([
            'user_id' => $owner->id,
        ], $attributes));
    }

    /**
     * Create a test booking
     */
    protected function createBooking(array $attributes = []): \App\Models\Booking
    {
        $property = $this->createProperty();
        $renter = $this->createUserWithRole('renter');
        
        return \App\Models\Booking::factory()->create(array_merge([
            'property_id' => $property->id,
            'user_id' => $renter->id,
        ], $attributes));
    }

    /**
     * Assert API response structure
     */
    protected function assertApiResponse($response, int $statusCode = 200): void
    {
        $response->assertStatus($statusCode);
        $response->assertJsonStructure([
            'success',
            'message',
            'timestamp',
            'request_id',
        ]);
    }

    /**
     * Assert API success response
     */
    protected function assertApiSuccess($response, string $message = null): void
    {
        $this->assertApiResponse($response, 200);
        $response->assertJson(['success' => true]);
        
        if ($message) {
            $response->assertJson(['message' => $message]);
        }
    }

    /**
     * Assert API error response
     */
    protected function assertApiError($response, int $statusCode = 400, string $errorCode = null): void
    {
        $this->assertApiResponse($response, $statusCode);
        $response->assertJson(['success' => false]);
        
        if ($errorCode) {
            $response->assertJson(['error_code' => $errorCode]);
        }
    }
}
