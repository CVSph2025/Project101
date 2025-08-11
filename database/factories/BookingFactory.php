<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Property;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+2 months');
        $endDate = Carbon::parse($startDate)->addDays(fake()->numberBetween(2, 14));
        $nights = Carbon::parse($startDate)->diffInDays($endDate);
        $pricePerNight = fake()->numberBetween(50, 300);
        $subtotal = $nights * $pricePerNight;
        $cleaningFee = 50;
        $serviceFee = $subtotal * 0.1;
        $taxes = ($subtotal + $serviceFee) * 0.12;
        
        return [
            'property_id' => Property::factory(),
            'user_id' => User::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_price' => $subtotal,
            'guest_count' => fake()->numberBetween(1, 4),
            'cleaning_fee' => $cleaningFee,
            'service_fee' => round($serviceFee, 2),
            'taxes' => round($taxes, 2),
            'status' => fake()->randomElement(['pending', 'confirmed', 'cancelled', 'completed']),
            'special_requests' => fake()->optional()->sentence(),
            'booking_source' => 'web',
            'confirmation_code' => 'HMG' . strtoupper(fake()->bothify('######')),
            'guest_details' => [
                'names' => [fake()->name()],
                'phone' => fake()->phoneNumber(),
                'emergency_contact' => fake()->phoneNumber(),
            ],
            'cancellation_policy' => fake()->randomElement(['flexible', 'moderate', 'strict']),
            'host_notes' => fake()->optional()->sentence(),
            'message_count' => fake()->numberBetween(0, 5),
            'check_in_method' => fake()->randomElement(['self_checkin', 'meet_host', 'lockbox']),
            'is_extended' => false,
            'extension_fee' => 0,
            'review_reminder_sent' => false,
            'has_pets' => fake()->boolean(20),
            'booking_metadata' => [
                'user_agent' => fake()->userAgent(),
                'ip_address' => fake()->ipv4(),
                'device_type' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            ],
        ];
    }

    /**
     * Create a pending booking
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'confirmed_at' => null,
        ]);
    }

    /**
     * Create a confirmed booking
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Create a cancelled booking
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Create a completed booking
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'confirmed_at' => now()->subDays(10),
            'actual_check_in' => now()->subDays(7),
            'actual_check_out' => now()->subDays(3),
        ]);
    }

    /**
     * Create a future booking
     */
    public function future(): static
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+2 months');
        $endDate = Carbon::parse($startDate)->addDays(fake()->numberBetween(2, 7));
        
        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Create a past booking
     */
    public function past(): static
    {
        $startDate = fake()->dateTimeBetween('-2 months', '-1 week');
        $endDate = Carbon::parse($startDate)->addDays(fake()->numberBetween(2, 7));
        
        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'completed',
        ]);
    }
}
