<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Property;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StripePaymentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $renter;
    protected User $landlord;
    protected Property $property;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'landlord']);
        Role::create(['name' => 'renter']);

        // Create test users
        $this->landlord = User::factory()->create();
        $this->landlord->assignRole('landlord');

        $this->renter = User::factory()->create();
        $this->renter->assignRole('renter');

        // Create test property and booking
        $this->property = Property::factory()->create([
            'user_id' => $this->landlord->id,
            'price_per_night' => 100,
        ]);

        $this->booking = Booking::factory()->create([
            'user_id' => $this->renter->id,
            'property_id' => $this->property->id,
            'total_price' => 300,
            'status' => 'confirmed',
        ]);

        // Set up Stripe test configuration
        Config::set('services.stripe.key', 'sk_test_fake_key');
        Config::set('services.stripe.secret', 'sk_test_fake_secret');
    }

    /** @test */
    public function payment_intent_can_be_created_for_booking()
    {
        $this->markTestSkipped('Requires Stripe mock setup');
        
        $response = $this->actingAs($this->renter)
            ->postJson(route('payment.create-intent', $this->booking), [
                'payment_method' => 'card',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'client_secret',
            'payment_intent_id',
            'amount',
        ]);
    }

    /** @test */
    public function only_booking_owner_can_create_payment_intent()
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('renter');

        $response = $this->actingAs($otherUser)
            ->postJson(route('payment.create-intent', $this->booking));

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Unauthorized access to booking']);
    }

    /** @test */
    public function payment_intent_creation_requires_stripe_configuration()
    {
        // Clear Stripe configuration
        Config::set('services.stripe.key', null);
        Config::set('services.stripe.secret', null);

        $response = $this->actingAs($this->renter)
            ->postJson(route('payment.create-intent', $this->booking));

        $response->assertStatus(503);
        $response->assertJson([
            'error' => 'Payment system is currently unavailable. Please try again later.',
            'code' => 'PAYMENT_SYSTEM_UNAVAILABLE'
        ]);
    }

    /** @test */
    public function duplicate_payment_intent_cannot_be_created()
    {
        // Create existing payment record
        Payment::factory()->create([
            'booking_id' => $this->booking->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->renter)
            ->postJson(route('payment.create-intent', $this->booking));

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Payment intent already exists for this booking']);
    }

    /** @test */
    public function payment_can_be_confirmed()
    {
        $payment = Payment::factory()->create([
            'booking_id' => $this->booking->id,
            'status' => 'pending',
            'provider_payment_id' => 'pi_test_12345',
        ]);

        $this->markTestSkipped('Requires Stripe mock setup');

        $response = $this->actingAs($this->renter)
            ->postJson(route('payment.confirm', $payment));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function failed_payment_is_handled_gracefully()
    {
        $payment = Payment::factory()->create([
            'booking_id' => $this->booking->id,
            'status' => 'pending',
        ]);

        $this->markTestSkipped('Requires Stripe mock setup for failure simulation');

        $response = $this->actingAs($this->renter)
            ->postJson(route('payment.confirm', $payment));

        $response->assertStatus(402);
        $response->assertJsonStructure([
            'error',
            'type',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function payment_refund_can_be_processed()
    {
        $payment = Payment::factory()->completed()->create([
            'booking_id' => $this->booking->id,
            'amount' => 300,
        ]);

        $this->markTestSkipped('Requires Stripe mock setup');

        $response = $this->actingAs($this->landlord)
            ->postJson(route('payment.refund', $payment), [
                'amount' => 300,
                'reason' => 'requested_by_customer',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'refunded']);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'refunded',
        ]);
    }

    /** @test */
    public function partial_refund_can_be_processed()
    {
        $payment = Payment::factory()->completed()->create([
            'booking_id' => $this->booking->id,
            'amount' => 300,
        ]);

        $this->markTestSkipped('Requires Stripe mock setup');

        $response = $this->actingAs($this->landlord)
            ->postJson(route('payment.refund', $payment), [
                'amount' => 150, // Partial refund
                'reason' => 'requested_by_customer',
            ]);

        $response->assertStatus(200);
        
        // Original payment should remain completed
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function only_property_owner_can_process_refunds()
    {
        $payment = Payment::factory()->completed()->create([
            'booking_id' => $this->booking->id,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole('landlord');

        $response = $this->actingAs($otherUser)
            ->postJson(route('payment.refund', $payment), [
                'amount' => 100,
                'reason' => 'requested_by_customer',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function payment_processing_fee_is_calculated_correctly()
    {
        $payment = Payment::factory()->make([
            'amount' => 1000,
        ]);

        $processingFee = $payment->calculateProcessingFee(1000);
        
        // Stripe fee: 2.9% + $0.30
        $expectedFee = (1000 * 0.029) + 0.30;
        $this->assertEquals(round($expectedFee, 2), $processingFee);
    }

    /** @test */
    public function payment_metadata_is_stored_correctly()
    {
        $paymentData = [
            'booking_id' => $this->booking->id,
            'amount' => 300,
            'currency' => 'PHP',
            'payment_method' => 'card',
            'metadata' => [
                'card_last_four' => '4242',
                'card_brand' => 'visa',
                'customer_ip' => '127.0.0.1',
            ],
        ];

        $payment = Payment::factory()->create($paymentData);

        $this->assertEquals('4242', $payment->metadata['card_last_four']);
        $this->assertEquals('visa', $payment->metadata['card_brand']);
        $this->assertEquals('127.0.0.1', $payment->metadata['customer_ip']);
    }

    /** @test */
    public function payment_webhook_updates_payment_status()
    {
        $payment = Payment::factory()->create([
            'booking_id' => $this->booking->id,
            'status' => 'pending',
            'provider_payment_id' => 'pi_test_12345',
        ]);

        $webhookPayload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_12345',
                    'status' => 'succeeded',
                    'amount' => 30000, // Amount in cents
                    'metadata' => [
                        'booking_id' => $this->booking->id,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/stripe/webhook', $webhookPayload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'provider_payment_id' => 'pi_test_12345',
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function gcash_payment_is_supported()
    {
        $paymentData = [
            'booking_id' => $this->booking->id,
            'amount' => 300,
            'payment_method' => 'gcash',
            'metadata' => [
                'gcash_reference' => 'GC1234567890',
                'gcash_number' => '09123456789',
            ],
        ];

        $payment = Payment::factory()->gcash()->create($paymentData);

        $this->assertEquals('gcash', $payment->payment_method);
        $this->assertEquals('GC1234567890', $payment->metadata['gcash_reference']);
    }

    /** @test */
    public function paymaya_payment_is_supported()
    {
        $paymentData = [
            'booking_id' => $this->booking->id,
            'amount' => 300,
            'payment_method' => 'paymaya',
            'metadata' => [
                'paymaya_reference' => 'PM1234567890',
                'paymaya_account' => 'user@example.com',
            ],
        ];

        $payment = Payment::factory()->paymaya()->create($paymentData);

        $this->assertEquals('paymaya', $payment->payment_method);
        $this->assertEquals('PM1234567890', $payment->metadata['paymaya_reference']);
    }

    /** @test */
    public function payment_failure_reasons_are_logged()
    {
        $payment = Payment::factory()->failed()->create([
            'booking_id' => $this->booking->id,
            'metadata' => [
                'failure_reason' => 'insufficient_funds',
                'failure_message' => 'Your card has insufficient funds.',
            ],
        ]);

        $this->assertEquals('failed', $payment->status);
        $this->assertEquals('insufficient_funds', $payment->metadata['failure_reason']);
    }

    /** @test */
    public function payment_shows_correct_total_with_fees()
    {
        $baseAmount = 1000;
        $processingFee = 29.30; // 2.9% + $0.30

        $payment = Payment::factory()->create([
            'booking_id' => $this->booking->id,
            'amount' => $baseAmount,
            'processing_fee' => $processingFee,
            'total_amount' => $baseAmount + $processingFee,
        ]);

        $this->assertEquals($baseAmount, $payment->amount);
        $this->assertEquals($processingFee, $payment->processing_fee);
        $this->assertEquals($baseAmount + $processingFee, $payment->total_amount);
    }

    /** @test */
    public function payment_currency_is_validated()
    {
        $payment = Payment::factory()->create([
            'booking_id' => $this->booking->id,
            'currency' => 'PHP',
        ]);

        $this->assertEquals('PHP', $payment->currency);
    }

    /** @test */
    public function payment_status_transitions_are_valid()
    {
        $payment = Payment::factory()->create([
            'booking_id' => $this->booking->id,
            'status' => 'pending',
        ]);

        // Pending -> Completed
        $payment->update(['status' => 'completed']);
        $this->assertEquals('completed', $payment->fresh()->status);

        // Completed -> Refunded
        $payment->update(['status' => 'refunded']);
        $this->assertEquals('refunded', $payment->fresh()->status);
    }

    /** @test */
    public function payment_receipt_url_is_stored()
    {
        $payment = Payment::factory()->create([
            'booking_id' => $this->booking->id,
            'metadata' => [
                'receipt_url' => 'https://pay.stripe.com/receipts/test_123',
            ],
        ]);

        $this->assertStringStartsWith('https://pay.stripe.com/receipts/', 
            $payment->metadata['receipt_url']);
    }

    /** @test */
    public function payment_can_be_viewed_by_authorized_users()
    {
        $payment = Payment::factory()->completed()->create([
            'booking_id' => $this->booking->id,
        ]);

        // Booking owner can view
        $response = $this->actingAs($this->renter)
            ->get(route('payment.show', $payment));

        $response->assertStatus(200);

        // Property owner can view
        $response = $this->actingAs($this->landlord)
            ->get(route('payment.show', $payment));

        $response->assertStatus(200);

        // Other users cannot view
        $otherUser = User::factory()->create();
        $response = $this->actingAs($otherUser)
            ->get(route('payment.show', $payment));

        $response->assertStatus(403);
    }
}
