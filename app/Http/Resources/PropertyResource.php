<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'property_type' => $this->property_type,
            'price_per_night' => $this->price_per_night,
            'currency' => 'PHP', // Or get from config
            'formatted_price' => '₱' . number_format($this->price_per_night, 2),
            'address' => [
                'full_address' => $this->address,
                'city' => $this->city,
                'country' => $this->country,
                'coordinates' => $this->when($this->latitude && $this->longitude, [
                    'latitude' => (float) $this->latitude,
                    'longitude' => (float) $this->longitude,
                ])
            ],
            'specifications' => [
                'bedrooms' => $this->bedrooms,
                'bathrooms' => $this->bathrooms,
                'max_guests' => $this->max_guests,
                'property_size' => $this->property_size,
            ],
            'amenities' => $this->amenities ? explode(',', $this->amenities) : [],
            'house_rules' => $this->house_rules,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'stay_requirements' => [
                'minimum_stay' => $this->minimum_stay,
                'maximum_stay' => $this->maximum_stay,
            ],
            'availability' => [
                'is_active' => $this->is_active,
                'instant_book' => $this->instant_book ?? false,
            ],
            'images' => PropertyImageResource::collection($this->whenLoaded('images')),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'ratings' => [
                'average_rating' => (float) $this->average_rating,
                'total_reviews' => $this->reviews_count ?? 0,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Include additional data based on user permissions
            $this->mergeWhen($this->isOwner($request->user()), [
                'analytics' => [
                    'total_bookings' => $this->bookings_count ?? 0,
                    'total_revenue' => $this->total_revenue ?? 0,
                    'occupancy_rate' => $this->calculateOccupancyRate(),
                    'average_stay_duration' => $this->calculateAverageStayDuration(),
                ],
                'management' => [
                    'calendar_sync_enabled' => $this->calendar_sync_enabled ?? false,
                    'auto_accept_bookings' => $this->auto_accept_bookings ?? false,
                    'cleaning_fee' => $this->cleaning_fee,
                    'security_deposit' => $this->security_deposit,
                ]
            ]),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'can_book' => $this->canBeBookedBy($request->user()),
                'can_edit' => $this->canBeEditedBy($request->user()),
                'can_delete' => $this->canBeDeletedBy($request->user()),
                'is_favorited' => $this->isFavoritedBy($request->user()),
            ]
        ];
    }

    /**
     * Check if the given user is the owner of this property
     */
    protected function isOwner($user): bool
    {
        return $user && $this->user_id === $user->id;
    }

    /**
     * Check if property can be booked by the user
     */
    protected function canBeBookedBy($user): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$user) {
            return true; // Guests can book
        }

        // Owners cannot book their own properties
        return $this->user_id !== $user->id;
    }

    /**
     * Check if property can be edited by the user
     */
    protected function canBeEditedBy($user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->user_id === $user->id || $user->hasRole(['admin', 'super-admin']);
    }

    /**
     * Check if property can be deleted by the user
     */
    protected function canBeDeletedBy($user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->user_id === $user->id || $user->hasRole(['admin', 'super-admin']);
    }

    /**
     * Check if property is favorited by the user
     */
    protected function isFavoritedBy($user): bool
    {
        if (!$user) {
            return false;
        }

        // Implement favorites relationship if exists
        return false; // Placeholder
    }

    /**
     * Calculate occupancy rate for property owners
     */
    protected function calculateOccupancyRate(): ?float
    {
        // This would typically be calculated from bookings data
        // Placeholder implementation
        return null;
    }

    /**
     * Calculate average stay duration for property owners
     */
    protected function calculateAverageStayDuration(): ?float
    {
        // This would typically be calculated from bookings data
        // Placeholder implementation
        return null;
    }
}
