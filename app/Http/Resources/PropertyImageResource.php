<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->getImageUrl(),
            'thumbnail_url' => $this->getThumbnailUrl(),
            'alt_text' => $this->alt_text ?? 'Property image',
            'is_primary' => $this->is_primary ?? false,
            'sort_order' => $this->sort_order ?? 0,
            'metadata' => [
                'file_size' => $this->file_size,
                'dimensions' => [
                    'width' => $this->width,
                    'height' => $this->height,
                ],
                'format' => $this->format,
            ],
            'uploaded_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Get the full image URL
     */
    protected function getImageUrl(): string
    {
        // If using cloud storage
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        // If using local storage
        return asset('storage/' . $this->image_path);
    }

    /**
     * Get the thumbnail URL
     */
    protected function getThumbnailUrl(): string
    {
        // Generate thumbnail URL based on image path
        $path = pathinfo($this->image_path);
        $thumbnailPath = $path['dirname'] . '/thumbnails/' . $path['basename'];
        
        if (filter_var($thumbnailPath, FILTER_VALIDATE_URL)) {
            return $thumbnailPath;
        }

        return asset('storage/' . $thumbnailPath);
    }
}
