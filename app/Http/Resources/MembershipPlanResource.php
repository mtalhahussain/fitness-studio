<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'type'          => $this->type,
            'duration_days' => $this->duration_days,
            'price'         => $this->price,
            'description'   => $this->description,
            'features'      => $this->features ?? [],
            'is_active'     => $this->is_active,
        ];
    }
}
