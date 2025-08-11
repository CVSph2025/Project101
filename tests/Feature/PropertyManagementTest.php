<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PropertyManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $landlord;
    protected User $renter;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'landlord']);
        Role::create(['name' => 'renter']);
        Role::create(['name' => 'admin']);

        // Create test users
        $this->landlord = User::factory()->create();
        $this->landlord->assignRole('landlord');

        $this->renter = User::factory()->create();
        $this->renter->assignRole('renter');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        Storage::fake('public');
    }

    /** @test */
    public function landlord_can_create_property()
    {
        $propertyData = [
            'title' => 'Beautiful Apartment',
            'description' => 'A lovely apartment in the heart of the city.',
            'location' => 'Manila, Philippines',
            'price_per_night' => 100.00,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'max_guests' => 4,
            'property_type' => 'apartment',
            'amenities' => ['wifi', 'kitchen'],
            'house_rules' => 'No smoking',
            'check_in_time' => '15:00',
            'check_out_time' => '11:00',
            'cancellation_policy' => 'flexible',
            'instant_book' => true,
            'images' => [
                UploadedFile::fake()->image('property1.jpg', 800, 600),
            ],
        ];

        $response = $this->actingAs($this->landlord)
            ->post(route('properties.store'), $propertyData);

        $response->assertRedirect();
        $this->assertDatabaseHas('properties', [
            'title' => 'Beautiful Apartment',
            'user_id' => $this->landlord->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function renter_cannot_create_property()
    {
        $propertyData = [
            'title' => 'Unauthorized Property',
            'description' => 'This should fail',
            'location' => 'Manila, Philippines',
            'price_per_night' => 100.00,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'max_guests' => 4,
            'property_type' => 'apartment',
            'amenities' => ['wifi'],
            'house_rules' => 'No rules',
            'check_in_time' => '15:00',
            'check_out_time' => '11:00',
            'cancellation_policy' => 'flexible',
            'instant_book' => true,
            'images' => [
                UploadedFile::fake()->image('property1.jpg', 800, 600),
            ],
        ];

        $response = $this->actingAs($this->renter)
            ->post(route('properties.store'), $propertyData);

        $response->assertForbidden();
        $this->assertDatabaseMissing('properties', [
            'title' => 'Unauthorized Property',
        ]);
    }
}