<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    /**
     * Display a listing of properties
     */
    public function index(Request $request)
    {
        $query = Property::with(['user', 'primaryImage'])
            ->active()
            ->latest();
        
        // Handle search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('location', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Handle filters
        if ($request->filled('property_type')) {
            $query->ofType($request->property_type);
        }

        if ($request->filled('min_price') || $request->filled('max_price')) {
            $minPrice = $request->min_price ?? 0;
            $maxPrice = $request->max_price ?? 999999;
            $query->priceRange($minPrice, $maxPrice);
        }

        if ($request->filled('amenities')) {
            $amenities = is_array($request->amenities) ? $request->amenities : [$request->amenities];
            $query->hasAmenities($amenities);
        }

        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', '>=', $request->bedrooms);
        }

        if ($request->filled('bathrooms')) {
            $query->where('bathrooms', '>=', $request->bathrooms);
        }

        if ($request->filled('max_guests')) {
            $query->where('max_guests', '>=', $request->max_guests);
        }

        if ($request->filled('instant_book')) {
            $query->where('instant_book', true);
        }
        
        $properties = $query->paginate(12)->appends($request->query());
        
        // Get filter options for the view
        $propertyTypes = Property::getPropertyTypes();
        $amenities = Property::getAvailableAmenities();
        
        return view('properties.mobile-index', compact('properties', 'propertyTypes', 'amenities'));
    }

    /**
     * Show the form for creating a new property
     */
    public function create()
    {
        $this->authorize('create', Property::class);
        
        $propertyTypes = Property::getPropertyTypes();
        $amenities = Property::getAvailableAmenities();
        $cancellationPolicies = Property::getCancellationPolicies();
        
        return view('properties.create', compact('propertyTypes', 'amenities', 'cancellationPolicies'));
    }

    /**
     * Store a newly created property
     */
    public function store(Request $request)
    {
        $this->authorize('create', Property::class);
        
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'price_per_night' => 'required|numeric|min:0',
            'bedrooms' => 'required|integer|min:1|max:20',
            'bathrooms' => 'required|integer|min:1|max:20',
            'max_guests' => 'required|integer|min:1|max:50',
            'property_type' => 'required|in:' . implode(',', array_keys(Property::getPropertyTypes())),
            'amenities' => 'array',
            'amenities.*' => 'string|in:' . implode(',', array_keys(Property::getAvailableAmenities())),
            'house_rules' => 'nullable|string',
            'check_in_time' => 'required|date_format:H:i',
            'check_out_time' => 'required|date_format:H:i',
            'cancellation_policy' => 'required|in:' . implode(',', array_keys(Property::getCancellationPolicies())),
            'instant_book' => 'boolean',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
        ]);

        // Validate CDO location requirement
        if (!Property::isValidCdoLocation($request->location)) {
            return back()->withInput()->withErrors([
                'location' => 'Properties must be located within Cagayan de Oro City only.'
            ]);
        }

        DB::beginTransaction();
        
        try {
            // Create property
            $property = Auth::user()->properties()->create([
                'title' => $request->title,
                'description' => $request->description,
                'location' => $request->location,
                'price_per_night' => $request->price_per_night,
                'bedrooms' => $request->bedrooms,
                'bathrooms' => $request->bathrooms,
                'max_guests' => $request->max_guests,
                'property_type' => $request->property_type,
                'amenities' => $request->amenities ?? [],
                'house_rules' => $request->house_rules,
                'check_in_time' => $request->check_in_time,
                'check_out_time' => $request->check_out_time,
                'cancellation_policy' => $request->cancellation_policy,
                'instant_book' => $request->boolean('instant_book'),
                'is_active' => true,
            ]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $imagePath = $image->store('properties', 'public');
                    
                    PropertyImage::create([
                        'property_id' => $property->id,
                        'image_path' => $imagePath,
                        'alt_text' => $property->title . ' - Image ' . ($index + 1),
                        'sort_order' => $index,
                        'is_primary' => $index === 0, // First image is primary
                    ]);
                }
            }

            DB::commit();
            
            return redirect()->route('properties.show', $property)
                ->with('success', 'Property created successfully!');
                
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->withErrors(['error' => 'Failed to create property. Please try again.']);
        }
    }

    /**
     * Display the specified property
     */
    public function show(Property $property)
    {
        $property->load(['user', 'images', 'bookings' => function($query) {
            $query->confirmed()->future();
        }]);
        
        $relatedProperties = Property::with(['user', 'primaryImage'])
            ->active()
            ->where('id', '!=', $property->id)
            ->where('property_type', $property->property_type)
            ->orWhere('location', 'LIKE', '%' . explode(',', $property->location)[0] . '%')
            ->limit(6)
            ->get();
        
        return view('properties.show', compact('property', 'relatedProperties'));
    }

    /**
     * Show the form for editing the property
     */
    public function edit(Property $property)
    {
        $this->authorize('update', $property);
        
        $propertyTypes = Property::getPropertyTypes();
        $amenities = Property::getAvailableAmenities();
        $cancellationPolicies = Property::getCancellationPolicies();
        
        return view('properties.edit', compact('property', 'propertyTypes', 'amenities', 'cancellationPolicies'));
    }

    /**
     * Update the specified property
     */
    public function update(Request $request, Property $property)
    {
        $this->authorize('update', $property);
        
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'price_per_night' => 'required|numeric|min:0',
            'bedrooms' => 'required|integer|min:1|max:20',
            'bathrooms' => 'required|integer|min:1|max:20',
            'max_guests' => 'required|integer|min:1|max:50',
            'property_type' => 'required|in:' . implode(',', array_keys(Property::getPropertyTypes())),
            'amenities' => 'array',
            'amenities.*' => 'string|in:' . implode(',', array_keys(Property::getAvailableAmenities())),
            'house_rules' => 'nullable|string',
            'check_in_time' => 'required|date_format:H:i',
            'check_out_time' => 'required|date_format:H:i',
            'cancellation_policy' => 'required|in:' . implode(',', array_keys(Property::getCancellationPolicies())),
            'instant_book' => 'boolean',
            'new_images' => 'nullable|array|max:10',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|exists:property_images,id',
        ]);

        // Validate CDO location requirement
        if (!Property::isValidCdoLocation($request->location)) {
            return back()->withInput()->withErrors([
                'location' => 'Properties must be located within Cagayan de Oro City only.'
            ]);
        }

        DB::beginTransaction();
        
        try {
            // Update property
            $property->update([
                'title' => $request->title,
                'description' => $request->description,
                'location' => $request->location,
                'price_per_night' => $request->price_per_night,
                'bedrooms' => $request->bedrooms,
                'bathrooms' => $request->bathrooms,
                'max_guests' => $request->max_guests,
                'property_type' => $request->property_type,
                'amenities' => $request->amenities ?? [],
                'house_rules' => $request->house_rules,
                'check_in_time' => $request->check_in_time,
                'check_out_time' => $request->check_out_time,
                'cancellation_policy' => $request->cancellation_policy,
                'instant_book' => $request->boolean('instant_book'),
            ]);

            // Remove selected images
            if ($request->filled('remove_images')) {
                $imagesToRemove = PropertyImage::whereIn('id', $request->remove_images)
                    ->where('property_id', $property->id)
                    ->get();
                
                foreach ($imagesToRemove as $image) {
                    if (Storage::disk('public')->exists($image->image_path)) {
                        Storage::disk('public')->delete($image->image_path);
                    }
                    $image->delete();
                }
            }

            // Add new images
            if ($request->hasFile('new_images')) {
                $currentMaxOrder = $property->images()->max('sort_order') ?? -1;
                
                foreach ($request->file('new_images') as $index => $image) {
                    $imagePath = $image->store('properties', 'public');
                    
                    PropertyImage::create([
                        'property_id' => $property->id,
                        'image_path' => $imagePath,
                        'alt_text' => $property->title . ' - Image',
                        'sort_order' => $currentMaxOrder + $index + 1,
                    ]);
                }
            }

            // Ensure we have at least one primary image
            if (!$property->images()->where('is_primary', true)->exists()) {
                $property->images()->first()?->update(['is_primary' => true]);
            }

            DB::commit();
            
            return redirect()->route('properties.show', $property)
                ->with('success', 'Property updated successfully!');
                
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->withErrors(['error' => 'Failed to update property. Please try again.']);
        }
    }

    /**
     * Remove the specified property
     */
    public function destroy(Property $property)
    {
        $this->authorize('delete', $property);
        
        DB::beginTransaction();
        
        try {
            // Delete all images
            foreach ($property->images as $image) {
                if (Storage::disk('public')->exists($image->image_path)) {
                    Storage::disk('public')->delete($image->image_path);
                }
                $image->delete();
            }
            
            $property->delete();
            
            DB::commit();
            
            return redirect()->route('properties.index')
                ->with('success', 'Property deleted successfully!');
                
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to delete property. Please try again.']);
        }
    }

    /**
     * Toggle property active status
     */
    public function toggleStatus(Property $property)
    {
        $this->authorize('update', $property);
        
        $property->update(['is_active' => !$property->is_active]);
        
        $status = $property->is_active ? 'activated' : 'deactivated';
        
        return back()->with('success', "Property {$status} successfully!");
    }

    /**
     * Upload additional images via AJAX
     */
    public function uploadImages(Request $request, Property $property)
    {
        $this->authorize('update', $property);
        
        $request->validate([
            'images' => 'required|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedImages = [];
        $currentMaxOrder = $property->images()->max('sort_order') ?? -1;

        foreach ($request->file('images') as $index => $image) {
            $imagePath = $image->store('properties', 'public');
            
            $propertyImage = PropertyImage::create([
                'property_id' => $property->id,
                'image_path' => $imagePath,
                'alt_text' => $property->title . ' - Image',
                'sort_order' => $currentMaxOrder + $index + 1,
            ]);

            $uploadedImages[] = [
                'id' => $propertyImage->id,
                'url' => $propertyImage->url,
                'alt_text' => $propertyImage->alt_text,
            ];
        }

        return response()->json([
            'success' => true,
            'images' => $uploadedImages,
            'message' => 'Images uploaded successfully!'
        ]);
    }

    /**
     * Delete a specific image via AJAX
     */
    public function deleteImage(Property $property, PropertyImage $image)
    {
        $this->authorize('update', $property);
        
        if ($image->property_id !== $property->id) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }
        
        $image->delete();

        // Ensure we still have a primary image
        if ($image->is_primary && $property->images()->count() > 0) {
            $property->images()->first()->update(['is_primary' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully!'
        ]);
    }

    /**
     * Set primary image via AJAX
     */
    public function setPrimaryImage(Property $property, PropertyImage $image)
    {
        $this->authorize('update', $property);
        
        if ($image->property_id !== $property->id) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        // Remove primary status from all images
        $property->images()->update(['is_primary' => false]);
        
        // Set this image as primary
        $image->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Primary image updated successfully!'
        ]);
    }

    /**
     * Display properties owned by the authenticated user
     */
    public function ownerProperties()
    {
        $properties = Auth::user()->properties()->with('primaryImage')->latest()->paginate(12);
        return view('properties.owner-index', compact('properties'));
    }
}
