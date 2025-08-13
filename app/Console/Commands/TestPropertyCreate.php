<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Property;
use Illuminate\Support\Facades\Auth;

class TestPropertyCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-property-create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test property creation functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get the latest landlord user
        $landlord = User::whereHas('roles', function($query) {
            $query->where('name', 'landlord');
        })->latest()->first();

        if (!$landlord) {
            $this->error('No landlord user found!');
            return;
        }

        $this->info("Testing with landlord: {$landlord->name} ({$landlord->email})");

        // Simulate login
        Auth::login($landlord);

        $this->info('User logged in successfully');

        // Test authorization
        try {
            $canCreate = $landlord->can('create', Property::class);
            $this->info("Can create property: " . ($canCreate ? 'YES' : 'NO'));
        } catch (\Exception $e) {
            $this->error("Authorization error: " . $e->getMessage());
        }

        // Test getting property types
        try {
            $propertyTypes = Property::getPropertyTypes();
            $this->info("Property types count: " . count($propertyTypes));
            $this->info("Property types: " . implode(', ', array_keys($propertyTypes)));
        } catch (\Exception $e) {
            $this->error("Property types error: " . $e->getMessage());
        }

        // Test getting amenities
        try {
            $amenities = Property::getAvailableAmenities();
            $this->info("Amenities count: " . count($amenities));
        } catch (\Exception $e) {
            $this->error("Amenities error: " . $e->getMessage());
        }

        // Test getting cancellation policies
        try {
            $policies = Property::getCancellationPolicies();
            $this->info("Cancellation policies count: " . count($policies));
            $this->info("Policies: " . implode(', ', array_keys($policies)));
        } catch (\Exception $e) {
            $this->error("Cancellation policies error: " . $e->getMessage());
        }

        $this->info('Test completed!');
    }
}
