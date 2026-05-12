@extends('layouts.app')
@section('title', 'My Attendance')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">My Attendance</div>
        <div class="page-sub">{{ $monthStart->format('F Y') }}</div>
    </div>
    <form method="GET" action="{{ route('attendance.index') }}">
        <select name="month" class="form-select" style="width:160px" onchange="this.form.submit()">
            @foreach($monthOptions as $opt)
                <option value="{{ $opt['value'] }}" {{ $month === $opt['value'] ? 'selected' : '' }}>
                    {{ $opt['label'] }}
                </option>
            @endforeach
        </select>
    </form>
</div>

{{-- KPI Cards --}}
<div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--success-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--success)" stroke-width="1.8"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Present</div>
            <div class="value" style="color:var(--success)">{{ $presentCount }}</div>
            <div class="change">days this month</div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--error-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--error)" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Absent</div>
            <div class="value" style="color:var(--error)">{{ $absentCount }}</div>
            <div class="change">of {{ $daysSoFar }} days tracked</div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--info-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--info)" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Sessions</div>
            <div class="value">{{ $records->count() }}</div>
            <div class="change">check-ins this month</div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--primary-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="1.8"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Attendance Rate</div>
            <div class="value" style="font-size:22px">
                {{ $daysSoFar > 0 ? round($presentCount / $daysSoFar * 100) : 0 }}%
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start">

    {{-- Calendar Grid --}}
    <div class="card">
        <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:14px">{{ $monthStart->format('F Y') }}</div>

        {{-- Day headers --}}
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px">
            @foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $d)
            <div style="text-align:center;font-size:10px;font-weight:600;color:var(--text-muted);padding:2px 0">{{ $d }}</div>
            @endforeach
        </div>

        {{-- Calendar days --}}
        @php
            $firstDow  = $monthStart->dayOfWeek;   // 0=Sun
            $today     = now()->format('Y-m-d');
        @endphp
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">
            {{-- Empty cells before month start --}}
            @for($i = 0; $i < $firstDow; $i++)
            <div></div>
            @endfor

            {{-- Day cells --}}
            @for($day = 1; $day <= $daysInMonth; $day++)
            @php
                $dateStr   = $monthStart->format('Y-m') . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                $isPresent = $presentDays->contains($dateStr);
                $isFuture  = $dateStr > $today;
                $isToday   = $dateStr === $today;
            @endphp
            <div style="
                aspect-ratio:1;
                border-radius:8px;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:12px;
                font-weight:{{ $isToday ? '700' : '500' }};
                position:relative;
                @if($isPresent)
                    background:var(--success);
                    color:#fff;
                @elseif($isFuture)
                    background:transparent;
                    color:var(--text-muted);
                    opacity:.35;
                @elseif($isToday && !$isPresent)
                    background:var(--warning-dim);
                    color:var(--warning);
                    border:1px solid var(--warning);
                @else
                    background:var(--error-dim);
                    color:var(--error);
                @endif
            ">
                {{ $day }}
            </div>
            @endfor
        </div>

        {{-- Legend --}}
        <div style="display:flex;gap:14px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted)">
                <div style="width:10px;height:10px;border-radius:3px;background:var(--success)"></div> Present
            </div>
            <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted)">
                <div style="width:10px;height:10px;border-radius:3px;background:var(--error-dim);border:1px solid var(--error)"></div> Absent
            </div>
            <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted)">
                <div style="width:10px;height:10px;border-radius:3px;background:var(--warning-dim);border:1px solid var(--warning)"></div> Today
            </div>
        </div>
    </div>

    {{-- Session Detail Table --}}
    <div class="card" style="padding:0">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border)">
            <div class="card-title">Session Log</div>
            <div class="card-subtitle">{{ $records->count() }} check-ins in {{ $monthStart->format('F Y') }}</div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Duration</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                    <tr>
                        <td>
                            <div class="cell-main">{{ $record->check_in_time->format('d M') }}</div>
                            <div class="cell-sub">{{ $record->check_in_time->format('l') }}</div>
                        </td>
                        <td>{{ $record->check_in_time->format('h:i A') }}</td>
                        <td>
                            @if($record->check_out_time)
                                {{ $record->check_out_time->format('h:i A') }}
                            @else
                                @if($record->check_in_time->isToday())
                                    <span class="badge badge-green" style="font-size:10px">In Gym</span>
                                @else
                                    <span style="color:var(--text-muted)">—</span>
                                @endif
                            @endif
                        </td>
                        <td>
                            @if($record->check_out_time)
                                @php
                                    $mins = $record->check_in_time->diffInMinutes($record->check_out_time);
                                    $h = intdiv($mins, 60); $m = $mins % 60;
                                @endphp
                                <span style="color:var(--text)">{{ $h > 0 ? $h.'h ' : '' }}{{ $m }}m</span>
                            @else
                                <span style="color:var(--text-muted)">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $record->source === 'biometric' ? 'badge-purple' : 'badge-gray' }}" style="font-size:10px">
                                {{ ucfirst($record->source) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state" style="padding:40px">
                                <div style="opacity:.3;margin-bottom:10px">
                                    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <p>No check-ins in {{ $monthStart->format('F Y') }}</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
