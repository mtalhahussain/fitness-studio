@extends('layouts.app')
@section('title', 'My Sessions')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">My Sessions</div>
        <div class="page-sub">Plan training and mark each session done after it happens.</div>
    </div>
    <button class="btn btn-primary" @click="$dispatch('open-session')">+ Add Session</button>
</div>

@if($counts['pending'] > 0 && $tab !== 'past')
    <a href="{{ route('my.sessions', ['tab' => 'past']) }}" class="card" style="display:block;text-decoration:none;margin-bottom:16px;background:var(--warning-dim);border-color:transparent">
        <span style="color:var(--warning);font-weight:600;font-size:13px">
            ⏰ {{ $counts['pending'] }} past {{ \Illuminate\Support\Str::plural('session', $counts['pending']) }} still waiting to be marked Done / No-show → tap to review
        </span>
    </a>
@endif

<div class="card">
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
        @foreach(['today' => "Today ({$counts['today']})", 'upcoming' => "Upcoming ({$counts['upcoming']})", 'past' => 'Past'] as $key => $label)
            <a href="{{ route('my.sessions', ['tab' => $key]) }}" class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-outline' }}">{{ $label }}</a>
        @endforeach
    </div>

    @forelse($sessions as $session)
        @include('trainer._session-row')
    @empty
        <div class="empty-state" style="padding:40px">
            <p>{{ ['today' => 'No sessions today.', 'upcoming' => 'No upcoming sessions.', 'past' => 'No past sessions yet.'][$tab] }}</p>
            <button class="btn btn-outline btn-sm" style="margin-top:8px" @click="$dispatch('open-session')">+ Add Session</button>
        </div>
    @endforelse

    @if($sessions->hasPages())
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--text-muted)">
            <span>Page {{ $sessions->currentPage() }} of {{ $sessions->lastPage() }}</span>
            <div style="display:flex;gap:6px">
                @if($sessions->previousPageUrl())<a href="{{ $sessions->previousPageUrl() }}" class="btn btn-outline btn-sm">← Prev</a>@endif
                @if($sessions->nextPageUrl())<a href="{{ $sessions->nextPageUrl() }}" class="btn btn-outline btn-sm">Next →</a>@endif
            </div>
        </div>
    @endif
</div>

@include('trainer._session-modal')
@endsection
