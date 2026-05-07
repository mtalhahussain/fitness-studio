<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReportService extends BaseService
{
    // ── Monthly Revenue Report ────────────────────────────────────────────────

    public function monthlyRevenue(int $year): array
    {
        $gymId = $this->gymId();
        $g     = $gymId ? 'AND p.gym_id = ?' : '';

        $bindings = $gymId
            ? [$year, $year - 1, $gymId]
            : [$year, $year - 1];

        $rows = DB::select("
            SELECT
                MONTH(p.paid_at)  AS month,
                YEAR(p.paid_at)   AS year,
                SUM(p.amount)     AS revenue,
                COUNT(*)          AS transactions
            FROM payments p
            WHERE YEAR(p.paid_at) IN (?, ?) {$g}
              AND NOT EXISTS (
                  SELECT 1 FROM invoice_items ii
                  WHERE ii.invoice_id = p.invoice_id AND ii.item_type = 'plan'
              )
            GROUP BY YEAR(p.paid_at), MONTH(p.paid_at)
            ORDER BY YEAR(p.paid_at), MONTH(p.paid_at)
        ", $bindings);

        $current   = array_fill(1, 12, 0.0);
        $previous  = array_fill(1, 12, 0.0);
        $txCurrent = array_fill(1, 12, 0);

        foreach ($rows as $row) {
            if ((int) $row->year === $year) {
                $current[(int) $row->month]   = (float) $row->revenue;
                $txCurrent[(int) $row->month] = (int)   $row->transactions;
            } else {
                $previous[(int) $row->month] = (float) $row->revenue;
            }
        }

        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        return [
            'labels'   => $labels,
            'datasets' => [
                ['label' => "Revenue {$year}",        'data' => array_values($current)],
                ['label' => 'Revenue ' . ($year - 1), 'data' => array_values($previous)],
            ],
            'transactions' => array_values($txCurrent),
            'summary' => [
                'year'         => $year,
                'total'        => array_sum($current),
                'average'      => round(array_sum($current) / 12, 2),
                'best_month'   => array_sum($current) > 0 ? $labels[array_search(max($current), $current)] : null,
                'best_revenue' => array_sum($current) > 0 ? max($current) : 0,
            ],
        ];
    }

    // ── Membership Revenue Report (separate from POS) ────────────────────────

    public function membershipRevenue(int $year): array
    {
        $gymId = $this->gymId();
        $gp    = $gymId ? 'AND p.gym_id = ?' : '';
        $gm    = $gymId ? 'AND m.gym_id = ?' : '';

        // Leg A: payments linked to a plan invoice (new invoice-based flow)
        $bindingsA = $gymId ? [$year, $year - 1, $gymId] : [$year, $year - 1];

        // Leg B: memberships without an invoice (assigned before invoice system)
        $bindingsB = $gymId ? [$year, $year - 1, $gymId] : [$year, $year - 1];

        $rows = DB::select("
            SELECT month, year, SUM(revenue) AS revenue, SUM(memberships) AS memberships
            FROM (
                SELECT
                    MONTH(p.paid_at) AS month,
                    YEAR(p.paid_at)  AS year,
                    SUM(p.amount)    AS revenue,
                    COUNT(*)         AS memberships
                FROM payments p
                WHERE YEAR(p.paid_at) IN (?, ?) {$gp}
                  AND EXISTS (
                      SELECT 1 FROM invoice_items ii
                      WHERE ii.invoice_id = p.invoice_id AND ii.item_type = 'plan'
                  )
                GROUP BY YEAR(p.paid_at), MONTH(p.paid_at)

                UNION ALL

                SELECT
                    MONTH(m.created_at) AS month,
                    YEAR(m.created_at)  AS year,
                    SUM(m.amount_paid)  AS revenue,
                    COUNT(*)            AS memberships
                FROM memberships m
                WHERE YEAR(m.created_at) IN (?, ?) {$gm}
                  AND m.amount_paid > 0
                  AND m.invoice_id IS NULL
                GROUP BY YEAR(m.created_at), MONTH(m.created_at)
            ) combined
            GROUP BY year, month
            ORDER BY year, month
        ", array_merge($bindingsA, $bindingsB));

        $current   = array_fill(1, 12, 0.0);
        $previous  = array_fill(1, 12, 0.0);
        $txCurrent = array_fill(1, 12, 0);

        foreach ($rows as $row) {
            if ((int) $row->year === $year) {
                $current[(int) $row->month]   = (float) $row->revenue;
                $txCurrent[(int) $row->month] = (int)   $row->memberships;
            } else {
                $previous[(int) $row->month] = (float) $row->revenue;
            }
        }

        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        return [
            'labels'   => $labels,
            'datasets' => [
                ['label' => "Membership {$year}",        'data' => array_values($current)],
                ['label' => 'Membership ' . ($year - 1), 'data' => array_values($previous)],
            ],
            'memberships' => array_values($txCurrent),
            'summary' => [
                'year'         => $year,
                'total'        => array_sum($current),
                'average'      => round(array_sum($current) / 12, 2),
                'best_month'   => array_sum($current) > 0 ? $labels[array_search(max($current), $current)] : null,
                'best_revenue' => array_sum($current) > 0 ? max($current) : 0,
            ],
        ];
    }

    // ── Member Growth Report ──────────────────────────────────────────────────

    public function memberGrowth(int $year): array
    {
        $gymId = $this->gymId();
        $gu    = $gymId ? 'AND u.gym_id = ?' : '';

        $rows = DB::select("
            SELECT
                MONTH(u.created_at) AS month,
                COUNT(*)            AS new_members
            FROM users u
            INNER JOIN model_has_roles mhr
                ON  mhr.model_id   = u.id
                AND mhr.model_type = ?
                AND mhr.role_id    = (SELECT id FROM roles WHERE name = 'member' LIMIT 1)
            WHERE YEAR(u.created_at) = ?
              AND u.deleted_at IS NULL {$gu}
            GROUP BY MONTH(u.created_at)
            ORDER BY MONTH(u.created_at)
        ", array_merge(['App\Models\User', $year], $gymId ? [$gymId] : []));

        $newPerMonth = array_fill(1, 12, 0);
        foreach ($rows as $row) {
            $newPerMonth[(int) $row->month] = (int) $row->new_members;
        }

        $baseline = (int) DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM users u
            INNER JOIN model_has_roles mhr
                ON  mhr.model_id   = u.id
                AND mhr.model_type = ?
                AND mhr.role_id    = (SELECT id FROM roles WHERE name = 'member' LIMIT 1)
            WHERE YEAR(u.created_at) < ?
              AND u.deleted_at IS NULL {$gu}
        ", array_merge(['App\Models\User', $year], $gymId ? [$gymId] : []))->cnt;

        $cumulative = [];
        $running = $baseline;
        foreach (range(1, 12) as $m) {
            $running += $newPerMonth[$m];
            $cumulative[] = $running;
        }

        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        return [
            'labels'   => $labels,
            'datasets' => [
                ['label' => 'New Members',              'data' => array_values($newPerMonth), 'type' => 'bar'],
                ['label' => 'Total Members (Cumulative)', 'data' => $cumulative,              'type' => 'line'],
            ],
            'summary' => [
                'year'           => $year,
                'total_new'      => array_sum($newPerMonth),
                'baseline'       => $baseline,
                'end_of_year'    => end($cumulative),
                'best_month'     => $labels[array_search(max($newPerMonth), $newPerMonth)],
                'best_new_count' => max($newPerMonth),
            ],
        ];
    }

    // ── Attendance Trends ─────────────────────────────────────────────────────

    public function attendanceTrends(string $period, string $startDate, string $endDate): array
    {
        $gymId = $this->gymId();
        $g     = $gymId ? 'AND gym_id = ?' : '';

        [$groupExpr, $labelExpr] = match ($period) {
            'weekly'  => ["YEARWEEK(check_in_time, 1)", "DATE_FORMAT(MIN(check_in_time), '%Y-W%u')"],
            'monthly' => ["DATE_FORMAT(check_in_time, '%Y-%m')", "DATE_FORMAT(check_in_time, '%Y-%m')"],
            default   => ["DATE(check_in_time)", "DATE(check_in_time)"],
        };

        $rows = DB::select("
            SELECT
                {$labelExpr}                                                           AS period_label,
                COUNT(*)                                                               AS check_ins,
                COUNT(DISTINCT user_id)                                                AS unique_members,
                AVG(TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time))              AS avg_duration_minutes
            FROM attendances
            WHERE DATE(check_in_time) BETWEEN ? AND ?
              AND check_in_time IS NOT NULL {$g}
            GROUP BY {$groupExpr}
            ORDER BY {$groupExpr}
        ", array_merge([$startDate, $endDate], $gymId ? [$gymId] : []));

        $labels        = [];
        $checkIns      = [];
        $uniqueMembers = [];
        $avgDurations  = [];

        foreach ($rows as $row) {
            $labels[]        = $row->period_label;
            $checkIns[]      = (int)   $row->check_ins;
            $uniqueMembers[] = (int)   $row->unique_members;
            $avgDurations[]  = $row->avg_duration_minutes
                ? round((float) $row->avg_duration_minutes, 1)
                : null;
        }

        $total = array_sum($checkIns);

        return [
            'labels'   => $labels,
            'datasets' => [
                ['label' => 'Check-ins',       'data' => $checkIns,      'type' => 'bar'],
                ['label' => 'Unique Members',  'data' => $uniqueMembers, 'type' => 'line'],
            ],
            'avg_duration_minutes' => $avgDurations,
            'summary' => [
                'period'             => $period,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'total_check_ins'    => $total,
                'average_per_period' => count($checkIns) ? round($total / count($checkIns), 1) : 0,
                'peak_period'        => $total > 0 ? $labels[array_search(max($checkIns), $checkIns)] : null,
            ],
        ];
    }
}
