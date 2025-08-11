<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Property;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionControlTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;
    protected User $landlord;
    protected User $renter;
    protected User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions
        $this->createRolesAndPermissions();

        // Create test users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->landlord = User::factory()->create();
        $this->landlord->assignRole('landlord');

        $this->renter = User::factory()->create();
        $this->renter->assignRole('renter');

        $this->guest = User::factory()->create();
        // No role assigned - guest user
    }

    private function createRolesAndPermissions(): void
    {
        // Create roles
        $adminRole = Role::create(['name' => 'admin']);
        $landlordRole = Role::create(['name' => 'landlord']);
        $renterRole = Role::create(['name' => 'renter']);

        // Create permissions
        $permissions = [
            // Property permissions
            'create-properties',
            'view-properties',
            'edit-properties',
            'delete-properties',
            'manage-all-properties',

            // Booking permissions
            'create-bookings',
            'view-bookings',
            'edit-bookings',
            'cancel-bookings',
            'confirm-bookings',
            'manage-all-bookings',

            // Payment permissions
            'view-payments',
            'process-refunds',
            'manage-all-payments',

            // User management permissions
            'view-users',
            'edit-users',
            'delete-users',
            'manage-roles',

            // Admin permissions
            'access-admin-panel',
            'view-analytics',
            'manage-system-settings',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign permissions to roles
        $adminRole->givePermissionTo($permissions);

        $landlordRole->givePermissionTo([
            'create-properties',
            'view-properties',
            'edit-properties',
            'delete-properties',
            'view-bookings',
            'confirm-bookings',
            'cancel-bookings',
            'view-payments',
            'process-refunds',
        ]);

        $renterRole->givePermissionTo([
            'view-properties',
            'create-bookings',
            'view-bookings',
            'edit-bookings',
            'cancel-bookings',
            'view-payments',
        ]);
    }

    /** @test */
    public function admin_can_access_admin_panel()
    {
        // Debug: Check if user has admin role
        $this->assertTrue($this->admin->hasRole('admin'), 'Admin user should have admin role');
        
        $response = $this->actingAs($this->admin)
            ->get('/admin/dashboard');

        // Debug: If it fails, check what middleware blocked it
        if ($response->status() !== 200) {
            dump('Response status: ' . $response->status());
            dump('Response content: ' . $response->getContent());
        }

        $response->assertStatus(200);
    }

    /** @test */
    public function non_admin_cannot_access_admin_panel()
    {
        $users = [$this->landlord, $this->renter, $this->guest];

        foreach ($users as $user) {
            $response = $this->actingAs($user)
                ->get('/admin/dashboard');

            $response->assertStatus(403);
        }
    }

    /** @test */
    public function landlord_can_create_properties()
    {
        $propertyData = Property::factory()->make()->toArray();

        $response = $this->actingAs($this->landlord)
            ->post(route('properties.store'), $propertyData);

        $response->assertRedirect();
        $this->assertDatabaseHas('properties', [
            'title' => $propertyData['title'],
            'user_id' => $this->landlord->id,
        ]);
    }

    /** @test */
    public function renter_cannot_create_properties()
    {
        $propertyData = Property::factory()->make()->toArray();

        $response = $this->actingAs($this->renter)
            ->post(route('properties.store'), $propertyData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('properties', [
            'title' => $propertyData['title'],
        ]);
    }

    /** @test */
    public function guest_cannot_create_properties()
    {
        $propertyData = Property::factory()->make()->toArray();

        $response = $this->actingAs($this->guest)
            ->post(route('properties.store'), $propertyData);

        $response->assertStatus(403);
    }

    /** @test */
    public function landlord_can_only_edit_their_own_properties()
    {
        $ownProperty = Property::factory()->create(['user_id' => $this->landlord->id]);
        $otherProperty = Property::factory()->create();

        // Can edit own property
        $response = $this->actingAs($this->landlord)
            ->patch(route('properties.update', $ownProperty), ['title' => 'Updated Title']);

        $response->assertRedirect();
        $this->assertDatabaseHas('properties', [
            'id' => $ownProperty->id,
            'title' => 'Updated Title',
        ]);

        // Cannot edit other's property
        $response = $this->actingAs($this->landlord)
            ->patch(route('properties.update', $otherProperty), ['title' => 'Unauthorized Update']);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('properties', [
            'id' => $otherProperty->id,
            'title' => 'Unauthorized Update',
        ]);
    }

    /** @test */
    public function admin_can_manage_all_properties()
    {
        $property = Property::factory()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('properties.update', $property), ['title' => 'Admin Updated']);

        $response->assertRedirect();
        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'title' => 'Admin Updated',
        ]);
    }

    /** @test */
    public function renter_can_create_bookings()
    {
        $property = Property::factory()->create();

        $bookingData = [
            'property_id' => $property->id,
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), $bookingData);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->renter->id,
            'property_id' => $property->id,
        ]);
    }

    /** @test */
    public function guest_cannot_create_bookings()
    {
        $property = Property::factory()->create();

        $bookingData = [
            'property_id' => $property->id,
            'start_date' => now()->addDays(1)->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->guest)
            ->post(route('bookings.store'), $bookingData);

        $response->assertStatus(403);
    }

    /** @test */
    public function landlord_can_confirm_bookings_for_their_properties()
    {
        $property = Property::factory()->create(['user_id' => $this->landlord->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->landlord)
            ->patch(route('bookings.confirm', $booking));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    /** @test */
    public function landlord_cannot_confirm_bookings_for_other_properties()
    {
        $otherProperty = Property::factory()->create();
        $booking = Booking::factory()->create([
            'property_id' => $otherProperty->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->landlord)
            ->patch(route('bookings.confirm', $booking));

        $response->assertStatus(403);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function renter_can_view_their_own_bookings()
    {
        $ownBooking = Booking::factory()->create(['user_id' => $this->renter->id]);
        $otherBooking = Booking::factory()->create();

        // Can view own booking
        $response = $this->actingAs($this->renter)
            ->get(route('bookings.show', $ownBooking));

        $response->assertStatus(200);

        // Cannot view other's booking
        $response = $this->actingAs($this->renter)
            ->get(route('bookings.show', $otherBooking));

        $response->assertStatus(403);
    }

    /** @test */
    public function users_can_only_view_properties_they_have_permission_for()
    {
        $activeProperty = Property::factory()->create(['is_active' => true]);
        $inactiveProperty = Property::factory()->create(['is_active' => false]);

        // All users can view active properties
        $users = [$this->admin, $this->landlord, $this->renter, $this->guest];

        foreach ($users as $user) {
            $response = $this->actingAs($user)
                ->get(route('properties.show', $activeProperty));

            $response->assertStatus(200);
        }

        // Only admin and property owner can view inactive properties
        $response = $this->actingAs($this->admin)
            ->get(route('properties.show', $inactiveProperty));
        $response->assertStatus(200);

        $response = $this->actingAs($inactiveProperty->user)
            ->get(route('properties.show', $inactiveProperty));
        $response->assertStatus(200);

        // Others cannot view inactive properties
        $response = $this->actingAs($this->renter)
            ->get(route('properties.show', $inactiveProperty));
        $response->assertStatus(403);
    }

    /** @test */
    public function role_based_dashboard_access_is_enforced()
    {
        // Admin dashboard
        $response = $this->actingAs($this->admin)
            ->get('/admin/dashboard');
        $response->assertStatus(200);

        // Landlord dashboard
        $response = $this->actingAs($this->landlord)
            ->get('/dashboard');
        $response->assertStatus(200);

        // Renter dashboard
        $response = $this->actingAs($this->renter)
            ->get('/dashboard');
        $response->assertStatus(200);

        // Cross-access should be denied
        $response = $this->actingAs($this->renter)
            ->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /** @test */
    public function api_endpoints_respect_permissions()
    {
        // Admin can access all API endpoints
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');
        $response->assertStatus(200);

        // Regular users cannot access admin API
        $response = $this->actingAs($this->renter)
            ->getJson('/api/admin/users');
        $response->assertStatus(403);
    }

    /** @test */
    public function permission_middleware_blocks_unauthorized_actions()
    {
        // Test property deletion permission
        $property = Property::factory()->create();

        // Admin can delete any property
        $response = $this->actingAs($this->admin)
            ->delete(route('properties.destroy', $property));
        $response->assertRedirect();

        // Recreate property for next test
        $property = Property::factory()->create(['user_id' => $this->landlord->id]);

        // Owner can delete their property
        $response = $this->actingAs($this->landlord)
            ->delete(route('properties.destroy', $property));
        $response->assertRedirect();

        // Recreate property for next test
        $property = Property::factory()->create();

        // Renter cannot delete any property
        $response = $this->actingAs($this->renter)
            ->delete(route('properties.destroy', $property));
        $response->assertStatus(403);
    }

    /** @test */
    public function super_admin_permissions_override_all_restrictions()
    {
        // Create super admin role
        $superAdminRole = Role::create(['name' => 'super-admin']);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        // Super admin should have access to everything
        $property = Property::factory()->create();
        $booking = Booking::factory()->create();

        $endpoints = [
            ['GET', route('properties.show', $property)],
            ['PATCH', route('properties.update', $property)],
            ['DELETE', route('properties.destroy', $property)],
            ['GET', route('bookings.show', $booking)],
            ['PATCH', route('bookings.confirm', $booking)],
            ['GET', '/admin'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->actingAs($superAdmin)->call($method, $url);
            $this->assertNotEquals(403, $response->getStatusCode(), 
                "Super admin should have access to {$method} {$url}");
        }
    }

    /** @test */
    public function guest_permissions_are_most_restrictive()
    {
        $property = Property::factory()->create();
        $booking = Booking::factory()->create();

        $restrictedActions = [
            ['POST', route('properties.store')],
            ['PATCH', route('properties.update', $property)],
            ['DELETE', route('properties.destroy', $property)],
            ['POST', route('bookings.store')],
            ['PATCH', route('bookings.confirm', $booking)],
            ['GET', '/admin'],
            ['GET', route('bookings.show', $booking)],
        ];

        foreach ($restrictedActions as [$method, $url]) {
            $response = $this->actingAs($this->guest)->call($method, $url);
            $this->assertEquals(403, $response->getStatusCode(), 
                "Guest should not have access to {$method} {$url}");
        }
    }

    /** @test */
    public function permission_caching_works_correctly()
    {
        // First request should hit the database
        $response = $this->actingAs($this->landlord)
            ->get(route('properties.create'));
        $response->assertStatus(200);

        // Second request should use cached permissions
        $response = $this->actingAs($this->landlord)
            ->get(route('properties.create'));
        $response->assertStatus(200);
    }

    /** @test */
    public function role_changes_take_effect_immediately()
    {
        $user = User::factory()->create();
        $user->assignRole('renter');

        // As renter, cannot create properties
        $response = $this->actingAs($user)
            ->get(route('properties.create'));
        $response->assertStatus(403);

        // Promote to landlord
        $user->removeRole('renter');
        $user->assignRole('landlord');

        // Should now be able to create properties
        $response = $this->actingAs($user)
            ->get(route('properties.create'));
        $response->assertStatus(200);
    }

    /** @test */
    public function resource_based_permissions_work_with_policies()
    {
        $landlord1 = User::factory()->create();
        $landlord1->assignRole('landlord');
        
        $landlord2 = User::factory()->create();
        $landlord2->assignRole('landlord');

        $property1 = Property::factory()->create(['user_id' => $landlord1->id]);
        $property2 = Property::factory()->create(['user_id' => $landlord2->id]);

        // Landlord1 can edit their property
        $response = $this->actingAs($landlord1)
            ->get(route('properties.edit', $property1));
        $response->assertStatus(200);

        // Landlord1 cannot edit landlord2's property
        $response = $this->actingAs($landlord1)
            ->get(route('properties.edit', $property2));
        $response->assertStatus(403);
    }
}
