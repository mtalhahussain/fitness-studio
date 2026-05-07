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

    {{-- Biometric Device Info Panel --}}
    <div x-data="{ open: false }" style="margin-bottom:24px">
        <div class="card" style="padding:0;border:1px solid rgba(124,92,252,0.25)">
            {{-- Header (always visible) --}}
            <button
                type="button"
                @click="open = !open"
                style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:transparent;border:none;cursor:pointer;text-align:left;"
            >
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:32px;height:32px;border-radius:8px;background:rgba(124,92,252,0.15);display:flex;align-items:center;justify-content:center;font-size:16px">🖐️</div>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary)">Biometric Device Integration</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:1px">ZKTeco &amp; compatible devices — connect via HTTP Push API</div>
                    </div>
                </div>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                     :style="open ? 'transform:rotate(180deg);transition:.2s' : 'transition:.2s'"
                     style="color:var(--text-muted);flex-shrink:0">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            {{-- Expandable Body --}}
            <div x-show="open" x-collapse style="border-top:1px solid var(--border)">
                <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:20px">

                    {{-- Step 1 --}}
                    <div style="grid-column:1/-1">
                        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px">How to connect your device</div>
                    </div>

                    {{-- Step cards --}}
                    <div style="background:var(--surface);border-radius:10px;padding:14px;border:1px solid var(--border)">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                            <span style="width:20px;height:20px;background:var(--primary);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">1</span>
                            <span style="font-size:12px;font-weight:600;color:var(--text-primary)">Set Push Server on Device</span>
                        </div>
                        <p style="font-size:12px;color:var(--text-muted);line-height:1.6;margin:0">
                            On the ZKTeco device go to <strong style="color:var(--text-secondary)">Menu → Comm → Cloud Server</strong> and set the server address to your app URL. The device will push punch logs automatically.
                        </p>
                    </div>

                    <div style="background:var(--surface);border-radius:10px;padding:14px;border:1px solid var(--border)">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                            <span style="width:20px;height:20px;background:var(--primary);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">2</span>
                            <span style="font-size:12px;font-weight:600;color:var(--text-primary)">Generate an API Token</span>
                        </div>
                        <p style="font-size:12px;color:var(--text-muted);line-height:1.6;margin:0">
                            Create a dedicated <em>device user</em> account in this system with the <strong style="color:var(--text-secondary)">owner</strong> role, then generate a Sanctum API token for it. This token goes into the device header config.
                        </p>
                    </div>

                    <div style="background:var(--surface);border-radius:10px;padding:14px;border:1px solid var(--border)">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                            <span style="width:20px;height:20px;background:var(--primary);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">3</span>
                            <span style="font-size:12px;font-weight:600;color:var(--text-primary)">Map Enrollment Numbers</span>
                        </div>
                        <p style="font-size:12px;color:var(--text-muted);line-height:1.6;margin:0">
                            The enrollment number programmed on the device must match the member's <strong style="color:var(--text-secondary)">User ID</strong> in this system. First sync auto-links via the attendance record; subsequent punches resolve automatically.
                        </p>
                    </div>

                    <div style="background:var(--surface);border-radius:10px;padding:14px;border:1px solid var(--border)">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                            <span style="width:20px;height:20px;background:var(--primary);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">4</span>
                            <span style="font-size:12px;font-weight:600;color:var(--text-primary)">Toggle vs Typed Punches</span>
                        </div>
                        <p style="font-size:12px;color:var(--text-muted);line-height:1.6;margin:0">
                            Devices that don't distinguish check-in / check-out (punch_type = null) use <strong style="color:var(--text-secondary)">toggle mode</strong> — first punch = check-in, next = check-out. Devices with punch_type <strong style="color:var(--text-secondary)">0 / 1</strong> are handled directly.
                        </p>
                    </div>

                    {{-- API Reference --}}
                    <div style="grid-column:1/-1;background:rgba(0,0,0,0.25);border-radius:10px;padding:16px;font-family:monospace;font-size:12px;line-height:2">
                        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px;font-family:inherit">API Endpoints</div>

                        <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
                            <span style="background:rgba(52,211,153,0.15);color:#34d399;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">POST</span>
                            <span style="color:var(--text-primary)">/api/biometric/sync</span>
                            <span style="color:var(--text-muted);font-family:sans-serif;font-size:11px">— batch upload of multiple punch logs from device</span>
                        </div>

                        <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
                            <span style="background:rgba(52,211,153,0.15);color:#34d399;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">POST</span>
                            <span style="color:var(--text-primary)">/api/biometric/punch</span>
                            <span style="color:var(--text-muted);font-family:sans-serif;font-size:11px">— single real-time punch (ZKTeco SDK push mode)</span>
                        </div>

                        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
                            <div style="color:var(--text-muted);margin-bottom:4px;font-family:sans-serif;font-size:11px">Required headers &amp; sample body:</div>
                            <span style="color:#94a3b8">Authorization:</span> <span style="color:#f472b6">Bearer &lt;your-api-token&gt;</span><br>
                            <span style="color:#94a3b8">Content-Type:</span>  <span style="color:#f472b6">application/json</span><br>
                            <br>
                            <span style="color:#64748b">// batch sync body</span><br>
                            <span style="color:#7dd3fc">&#123; "logs": [&#123; "device_user_id": "12", "punch_time": "2025-01-15 09:05:00", "punch_type": 0 &#125;, ...] &#125;</span><br>
                            <br>
                            <span style="color:#64748b">// single punch body</span><br>
                            <span style="color:#7dd3fc">&#123; "device_user_id": "12", "punch_time": "2025-01-15 09:05:00", "punch_type": null &#125;</span>
                        </div>
                    </div>

                    {{-- Note --}}
                    <div style="grid-column:1/-1;display:flex;gap:10px;align-items:flex-start;background:rgba(251,191,36,0.07);border:1px solid rgba(251,191,36,0.2);border-radius:8px;padding:12px 14px">
                        <span style="font-size:14px;flex-shrink:0">💡</span>
                        <p style="font-size:12px;color:var(--text-muted);line-height:1.6;margin:0">
                            Duplicate punches within <strong style="color:var(--text-secondary)">60 seconds</strong> are automatically ignored. The system records the source as <em>biometric</em> so you can filter manual vs device entries in the table below.
                        </p>
                    </div>

                </div>
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
