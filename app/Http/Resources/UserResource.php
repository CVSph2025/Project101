<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($this->shouldShowEmail($request), $this->email),
            'avatar' => $this->getAvatarUrl(),
            'profile' => [
                'bio' => $this->bio,
                'location' => $this->location,
                'languages' => $this->languages ? explode(',', $this->languages) : [],
                'verified' => $this->isVerified(),
            ],
            'roles' => $this->when($this->shouldShowRoles($request), 
                $this->roles->pluck('name')
            ),
            'permissions' => $this->when($this->shouldShowPermissions($request), 
                $this->getAllPermissions()->pluck('name')
            ),
            'host_since' => $this->created_at?->toDateString(),
            'last_seen' => $this->when($this->shouldShowLastSeen($request), 
                $this->last_seen_at?->toISOString()
            ),
            
            // Include sensitive data only for the user themselves or admins
            $this->mergeWhen($this->shouldShowSensitiveData($request), [
                'email_verified_at' => $this->email_verified_at?->toISOString(),
                'phone' => $this->phone,
                'phone_verified_at' => $this->phone_verified_at?->toISOString(),
                'identity_verified' => $this->identity_verified,
                'two_factor_enabled' => $this->two_factor_enabled ?? false,
                'preferences' => [
                    'notification_preferences' => $this->notification_preferences,
                    'privacy_settings' => $this->privacy_settings,
                    'recommendation_preferences' => $this->recommendation_preferences,
                ],
            ]),
        ];
    }

    /**
     * Get the user's avatar URL
     */
    protected function getAvatarUrl(): string
    {
        if ($this->avatar) {
            return filter_var($this->avatar, FILTER_VALIDATE_URL) 
                ? $this->avatar 
                : asset('storage/' . $this->avatar);
        }

        // Generate gravatar or default avatar
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }

    /**
     * Check if user is verified
     */
    protected function isVerified(): array
    {
        return [
            'email' => !is_null($this->email_verified_at),
            'phone' => !is_null($this->phone_verified_at),
            'identity' => $this->identity_verified ?? false,
        ];
    }

    /**
     * Determine if email should be shown
     */
    protected function shouldShowEmail(Request $request): bool
    {
        $currentUser = $request->user();
        
        if (!$currentUser) {
            return false;
        }

        // Show to self or admins
        return $this->id === $currentUser->id || 
               $currentUser->hasRole(['admin', 'super-admin']);
    }

    /**
     * Determine if roles should be shown
     */
    protected function shouldShowRoles(Request $request): bool
    {
        $currentUser = $request->user();
        
        if (!$currentUser) {
            return false;
        }

        // Show to self or admins
        return $this->id === $currentUser->id || 
               $currentUser->hasRole(['admin', 'super-admin']);
    }

    /**
     * Determine if permissions should be shown
     */
    protected function shouldShowPermissions(Request $request): bool
    {
        $currentUser = $request->user();
        
        if (!$currentUser) {
            return false;
        }

        // Show only to admins
        return $currentUser->hasRole(['admin', 'super-admin']);
    }

    /**
     * Determine if last seen should be shown
     */
    protected function shouldShowLastSeen(Request $request): bool
    {
        $currentUser = $request->user();
        
        if (!$currentUser) {
            return false;
        }

        // Show to self or admins
        return $this->id === $currentUser->id || 
               $currentUser->hasRole(['admin', 'super-admin']);
    }

    /**
     * Determine if sensitive data should be shown
     */
    protected function shouldShowSensitiveData(Request $request): bool
    {
        $currentUser = $request->user();
        
        if (!$currentUser) {
            return false;
        }

        // Show only to self
        return $this->id === $currentUser->id;
    }
}
