@extends('layouts.app')
@section('title', 'My Dashboard')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ explode(' ', auth()->user()->name)[0] }}</div>
        <div class="page-sub">Your membership, check-ins, and sessions at a glance.</div>
    </div>
    <div style="font-size:12px;color:var(--text-muted)">{{ now()->format('d-M-Y') }}</div>
</div>

{{-- KPI Cards --}}
<div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(4,minmax(0,1fr))">
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--primary-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Membership</div>
            <div class="value" style="font-size:18px">
                @if($membership)
                    <span style="color:var(--success)">Active</span>
                @else
                    <span style="color:var(--error)">None</span>
                @endif
            </div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--info-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--info)" stroke-width="1.8"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Check-ins This Month</div>
            <div class="value">{{ $monthCheckins }}</div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--success-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--success)" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Today's Check-in</div>
            <div class="value" style="font-size:18px">
                @if($todayRecord)
                    <span style="color:var(--success)">{{ $todayRecord->check_in_time->format('h:i A') }}</span>
                @else
                    <span style="color:var(--text-muted);font-size:14px">Not yet</span>
                @endif
            </div>
        </div>
    </div>
    <div class="stat-card" style="min-width:0">
        <div class="stat-icon" style="background:var(--warning-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--warning)" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Plan Expires</div>
            <div class="value" style="font-size:18px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                @if($membership)
                    {{ $membership->end_date->format('d M Y') }}
                @else
                    <span style="color:var(--text-muted);font-size:14px">—</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Membership Info Banner --}}
@if($membership)
<div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,var(--primary-dim),rgba(108,99,255,0.06));border-color:rgba(108,99,255,0.2)">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="width:48px;height:48px;border-radius:12px;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:15px;font-weight:700;color:var(--text)">{{ $membership->plan->name ?? 'Membership' }}</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                {{ ucfirst($membership->plan->type ?? '') }} plan &nbsp;·&nbsp;
                {{ $membership->start_date->format('d M Y') }} → {{ $membership->end_date->format('d M Y') }}
            </div>
        </div>
        <div style="text-align:right;flex-shrink:0">
            @php $daysLeft = (int) now()->diffInDays($membership->end_date, false); @endphp
            @if($daysLeft <= 7)
                <span class="badge badge-red">{{ $daysLeft }} days left</span>
            @elseif($daysLeft <= 30)
                <span class="badge badge-yellow">{{ $daysLeft }} days left</span>
            @else
                <span class="badge badge-green">{{ $daysLeft }} days left</span>
            @endif
        </div>
    </div>
</div>
@else
<div class="card" style="margin-bottom:20px;text-align:center;padding:24px;border-color:rgba(239,68,68,0.2);background:var(--error-dim)">
    <div style="color:var(--error);font-weight:600;font-size:14px">No active membership. Please contact the gym to enroll.</div>
</div>
@endif

{{-- Payment History --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <div>
            <div class="card-title">Payment History</div>
            <div class="card-subtitle">Your recent invoices</div>
        </div>
    </div>
    @if($invoices->isEmpty())
        <div class="empty-state" style="padding:32px">
            <div style="opacity:.3;margin-bottom:10px">
                <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <p>No invoices yet</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $inv)
                    @php
                        $paid = $inv->payments->sum('amount');
                        $due  = max(0, $inv->total_amount - $paid);
                    @endphp
                    <tr>
                        <td>
                            <div class="cell-main">{{ $inv->invoice_number }}</div>
                            <div class="cell-sub">{{ $inv->created_at->format('d M Y') }}</div>
                        </td>
                        <td>{{ $inv->created_at->format('d M Y') }}</td>
                        <td>PKR {{ number_format($inv->total_amount, 0) }}</td>
                        <td style="color:var(--success)">PKR {{ number_format($paid, 0) }}</td>
                        <td style="color:{{ $due > 0 ? 'var(--error)' : 'var(--text-muted)' }}">
                            {{ $due > 0 ? 'PKR '.number_format($due, 0) : '—' }}
                        </td>
                        <td>
                            @if($inv->status === 'paid')
                                <span class="badge badge-green">Paid</span>
                            @elseif($inv->status === 'partially_paid')
                                <span class="badge badge-yellow">Partial</span>
                            @elseif($inv->status === 'cancelled')
                                <span class="badge badge-gray">Cancelled</span>
                            @else
                                <span class="badge badge-red">Unpaid</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

    {{-- Upcoming Sessions --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Upcoming Sessions</div>
                <div class="card-subtitle">Scheduled training with your trainer</div>
            </div>
        </div>
        @if($upcomingSessions->isEmpty())
            <div class="empty-state" style="padding:32px">
                <div style="opacity:.3;margin-bottom:10px">
                    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <p>No upcoming sessions scheduled</p>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:10px">
                @foreach($upcomingSessions as $session)
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
                    <div style="width:42px;height:42px;border-radius:10px;background:var(--primary-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $session->title }}</div>
                        <div style="font-size:12px;color:var(--text-muted)">with {{ $session->trainer?->name ?? '—' }}</div>
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

    {{-- Recent Attendance --}}
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Recent Check-ins</div>
                <div class="card-subtitle">Your attendance history</div>
            </div>
        </div>
        @if($recentAttendance->isEmpty())
            <div class="empty-state" style="padding:32px">
                <div style="opacity:.3;margin-bottom:10px">
                    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <p>No check-in records yet</p>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:0">
                @foreach($recentAttendance as $record)
                <div style="display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--border)">
                    <div style="width:8px;height:8px;border-radius:50%;background:{{ $record->check_out_time ? 'var(--success)' : 'var(--warning)' }};flex-shrink:0"></div>
                    <div style="flex:1">
                        <div style="font-size:13px;font-weight:500;color:var(--text)">{{ $record->check_in_time->format('l, d M Y') }}</div>
                        <div style="font-size:11px;color:var(--text-muted)">
                            In: {{ $record->check_in_time->format('h:i A') }}
                            @if($record->check_out_time)
                                &nbsp;·&nbsp; Out: {{ $record->check_out_time->format('h:i A') }}
                            @else
                                &nbsp;·&nbsp; <span style="color:var(--warning)">Still in</span>
                            @endif
                        </div>
                    </div>
                    @if($record->check_out_time)
                    <div style="font-size:11px;color:var(--text-muted);flex-shrink:0">
                        {{ gmdate('H:i', $record->check_in_time->diffInSeconds($record->check_out_time)) }}h
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
