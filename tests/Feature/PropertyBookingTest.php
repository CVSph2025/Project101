<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Property;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Tests\TestCase;

class PropertyBookingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $landlord;
    protected User $renter;
    protected User $admin;
    protected Property $property;

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

        // Create a test property
        $this->property = Property::factory()->create([
            'user_id' => $this->landlord->id,
            'price_per_night' => 100,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function renter_can_create_booking_request()
    {
        $bookingData = [
            'property_id' => $this->property->id,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            'special_requests' => 'Early check-in please',
        ];

        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), $bookingData);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->renter->id,
            'property_id' => $this->property->id,
            'status' => 'pending',
            'total_price' => 300, // 3 nights * $100
        ]);
    }

    /** @test */
    public function landlord_cannot_book_their_own_property()
    {
        $bookingData = [
            'property_id' => $this->property->id,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->landlord)
            ->post(route('bookings.store'), $bookingData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('bookings', [
            'user_id' => $this->landlord->id,
            'property_id' => $this->property->id,
        ]);
    }

    /** @test */
    public function booking_requires_valid_dates()
    {
        // Test past start date
        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), [
                'property_id' => $this->property->id,
                'start_date' => Carbon::yesterday()->format('Y-m-d'),
                'end_date' => Carbon::tomorrow()->format('Y-m-d'),
            ]);

        $response->assertSessionHasErrors(['start_date']);

        // Test end date before start date
        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), [
                'property_id' => $this->property->id,
                'start_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
                'end_date' => Carbon::tomorrow()->format('Y-m-d'),
            ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    /** @test */
    public function booking_prevents_overlapping_dates()
    {
        // Create an existing booking
        $existingBooking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(3),
            'status' => 'confirmed',
        ]);

        // Try to create overlapping booking
        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), [
                'property_id' => $this->property->id,
                'start_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
                'end_date' => Carbon::tomorrow()->addDays(4)->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['dates']);
    }

    /** @test */
    public function cancelled_bookings_do_not_block_dates()
    {
        // Create a cancelled booking
        $cancelledBooking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(3),
            'status' => 'cancelled',
        ]);

        // Should be able to book the same dates
        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), [
                'property_id' => $this->property->id,
                'start_date' => Carbon::tomorrow()->format('Y-m-d'),
                'end_date' => Carbon::tomorrow()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->renter->id,
            'property_id' => $this->property->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function landlord_can_confirm_booking()
    {
        $booking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->landlord)
            ->patch(route('bookings.confirm', $booking));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
        $this->assertNotNull($booking->fresh()->confirmed_at);
    }

    /** @test */
    public function only_property_owner_can_confirm_booking()
    {
        $otherLandlord = User::factory()->create();
        $otherLandlord->assignRole('landlord');

        $booking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($otherLandlord)
            ->patch(route('bookings.confirm', $booking));

        $response->assertStatus(403);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function booking_can_be_cancelled()
    {
        $booking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->landlord)
            ->patch(route('bookings.cancel', $booking));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
        $this->assertNotNull($booking->fresh()->cancelled_at);
    }

    /** @test */
    public function guest_can_cancel_their_own_booking()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->renter->id,
            'property_id' => $this->property->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->renter)
            ->patch(route('bookings.cancel', $booking));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    /** @test */
    public function booking_total_price_is_calculated_correctly()
    {
        $nights = 5;
        $startDate = Carbon::tomorrow();
        $endDate = $startDate->copy()->addDays($nights);

        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), [
                'property_id' => $this->property->id,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $expectedTotal = $nights * $this->property->price_per_night;
        
        $this->assertDatabaseHas('bookings', [
            'property_id' => $this->property->id,
            'total_price' => $expectedTotal,
        ]);
    }

    /** @test */
    public function booking_includes_service_fees_and_taxes()
    {
        $booking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'total_price' => 500, // base price
        ]);

        // Test calculation method
        $calculation = Booking::calculateTotalPrice($this->property, 
            Carbon::tomorrow(), 
            Carbon::tomorrow()->addDays(5)
        );

        $this->assertArrayHasKey('cleaning_fee', $calculation);
        $this->assertArrayHasKey('service_fee', $calculation);
        $this->assertArrayHasKey('taxes', $calculation);
        $this->assertArrayHasKey('total', $calculation);
        $this->assertGreaterThan($calculation['subtotal'], $calculation['total']);
    }

    /** @test */
    public function confirmation_code_is_generated_for_bookings()
    {
        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), [
                'property_id' => $this->property->id,
                'start_date' => Carbon::tomorrow()->format('Y-m-d'),
                'end_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $booking = Booking::where('user_id', $this->renter->id)->latest()->first();
        
        $this->assertNotNull($booking->confirmation_code);
        $this->assertStringStartsWith('HMG', $booking->confirmation_code);
    }

    /** @test */
    public function booking_can_be_updated_before_confirmation()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->renter->id,
            'property_id' => $this->property->id,
            'status' => 'pending',
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(3),
        ]);

        $newStartDate = Carbon::tomorrow()->addDays(5);
        $newEndDate = Carbon::tomorrow()->addDays(8);

        $response = $this->actingAs($this->renter)
            ->patch(route('bookings.update', $booking), [
                'start_date' => $newStartDate->format('Y-m-d'),
                'end_date' => $newEndDate->format('Y-m-d'),
                'special_requests' => 'Updated request',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'start_date' => $newStartDate->format('Y-m-d'),
            'end_date' => $newEndDate->format('Y-m-d'),
            'special_requests' => 'Updated request',
        ]);
    }

    /** @test */
    public function confirmed_booking_cannot_be_updated()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->renter->id,
            'property_id' => $this->property->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->renter)
            ->patch(route('bookings.update', $booking), [
                'special_requests' => 'Should not be updated',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function booking_cancellation_respects_policy()
    {
        // Create property with strict cancellation policy
        $strictProperty = Property::factory()->create([
            'user_id' => $this->landlord->id,
            'cancellation_policy' => 'strict',
        ]);

        $booking = Booking::factory()->create([
            'property_id' => $strictProperty->id,
            'start_date' => Carbon::tomorrow()->addDays(3), // Less than 7 days
            'status' => 'confirmed',
            'total_price' => 500,
        ]);

        $cancellationFee = $booking->calculateCancellationFee();
        
        // For strict policy, should be full amount if less than 7 days
        $this->assertEquals(500, $cancellationFee);
    }

    /** @test */
    public function guest_details_are_stored_with_booking()
    {
        $bookingData = [
            'property_id' => $this->property->id,
            'start_date' => Carbon::tomorrow()->format('Y-m-d'),
            'end_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'guest_details' => [
                'names' => ['John Doe', 'Jane Doe'],
                'phone' => '+639123456789',
                'emergency_contact' => '+639987654321',
            ],
        ];

        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), $bookingData);

        $response->assertRedirect();
        $booking = Booking::where('user_id', $this->renter->id)->latest()->first();
        
        $this->assertEquals(['John Doe', 'Jane Doe'], $booking->guest_details['names']);
        $this->assertEquals('+639123456789', $booking->guest_details['phone']);
    }

    /** @test */
    public function booking_status_progression_is_correct()
    {
        $booking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'status' => 'pending',
        ]);

        // Pending -> Confirmed
        $this->assertTrue($booking->status === 'pending');
        
        $booking->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $this->assertTrue($booking->fresh()->status === 'confirmed');
        
        // Can be cancelled from confirmed
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->assertTrue($booking->fresh()->status === 'cancelled');
    }

    /** @test */
    public function booking_notifications_are_sent()
    {
        // This would require notification testing setup
        // For now, just verify the booking process doesn't fail
        $response = $this->actingAs($this->renter)
            ->post(route('bookings.store'), [
                'property_id' => $this->property->id,
                'start_date' => Carbon::tomorrow()->format('Y-m-d'),
                'end_date' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    /** @test */
    public function admin_can_manage_any_booking()
    {
        $booking = Booking::factory()->create([
            'property_id' => $this->property->id,
            'status' => 'pending',
        ]);

        // Admin can confirm
        $response = $this->actingAs($this->admin)
            ->patch(route('bookings.confirm', $booking));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);

        // Admin can cancel
        $response = $this->actingAs($this->admin)
            ->patch(route('bookings.cancel', $booking));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }
}
