<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Property extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'location',
        'price_per_night',
        'image',
        'bedrooms',
        'bathrooms',
        'max_guests',
        'property_type',
        'amenities',
        'house_rules',
        'check_in_time',
        'check_out_time',
        'cancellation_policy',
        'instant_book',
        'featured',
        'is_active',
        'lat',
        'lng'
    ];

    protected $casts = [
        'price_per_night' => 'decimal:2',
        'amenities' => 'array',
        'instant_book' => 'boolean',
        'featured' => 'boolean',
        'is_active' => 'boolean',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'check_in_time' => 'datetime:H:i',
        'check_out_time' => 'datetime:H:i',
    ];

    /**
     * Get the user that owns the property
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bookings for the property
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the images for the property
     */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    /**
     * Get the reviews for the property
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the primary image for the property
     */
    public function primaryImage()
    {
        return $this->hasOne(PropertyImage::class)->where('is_primary', true);
    }

    /**
     * Scope a query to only include active properties.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include featured properties.
     */
    public function scopeFeatured(Builder $query): void
    {
        $query->where('featured', true);
    }

    /**
     * Scope a query to filter by property type.
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('property_type', $type);
    }

    /**
     * Scope a query to filter by price range.
     */
    public function scopePriceRange(Builder $query, float $min, float $max): void
    {
        $query->whereBetween('price_per_night', [$min, $max]);
    }

    /**
     * Scope a query to filter by location.
     */
    public function scopeLocation(Builder $query, string $location): void
    {
        $query->where('location', 'LIKE', "%{$location}%");
    }

    /**
     * Scope a query to filter by amenities.
     */
    public function scopeHasAmenities(Builder $query, array $amenities): void
    {
        foreach ($amenities as $amenity) {
            $query->whereJsonContains('amenities', $amenity);
        }
    }

    /**
     * Get available amenities list
     */
    public static function getAvailableAmenities(): array
    {
        return [
            'wifi' => 'Wi-Fi',
            'kitchen' => 'Kitchen',
            'washer' => 'Washer',
            'dryer' => 'Dryer',
            'air_conditioning' => 'Air Conditioning',
            'heating' => 'Heating',
            'pool' => 'Pool',
            'hot_tub' => 'Hot Tub',
            'parking' => 'Free Parking',
            'gym' => 'Gym',
            'tv' => 'TV',
            'workspace' => 'Dedicated Workspace',
            'smoking_allowed' => 'Smoking Allowed',
            'pets_allowed' => 'Pets Allowed',
            'events_allowed' => 'Events Allowed',
            'breakfast' => 'Breakfast',
            'laptop_friendly' => 'Laptop Friendly Workspace',
            'hair_dryer' => 'Hair Dryer',
            'iron' => 'Iron',
            'security_cameras' => 'Security Cameras',
            'first_aid_kit' => 'First Aid Kit',
            'fire_extinguisher' => 'Fire Extinguisher',
            'essentials' => 'Essentials (towels, bed sheets, soap, toilet paper)',
        ];
    }

    /**
     * Get property types list
     */
    public static function getPropertyTypes(): array
    {
        return [
            'apartment' => 'Apartment',
            'house' => 'House',
            'villa' => 'Villa',
            'condo' => 'Condo',
            'studio' => 'Studio',
            'loft' => 'Loft',
        ];
    }

    /**
     * Get cancellation policies list
     */
    public static function getCancellationPolicies(): array
    {
        return [
            'flexible' => 'Flexible - Free cancellation up to 24 hours before check-in',
            'moderate' => 'Moderate - Free cancellation up to 5 days before check-in',
            'strict' => 'Strict - Free cancellation up to 30 days before check-in',
        ];
    }

    /**
     * Check if property is available for given dates
     */
    public function isAvailable($startDate, $endDate): bool
    {
        return !$this->bookings()
            ->where('status', 'confirmed')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();
    }
}
