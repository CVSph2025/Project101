<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'check_in_date' => ['required', 'date', 'after:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'guests' => ['required', 'integer', 'min:1'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', 'string', 'in:stripe,paypal,gcash,paymaya'],
            'agree_to_terms' => ['required', 'accepted'],
            'agree_to_cancellation_policy' => ['required', 'accepted'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'property_id.required' => 'Property selection is required',
            'property_id.exists' => 'Selected property does not exist',
            'check_in_date.required' => 'Check-in date is required',
            'check_in_date.after' => 'Check-in date must be in the future',
            'check_out_date.required' => 'Check-out date is required',
            'check_out_date.after' => 'Check-out date must be after check-in date',
            'guests.min' => 'At least 1 guest is required',
            'guest_name.required' => 'Guest name is required',
            'guest_email.required' => 'Guest email is required',
            'guest_email.email' => 'Please provide a valid email address',
            'guest_phone.required' => 'Guest phone number is required',
            'payment_method.in' => 'Invalid payment method selected',
            'agree_to_terms.accepted' => 'You must agree to the terms and conditions',
            'agree_to_cancellation_policy.accepted' => 'You must agree to the cancellation policy',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                ApiResponse::validationError($validator->errors()->toArray())
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Check if property exists and is available
            if ($this->filled('property_id')) {
                $property = \App\Models\Property::find($this->property_id);
                
                if (!$property) {
                    return;
                }

                // Check if property is active
                if (!$property->is_active) {
                    $validator->errors()->add(
                        'property_id',
                        'This property is currently unavailable for booking'
                    );
                }

                // Check guest capacity
                if ($this->filled('guests') && $this->guests > $property->max_guests) {
                    $validator->errors()->add(
                        'guests',
                        "This property can accommodate a maximum of {$property->max_guests} guests"
                    );
                }

                // Check minimum/maximum stay requirements
                if ($this->filled(['check_in_date', 'check_out_date'])) {
                    $checkIn = \Carbon\Carbon::parse($this->check_in_date);
                    $checkOut = \Carbon\Carbon::parse($this->check_out_date);
                    $nights = $checkIn->diffInDays($checkOut);

                    if ($property->minimum_stay && $nights < $property->minimum_stay) {
                        $validator->errors()->add(
                            'check_out_date',
                            "Minimum stay for this property is {$property->minimum_stay} nights"
                        );
                    }

                    if ($property->maximum_stay && $nights > $property->maximum_stay) {
                        $validator->errors()->add(
                            'check_out_date',
                            "Maximum stay for this property is {$property->maximum_stay} nights"
                        );
                    }
                }

                // Check for conflicting bookings
                if ($this->filled(['check_in_date', 'check_out_date'])) {
                    $conflictingBookings = \App\Models\Booking::where('property_id', $this->property_id)
                        ->where('status', '!=', 'cancelled')
                        ->where(function ($query) {
                            $query->whereBetween('check_in_date', [$this->check_in_date, $this->check_out_date])
                                  ->orWhereBetween('check_out_date', [$this->check_in_date, $this->check_out_date])
                                  ->orWhere(function ($q) {
                                      $q->where('check_in_date', '<=', $this->check_in_date)
                                        ->where('check_out_date', '>=', $this->check_out_date);
                                  });
                        })
                        ->exists();

                    if ($conflictingBookings) {
                        $validator->errors()->add(
                            'check_in_date',
                            'These dates are not available. Please select different dates.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize phone numbers
        if ($this->has('guest_phone')) {
            $phone = preg_replace('/[^0-9+]/', '', $this->guest_phone);
            $this->merge(['guest_phone' => $phone]);
        }

        if ($this->has('emergency_contact_phone')) {
            $phone = preg_replace('/[^0-9+]/', '', $this->emergency_contact_phone);
            $this->merge(['emergency_contact_phone' => $phone]);
        }

        // Trim string values
        $stringFields = ['guest_name', 'guest_email', 'special_requests', 'emergency_contact_name'];
        foreach ($stringFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => trim($this->$field)]);
            }
        }
    }
}
