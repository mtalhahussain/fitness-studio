<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'email'                   => $this->email,
            'phone'                   => $this->phone,
            'status'                  => $this->status,
            'gym_id'                  => $this->gym_id,
            'profile'                 => $this->whenLoaded('trainerProfile', fn () => [
                'specialization'   => $this->trainerProfile->specialization,
                'bio'              => $this->trainerProfile->bio,
                'experience_years' => $this->trainerProfile->experience_years,
                'certifications'   => $this->trainerProfile->certifications ?? [],
                'hourly_rate'      => $this->trainerProfile->hourly_rate,
                'is_active'        => $this->trainerProfile->is_active,
            ]),
            'assigned_members_count'  => $this->whenCounted('assignedMembers'),
            'training_sessions_count' => $this->whenCounted('trainingSessions'),
            'assigned_members'        => MemberResource::collection($this->whenLoaded('assignedMembers')),
            'created_at'              => $this->created_at?->toDateString(),
        ];
    }
}
