<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 1000);
        $processingFee = round($amount * 0.029 + 0.30, 2); // Stripe fee structure
        
        return [
            'booking_id' => Booking::factory(),
            'amount' => $amount,
            'currency' => 'PHP',
            'payment_method' => fake()->randomElement(['card', 'bank_transfer', 'gcash', 'paymaya']),
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_' . fake()->bothify('?????????????????'),
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'refunded']),
            'processing_fee' => $processingFee,
            'total_amount' => $amount + $processingFee,
            'metadata' => [
                'card_last_four' => fake()->numerify('****'),
                'card_brand' => fake()->randomElement(['visa', 'mastercard', 'american_express']),
                'payment_intent_id' => 'pi_' . fake()->bothify('?????????????????'),
                'receipt_url' => fake()->url(),
            ],
        ];
    }

    /**
     * Create a pending payment
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'provider_payment_id' => null,
        ]);
    }

    /**
     * Create a completed payment
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'provider_payment_id' => 'pi_' . fake()->bothify('?????????????????'),
        ]);
    }

    /**
     * Create a failed payment
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'provider_payment_id' => null,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'failure_reason' => fake()->randomElement([
                    'insufficient_funds',
                    'card_declined',
                    'expired_card',
                    'processing_error'
                ]),
                'failure_message' => fake()->sentence(),
            ]),
        ]);
    }

    /**
     * Create a refunded payment
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'refund_id' => 're_' . fake()->bothify('?????????????????'),
                'refund_reason' => fake()->randomElement([
                    'requested_by_customer',
                    'duplicate',
                    'fraudulent'
                ]),
                'refunded_at' => now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Create a GCash payment
     */
    public function gcash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'gcash',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'gcash_reference' => fake()->numerify('GC##########'),
                'gcash_number' => '09' . fake()->numerify('#########'),
            ]),
        ]);
    }

    /**
     * Create a PayMaya payment
     */
    public function paymaya(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'paymaya',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'paymaya_reference' => fake()->numerify('PM##########'),
                'paymaya_account' => fake()->email(),
            ]),
        ]);
    }
}
