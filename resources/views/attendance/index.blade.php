@extends('layouts.app')
@section('title', 'Attendance')

@section('content')
<div x-data="attendancePage()" x-init="init()">

    <div class="page-header">
        <div>
            <div class="page-title">Attendance</div>
            <div class="page-sub">{{ now()->format('d-M-Y') }}</div>
        </div>
        <button class="btn btn-primary" @click="checkInModal = true">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Manual Check-in
        </button>
    </div>

    {{-- Summary Cards --}}
    <div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(3,1fr)">
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--info-dim)">📊</div>
            <div class="stat-content">
                <div class="label">Total Today</div>
                <div class="value" x-text="summary.total"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--success-dim)">✅</div>
            <div class="stat-content">
                <div class="label">Checked In</div>
                <div class="value" x-text="summary.checked_in" style="color:var(--success)"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:var(--primary-dim)">🚪</div>
            <div class="stat-content">
                <div class="label">Checked Out</div>
                <div class="value" x-text="summary.checked_out"></div>
            </div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="toolbar">
        <div class="search-wrap" style="flex:1;max-width:300px">
            <svg class="search-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="form-input search-input" placeholder="Search member..." x-model="search" @input.debounce.400ms="load()">
        </div>
        <select class="form-select" style="width:150px" x-model="statusFilter" @change="load()" x-select2>
            <option value="">All</option>
            <option value="checked_in">Checked In</option>
            <option value="checked_out">Checked Out</option>
        </select>
        <button class="btn btn-outline" @click="load()" title="Refresh">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </button>
    </div>

    {{-- Attendance Table --}}
    <div class="card" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Duration</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)"><span class="spinner"></span></td></tr>
                    </template>
                    <template x-if="!loading && records.length === 0">
                        <tr><td colspan="7"><div class="empty-state"><div class="icon">🕐</div><p>No attendance records today</p></div></td></tr>
                    </template>
                    <template x-for="r in records" :key="r.id">
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div class="avatar" :style="`background:${avatarBg(r.user?.name||'?')}`" x-text="initials(r.user?.name||'?')"></div>
                                    <div>
                                        <div class="cell-main" x-text="r.user?.name || '—'"></div>
                                        <div class="cell-sub" x-text="r.user?.email || ''"></div>
                                    </div>
                                </div>
                            </td>
                            <td x-text="r.check_in_time ? new Date(r.check_in_time).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '—'"></td>
                            <td x-text="r.check_out_time ? new Date(r.check_out_time).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '—'"></td>
                            <td x-text="r.duration_mins ? r.duration_mins + ' min' : '—'"></td>
                            <td>
                                <span class="badge" :class="r.source==='biometric'?'badge-purple':'badge-gray'" x-text="r.source"></span>
                            </td>
                            <td>
                                <span class="badge" :class="r.status==='checked_in'?'badge-green':'badge-blue'" x-text="r.status==='checked_in'?'In Gym':'Left'"></span>
                                <span x-show="r.is_late_checkout" class="badge badge-yellow" style="margin-left:4px">Late</span>
                            </td>
                            <td style="text-align:right">
                                <button x-show="r.status === 'checked_in'" class="btn btn-outline btn-sm"
                                    @click="doCheckOut(r.user.id, r.user.name)">Check Out</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Check-in Modal --}}
    <div class="modal-overlay" x-show="checkInModal" x-transition @click.self="checkInModal=false" style="display:none">
        <div class="modal" @click.stop style="max-width:420px">
            <div class="modal-header">
                <div class="modal-title">Manual Check-in</div>
                <button class="modal-close" @click="checkInModal=false">×</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="form-group">
                    <label class="form-label">Select Member *</label>
                    <select class="form-select" x-model="selectedMemberId" x-select2>
                        <option value="">Choose a member...</option>
                        @foreach($members as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" @click="checkInModal=false">Cancel</button>
                <button type="button" class="btn btn-success" :disabled="!selectedMemberId || ciLoading" @click="doCheckIn()">
                    <span x-show="ciLoading" class="spinner"></span>
                    ✓ Check In
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function attendancePage() {
    return {
        records: @json($records->items()),
        summary: @json($summary),
        loading: false,
        search: '',
        statusFilter: '',
        checkInModal: false,
        selectedMemberId: '',
        ciLoading: false,

        init() {
            setInterval(() => this.load(), 30000);
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.search)       params.set('search', this.search);
                if (this.statusFilter) params.set('status', this.statusFilter);
                const res = await get(`/attendance?${params}`);
                this.records = res.records;
                this.summary = res.summary;
            } catch(e) { toast('Refresh failed', 'error'); }
            this.loading = false;
        },

        async doCheckIn() {
            if (!this.selectedMemberId) return;
            this.ciLoading = true;
            try {
                const res = await post('/attendance/check-in', { user_id: this.selectedMemberId });
                toast(res.message, 'success');
                this.checkInModal = false;
                this.selectedMemberId = '';
                await this.load();
            } catch(e) { toast(e.message, 'error'); }
            this.ciLoading = false;
        },

        async doCheckOut(userId, name) {
            try {
                const res = await post('/attendance/check-out', { user_id: userId });
                toast(res.message, 'success');
                await this.load();
            } catch(e) { toast(e.message, 'error'); }
        },
    };
}
</script>
@endpush
