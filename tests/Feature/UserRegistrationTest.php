<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'landlord']);
        Role::create(['name' => 'renter']);
        Role::create(['name' => 'admin']);
    }

    /** @test */
    public function user_can_register_as_landlord()
    {
        $response = $this->post('/register', [
            'name' => 'Juan dela Cruz',
            'email' => 'juan@cdo.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'landlord'
        ]);

        $response->assertRedirect('/dashboard');
        
        $user = User::where('email', 'juan@cdo.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('landlord'));
    }

    /** @test */
    public function user_can_register_as_renter()
    {
        $response = $this->post('/register', [
            'name' => 'Maria Santos',
            'email' => 'maria@cdo.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'renter'
        ]);

        $response->assertRedirect('/dashboard');
        
        $user = User::where('email', 'maria@cdo.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('renter'));
    }

    /** @test */
    public function registration_requires_user_type()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@cdo.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
            // Missing user_type
        ]);

        $response->assertSessionHasErrors(['user_type']);
    }

    /** @test */
    public function landlord_redirects_to_owner_dashboard()
    {
        $landlord = User::factory()->create();
        $landlord->assignRole('landlord');

        $response = $this->actingAs($landlord)->get('/dashboard');
        
        $response->assertRedirect('/owner/dashboard');
    }

    /** @test */
    public function renter_redirects_to_renter_dashboard()
    {
        $renter = User::factory()->create();
        $renter->assignRole('renter');

        $response = $this->actingAs($renter)->get('/dashboard');
        
        $response->assertRedirect('/renter/dashboard');
    }
}
