@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ explode(' ', auth()->user()->name)[0] }} 👋</div>
        <div class="page-sub">Here's what's happening at your gym today.</div>
    </div>
    <div style="font-size:12px;color:var(--text-muted)">{{ now()->format('D, M d Y') }}</div>
</div>

{{-- Stat Cards --}}
<div class="stat-grid" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-dim)">👥</div>
        <div class="stat-content">
            <div class="label">Total Members</div>
            <div class="value">{{ number_format($stats['total_members']) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--success-dim)">✅</div>
        <div class="stat-content">
            <div class="label">Active Memberships</div>
            <div class="value">{{ number_format($stats['active_memberships']) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--info-dim)">🕐</div>
        <div class="stat-content">
            <div class="label">Today's Attendance</div>
            <div class="value">{{ number_format($stats['today_attendance']) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(244,114,182,0.12)">🏋️</div>
        <div class="stat-content">
            <div class="label">Total Trainers</div>
            <div class="value">{{ number_format($stats['total_trainers']) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--warning-dim)">📅</div>
        <div class="stat-content">
            <div class="label">Upcoming Sessions</div>
            <div class="value">{{ number_format($stats['upcoming_sessions']) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--error-dim)">⚠️</div>
        <div class="stat-content">
            <div class="label">Expiring in 7 Days</div>
            <div class="value">{{ number_format($stats['expiring_soon']) }}</div>
            @if($stats['expiring_soon'] > 0)
            <div class="change warn">Needs attention</div>
            @endif
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

    {{-- Today's Attendance --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Today's Attendance</div>
                <div class="card-subtitle">{{ now()->format('M d, Y') }}</div>
            </div>
            <a href="{{ route('attendance.index') }}" class="btn btn-outline btn-sm">View all →</a>
        </div>
        @if($todayAttendance->isEmpty())
            <div class="empty-state"><div class="icon">🕐</div><p>No check-ins today</p></div>
        @else
            <div class="table-wrap">
                <table>
                    <tbody>
                        @foreach($todayAttendance as $rec)
                        <tr>
                            <td>
                                <div class="cell-main">{{ $rec->user?->name ?? 'Unknown' }}</div>
                                <div class="cell-sub">{{ $rec->check_in_time->format('h:i A') }}</div>
                            </td>
                            <td>
                                @if($rec->isOpen())
                                    <span class="badge badge-green">Checked In</span>
                                @else
                                    <span class="badge badge-blue">Out {{ $rec->check_out_time->format('h:i A') }}</span>
                                @endif
                            </td>
                            <td style="text-align:right">
                                <span class="badge badge-{{ $rec->source === 'biometric' ? 'purple' : 'gray' }}">{{ $rec->source }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
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
