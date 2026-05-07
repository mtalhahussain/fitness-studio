<?php

namespace App\Services;

use App\GymContext;
use RuntimeException;

abstract class BaseService
{
    /**
     * Returns the current tenant's gym_id, or null for super-admin.
     * Safe to call from any service method.
     */
    protected function gymId(): ?int
    {
        return app(GymContext::class)->id();
    }

    /**
     * Like gymId() but throws when no gym context is set.
     * Use for write operations that must never be gym-less.
     */
    protected function requireGymId(): int
    {
        $id = $this->gymId();

        if ($id === null) {
            throw new RuntimeException('This operation requires an active gym context.');
        }

        return $id;
    }
}
