<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole(['landlord', 'admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', 'min:5'],
            'description' => ['required', 'string', 'min:50', 'max:5000'],
            'property_type' => ['required', 'string', 'in:apartment,house,condo,studio,room'],
            'price_per_night' => ['required', 'numeric', 'min:1', 'max:100000'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:20'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],
            'house_rules' => ['nullable', 'string', 'max:2000'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => [
                'image',
                'mimes:jpeg,png,jpg,gif',
                'max:5120', // 5MB max per image
                'dimensions:min_width=800,min_height=600'
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Property title is required',
            'title.min' => 'Property title must be at least 5 characters',
            'description.required' => 'Property description is required',
            'description.min' => 'Property description must be at least 50 characters',
            'property_type.in' => 'Invalid property type selected',
            'price_per_night.min' => 'Price per night must be at least $1',
            'price_per_night.max' => 'Price per night cannot exceed $100,000',
            'max_guests.min' => 'Property must accommodate at least 1 guest',
            'images.*.mimes' => 'Images must be in JPEG, PNG, JPG, or GIF format',
            'images.*.max' => 'Each image must not exceed 5MB',
            'images.*.dimensions' => 'Images must be at least 800x600 pixels',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'price_per_night' => 'price per night',
            'max_guests' => 'maximum guests',
            'check_in_time' => 'check-in time',
            'check_out_time' => 'check-out time',
            'minimum_stay' => 'minimum stay',
            'maximum_stay' => 'maximum stay',
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
            // Custom validation logic
            if ($this->filled(['minimum_stay', 'maximum_stay'])) {
                if ($this->minimum_stay > $this->maximum_stay) {
                    $validator->errors()->add(
                        'maximum_stay',
                        'Maximum stay must be greater than or equal to minimum stay'
                    );
                }
            }

            // Validate check-in/out times
            if ($this->filled(['check_in_time', 'check_out_time'])) {
                if ($this->check_in_time >= $this->check_out_time) {
                    $validator->errors()->add(
                        'check_out_time',
                        'Check-out time must be after check-in time'
                    );
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean and normalize data
        if ($this->has('price_per_night')) {
            $this->merge([
                'price_per_night' => (float) str_replace(['$', ','], '', $this->price_per_night)
            ]);
        }

        // Normalize amenities
        if ($this->has('amenities') && is_string($this->amenities)) {
            $this->merge([
                'amenities' => array_filter(explode(',', $this->amenities))
            ]);
        }

        // Trim string values
        $stringFields = ['title', 'description', 'address', 'city', 'country', 'house_rules'];
        foreach ($stringFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => trim($this->$field)]);
            }
        }
    }
}
