<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'all_children' => CategoryResource::collection($this->whenLoaded('allChildren')),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'full_path' => $this->when($request->has('include_path'), fn() => $this->full_path),
            'depth' => $this->when($request->has('include_depth'), fn() => $this->depth),
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
