@extends('layouts.app')
@section('title', $member->name)

@section('content')
<div class="page-header">
    <div style="display:flex;align-items:center;gap:14px">
        <div class="avatar" style="width:48px;height:48px;background:{{ collect(['#6C63FF','#f472b6','#22c55e','#3b82f6','#eab308','#ef4444','#14b8a6'])[abs(crc32($member->name)) % 7] }};font-size:15px;font-weight:700">{{ strtoupper(substr($member->name,0,2)) }}</div>
        <div>
            <a href="{{ route('my.members') }}" style="font-size:12px;color:var(--text-muted);text-decoration:none">← My Members</a>
            <div class="page-title">{{ $member->name }}</div>
            <div class="page-sub">
                {{ $member->phone ?: '' }}{{ $member->phone && $member->email ? ' · ' : '' }}{{ $member->email }}
                @if($member->activeMembership)
                    · <span class="badge badge-green" style="font-size:10px">{{ $member->activeMembership->plan->name ?? 'Active plan' }}</span>
                    <span style="font-size:12px">till {{ $member->activeMembership->end_date?->format('d M Y') }}</span>
                @else
                    · <span class="badge badge-red" style="font-size:10px">No active plan</span>
                @endif
                @if($period)
                    · <span class="badge {{ $period->isPaused() ? 'badge-yellow' : 'badge-purple' }}" style="font-size:10px">Training {{ $period->isPaused() ? 'paused' : 'since ' . $period->start_date->format('d M') }}</span>
                @endif
            </div>
        </div>
    </div>
    <button class="btn btn-primary" @click="$dispatch('open-session', { memberId: {{ $member->id }} })">+ Add Session for {{ explode(' ', $member->name)[0] }}</button>
</div>

{{-- KPI Cards --}}
<div class="stat-grid" style="margin-bottom:20px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
    <div class="stat-card"><div class="stat-content">
        <div class="label">Present ({{ $monthStart->format('M') }})</div>
        <div class="value" style="color:var(--success)">{{ $presentCount }}</div>
        <div class="change">days</div>
    </div></div>
    <div class="stat-card"><div class="stat-content">
        <div class="label">Absent</div>
        <div class="value" style="color:var(--error)">{{ max($daysSoFar - $presentCount, 0) }}</div>
        <div class="change">of {{ $daysSoFar }} days</div>
    </div></div>
    <div class="stat-card"><div class="stat-content">
        <div class="label">Attendance Rate</div>
        <div class="value">{{ $daysSoFar > 0 ? round($presentCount / $daysSoFar * 100) : 0 }}%</div>
    </div></div>
    <div class="stat-card"><div class="stat-content">
        <div class="label">Upcoming Sessions</div>
        <div class="value">{{ $upcoming->count() }}</div>
    </div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;align-items:start">

    {{-- Attendance --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Attendance</div>
                <div class="card-subtitle">Green = came to gym</div>
            </div>
            <form method="GET">
                <select name="month" class="form-select" style="width:130px" onchange="this.form.submit()">
                    @foreach($monthOptions as $opt)
                        <option value="{{ $opt['value'] }}" @selected($monthStart->format('Y-m') === $opt['value'])>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px">
            @foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $d)
                <div style="text-align:center;font-size:10px;font-weight:600;color:var(--text-muted)">{{ $d }}</div>
            @endforeach
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:16px">
            @for($i = 0; $i < $monthStart->dayOfWeek; $i++)<div></div>@endfor
            @for($day = 1; $day <= $monthStart->daysInMonth; $day++)
                @php
                    $date    = $monthStart->copy()->day($day);
                    $present = isset($presentDays[$date->format('Y-m-d')]);
                    $future  = $date->isAfter(today());
                    $bg      = $present ? 'var(--success)' : ($future ? 'transparent' : 'var(--error-dim)');
                    $fg      = $present ? '#fff' : ($future ? 'var(--text-muted)' : 'var(--error)');
                @endphp
                <div title="{{ $date->format('D d M') }}{{ $present ? ' — present' : '' }}"
                     style="aspect-ratio:1;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;background:{{ $bg }};color:{{ $fg }};{{ $date->isToday() ? 'outline:2px solid var(--primary);' : '' }}">{{ $day }}</div>
            @endfor
        </div>

        <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:6px">Visits</div>
        @forelse($records as $r)
            <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:7px 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text)">{{ $r->check_in_time->format('D, d M') }}</span>
                <span style="color:var(--text-muted)">
                    {{ $r->check_in_time->format('h:i A') }} → {{ $r->check_out_time?->format('h:i A') ?? 'still inside' }}
                    @if($r->duration()) · {{ intdiv($r->duration(), 60) }}h {{ $r->duration() % 60 }}m @endif
                </span>
            </div>
        @empty
            <div class="empty-state" style="padding:16px"><p>No visits this month</p></div>
        @endforelse
    </div>

    {{-- Sessions --}}
    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Upcoming Sessions</div>
                    <div class="card-subtitle">Planned training with you</div>
                </div>
            </div>
            @forelse($upcoming as $session)
                @include('trainer._session-row', ['showMember' => false])
            @empty
                <div class="empty-state" style="padding:20px">
                    <p>Nothing planned yet.</p>
                    <button class="btn btn-outline btn-sm" style="margin-top:8px" @click="$dispatch('open-session', { memberId: {{ $member->id }} })">+ Plan a session</button>
                </div>
            @endforelse
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Session History</div>
                    <div class="card-subtitle">Last 20 sessions</div>
                </div>
            </div>
            @forelse($history as $session)
                @include('trainer._session-row', ['showMember' => false])
            @empty
                <div class="empty-state" style="padding:20px"><p>No past sessions</p></div>
            @endforelse
        </div>
    </div>
</div>

@include('trainer._session-modal', ['presetMemberId' => $member->id])
@endsection
