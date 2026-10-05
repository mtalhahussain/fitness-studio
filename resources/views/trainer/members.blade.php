@extends('layouts.app')
@section('title', 'My Members')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">My Members</div>
        <div class="page-sub">Members assigned to you. Tap a name to see attendance and sessions.</div>
    </div>
    <button class="btn btn-primary" @click="$dispatch('open-session')">+ Add Session</button>
</div>

{{-- KPI Cards --}}
<div class="stat-grid" style="margin-bottom:20px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
    @foreach([
        ['My Members',       $stats['total'],          'var(--primary)', 'var(--primary-dim)'],
        ['In Gym Today',     $stats['present_today'],  'var(--success)', 'var(--success-dim)'],
        ['Sessions Today',   $stats['sessions_today'], 'var(--info)',    'var(--info-dim)'],
        ['Upcoming Sessions',$stats['upcoming'],       'var(--warning)', 'var(--warning-dim)'],
    ] as [$label, $value, $color, $bg])
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:{{ $bg }}">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="{{ $color }}" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">{{ $label }}</div>
            <div class="value">{{ $value }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    {{-- Search + filter --}}
    <form method="GET" action="{{ route('my.members') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <input class="form-input" name="search" value="{{ $search }}" placeholder="Search name, phone or email…" style="flex:1;min-width:200px">
        <button class="btn btn-outline">Search</button>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            @foreach(['all' => 'All', 'present' => 'In gym today', 'absent' => 'Not in today'] as $key => $label)
                <a href="{{ route('my.members', array_filter(['filter' => $key, 'search' => $search])) }}"
                   class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline' }}">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    @if($members->isEmpty())
        <div class="empty-state" style="padding:40px">
            <p>
                @if($stats['total'] === 0)
                    No members are assigned to you yet. The gym owner can assign members from the Trainers or Members page.
                @else
                    No members match this search.
                @endif
            </p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Plan</th>
                        <th>Today</th>
                        <th>This Month</th>
                        <th>Last Visit</th>
                        <th>Next Session</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                    @php $next = $nextSession[$member->id] ?? null; $last = $lastVisit[$member->id] ?? null; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('my.members.show', $member) }}" style="display:flex;align-items:center;gap:10px;text-decoration:none">
                                <div class="avatar" style="background:{{ collect(['#65a30d','#f472b6','#22c55e','#3b82f6','#eab308','#ef4444','#14b8a6'])[abs(crc32($member->name)) % 7] }};font-size:11px;font-weight:700">{{ strtoupper(substr($member->name,0,2)) }}</div>
                                <div>
                                    <div class="cell-main">{{ $member->name }}</div>
                                    <div class="cell-sub">{{ $member->phone ?: $member->email }}</div>
                                </div>
                            </a>
                        </td>
                        <td>
                            @if($member->activeMembership)
                                <span class="badge badge-green" style="font-size:10px">{{ $member->activeMembership->plan->name ?? 'Active' }}</span>
                            @else
                                <span class="badge badge-red" style="font-size:10px">No active plan</span>
                            @endif
                        </td>
                        <td>
                            @if(isset($presentToday[$member->id]))
                                <span class="badge badge-green" style="font-size:10px">✓ In gym</span>
                            @else
                                <span class="badge badge-gray" style="font-size:10px">Not yet</span>
                            @endif
                        </td>
                        <td><span class="cell-main">{{ $monthDays[$member->id] ?? 0 }}</span> <span class="cell-sub">days</span></td>
                        <td class="cell-sub">{{ $last ? \Carbon\Carbon::parse($last)->diffForHumans() : 'Never' }}</td>
                        <td class="cell-sub">
                            @if($next)
                                <div class="cell-main" style="font-size:12px">{{ $next->title }}</div>
                                {{ $next->scheduled_at->format('d M, h:i A') }}
                            @else
                                —
                            @endif
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <button type="button" class="btn btn-outline btn-sm" @click="$dispatch('open-session', { memberId: {{ $member->id }} })">+ Session</button>
                            <a href="{{ route('my.members.show', $member) }}" class="btn btn-primary btn-sm">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@include('trainer._session-modal')
@endsection
