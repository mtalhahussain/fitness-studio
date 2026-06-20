@extends('layouts.app')
@section('title', $trainer->name . ' — Commission')

@section('content')
<div x-data="commissionPage()" x-init="init()">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <div class="page-title">{{ $trainer->name }} — Commission</div>
            <div class="page-sub">{{ $trainer->trainerProfile?->specialization ?? 'Trainer' }} · Current rate: <strong>{{ $currentRate }}%</strong> trainer share</div>
        </div>
        <div style="display:flex;gap:10px">
            @if ($canManageCommission)
            <button class="btn btn-outline" @click="configModal.show=true">⚙ Set Rate</button>
            @endif
            <a href="{{ route('trainers.index') }}" class="btn btn-outline">← Back</a>
        </div>
    </div>

    {{-- Month filter --}}
    <div class="toolbar" style="margin-bottom:20px">
        <div class="form-group" style="flex-direction:row;align-items:center;gap:10px;margin:0">
            <label class="form-label" style="white-space:nowrap;margin:0">Month:</label>
            <input type="month" class="form-input" style="width:180px" x-model="selectedMonth" @change="loadEarnings()">
        </div>
        <button class="btn btn-outline btn-sm" @click="selectedMonth=''; loadEarnings()">All Time</button>
    </div>

    {{-- Stat cards --}}
    <div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(4,minmax(0,1fr))">
        <div class="stat-card" style="min-width:0">
            <div class="stat-icon" style="background:var(--primary-dim)">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <div class="stat-content" style="min-width:0;overflow:hidden">
                <div class="label">Trainer Earnings{{ $month ? ' (Month)' : '' }}</div>
                <div class="value" style="font-size:20px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="currency(earnings.lifetime_earnings)"></div>
            </div>
        </div>
        <div class="stat-card" style="min-width:0">
            <div class="stat-icon" style="background:var(--success-dim)">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--success)" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div class="stat-content" style="min-width:0;overflow:hidden">
                <div class="label">Gym Revenue{{ $month ? ' (Month)' : '' }}</div>
                <div class="value" style="font-size:20px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="currency(earnings.gym_share)"></div>
            </div>
        </div>
        <div class="stat-card" style="min-width:0">
            <div class="stat-icon" style="background:var(--info-dim)">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--info)" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            </div>
            <div class="stat-content" style="min-width:0;overflow:hidden">
                <div class="label">Total Processed</div>
                <div class="value" style="font-size:20px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="currency(earnings.total_revenue)"></div>
            </div>
        </div>
        <div class="stat-card" style="min-width:0">
            <div class="stat-icon" style="background:var(--warning-dim)">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--warning)" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <div class="stat-content" style="min-width:0;overflow:hidden">
                <div class="label">Active Members</div>
                <div class="value" style="font-size:20px">{{ $memberStats['active_members'] }}</div>
                <div class="change">{{ $memberStats['total_members'] }} total · {{ $memberStats['paused_members'] }} paused</div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

        {{-- Monthly breakdown chart --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Monthly Breakdown</div>
            </div>
            <template x-if="monthly.length === 0">
                <div class="empty-state" style="padding:30px"><div class="icon">📈</div><p>No commission data yet</p></div>
            </template>
            <div style="display:flex;flex-direction:column;gap:10px" x-show="monthly.length > 0">
                <template x-for="row in monthly" :key="row.period_month">
                    <div style="display:flex;align-items:center;gap:12px">
                        <div style="width:60px;font-size:11px;color:var(--text-muted);flex-shrink:0" x-text="fmtMonth(row.period_month)"></div>
                        <div style="flex:1;background:rgba(255,255,255,0.05);border-radius:4px;height:20px;overflow:hidden">
                            <div style="height:100%;background:var(--primary);border-radius:4px;transition:.3s"
                                 :style="`width:${maxTotal > 0 ? (row.total/maxTotal*100) : 0}%`"></div>
                        </div>
                        <div style="width:90px;text-align:right;font-size:12px;color:var(--text-dim)">
                            <span style="color:var(--success);font-weight:600" x-text="currency(row.trainer_share)"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Member overview --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Member Status</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
                <div style="padding:14px;background:var(--success-dim);border-radius:10px;text-align:center">
                    <div style="font-size:24px;font-weight:700;color:var(--success)">{{ $memberStats['active_members'] }}</div>
                    <div style="font-size:12px;color:var(--text-muted)">Active</div>
                </div>
                <div style="padding:14px;background:var(--warning-dim);border-radius:10px;text-align:center">
                    <div style="font-size:24px;font-weight:700;color:var(--warning)">{{ $memberStats['paused_members'] }}</div>
                    <div style="font-size:12px;color:var(--text-muted)">Paused</div>
                </div>
                <div style="padding:14px;background:var(--error-dim);border-radius:10px;text-align:center">
                    <div style="font-size:24px;font-weight:700;color:var(--error)">{{ $memberStats['inactive_members'] }}</div>
                    <div style="font-size:12px;color:var(--text-muted)">Inactive</div>
                </div>
                <div style="padding:14px;background:var(--primary-dim);border-radius:10px;text-align:center">
                    <div style="font-size:24px;font-weight:700;color:var(--primary)">{{ $memberStats['total_members'] }}</div>
                    <div style="font-size:12px;color:var(--text-muted)">Total</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Per-member commission breakdown --}}
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <div class="card-title">Commission per Member</div>
            <div class="card-subtitle" x-text="selectedMonth ? `for ${selectedMonth}` : 'all time'"></div>
        </div>
        <template x-if="earnings.by_member && earnings.by_member.length === 0">
            <div class="empty-state" style="padding:30px"><p>No commissions recorded{{ $month ? ' for this month' : '' }}.</p></div>
        </template>
        <div class="table-wrap" x-show="earnings.by_member && earnings.by_member.length > 0">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Payments</th>
                        <th>Total Amount</th>
                        <th>Trainer Share</th>
                        <th>Gym Share</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in earnings.by_member" :key="row.member?.id">
                        <tr>
                            <td>
                                <div class="cell-main" x-text="row.member?.name || '—'"></div>
                                <div class="cell-sub" x-text="row.member?.email"></div>
                            </td>
                            <td x-text="row.count"></td>
                            <td x-text="currency(row.total_amount)"></td>
                            <td><span style="color:var(--success);font-weight:600" x-text="currency(row.trainer_share)"></span></td>
                            <td><span style="color:var(--info)" x-text="currency(row.gym_share)"></span></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Member training periods --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Training Period History</div>
            <div style="display:flex;gap:8px">
                <select class="form-select" style="width:140px" x-model="periodFilter" @change="filterPeriods()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                    <option value="ended">Ended</option>
                </select>
            </div>
        </div>
        <template x-if="filteredPeriods.length === 0">
            <div class="empty-state" style="padding:30px"><p>No training periods found.</p></div>
        </template>
        <div class="table-wrap" x-show="filteredPeriods.length > 0">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Start</th>
                        <th>End / Pause</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="p in filteredPeriods" :key="p.id">
                        <tr>
                            <td>
                                <div class="cell-main" x-text="p.member?.name || '—'"></div>
                                <div class="cell-sub" x-text="p.member?.email"></div>
                            </td>
                            <td x-text="fmtDate(p.start_date)"></td>
                            <td x-text="p.end_date ? fmtDate(p.end_date) : '—'"></td>
                            <td>
                                <span class="badge"
                                    :class="p.status==='active'?'badge-green':p.status==='paused'?'badge-yellow':'badge-gray'"
                                    x-text="p.status"></span>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted)" x-text="p.notes || '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    @if ($canManageCommission)
    {{-- Set commission rate modal --}}
    <div class="modal-overlay" x-show="configModal.show" x-transition @click.self="configModal.show=false" style="display:none">
        <div class="modal" @click.stop style="max-width:420px">
            <div class="modal-header">
                <div class="modal-title">Set Commission Rate</div>
                <button class="modal-close" @click="configModal.show=false">×</button>
            </div>
            <form @submit.prevent="saveConfig()">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="form-group">
                        <label class="form-label">Apply To</label>
                        <select class="form-select" x-model="configModal.form.scope">
                            <option value="trainer">This trainer only ({{ $trainer->name }})</option>
                            <option value="gym">All trainers in gym (default)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trainer Commission Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-input"
                               placeholder="e.g. 50 for 50/50 split"
                               x-model="configModal.form.commission_rate" required>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                            Trainer gets this %. Gym gets the rest.
                            <span x-show="configModal.form.commission_rate">
                                (<span x-text="configModal.form.commission_rate"></span>% trainer /
                                <span x-text="(100 - parseFloat(configModal.form.commission_rate || 0)).toFixed(2)"></span>% gym)
                            </span>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Effective From *</label>
                            <input type="date" class="form-input" x-model="configModal.form.effective_from" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Effective To (optional)</label>
                            <input type="date" class="form-input" x-model="configModal.form.effective_to">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="configModal.show=false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="configModal.loading">
                        <span x-show="configModal.loading" class="spinner"></span>
                        Save Rate
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
function commissionPage() {
    const initEarnings = @json($earnings);
    const initMonthly  = @json($monthly);
    const allPeriods   = @json($members);
    const trainerId    = {{ $trainer->id }};

    return {
        earnings:       initEarnings,
        monthly:        initMonthly,
        selectedMonth:  '{{ $month }}',
        periodFilter:   '',
        filteredPeriods: allPeriods,
        configModal: {
            show: false, loading: false,
            form: { scope: 'trainer', commission_rate: '{{ $currentRate }}', effective_from: new Date().toISOString().slice(0,10), effective_to: '' }
        },

        get maxTotal() {
            return Math.max(...this.monthly.map(r => parseFloat(r.total || 0)), 1);
        },

        init() {},

        filterPeriods() {
            this.filteredPeriods = this.periodFilter
                ? allPeriods.filter(p => p.status === this.periodFilter)
                : allPeriods;
        },

        async loadEarnings() {
            try {
                const params = this.selectedMonth ? `?month=${this.selectedMonth}` : '';
                const res = await get(`/trainers/${trainerId}/earnings${params}`);
                this.earnings = res.earnings;
                this.monthly  = res.monthly;
            } catch(e) { toast('Failed to load earnings', 'error'); }
        },

        async saveConfig() {
            this.configModal.loading = true;
            try {
                const payload = {
                    commission_rate: this.configModal.form.commission_rate,
                    effective_from:  this.configModal.form.effective_from,
                    effective_to:    this.configModal.form.effective_to || null,
                    trainer_id:      this.configModal.form.scope === 'trainer' ? trainerId : null,
                };
                await post('/commission-config', payload);
                toast('Commission rate saved.');
                this.configModal.show = false;
                window.location.reload();
            } catch(e) { toast(e.message, 'error'); }
            this.configModal.loading = false;
        },

        fmtMonth(d) {
            if (!d) return '—';
            const dt = new Date(d);
            return dt.toLocaleDateString([], { month: 'short', year: 'numeric' });
        },
    };
}
</script>
@endpush
