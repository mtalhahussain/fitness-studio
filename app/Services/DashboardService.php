<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Return the 5 core KPIs for the dashboard.
     * Results are cached for 60 seconds to avoid hammering the DB on every page hit.
     */
    public function getStats(?int $gymId): array
    {
        return Cache::remember(
            'dashboard_stats_' . ($gymId ?? 'all'),
            60,
            fn () => $this->queryStats($gymId)
        );
    }

    /**
     * Single-query implementation — all 5 aggregates in one DB round trip.
     *
     * Why one query:
     *   5 COUNT/SUM subqueries inside a SELECT are resolved by the DB engine
     *   in a single network call instead of 5. On indexed columns the cost
     *   is negligible; the round-trip saving matters under concurrent load.
     *
     * Binding layout (positional ?):
     *   [0] model_type   → 'App\Models\User'  (Spatie role join)
     *   [1] gym_id       → users   subquery    (only when $gymId is set)
     *   [2] gym_id       → memberships active
     *   [3] gym_id       → memberships expired
     *   [4] gym_id       → attendances
     *   [5] gym_id       → payments
     */
    private function queryStats(?int $gymId): array
    {
        $gu = $gymId ? 'AND u.gym_id = ?' : '';   // aliased users table
        $g  = $gymId ? 'AND gym_id = ?' : '';      // all other tables

        $bindings = array_merge(
            ['App\Models\User'],
            $gymId ? array_fill(0, 5, $gymId) : []
        );

        $row = DB::selectOne("
            SELECT
              ( SELECT COUNT(*)
                FROM   users u
                INNER JOIN model_has_roles mhr
                  ON  mhr.model_id   = u.id
                  AND mhr.model_type = ?
                  AND mhr.role_id    = (SELECT id FROM roles WHERE name = 'member' LIMIT 1)
                WHERE  u.deleted_at IS NULL {$gu}
              ) AS total_members,

              ( SELECT COUNT(*)
                FROM   memberships
                WHERE  status = 'active' AND deleted_at IS NULL {$g}
              ) AS active_memberships,

              ( SELECT COUNT(*)
                FROM   memberships
                WHERE  status = 'expired' AND deleted_at IS NULL {$g}
              ) AS expired_memberships,

              ( SELECT COUNT(*)
                FROM   attendances
                WHERE  DATE(check_in_time) = CURDATE() {$g}
              ) AS today_checkins,

              ( SELECT COALESCE(SUM(amount), 0)
                FROM   payments
                WHERE  YEAR(paid_at)  = YEAR(CURDATE())
                  AND  MONTH(paid_at) = MONTH(CURDATE()) {$g}
              ) AS monthly_revenue
        ", $bindings);

        return [
            'total_members'       => (int)   ($row->total_members       ?? 0),
            'active_memberships'  => (int)   ($row->active_memberships  ?? 0),
            'expired_memberships' => (int)   ($row->expired_memberships ?? 0),
            'today_checkins'      => (int)   ($row->today_checkins      ?? 0),
            'monthly_revenue'     => (float) ($row->monthly_revenue     ?? 0),
        ];
    }

    /**
     * Bust the stats cache — call after any write that changes a KPI
     * (membership created/expired, payment recorded, etc.)
     */
    public function bustCache(?int $gymId): void
    {
        Cache::forget('dashboard_stats_' . ($gymId ?? 'all'));
    }
}
