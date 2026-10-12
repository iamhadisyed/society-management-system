<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'block' => $this->whenLoaded('block', fn () => ['id' => $this->block->id, 'name' => $this->block->name]),
            'street' => $this->whenLoaded('street', fn () => ['id' => $this->street->id, 'name' => $this->street->name]),
            'unit_number' => $this->unit_number,
            'full_address' => $this->full_address,
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'tariff_type' => $this->whenLoaded('tariffType', fn () => ['id' => $this->tariffType->id, 'name' => $this->tariffType->name]),
            'residence_status' => $this->residence_status,
            'owner_name' => $this->owner_name,
            'occupant_name' => $this->occupant_name,
            'reference_number' => $this->reference_number,
            'current_bill_number' => $this->current_bill_number,
            'is_linked' => $this->isLinked(),
            'linked_at' => $this->linked_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
