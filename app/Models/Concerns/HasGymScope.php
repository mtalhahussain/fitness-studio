<?php

namespace App\Models\Concerns;

use App\Models\Scopes\GymScope;
use Illuminate\Database\Eloquent\Builder;

trait HasGymScope
{
    public static function bootHasGymScope(): void
    {
        static::addGlobalScope(new GymScope());
    }

    /** Bypass the gym scope — use for admin cross-gym queries. */
    public static function acrossGyms(): Builder
    {
        return static::withoutGlobalScope(GymScope::class);
    }
}
