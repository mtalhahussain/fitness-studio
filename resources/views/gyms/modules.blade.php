@extends('layouts.app')
@section('title', 'Manage Modules — ' . $gym->name)

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Module Settings</div>
        <div class="page-sub">{{ $gym->name }} — Enable or disable features for this gym</div>
    </div>
    <a href="{{ route('gyms.index') }}" class="btn btn-outline btn-sm">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>
        Back to Gyms
    </a>
</div>

<div style="max-width:720px">
    <div class="card" style="margin-bottom:16px;background:var(--primary-dim);border-color:rgba(108,99,255,0.2)">
        <div style="display:flex;align-items:center;gap:10px;color:var(--primary)">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span style="font-size:13px;font-weight:500">Changes take effect immediately — the gym owner will see the updated navigation on next page load.</span>
        </div>
    </div>

    <div class="card" x-data="moduleManager()" x-init="init()">
        <div class="card-header">
            <div>
                <div class="card-title">Available Modules</div>
                <div class="card-subtitle">Toggle each module on or off for this gym</div>
            </div>
            <button class="btn btn-primary" @click="save()" :disabled="saving">
                <span x-show="saving" class="spinner" style="width:13px;height:13px;border-width:2px"></span>
                <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
            </button>
        </div>

        <div style="display:flex;flex-direction:column;gap:0">
            @foreach($available as $key => $module)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 0;border-bottom:1px solid var(--border);gap:16px"
                 @if(!$loop->last) style="border-bottom:1px solid var(--border)" @endif>

                <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0">
                    <div style="width:40px;height:40px;border-radius:10px;background:var(--primary-dim);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        {!! $module['icon'] !!}
                    </div>
                    <div style="min-width:0">
                        <div style="font-size:14px;font-weight:600;color:var(--text)">{{ $module['label'] }}</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">{{ $module['description'] }}</div>
                    </div>
                </div>

                {{-- Toggle switch --}}
                <div style="display:inline-flex;align-items:center;cursor:pointer;flex-shrink:0"
                     @click="toggleModule('{{ $key }}')">
                    <div style="width:44px;height:24px;border-radius:12px;position:relative;transition:background .2s"
                         :style="{ background: modules.includes('{{ $key }}') ? 'var(--primary)' : '#475569' }">
                        <div style="position:absolute;top:2px;width:20px;height:20px;border-radius:50%;background:#fff;transition:left .2s;box-shadow:0 1px 4px rgba(0,0,0,0.3)"
                             :style="{ left: modules.includes('{{ $key }}') ? '22px' : '2px' }">
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px">
            <a href="{{ route('gyms.index') }}" class="btn btn-outline">Cancel</a>
            <button class="btn btn-primary" @click="save()" :disabled="saving">
                <span x-show="saving" class="spinner" style="width:13px;height:13px;border-width:2px"></span>
                <span x-text="saving ? 'Saving...' : 'Save Changes'"></span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function moduleManager() {
    return {
        modules: @json($enabled),
        saving: false,

        init() {},

        toggleModule(key) {
            const idx = this.modules.indexOf(key);
            if (idx === -1) {
                this.modules.push(key);
            } else {
                this.modules.splice(idx, 1);
            }
        },

        async save() {
            this.saving = true;
            try {
                const res = await post('{{ route('gyms.modules.update', $gym) }}', { modules: this.modules });
                toast('Modules updated successfully.', 'success', '{{ $gym->name }} module settings saved.');
            } catch(e) {
                toast(e.message || 'Failed to update modules.', 'error');
            } finally {
                this.saving = false;
            }
        }
    }
}
</script>
@endpush
@endsection
