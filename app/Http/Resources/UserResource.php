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
            'email' => $this->email,
            'role' => $this->role,
            'subscription_status' => $this->subscription_status,
            'is_public' => $this->is_public,
            'position' => $this->position,
            'country' => $this->country,
            'age' => $this->age,
            'photo_url' => $this->photo_url,
            'rating' => $this->rating,
            'views_count' => $this->views_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
