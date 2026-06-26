<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'room_number'     => $this->room_number,
            'type'            => $this->type,
            'floor'           => $this->floor,
            'capacity'        => $this->capacity,
            'price_per_night' => $this->price_per_night,
            'status'          => $this->status,
            'description'     => $this->description,
            'facilities'      => $this->facilities,
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
