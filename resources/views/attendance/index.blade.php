@extends('layouts.app')
@section('title', 'Attendance')

@section('content')
<div x-data="attendancePage()" x-init="init()">

    <div class="page-header">
        <div>
            <div class="page-title">Attendance</div>
            <div class="page-sub" x-text="startDate + ' to ' + endDate"></div>
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
                <div class="label">Total Sessions in Range</div>
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
    <div class="toolbar" style="flex-wrap:wrap;align-items:end">
        <div class="form-group">
            <label class="form-label">Member</label>
            <select class="form-select" x-model="memberFilter" @change="load(1)" x-select2>
                <option value="">All Members</option>
                @foreach($filterMembers as $member)
                    <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Month</label>
            <input type="month" class="form-input" x-model="month" @change="selectMonth()">
        </div>
        <div class="form-group">
            <label class="form-label">From</label>
            <input type="date" class="form-input" x-model="startDate" @change="load(1)">
        </div>
        <div class="form-group">
            <label class="form-label">To</label>
            <input type="date" class="form-input" x-model="endDate" :min="startDate" @change="load(1)">
        </div>
        <div class="form-group">
            <label class="form-label">View</label>
            <select class="form-select" x-model="viewMode" @change="load(1)">
                <option value="list">Session List</option>
                <option value="monthly">Monthly Attendance</option>
            </select>
        </div>
    </div>
    <div class="toolbar">
        <div class="search-wrap" style="flex:1;max-width:300px">
            <svg class="search-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="form-input search-input" placeholder="Search member..." x-model="search" @input.debounce.400ms="load(1)">
        </div>
        <select class="form-select" style="width:150px" x-model="statusFilter" @change="load(1)" x-select2>
            <option value="">All</option>
            <option value="checked_in">Checked In</option>
            <option value="checked_out">Checked Out</option>
        </select>
        <button class="btn btn-outline" @click="load()" title="Refresh">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </button>
    </div>

    {{-- Attendance Table --}}
    <div class="card" style="padding:0" x-show="viewMode === 'list'">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Date</th>
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
                        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)"><span class="spinner"></span></td></tr>
                    </template>
                    <template x-if="!loading && records.length === 0">
                        <tr><td colspan="8"><div class="empty-state"><div class="icon">🕐</div><p>No attendance records for these filters</p></div></td></tr>
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
                            <td x-text="r.check_in_time ? new Date(r.check_in_time).toLocaleDateString('en-GB', {timeZone: @js(config('app.timezone'))}) : '—'"></td>
                            <td x-text="r.check_in_time ? new Date(r.check_in_time).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',timeZone: @js(config('app.timezone'))}) : '—'"></td>
                            <td x-text="r.check_out_time ? new Date(r.check_out_time).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',timeZone: @js(config('app.timezone'))}) : '—'"></td>
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

    <div class="card" style="padding:0" x-show="viewMode === 'monthly'">
        <div x-show="loading" style="padding:12px 20px"><span class="spinner"></span> Loading attendance...</div>
        <div style="padding:16px 20px;color:var(--text-muted)">P = Present · — = No check-in · Future dates are blank. Present days count each date once.</div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Member</th><th>Present Days</th><th>Sessions</th>
                    <template x-for="day in dates" :key="day.date"><th style="text-align:center;white-space:nowrap" x-text="day.label"></th></template>
                </tr></thead>
                <tbody>
                    <template x-if="!loading && monthly.length === 0"><tr><td :colspan="dates.length + 3" style="text-align:center;padding:30px">No members found</td></tr></template>
                    <template x-for="member in monthly" :key="member.id">
                        <tr>
                            <td style="white-space:nowrap"><div class="cell-main" x-text="member.name"></div><div class="cell-sub" x-text="member.email"></div></td>
                            <td x-text="member.present_count"></td><td x-text="member.sessions"></td>
                            <template x-for="day in dates" :key="day.date">
                                <td style="text-align:center" :title="member.name + ' · ' + day.date">
                                    <span :class="member.days.includes(day.date) ? 'badge badge-green' : ''" x-text="member.days.includes(day.date) ? 'P' : (day.future ? '' : '—')"></span>
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px">
        <span class="cell-sub" x-text="`Page ${pagination.current_page} of ${pagination.last_page} · ${pagination.total} ${viewMode === 'monthly' ? 'members' : 'sessions'}`"></span>
        <div style="display:flex;gap:8px">
            <button class="btn btn-outline btn-sm" :disabled="loading || pagination.current_page <= 1" @click="load(pagination.current_page - 1)">Previous</button>
            <button class="btn btn-outline btn-sm" :disabled="loading || pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">Next</button>
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
        search: @js($filters['search'] ?? ''),
        statusFilter: @js($filters['status'] ?? ''),
        sourceFilter: @js($filters['source'] ?? ''),
        perPage: @js($filters['per_page'] ?? 20),
        memberFilter: @js((string) ($filters['member_id'] ?? '')),
        month: @js($filters['month']),
        startDate: @js($filters['start_date']),
        endDate: @js($filters['end_date']),
        viewMode: @js($filters['view']),
        monthly: @json($monthly->items()),
        dates: @json($dates),
        pagination: @json($paginationData),
        requestNumber: 0,
        checkInModal: false,
        selectedMemberId: '',
        ciLoading: false,

        init() {
            setInterval(() => this.load(), 30000);
        },

        selectMonth() {
            if (!/^\d{4}-\d{2}$/.test(this.month)) return;
            const [year, month] = this.month.split('-').map(Number);
            this.startDate = this.month + '-01';
            this.endDate = this.month + '-' + new Date(year, month, 0).getDate();
            this.load(1);
        },

        async load(page = this.pagination.current_page) {
            if (!this.startDate || !this.endDate || this.endDate < this.startDate) {
                toast('Choose a valid start and end date', 'error');
                return;
            }
            const requestNumber = ++this.requestNumber;
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.search)       params.set('search', this.search);
                if (this.statusFilter) params.set('status', this.statusFilter);
                if (this.sourceFilter) params.set('source', this.sourceFilter);
                params.set('per_page', this.perPage);
                if (this.memberFilter) params.set('member_id', this.memberFilter);
                params.set('month', this.month);
                params.set('start_date', this.startDate);
                params.set('end_date', this.endDate);
                params.set('view', this.viewMode);
                params.set('page', page);
                const res = await get(`/attendance?${params}`);
                if (requestNumber !== this.requestNumber) return;
                this.records = res.records;
                this.summary = res.summary;
                this.monthly = res.monthly;
                this.dates = res.dates;
                this.pagination = res.pagination;
                history.replaceState(null, '', `/attendance?${params}`);
            } catch(e) { if (requestNumber === this.requestNumber) toast(e.message || 'Refresh failed', 'error'); }
            if (requestNumber === this.requestNumber) this.loading = false;
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
