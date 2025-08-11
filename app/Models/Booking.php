<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory;
    protected $fillable = [
        'property_id',
        'user_id',
        'start_date',
        'end_date',
        'total_price',
        'guest_count',
        'cleaning_fee',
        'service_fee',
        'taxes',
        'status',
        'special_requests',
        'check_in_instructions',
        'cancellation_reason',
        'confirmed_at',
        'cancelled_at',
        // Advanced booking fields
        'booking_source',
        'confirmation_code',
        'guest_details',
        'cancellation_policy',
        'cancellation_fee',
        'cancellation_deadline',
        'host_notes',
        'last_message_at',
        'message_count',
        'actual_check_in',
        'actual_check_out',
        'check_in_method',
        'is_extended',
        'extension_fee',
        'modification_history',
        'review_reminder_sent',
        'review_deadline',
        'emergency_contacts',
        'has_pets',
        'accessibility_needs',
        'property_views_before_booking',
        'referral_source',
        'booking_metadata'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_price' => 'decimal:2',
        'cleaning_fee' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'taxes' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        // Advanced booking casts
        'guest_details' => 'array',
        'cancellation_fee' => 'decimal:2',
        'cancellation_deadline' => 'datetime',
        'last_message_at' => 'datetime',
        'check_in_instructions' => 'array',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'is_extended' => 'boolean',
        'extension_fee' => 'decimal:2',
        'modification_history' => 'array',
        'review_reminder_sent' => 'boolean',
        'review_deadline' => 'datetime',
        'emergency_contacts' => 'array',
        'has_pets' => 'boolean',
        'booking_metadata' => 'array',
    ];

    /**
     * Get the property that was booked
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who made the booking
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment for this booking
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Get the messages for this booking
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Generate a unique confirmation code
     */
    public static function generateConfirmationCode(): string
    {
        do {
            $code = 'HMG' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        } while (self::where('confirmation_code', $code)->exists());
        
        return $code;
    }

    /**
     * Calculate cancellation fee based on policy and timing
     */
    public function calculateCancellationFee(): float
    {
        $daysUntilCheckIn = $this->start_date->diffInDays(now());
        $totalAmount = $this->total_price;

        return match($this->cancellation_policy) {
            'flexible' => $daysUntilCheckIn >= 1 ? 0 : $totalAmount * 0.1,
            'moderate' => $daysUntilCheckIn >= 5 ? 0 : ($daysUntilCheckIn >= 1 ? $totalAmount * 0.5 : $totalAmount),
            'strict' => $daysUntilCheckIn >= 7 ? $totalAmount * 0.5 : $totalAmount,
            'super_strict' => $daysUntilCheckIn >= 30 ? $totalAmount * 0.5 : $totalAmount,
            default => 0,
        };
    }

    /**
     * Mark booking as checked in
     */
    public function checkIn(): bool
    {
        return $this->update([
            'status' => 'active',
            'actual_check_in' => now(),
        ]);
    }

    /**
     * Mark booking as checked out
     */
    public function checkOut(): bool
    {
        return $this->update([
            'status' => 'completed',
            'actual_check_out' => now(),
            'review_deadline' => now()->addDays(14), // 14 days to leave a review
        ]);
    }

    /**
     * Get booking status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'active' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get formatted confirmation code
     */
    public function getFormattedConfirmationCodeAttribute(): string
    {
        if (!$this->confirmation_code) {
            return 'N/A';
        }

        return chunk_split($this->confirmation_code, 3, '-');
    }

    /**
     * Check if review reminder should be sent
     */
    public function shouldSendReviewReminder(): bool
    {
        return $this->status === 'completed' 
            && !$this->review_reminder_sent 
            && !$this->review
            && $this->review_deadline
            && now()->isBefore($this->review_deadline);
    }

    /**
     * Scope a query to only include confirmed bookings.
     */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to only include pending bookings.
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include cancelled bookings.
     */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeInDateRange(Builder $query, $startDate, $endDate): void
    {
        $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($query) use ($startDate, $endDate) {
                    $query->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        });
    }

    /**
     * Get the number of nights for this booking
     */
    public function getNightsAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Get the subtotal (total without fees and taxes)
     */
    public function getSubtotalAttribute(): float
    {
        return $this->total_price - $this->cleaning_fee - $this->service_fee - $this->taxes;
    }

    /**
     * Get the grand total including all fees
     */
    public function getGrandTotalAttribute(): float
    {
        return $this->total_price + $this->cleaning_fee + $this->service_fee + $this->taxes;
    }

    /**
     * Check if booking can be cancelled
     */
    public function canBeCancelled(): bool
    {
        if ($this->status !== 'confirmed' && $this->status !== 'pending') {
            return false;
        }

        // Check cancellation policy
        $policy = $this->property->cancellation_policy;
        $now = Carbon::now();
        
        switch ($policy) {
            case 'flexible':
                return $now->lt($this->start_date->subDay());
            case 'moderate':
                return $now->lt($this->start_date->subDays(5));
            case 'strict':
                return $now->lt($this->start_date->subDays(30));
            default:
                return false;
        }
    }

    /**
     * Calculate total price for booking
     */
    public static function calculateTotalPrice($property, $startDate, $endDate, $guestCount = 1): array
    {
        $nights = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $subtotal = $property->price_per_night * $nights;
        
        // Calculate fees (you can make these configurable)
        $cleaningFee = 50; // Fixed cleaning fee
        $serviceFee = $subtotal * 0.1; // 10% service fee
        $taxes = ($subtotal + $serviceFee) * 0.12; // 12% taxes
        
        $total = $subtotal + $cleaningFee + $serviceFee + $taxes;
        
        return [
            'nights' => $nights,
            'subtotal' => round($subtotal, 2),
            'cleaning_fee' => round($cleaningFee, 2),
            'service_fee' => round($serviceFee, 2),
            'taxes' => round($taxes, 2),
            'total' => round($total, 2)
        ];
    }
}
