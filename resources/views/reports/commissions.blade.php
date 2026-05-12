@extends('layouts.app')
@section('title', 'Commission Report')

@section('content')
<div x-data="commissionReport()" x-init="init()">

    <div class="page-header">
        <div>
            <div class="page-title">Commission Report</div>
            <div class="page-sub">Trainer vs Gym revenue split</div>
        </div>
        <button class="btn btn-outline" @click="configModal.show=true">⚙ Set Default Rate</button>
    </div>

    {{-- Month filter --}}
    <div class="toolbar" style="margin-bottom:20px">
        <div class="form-group" style="flex-direction:row;align-items:center;gap:10px;margin:0">
            <label class="form-label" style="white-space:nowrap;margin:0">Month:</label>
            <input type="month" class="form-input" style="width:180px" x-model="selectedMonth" @change="load()">
        </div>
        <button class="btn btn-outline btn-sm" @click="selectedMonth=''; load()">All Time</button>
    </div>

    {{-- Summary cards --}}
    <div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(4,minmax(0,1fr))">
        <div class="stat-card" style="min-width:0">
            <div class="stat-icon" style="background:var(--primary-dim)">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <div class="stat-content" style="min-width:0;overflow:hidden">
                <div class="label">Total Revenue</div>
                <div class="value" style="font-size:20px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="currency(split.total)"></div>
            </div>
        </div>
        <div class="stat-card" style="min-width:0">
            <div class="stat-icon" style="background:var(--success-dim)">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--success)" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <div class="stat-content" style="min-width:0;overflow:hidden">
                <div class="label">Trainer Payouts</div>
                <div class="value" style="font-size:20px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="currency(split.trainer_total)"></div>
                <div class="change up" x-text="split.total > 0 ? ((split.trainer_total/split.total*100).toFixed(1) + '% of total') : ''"></div>
            </div>
        </div>
        <div class="stat-card" style="min-width:0">
            <div class="stat-icon" style="background:var(--info-dim)">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--info)" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div class="stat-content" style="min-width:0;overflow:hidden">
                <div class="label">Gym Revenue</div>
                <div class="value" style="font-size:20px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="currency(split.gym_total)"></div>
                <div class="change up" x-text="split.total > 0 ? ((split.gym_total/split.total*100).toFixed(1) + '% of total') : ''"></div>
            </div>
        </div>
        <div class="stat-card" style="min-width:0">
            <div class="stat-icon" style="background:var(--warning-dim)">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--warning)" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </div>
            <div class="stat-content" style="min-width:0;overflow:hidden">
                <div class="label">Active Trainers</div>
                <div class="value" style="font-size:20px">{{ $trainers->count() }}</div>
            </div>
        </div>
    </div>

    {{-- Revenue split visual --}}
    <div class="card" style="margin-bottom:24px" x-show="split.total > 0">
        <div class="card-header">
            <div class="card-title">Revenue Split</div>
        </div>
        <div style="height:24px;border-radius:12px;overflow:hidden;display:flex;margin-bottom:10px">
            <div style="background:var(--success);transition:.5s"
                 :style="`width:${split.total > 0 ? (split.trainer_total/split.total*100) : 0}%`"></div>
            <div style="background:var(--info);flex:1"></div>
        </div>
        <div style="display:flex;gap:20px;font-size:12px">
            <div style="display:flex;align-items:center;gap:6px">
                <div style="width:12px;height:12px;border-radius:3px;background:var(--success)"></div>
                <span style="color:var(--text-muted)">Trainer</span>
                <strong x-text="split.total > 0 ? (split.trainer_total/split.total*100).toFixed(1)+'%' : '0%'"></strong>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <div style="width:12px;height:12px;border-radius:3px;background:var(--info)"></div>
                <span style="color:var(--text-muted)">Gym</span>
                <strong x-text="split.total > 0 ? (split.gym_total/split.total*100).toFixed(1)+'%' : '0%'"></strong>
            </div>
        </div>
    </div>

    {{-- Per-trainer breakdown --}}
    <div class="card" style="margin-bottom:24px">
        <div class="card-header">
            <div class="card-title">Trainer Breakdown</div>
            <div class="card-subtitle" x-text="selectedMonth ? `for ${selectedMonth}` : 'all time'"></div>
        </div>
        <template x-if="report.length === 0">
            <div class="empty-state" style="padding:30px"><div class="icon">📊</div><p>No commission data{{ $month ? ' for this month' : '' }}.</p></div>
        </template>
        <div class="table-wrap" x-show="report.length > 0">
            <table>
                <thead>
                    <tr>
                        <th>Trainer</th>
                        <th>Members</th>
                        <th>Payments</th>
                        <th>Total Revenue</th>
                        <th>Trainer Share</th>
                        <th>Gym Share</th>
                        <th>Split</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in report" :key="row.trainer?.id">
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div class="avatar" style="width:30px;height:30px;font-size:11px"
                                         :style="`background:${avatarBg(row.trainer?.name||'?')}`"
                                         x-text="initials(row.trainer?.name||'?')"></div>
                                    <div>
                                        <div class="cell-main" x-text="row.trainer?.name || '—'"></div>
                                        <div class="cell-sub" x-text="row.trainer?.email"></div>
                                    </div>
                                </div>
                            </td>
                            <td x-text="row.members?.length || 0"></td>
                            <td x-text="row.count"></td>
                            <td x-text="currency(row.total_revenue)"></td>
                            <td><span style="color:var(--success);font-weight:600" x-text="currency(row.trainer_share)"></span></td>
                            <td><span style="color:var(--info)" x-text="currency(row.gym_share)"></span></td>
                            <td style="font-size:11px;color:var(--text-muted)">
                                <span x-text="row.total_revenue > 0 ? (row.trainer_share/row.total_revenue*100).toFixed(1)+'% / '+(row.gym_share/row.total_revenue*100).toFixed(1)+'%' : '—'"></span>
                            </td>
                            <td>
                                <a :href="`/trainers/${row.trainer?.id}/commission`" class="btn btn-outline btn-sm">View</a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Commission configs --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Commission Rate Configuration</div>
            <button class="btn btn-primary btn-sm" @click="configModal.show=true">+ Set Rate</button>
        </div>
        <div style="margin-bottom:16px;padding:14px;background:var(--primary-dim);border-radius:10px;border:1px solid rgba(108,99,255,0.2)">
            <div style="font-size:12px;color:var(--text-muted)">Gym Default Rate</div>
            <div style="font-size:20px;font-weight:700;color:var(--primary)">{{ $configs['default_rate'] }}%</div>
            <div style="font-size:11px;color:var(--text-muted)">trainer share · applies when no trainer-specific rate is set</div>
        </div>
        <template x-if="{{ count($configs['trainer_configs']) === 0 ? 'true' : 'false' }}">
            <div style="font-size:13px;color:var(--text-muted)">No trainer-specific overrides configured.</div>
        </template>
        @if(count($configs['trainer_configs']) > 0)
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Trainer</th><th>Rate</th><th>From</th><th>To</th></tr>
                </thead>
                <tbody>
                    @foreach($configs['trainer_configs'] as $cfg)
                    <tr>
                        <td class="cell-main">{{ $cfg->trainer?->name ?? '—' }}</td>
                        <td><span style="color:var(--primary);font-weight:600">{{ $cfg->commission_rate }}%</span></td>
                        <td>{{ $cfg->effective_from?->format('d M Y') }}</td>
                        <td>{{ $cfg->effective_to?->format('d M Y') ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Set rate modal --}}
    <div class="modal-overlay" x-show="configModal.show" x-transition @click.self="configModal.show=false" style="display:none">
        <div class="modal" @click.stop style="max-width:440px">
            <div class="modal-header">
                <div class="modal-title">Set Commission Rate</div>
                <button class="modal-close" @click="configModal.show=false">×</button>
            </div>
            <form @submit.prevent="saveConfig()">
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="form-group">
                        <label class="form-label">Apply To</label>
                        <select class="form-select" x-model="configModal.form.trainer_id" x-select2>
                            <option value="">Gym Default (all trainers)</option>
                            @foreach($trainers as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trainer Commission Rate (%)</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-input"
                               placeholder="e.g. 50" x-model="configModal.form.commission_rate" required>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px" x-show="configModal.form.commission_rate">
                            Trainer: <strong x-text="configModal.form.commission_rate + '%'"></strong> ·
                            Gym: <strong x-text="(100-parseFloat(configModal.form.commission_rate||0)).toFixed(2)+'%'"></strong>
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

</div>
@endsection

@push('scripts')
<script>
function commissionReport() {
    return {
        report:        @json($report),
        split:         @json($split),
        selectedMonth: '{{ $month }}',
        configModal: {
            show: false, loading: false,
            form: { trainer_id: '', commission_rate: '{{ $configs['default_rate'] }}', effective_from: new Date().toISOString().slice(0,10), effective_to: '' }
        },

        init() {},

        async load() {
            try {
                const params = new URLSearchParams();
                if (this.selectedMonth) params.set('month', this.selectedMonth);
                const res = await fetch(`/reports/commissions?${params}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    window.location.href = `/reports/commissions${this.selectedMonth ? '?month='+this.selectedMonth : ''}`;
                }
            } catch(e) {}
            window.location.href = `/reports/commissions${this.selectedMonth ? '?month='+this.selectedMonth : ''}`;
        },

        async saveConfig() {
            this.configModal.loading = true;
            try {
                await post('/commission-config', {
                    trainer_id:      this.configModal.form.trainer_id || null,
                    commission_rate: this.configModal.form.commission_rate,
                    effective_from:  this.configModal.form.effective_from,
                    effective_to:    this.configModal.form.effective_to || null,
                });
                toast('Commission rate saved.');
                this.configModal.show = false;
                window.location.reload();
            } catch(e) { toast(e.message, 'error'); }
            this.configModal.loading = false;
        },
    };
}
</script>
@endpush
