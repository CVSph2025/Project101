<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Property;

class PropertyModelTest extends TestCase
{
    /** @test */
    public function it_validates_cagayan_de_oro_location()
    {
        $validLocations = [
            'Carmen, Cagayan de Oro City',
            'Divisoria, Cagayan de Oro City',
            'Lapasan, Cagayan de Oro City',
            'Cogon, Cagayan de Oro City',
            'Gusa, Cagayan de Oro City',
            'Nazareth, Cagayan de Oro City',
            'Macasandig, Cagayan de Oro City'
        ];

        foreach ($validLocations as $location) {
            $this->assertTrue(Property::isValidCdoLocation($location));
        }
    }

    /** @test */
    public function it_rejects_non_cdo_locations()
    {
        $invalidLocations = [
            'Manila, Philippines',
            'Cebu City, Philippines',
            'Davao City, Philippines',
            'Butuan City, Agusan del Norte',
            'Iligan City, Lanao del Norte'
        ];

        foreach ($invalidLocations as $location) {
            $this->assertFalse(Property::isValidCdoLocation($location));
        }
    }

    /** @test */
    public function it_extracts_barangay_from_location()
    {
        $location = 'Carmen, Cagayan de Oro City';
        $barangay = Property::extractBarangay($location);
        
        $this->assertEquals('Carmen', $barangay);
    }

    /** @test */
    public function it_formats_price_correctly()
    {
        $property = new Property();
        $property->price_per_night = 2500.50;
        
        $this->assertEquals('₱2,500.50', $property->getFormattedPriceAttribute());
    }

    /** @test */
    public function it_checks_if_property_is_available()
    {
        $property = new Property();
        $property->is_active = true;
        
        $this->assertTrue($property->isAvailable());
        
        $property->is_active = false;
        $this->assertFalse($property->isAvailable());
    }
}
