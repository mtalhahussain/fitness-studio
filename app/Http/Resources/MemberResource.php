<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'status'            => $this->status,
            'gym_id'            => $this->gym_id,
            'memberships_count' => $this->whenCounted('memberships'),
            'active_membership' => new MembershipResource($this->whenLoaded('activeMembership')),
            'created_at'        => $this->created_at?->toDateString(),
        ];
    }
}
