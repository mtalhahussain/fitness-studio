<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user'           => [
                'id'    => $this->user?->id,
                'name'  => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'gym_id'         => $this->gym_id,
            'check_in_time'  => $this->check_in_time?->toDateTimeString(),
            'check_out_time' => $this->check_out_time?->toDateTimeString(),
            'status'         => $this->isOpen() ? 'checked_in' : 'checked_out',
            'duration_mins'  => $this->duration(),
            'is_late_checkout' => $this->isLateCheckout(),
            'source'         => $this->source,
            'device_user_id' => $this->device_user_id,
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
