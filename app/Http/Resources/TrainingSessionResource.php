<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'session_type' => $this->session_type,
            'status'       => $this->status,
            'scheduled_at' => $this->scheduled_at?->toDateTimeString(),
            'ends_at'      => $this->scheduled_at?->addMinutes($this->duration_mins)->toDateTimeString(),
            'duration_mins' => $this->duration_mins,
            'is_upcoming'  => $this->isUpcoming(),
            'notes'        => $this->notes,
            'trainer'      => $this->whenLoaded('trainer', fn () => [
                'id'    => $this->trainer->id,
                'name'  => $this->trainer->name,
                'email' => $this->trainer->email,
            ]),
            'member'       => $this->whenLoaded('member', fn () => $this->member ? [
                'id'    => $this->member->id,
                'name'  => $this->member->name,
                'email' => $this->member->email,
                'phone' => $this->member->phone,
            ] : null),
            'gym_id'       => $this->gym_id,
            'created_at'   => $this->created_at?->toDateTimeString(),
        ];
    }
}
