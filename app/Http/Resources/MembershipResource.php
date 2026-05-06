<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'plan'           => new MembershipPlanResource($this->whenLoaded('plan')),
            'start_date'     => $this->start_date?->toDateString(),
            'end_date'       => $this->end_date?->toDateString(),
            'status'         => $this->status,
            'days_remaining' => $this->whenNotNull($this->resource ? $this->daysRemaining() : null),
            'amount_paid'    => $this->amount_paid,
            'notes'          => $this->notes,
            'created_at'     => $this->created_at?->toDateString(),
        ];
    }
}
