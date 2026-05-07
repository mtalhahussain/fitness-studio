@extends('layouts.app')
@section('title', 'Reports & Analytics')

@push('styles')
<style>
    .report-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .report-full   { grid-column: 1 / -1; }
    .chart-card    { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 22px; }
    .chart-card:hover { border-color: var(--border-hover); }
    .chart-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
    .chart-meta    { display: flex; flex-direction: column; gap: 3px; }
    .chart-title   { font-size: 15px; font-weight: 600; color: var(--text); }
    .chart-subtitle{ font-size: 12px; color: var(--text-muted); }
    .chart-controls{ display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

    .summary-strip { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .sum-item      { background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; }
    .sum-label     { font-size: 11px; color: var(--text-muted); font-weight: 500; margin-bottom: 4px; }
    .sum-value     { font-size: 18px; font-weight: 700; color: var(--text); line-height: 1.1; }
    .sum-value.accent { color: var(--primary); }
    .sum-value.green  { color: var(--success); }

    .chart-wrap    { position: relative; }
    .chart-loader  {
        position: absolute; inset: 0; background: rgba(8,8,19,0.7); backdrop-filter: blur(2px);
        border-radius: 8px; display: flex; align-items: center; justify-content: center;
        z-index: 5; opacity: 0; pointer-events: none; transition: opacity .2s;
    }
    .chart-loader.active { opacity: 1; pointer-events: all; }
    .loader-dot {
        width: 8px; height: 8px; border-radius: 50%; background: var(--primary);
        animation: dotPulse 1.4s ease-in-out infinite;
    }
    .loader-dot:nth-child(2) { animation-delay: .2s; }
    .loader-dot:nth-child(3) { animation-delay: .4s; }
    @keyframes dotPulse {
        0%,80%,100% { transform: scale(0.6); opacity: .4; }
        40%          { transform: scale(1);   opacity: 1;  }
    }
    .loader-dots { display: flex; gap: 6px; }

    .seg-control { display: flex; background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 6px; padding: 3px; gap: 2px; }
    .seg-btn     { padding: 4px 12px; border-radius: 4px; border: none; background: transparent; color: var(--text-muted); font-size: 12px; font-weight: 500; cursor: pointer; transition: .15s; font-family: 'Inter', sans-serif; }
    .seg-btn.active { background: var(--primary); color: #fff; }
    .seg-btn:hover:not(.active) { color: var(--text); }

    .export-btn { display: inline-flex; align-items: center; gap: 5px; padding: 5px 11px; border-radius: 6px; border: 1px solid var(--border); background: transparent; color: var(--text-muted); font-size: 11px; font-weight: 500; cursor: pointer; transition: .15s; font-family: 'Inter', sans-serif; }
    .export-btn:hover { border-color: var(--border-hover); color: var(--text); background: rgba(255,255,255,0.04); }

    @media (max-width: 900px) { .report-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Reports & Analytics</div>
        <div class="page-sub">Data-driven insights for your gym operations.</div>
    </div>
    <div style="font-size:12px;color:var(--text-muted)">{{ now()->format('D, M d Y') }}</div>
</div>

<div x-data="reports()">

    {{-- ── Revenue Report ─────────────────────────────────────────── --}}
    <div class="report-grid" style="margin-bottom:20px">
        <div class="chart-card report-full">
            <div class="chart-toolbar">
                <div class="chart-meta">
                    <div class="chart-title">Monthly Revenue</div>
                    <div class="chart-subtitle" x-text="`${revenueYear} vs ${revenueYear - 1} comparison`"></div>
                </div>
                <div class="chart-controls">
                    <select class="form-select" style="width:auto;padding:5px 10px;font-size:12px" x-model="revenueYear" @change="loadRevenue()">
                        @for($y = now()->year; $y >= now()->year - 4; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                    <button class="export-btn" @click="exportChart('revenueChart', 'revenue')">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export
                    </button>
                </div>
            </div>

            {{-- Summary strip --}}
            <div class="summary-strip">
                <div class="sum-item">
                    <div class="sum-label">Total Revenue</div>
                    <div class="sum-value accent" x-text="revSummary.total ? 'PKR ' + formatNum(revSummary.total) : '—'"></div>
                </div>
                <div class="sum-item">
                    <div class="sum-label">Monthly Avg</div>
                    <div class="sum-value" x-text="revSummary.average ? 'PKR ' + formatNum(revSummary.average) : '—'"></div>
                </div>
                <div class="sum-item">
                    <div class="sum-label">Peak Revenue</div>
                    <div class="sum-value" x-text="revSummary.best_revenue ? 'PKR ' + formatNum(revSummary.best_revenue) : '—'"></div>
                </div>
            </div>

            <div class="chart-wrap" style="height:300px">
                <div class="chart-loader" :class="{ active: loadingRevenue }">
                    <div class="loader-dots">
                        <div class="loader-dot"></div><div class="loader-dot"></div><div class="loader-dot"></div>
                    </div>
                </div>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ── Member Growth + Attendance side by side ───────────────── --}}
    <div class="report-grid">

        {{-- Member Growth --}}
        <div class="chart-card">
            <div class="chart-toolbar">
                <div class="chart-meta">
                    <div class="chart-title">Member Growth</div>
                    <div class="chart-subtitle" x-text="`New registrations — ${membersYear}`"></div>
                </div>
                <div class="chart-controls">
                    <select class="form-select" style="width:auto;padding:5px 10px;font-size:12px" x-model="membersYear" @change="loadMembers()">
                        @for($y = now()->year; $y >= now()->year - 4; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                    <button class="export-btn" @click="exportChart('membersChart', 'members')">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export
                    </button>
                </div>
            </div>

            <div class="summary-strip">
                <div class="sum-item">
                    <div class="sum-label">New This Year</div>
                    <div class="sum-value accent" x-text="memSummary.total_new ?? '—'"></div>
                </div>
                <div class="sum-item">
                    <div class="sum-label">Total Members</div>
                    <div class="sum-value green" x-text="memSummary.end_of_year ?? '—'"></div>
                </div>
            </div>

            <div class="chart-wrap" style="height:260px">
                <div class="chart-loader" :class="{ active: loadingMembers }">
                    <div class="loader-dots">
                        <div class="loader-dot"></div><div class="loader-dot"></div><div class="loader-dot"></div>
                    </div>
                </div>
                <canvas id="membersChart"></canvas>
            </div>
        </div>

        {{-- Attendance Trends --}}
        <div class="chart-card">
            <div class="chart-toolbar">
                <div class="chart-meta">
                    <div class="chart-title">Attendance Trends</div>
                    <div class="chart-subtitle" x-text="attSummary.start_date ? `${attSummary.start_date} → ${attSummary.end_date}` : 'Last 30 days'"></div>
                </div>
                <div class="chart-controls">
                    <div class="seg-control">
                        <button class="seg-btn" :class="{ active: attPeriod === 'daily' }"   @click="setAttPeriod('daily')">Daily</button>
                        <button class="seg-btn" :class="{ active: attPeriod === 'weekly' }"  @click="setAttPeriod('weekly')">Weekly</button>
                        <button class="seg-btn" :class="{ active: attPeriod === 'monthly' }" @click="setAttPeriod('monthly')">Monthly</button>
                    </div>
                    <button class="export-btn" @click="exportChart('attendanceChart', 'attendance')">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export
                    </button>
                </div>
            </div>

            {{-- Date range --}}
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:6px">
                    <span style="font-size:12px;color:var(--text-muted)">From</span>
                    <input type="date" class="form-input" style="padding:5px 10px;font-size:12px;width:auto" x-model="attStart" @change="loadAttendance()">
                </div>
                <div style="display:flex;align-items:center;gap:6px">
                    <span style="font-size:12px;color:var(--text-muted)">To</span>
                    <input type="date" class="form-input" style="padding:5px 10px;font-size:12px;width:auto" x-model="attEnd" @change="loadAttendance()">
                </div>
            </div>

            <div class="summary-strip">
                <div class="sum-item">
                    <div class="sum-label">Total Check-ins</div>
                    <div class="sum-value accent" x-text="attSummary.total_check_ins ?? '—'"></div>
                </div>
                <div class="sum-item">
                    <div class="sum-label">Avg / Period</div>
                    <div class="sum-value" x-text="attSummary.average_per_period ?? '—'"></div>
                </div>
                <div class="sum-item">
                    <div class="sum-label">Peak Period</div>
                    <div class="sum-value green" x-text="attSummary.peak_period || '—'"></div>
                </div>
            </div>

            <div class="chart-wrap" style="height:220px">
                <div class="chart-loader" :class="{ active: loadingAttendance }">
                    <div class="loader-dots">
                        <div class="loader-dot"></div><div class="loader-dot"></div><div class="loader-dot"></div>
                    </div>
                </div>
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
// ── Chart.js global defaults ──────────────────────────────────────────────
Chart.defaults.color           = '#64748b';
Chart.defaults.font.family     = 'Inter, sans-serif';
Chart.defaults.font.size       = 12;
Chart.defaults.borderColor     = 'rgba(255,255,255,0.06)';

const PALETTE = {
    primary:  '#6C63FF',
    accent:   '#f472b6',
    success:  '#22c55e',
    info:     '#3b82f6',
    warning:  '#eab308',
    primaryDim: 'rgba(108,99,255,0.15)',
    accentDim:  'rgba(244,114,182,0.15)',
    successDim: 'rgba(34,197,94,0.15)',
    infoDim:    'rgba(59,130,246,0.15)',
};

function makeGradient(ctx, color, alpha1 = 0.25, alpha2 = 0.01) {
    const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
    g.addColorStop(0, color.replace(')', `, ${alpha1})`).replace('rgb', 'rgba'));
    g.addColorStop(1, color.replace(')', `, ${alpha2})`).replace('rgb', 'rgba'));
    return g;
}

function chartDefaults(canvas) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'top',
                align: 'end',
                labels: { boxWidth: 10, boxHeight: 10, padding: 16, color: '#94a3b8', font: { size: 11 } }
            },
            tooltip: {
                backgroundColor: '#10101f',
                borderColor: 'rgba(255,255,255,0.1)',
                borderWidth: 1,
                titleColor: '#e2e8f0',
                bodyColor: '#94a3b8',
                padding: 12,
                cornerRadius: 8,
            }
        },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } },
            y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } },
        }
    };
}

// ── Alpine component ──────────────────────────────────────────────────────
function reports() {
    return {
        // Revenue
        revenueYear: {{ $currentYear }},
        revSummary: @json($revenue['summary']),
        loadingRevenue: false,
        revenueChart: null,

        // Members
        membersYear: {{ $currentYear }},
        memSummary: @json($members['summary']),
        loadingMembers: false,
        membersChart: null,

        // Attendance
        attPeriod: 'daily',
        attStart: '{{ now()->subDays(29)->toDateString() }}',
        attEnd:   '{{ now()->toDateString() }}',
        attSummary: @json($attendance['summary']),
        loadingAttendance: false,
        attendanceChart: null,

        init() {
            this.$nextTick(() => {
                try { this.buildRevenueChart(@json($revenue)); } catch(e) { console.error('Revenue chart:', e); }
                try { this.buildMembersChart(@json($members)); } catch(e) { console.error('Members chart:', e); }
                try { this.buildAttendanceChart(@json($attendance)); } catch(e) { console.error('Attendance chart:', e); }
            });
        },

        formatNum(n) {
            return parseFloat(n || 0).toLocaleString('en-PK', { maximumFractionDigits: 0 });
        },

        // ── Revenue ──────────────────────────────────────────────────
        buildRevenueChart(data) {
            const ctx = document.getElementById('revenueChart');
            if (this.revenueChart) this.revenueChart.destroy();


            this.revenueChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: data.datasets[0].label,
                            data: data.datasets[0].data,
                            backgroundColor: PALETTE.primaryDim,
                            borderColor: PALETTE.primary,
                            borderWidth: 2,
                            borderRadius: 6,
                            borderSkipped: false,
                            order: 2,
                        },
                        {
                            label: data.datasets[1].label,
                            data: data.datasets[1].data,
                            type: 'line',
                            borderColor: PALETTE.accent,
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: PALETTE.accent,
                            tension: 0.4,
                            order: 1,
                        }
                    ]
                },
                options: {
                    ...chartDefaults(ctx),
                    plugins: {
                        ...chartDefaults(ctx).plugins,
                        tooltip: {
                            ...chartDefaults(ctx).plugins.tooltip,
                            callbacks: {
                                label: ctx => ` ${ctx.dataset.label}: PKR ${ctx.parsed.y.toLocaleString()}`
                            }
                        }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } },
                        y: {
                            grid: { color: 'rgba(255,255,255,0.04)' },
                            ticks: { color: '#64748b', callback: v => 'PKR ' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v) }
                        }
                    }
                }
            });
        },

        async loadRevenue() {
            this.loadingRevenue = true;
            try {
                const data = await get(`/reports/data/revenue?year=${this.revenueYear}`);
                this.revSummary = data.summary;
                this.buildRevenueChart(data);
            } catch(e) { toast('Failed to load revenue data', 'error'); }
            finally { this.loadingRevenue = false; }
        },

        // ── Members ──────────────────────────────────────────────────
        buildMembersChart(data) {
            const ctx = document.getElementById('membersChart');
            if (this.membersChart) this.membersChart.destroy();

            this.membersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: data.datasets[0].label,
                            data: data.datasets[0].data,
                            backgroundColor: PALETTE.successDim,
                            borderColor: PALETTE.success,
                            borderWidth: 2,
                            borderRadius: 5,
                            borderSkipped: false,
                            order: 2,
                        },
                        {
                            label: data.datasets[1].label,
                            data: data.datasets[1].data,
                            type: 'line',
                            borderColor: PALETTE.info,
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: PALETTE.info,
                            tension: 0.4,
                            yAxisID: 'y2',
                            order: 1,
                        }
                    ]
                },
                options: {
                    ...chartDefaults(ctx),
                    scales: {
                        x:  { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' } },
                        y:  { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b' }, title: { display: true, text: 'New', color: '#64748b', font: { size: 11 } } },
                        y2: { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: PALETTE.info }, title: { display: true, text: 'Total', color: PALETTE.info, font: { size: 11 } } },
                    }
                }
            });
        },

        async loadMembers() {
            this.loadingMembers = true;
            try {
                const data = await get(`/reports/data/members?year=${this.membersYear}`);
                this.memSummary = data.summary;
                this.buildMembersChart(data);
            } catch(e) { toast('Failed to load member data', 'error'); }
            finally { this.loadingMembers = false; }
        },

        // ── Attendance ───────────────────────────────────────────────
        buildAttendanceChart(data) {
            const ctx = document.getElementById('attendanceChart');
            if (this.attendanceChart) this.attendanceChart.destroy();

            this.attendanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: data.datasets[0].label,
                            data: data.datasets[0].data,
                            backgroundColor: PALETTE.infoDim,
                            borderColor: PALETTE.info,
                            borderWidth: 2,
                            borderRadius: 4,
                            borderSkipped: false,
                            order: 2,
                        },
                        {
                            label: data.datasets[1].label,
                            data: data.datasets[1].data,
                            type: 'line',
                            borderColor: PALETTE.warning,
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: PALETTE.warning,
                            tension: 0.4,
                            order: 1,
                        }
                    ]
                },
                options: chartDefaults(ctx)
            });
        },

        setAttPeriod(p) {
            this.attPeriod = p;
            this.loadAttendance();
        },

        async loadAttendance() {
            this.loadingAttendance = true;
            try {
                const url = `/reports/data/attendance?period=${this.attPeriod}&start_date=${this.attStart}&end_date=${this.attEnd}`;
                const data = await get(url);
                this.attSummary = data.summary;
                this.buildAttendanceChart(data);
            } catch(e) { toast('Failed to load attendance data', 'error'); }
            finally { this.loadingAttendance = false; }
        },

        // ── Export ───────────────────────────────────────────────────
        exportChart(canvasId, name) {
            const canvas = document.getElementById(canvasId);
            const link   = document.createElement('a');
            link.download = `${name}-report-${new Date().toISOString().slice(0,10)}.png`;
            link.href     = canvas.toDataURL('image/png');
            link.click();
        }
    };
}
</script>
@endpush
