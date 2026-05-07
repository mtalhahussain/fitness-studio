@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ explode(' ', auth()->user()->name)[0] }} 👋</div>
        <div class="page-sub">Here's what's happening at your gym today.</div>
    </div>
    <div style="font-size:12px;color:var(--text-muted)">{{ now()->format('d-M-Y') }}</div>
</div>

{{-- Stat Cards --}}
<div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(4,1fr)">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="1.8"><path stroke-linecap="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Total Members</div>
            <div class="value">{{ number_format($stats['total_members']) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--success-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--success)" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Active Memberships</div>
            <div class="value">{{ number_format($stats['active_memberships']) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--info-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--info)" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline stroke-linecap="round" points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Today's Check-ins</div>
            <div class="value">{{ number_format($stats['today_checkins']) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--warning-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--warning)" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Monthly Revenue</div>
            <div class="value">{{ number_format($stats['monthly_revenue'], 0) }}</div>
        </div>
    </div>
</div>

{{-- Two-column layout --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

    {{-- Recent Members --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Recent Members</div>
                <div class="card-subtitle">Latest registrations</div>
            </div>
            <a href="{{ route('members.index') }}" class="btn btn-outline btn-sm">View all →</a>
        </div>
        @if($recentMembers->isEmpty())
            <div class="empty-state"><div class="icon">👥</div><p>No members yet</p></div>
        @else
            <div class="table-wrap">
                <table>
                    <tbody>
                        @foreach($recentMembers as $member)
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div class="avatar" style="background:{{ collect(['#6C63FF','#f472b6','#22c55e','#3b82f6','#eab308','#ef4444','#14b8a6'])[abs(crc32($member->name)) % 7] }};font-size:11px;font-weight:700">{{ strtoupper(substr($member->name,0,2)) }}</div>
                                    <div>
                                        <div class="cell-main">{{ $member->name }}</div>
                                        <div class="cell-sub">{{ $member->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($member->activeMembership)
                                    <span class="badge badge-green">{{ ucfirst($member->activeMembership->plan->type ?? '') }}</span>
                                @else
                                    <span class="badge badge-gray">No plan</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Today's Check-ins --}}
    <div class="card" style="display:flex;flex-direction:column">
        <div class="card-header">
            <div>
                <div class="card-title">Today's Check-ins</div>
                <div class="card-subtitle">{{ now()->format('d-M-Y') }} &mdash; {{ $todayAttendance->count() }} total</div>
            </div>
            <a href="{{ route('attendance.index') }}" class="btn btn-outline btn-sm">Attendance →</a>
        </div>
        @if($todayAttendance->isEmpty())
            <div class="empty-state"><div class="icon">🕐</div><p>No check-ins today</p></div>
        @else
            <div class="table-wrap" style="max-height:340px;overflow-y:auto">
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($todayAttendance as $rec)
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div class="avatar" style="background:{{ collect(['#6C63FF','#f472b6','#22c55e','#3b82f6','#eab308','#ef4444','#14b8a6'])[abs(crc32($rec->user?->name ?? '')) % 7] }};font-size:10px;width:28px;height:28px;font-weight:700">{{ strtoupper(substr($rec->user?->name ?? '?', 0, 2)) }}</div>
                                    <div>
                                        <div class="cell-main" style="font-size:13px">{{ $rec->user?->name ?? 'Unknown' }}</div>
                                        <div class="cell-sub">{{ $rec->user?->email ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:12px;white-space:nowrap">{{ $rec->check_in_time->format('h:i A') }}</td>
                            <td style="font-size:12px;white-space:nowrap;color:var(--text-muted)">
                                {{ $rec->check_out_time ? $rec->check_out_time->format('h:i A') : '—' }}
                            </td>
                            <td>
                                @if($rec->isOpen())
                                    <span class="badge badge-green" style="font-size:10px">In</span>
                                @else
                                    <span class="badge badge-blue" style="font-size:10px">Out</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Payment Due & Balance Table --}}
<div class="card" style="margin-top:20px">
    <div class="card-header">
        <div>
            <div class="card-title">Payment Due & Balances</div>
            <div class="card-subtitle">Members with outstanding amounts</div>
        </div>
        <span class="badge {{ $paymentsDue->isEmpty() ? 'badge-green' : 'badge-red' }}" style="font-size:12px;padding:4px 10px">
            {{ $paymentsDue->count() }} {{ $paymentsDue->count() === 1 ? 'member' : 'members' }}
        </span>
    </div>
    @if($paymentsDue->isEmpty())
        <div class="empty-state" style="padding:32px">
            <div class="icon">✅</div>
            <p>All members are fully paid up!</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Plan</th>
                        <th style="text-align:right">Plan Price</th>
                        <th style="text-align:right">Paid</th>
                        <th style="text-align:right">Balance Due</th>
                        <th>Expiry</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paymentsDue as $ms)
                    @php
                        $balance = (float)$ms->plan->price - (float)$ms->amount_paid;
                        $daysLeft = now()->startOfDay()->diffInDays($ms->end_date, false);
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="background:{{ collect(['#6C63FF','#f472b6','#22c55e','#3b82f6','#eab308','#ef4444','#14b8a6'])[abs(crc32($ms->user?->name ?? '')) % 7] }};font-size:11px;font-weight:700">{{ strtoupper(substr($ms->user?->name ?? '?', 0, 2)) }}</div>
                                <div>
                                    <div class="cell-main">{{ $ms->user?->name ?? '—' }}</div>
                                    <div class="cell-sub">{{ $ms->user?->phone ?? $ms->user?->email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-purple">{{ ucfirst($ms->plan->type) }}</span>
                            <div class="cell-sub" style="margin-top:2px">{{ $ms->plan->name }}</div>
                        </td>
                        <td style="text-align:right;font-size:13px">PKR {{ number_format($ms->plan->price, 0) }}</td>
                        <td style="text-align:right;font-size:13px;color:var(--success)">PKR {{ number_format($ms->amount_paid, 0) }}</td>
                        <td style="text-align:right">
                            <span style="font-weight:700;color:#ef4444;font-size:13px">PKR {{ number_format($balance, 0) }}</span>
                        </td>
                        <td style="font-size:12px;white-space:nowrap">
                            {{ $ms->end_date->format('d-M-Y') }}
                            @if($daysLeft <= 7 && $daysLeft >= 0)
                                <div style="font-size:10px;color:var(--warning)">{{ $daysLeft }}d left</div>
                            @elseif($daysLeft < 0)
                                <div style="font-size:10px;color:#ef4444">Expired</div>
                            @endif
                        </td>
                        <td>
                            @if($ms->amount_paid == 0)
                                <span class="badge badge-red" style="font-size:10px">Unpaid</span>
                            @else
                                <span class="badge" style="background:#ef444420;color:#ef4444;font-size:10px">Partial</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function avatarBg(name) {
    const colors = ['#6C63FF','#f472b6','#22c55e','#3b82f6','#eab308','#ef4444','#14b8a6'];
    let h = 0; for (let c of (name||'')) h = c.charCodeAt(0) + ((h << 5) - h);
    return colors[Math.abs(h) % colors.length];
}
</script>
@endpush
