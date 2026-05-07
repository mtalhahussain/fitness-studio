<?php

namespace App;

class GymContext
{
    private ?int $gymId = null;

    public function set(?int $gymId): void
    {
        $this->gymId = $gymId;
    }

    public function id(): ?int
    {
        return $this->gymId;
    }

    public function active(): bool
    {
        return $this->gymId !== null;
    }
}
