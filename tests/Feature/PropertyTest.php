<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Property;
use Spatie\Permission\Models\Role;

class PropertyTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'landlord']);
        Role::create(['name' => 'renter']);
        Role::create(['name' => 'admin']);
    }

    /** @test */
    public function landlord_can_create_property_in_cagayan_de_oro()
    {
        $landlord = User::factory()->create();
        $landlord->assignRole('landlord');

        $response = $this->actingAs($landlord)
            ->post('/properties', [
                'title' => 'Beautiful Condo in Cagayan de Oro',
                'description' => 'A modern condo in the heart of CDO',
                'location' => 'Divisoria, Cagayan de Oro City',
                'price_per_night' => 2500.00,
                'bedrooms' => 2,
                'bathrooms' => 1,
                'max_guests' => 4,
                'property_type' => 'condo',
                'amenities' => ['wifi', 'parking', 'aircon'],
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'cancellation_policy' => 'flexible'
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('properties', [
            'title' => 'Beautiful Condo in Cagayan de Oro',
            'location' => 'Divisoria, Cagayan de Oro City'
        ]);
    }

    /** @test */
    public function property_must_be_in_cagayan_de_oro_city()
    {
        $landlord = User::factory()->create();
        $landlord->assignRole('landlord');

        $response = $this->actingAs($landlord)
            ->post('/properties', [
                'title' => 'Property Outside CDO',
                'description' => 'A property outside Cagayan de Oro',
                'location' => 'Manila, Philippines', // Outside CDO
                'price_per_night' => 2500.00,
                'bedrooms' => 2,
                'bathrooms' => 1,
                'max_guests' => 4,
                'property_type' => 'condo',
                'amenities' => ['wifi'],
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'cancellation_policy' => 'flexible'
            ]);

        $response->assertSessionHasErrors(['location']);
    }

    /** @test */
    public function only_landlords_can_create_properties()
    {
        $renter = User::factory()->create();
        $renter->assignRole('renter');

        $response = $this->actingAs($renter)
            ->post('/properties', [
                'title' => 'Unauthorized Property',
                'location' => 'Cagayan de Oro City'
            ]);

        $response->assertForbidden();
    }

    /** @test */
    public function property_requires_valid_cdo_location()
    {
        $landlord = User::factory()->create();
        $landlord->assignRole('landlord');

        $validCdoLocations = [
            'Carmen, Cagayan de Oro City',
            'Divisoria, Cagayan de Oro City', 
            'Lapasan, Cagayan de Oro City',
            'Cogon, Cagayan de Oro City',
            'Gusa, Cagayan de Oro City'
        ];

        foreach ($validCdoLocations as $location) {
            $response = $this->actingAs($landlord)
                ->post('/properties', [
                    'title' => 'CDO Property',
                    'description' => 'Test property',
                    'location' => $location,
                    'price_per_night' => 2000,
                    'bedrooms' => 1,
                    'bathrooms' => 1,
                    'max_guests' => 2,
                    'property_type' => 'apartment'
                ]);

            $this->assertDatabaseHas('properties', [
                'location' => $location
            ]);
        }
    }

    /** @test */
    public function user_can_search_properties_in_cdo()
    {
        $user = User::factory()->create();
        
        // Create properties in CDO
        Property::factory()->create([
            'title' => 'Beach House CDO',
            'location' => 'Carmen, Cagayan de Oro City',
            'is_active' => true
        ]);

        Property::factory()->create([
            'title' => 'City Condo CDO',
            'location' => 'Divisoria, Cagayan de Oro City', 
            'is_active' => true
        ]);

        $response = $this->actingAs($user)
            ->get('/properties?search=CDO');

        $response->assertStatus(200);
        $response->assertSee('Beach House CDO');
        $response->assertSee('City Condo CDO');
    }
}
