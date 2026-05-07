<?php

namespace App\Models\Scopes;

use App\GymContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class GymScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $gymId = app(GymContext::class)->id();

        if ($gymId !== null) {
            $builder->where($model->getTable() . '.gym_id', $gymId);
        }
    }
}
