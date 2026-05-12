@extends('layouts.app')
@section('title', 'Platform Overview')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Platform Overview 🌐</div>
        <div class="page-sub">All gyms — switch to a gym context to manage it</div>
    </div>
    <div style="font-size:12px;color:var(--text-muted)">{{ now()->format('d-M-Y') }}</div>
</div>

{{-- Platform KPIs --}}
<div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(3,1fr)">
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--primary-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--primary)" stroke-width="1.8"><path stroke-linecap="round" d="M3 21h18M3 7v1a3 3 0 006 0V7m0 1a3 3 0 006 0V7m0 1a3 3 0 006 0V7H3l2-4h14l2 4"/><path stroke-linecap="round" d="M5 21V11.5M19 21V11.5M9 21v-5a2 2 0 014 0v5"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Total Gyms</div>
            <div class="value">{{ $totalGyms }}</div>
            <div class="change up">{{ $activeGyms }} active</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--success-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--success)" stroke-width="1.8"><path stroke-linecap="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">Total Members</div>
            <div class="value">{{ number_format($totalMembers) }}</div>
            <div class="change">across all gyms</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--warning-dim)">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--warning)" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-content">
            <div class="label">This Month Revenue</div>
            <div class="value">{{ number_format($monthlyRevenue, 0) }}</div>
            <div class="change">PKR {{ number_format($totalRevenue, 0) }} total</div>
        </div>
    </div>
</div>

{{-- Gyms Table --}}
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">All Gyms</div>
            <div class="card-subtitle">Switch context to manage any gym's data</div>
        </div>
        <a href="{{ route('gyms.index') }}" class="btn btn-primary btn-sm">Manage Gyms →</a>
    </div>

    @if($gyms->isEmpty())
        <div class="empty-state">
            <div class="icon">🏋️</div>
            <p>No gyms yet. <a href="{{ route('gyms.index') }}" style="color:var(--primary)">Create your first gym →</a></p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Gym</th>
                        <th>Owner</th>
                        <th style="text-align:center">Members</th>
                        <th style="text-align:center">Trainers</th>
                        <th>Status</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gyms as $gym)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar" style="background:linear-gradient(135deg,var(--primary),var(--accent));font-size:11px;font-weight:700">
                                    {{ strtoupper(substr($gym->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="cell-main">{{ $gym->name }}</div>
                                    <div class="cell-sub">{{ $gym->city }}{{ $gym->city && $gym->country ? ', ' : '' }}{{ $gym->country }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($gym->owner)
                            <div class="cell-main" style="font-size:13px">{{ $gym->owner->name }}</div>
                            <div class="cell-sub">{{ $gym->owner->email }}</div>
                            @else
                            <span style="color:var(--text-muted);font-size:12px">—</span>
                            @endif
                        </td>
                        <td style="text-align:center">
                            <span style="font-weight:600;color:var(--text)">{{ $gym->members_count }}</span>
                        </td>
                        <td style="text-align:center">
                            <span style="font-weight:600;color:var(--text)">{{ $gym->trainers_count }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $gym->status === 'active' ? 'badge-green' : 'badge-red' }}">
                                {{ ucfirst($gym->status) }}
                            </span>
                        </td>
                        <td style="text-align:right">
                            <button class="btn btn-outline btn-sm" onclick="switchToGym({{ $gym->id }}, '{{ addslashes($gym->name) }}')">
                                Switch Context
                            </button>
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
async function switchToGym(gymId, gymName) {
    try {
        await post(`/admin/switch-gym/${gymId}`);
        toast(`Switched to ${gymName}`, 'info');
        window.location.href = '/dashboard';
    } catch(e) { toast(e.message, 'error'); }
}
</script>
@endpush
