<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $propertyTypes = ['apartment', 'house', 'condo', 'villa', 'studio', 'townhouse'];
        $amenities = ['WiFi', 'Kitchen', 'Parking', 'Pool', 'Gym', 'Air Conditioning', 'Washer', 'TV'];
        $cancellationPolicies = ['flexible', 'moderate', 'strict'];
        
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(4),
            'location' => fake()->city() . ', ' . fake()->state(),
            'price_per_night' => fake()->numberBetween(50, 500),
            'image' => 'property-images/' . fake()->uuid() . '.jpg',
            'bedrooms' => fake()->numberBetween(1, 5),
            'bathrooms' => fake()->numberBetween(1, 3),
            'max_guests' => fake()->numberBetween(1, 10),
            'property_type' => fake()->randomElement($propertyTypes),
            'amenities' => fake()->randomElements($amenities, fake()->numberBetween(2, 6)),
            'house_rules' => fake()->paragraph(2),
            'check_in_time' => '15:00',
            'check_out_time' => '11:00',
            'cancellation_policy' => fake()->randomElement($cancellationPolicies),
            'instant_book' => fake()->boolean(30),
            'featured' => fake()->boolean(10),
            'is_active' => true,
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
        ];
    }

    /**
     * Create an inactive property
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a featured property
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    /**
     * Create a property with instant booking enabled
     */
    public function instantBook(): static
    {
        return $this->state(fn (array $attributes) => [
            'instant_book' => true,
        ]);
    }

    /**
     * Create an expensive property
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_per_night' => fake()->numberBetween(300, 1000),
        ]);
    }
}
