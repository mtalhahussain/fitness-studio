@extends('layouts.app')
@section('title', 'My Dashboard')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ explode(' ', auth()->user()->name)[0] }}</div>
        <div class="page-sub">Your sessions, members, and earnings at a glance.</div>
    </div>
    <div style="font-size:12px;color:var(--text-muted)">{{ now()->format('d-M-Y') }}</div>
</div>

{{-- KPI Cards --}}
<div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--primary-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Assigned Members</div>
            <div class="value">{{ $assignedCount }}</div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--info-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--info)" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Sessions Today</div>
            <div class="value">{{ $sessionsToday }}</div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--success-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--success)" stroke-width="1.8"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Completed This Month</div>
            <div class="value">{{ $sessionsThisMonth }}</div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--warning-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--warning)" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">My Earnings (Month)</div>
            <div class="value" style="font-size:20px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">PKR {{ number_format($monthEarnings, 0) }}</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

    {{-- Upcoming Sessions --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Upcoming Sessions</div>
                <div class="card-subtitle">Next scheduled training</div>
            </div>
            <a href="{{ route('trainers.commission', auth()->id()) }}" class="btn btn-outline btn-sm">Full Schedule →</a>
        </div>
        @if($upcomingSessions->isEmpty())
            <div class="empty-state" style="padding:32px">
                <div style="font-size:36px;opacity:.3;margin-bottom:10px">
                    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <p>No upcoming sessions scheduled</p>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:10px">
                @foreach($upcomingSessions as $session)
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
                    <div style="width:42px;height:42px;border-radius:10px;background:var(--primary-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $session->title }}</div>
                        <div style="font-size:12px;color:var(--text-muted)">with {{ $session->member?->name ?? '—' }}</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0">
                        <div style="font-size:12px;font-weight:600;color:var(--text)">{{ $session->scheduled_at->format('d M') }}</div>
                        <div style="font-size:11px;color:var(--text-muted)">{{ $session->scheduled_at->format('h:i A') }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Assigned Members --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">My Members</div>
                <div class="card-subtitle">Active training assignments</div>
            </div>
        </div>
        @if($assignedMembers->isEmpty())
            <div class="empty-state" style="padding:32px">
                <div style="font-size:36px;opacity:.3;margin-bottom:10px">
                    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </div>
                <p>No members assigned yet</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <tbody>
                        @foreach($assignedMembers as $member)
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
                                    <span class="badge badge-green" style="font-size:10px">{{ ucfirst($member->activeMembership->plan->type ?? '') }}</span>
                                @else
                                    <span class="badge badge-gray" style="font-size:10px">No plan</span>
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
@endsection
