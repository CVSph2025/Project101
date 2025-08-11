<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'booking_id',
        'amount',
        'currency',
        'payment_method',
        'provider',
        'provider_payment_id',
        'status',
        'processing_fee',
        'total_amount',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the booking that owns the payment
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Calculate processing fee based on amount
     */
    public function calculateProcessingFee(float $amount): float
    {
        // Stripe processing fee: 2.9% + $0.30 per transaction
        return round(($amount * 0.029) + 0.30, 2);
    }

    /**
     * Calculate total amount including fees
     */
    public function calculateTotalAmount(float $amount): float
    {
        $fee = $this->calculateProcessingFee($amount);
        return round($amount + $fee, 2);
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): bool
    {
        return $this->update(['status' => 'completed']);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(string $reason = null): bool
    {
        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['failure_reason'] = $reason;
        }
        
        return $this->update([
            'status' => 'failed',
            'metadata' => $metadata
        ]);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'canceled']);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    /**
     * Get formatted total amount with currency
     */
    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total_amount, 2);
    }

    /**
     * Get stripe payment intent
     */
    public function getStripePaymentIntent()
    {
        if ($this->provider !== 'stripe' || !$this->provider_payment_id) {
            return null;
        }

        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        
        try {
            return $stripe->paymentIntents->retrieve($this->provider_payment_id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Scope for completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed payments
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'canceled']);
    }
}
