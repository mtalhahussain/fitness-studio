{{-- One training session with one-tap status actions. Needs: $session. Optional: $showMember (default true). --}}
@php
    $showMember = $showMember ?? true;
    $badge = [
        'scheduled' => ['badge-blue', 'Scheduled'],
        'completed' => ['badge-green', 'Done'],
        'cancelled' => ['badge-gray', 'Cancelled'],
        'no_show'   => ['badge-red', 'No-show'],
    ][$session->status] ?? ['badge-gray', ucfirst($session->status)];
    $overdue = $session->status === 'scheduled' && $session->scheduled_at->isPast();
@endphp
<div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);flex-wrap:wrap">
    <div style="width:54px;text-align:center;flex-shrink:0">
        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase">{{ $session->scheduled_at->format('D') }}</div>
        <div style="font-size:18px;font-weight:700;color:var(--text);line-height:1.1">{{ $session->scheduled_at->format('d') }}</div>
        <div style="font-size:11px;color:var(--text-muted)">{{ $session->scheduled_at->format('M') }}</div>
    </div>

    <div style="flex:1;min-width:180px">
        <div style="font-size:13.5px;font-weight:600;color:var(--text)">{{ $session->title }}</div>
        <div style="font-size:12px;color:var(--text-muted)">
            {{ $session->scheduled_at->format('h:i A') }} · {{ $session->duration_mins }} min
            @if($showMember)
                · {{ $session->session_type === 'group' ? '👥 Group' : '👤' }}
                @if($session->member)
                    <a href="{{ route('my.members.show', $session->member_id) }}" style="color:var(--primary)">{{ $session->member->name }}</a>
                @endif
            @endif
        </div>
        @if($session->notes)
            <div style="font-size:12px;color:var(--text-muted);margin-top:3px;white-space:pre-line">{{ $session->notes }}</div>
        @endif
    </div>

    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
        @if($overdue)
            <span class="badge badge-yellow" style="font-size:10px">Mark result</span>
        @else
            <span class="badge {{ $badge[0] }}" style="font-size:10px">{{ $badge[1] }}</span>
        @endif

        @php $action = route('my.sessions.status', $session); @endphp
        @if($session->status === 'scheduled')
            @foreach(['completed' => ['btn-success', '✓ Done'], 'no_show' => ['btn-outline', 'No-show'], 'cancelled' => ['btn-outline', 'Cancel']] as $status => [$cls, $label])
                <form method="POST" action="{{ $action }}" style="display:inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button class="btn {{ $cls }} btn-sm">{{ $label }}</button>
                </form>
            @endforeach
        @else
            <form method="POST" action="{{ $action }}" style="display:inline">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="scheduled">
                <button class="btn btn-outline btn-sm" title="Undo">↺ Undo</button>
            </form>
        @endif
    </div>
</div>
